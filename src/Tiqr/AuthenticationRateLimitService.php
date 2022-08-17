<?php

/**
 * Copyright 2018 SURFnet B.V.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace App\Tiqr;

use App\Exception\TiqrServerRuntimeException;
use App\Tiqr\Response\AuthenticationErrorResponse;
use App\WithContextLogger;
use App\Tiqr\Response\AuthenticationResponse;
use App\Tiqr\Response\PermanentlyBlockedAuthenticationResponse;
use App\Tiqr\Response\RateLimitedAuthenticationResponse;
use App\Tiqr\Response\RejectedAuthenticationResponse;
use App\Tiqr\Response\TemporarilyBlockedAuthenticationResponse;
use DateTimeImmutable;
use mysql_xdevapi\Exception;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class AuthenticationRateLimitService implements AuthenticationRateLimitServiceInterface
{
    private $tiqrService;
    private $configuration;
    private $logger;

    /**
     *
     * @param TiqrServiceInterface $tiqrService
     * @param TiqrConfigurationInterface $configuration
     * @param LoggerInterface $logger
     * @throws \Exception
     */
    public function __construct(
        TiqrServiceInterface $tiqrService,
        TiqrConfigurationInterface $configuration,
        LoggerInterface $logger
    ) {
        $this->tiqrService = $tiqrService;
        $this->configuration = $configuration;
        $this->logger = $logger;
    }

    /**
     * @param TiqrUserInterface $user
     *
     * @return bool
     * @throws TiqrServerRuntimeException
     */
    public function isBlockedPermanently(TiqrUserInterface $user): bool
    {
        return $user->isBlocked(0);
    }

    /**
     * @param TiqrUserInterface $user
     *
     * @return bool
     * @throws Exception\ConfigurationException
     * @throws TiqrServerRuntimeException
     */
    public function isBlockedTemporarily(TiqrUserInterface $user): bool
    {
        return $this->configuration->temporarilyBlockEnabled() &&
            $user->isBlocked($this->configuration->getTemporarilyBlockDuration());
    }

    /**
     * @param string $sessionKey
     * @param TiqrUserInterface $user
     * @param string $response
     *
     * @return AuthenticationResponse
     * @throws \InvalidArgumentException
     * @throws Exception\ConfigurationException
     * @throws \Exception
     */
    public function authenticate(string $sessionKey, TiqrUserInterface $user, string $response): AuthenticationResponse
    {
        $logger = WithContextLogger::from(
            $this->logger,
            ['key' => $sessionKey, 'userId' => $user->getId(), 'sessionKey' => $sessionKey]
        );

        if ($this->isBlockedPermanently($user)) {
            $logger->notice('User is blocked indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        if ($this->isBlockedTemporarily($user)) {
            $logger->notice('User is temporarily blocked');

            return new TemporarilyBlockedAuthenticationResponse();
        }

        // Verify the app's response.
        $logger->info('Validating authentication response');
        $result = $this->tiqrService->authenticate($user, $response, $sessionKey);
        if ($result->isValid()) {
            $user->resetLoginAttempts();
            $logger->info('response is valid');

            return $result;
        }

        // The user did something wrong.
        if ($result instanceof RejectedAuthenticationResponse) {
            return $this->handleAuthenticationRejectResponse($logger, $result, $user);
        }

        $logger->error(
            'Unexpected error occurred ' . $result->getMessage()
        );
        return $result;
    }

    /**
     * @param LoggerInterface $logger
     * @param AuthenticationResponse $result
     * @param TiqrUserInterface $user
     *
     * @return AuthenticationResponse
     * @throws Exception\ConfigurationException
     */
    private function handleAuthenticationRejectResponse(
        LoggerInterface $logger,
        AuthenticationResponse $result,
        TiqrUserInterface $user
    ): AuthenticationResponse {
        $logger->notice('User login attempt is rejected');

        // If there is no limit how many times the user can try to login.
        if (!$this->configuration->hasMaxLoginAttempts()) {
            $logger->warning('Ignoring failed login attempt because max login attempts is not configured');

            return $result;
        }

        // Does the user still have login attempts left?.
        $currentLoginAttempts = $user->getLoginAttempts();
        if ($currentLoginAttempts < ($this->configuration->getMaxAttempts() - 1)) {
            $user->addLoginAttempt();
            $attemptsLeft = $this->configuration->getMaxAttempts() - $user->getLoginAttempts();
            $logger->notice(sprintf(
                'Increased failed login attempts to %s. Attempts left %s',
                $currentLoginAttempts + 1,
                $attemptsLeft
            ));

            return new RateLimitedAuthenticationResponse($result, $attemptsLeft);
        }

        $logger->notice('No login attempts left');

        // If temporarily block functionality is not enabled, we block the user forever.
        if (!$this->configuration->temporarilyBlockEnabled()) {
            $user->block();
            $logger->notice('User is blocked indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        $now = new \DateTimeImmutable();
        // Just block the user temporarily if we don't got a limit.
        if (!$this->configuration->hasMaxTemporarilyLoginAttempts()) {

            $user->blockTemporarily($now);
            $logger->notice('Increase temporarily block attempt');

            return new TemporarilyBlockedAuthenticationResponse();
        }

        // Block the user for always, if he has reached the maximum login attempts.
        if ($user->getTemporarilyLoginAttempts() < ($this->configuration->getMaxTemporarilyLoginAttempts() - 1)) {
            $user->block();
            $logger->notice('User reached max login attempts, user blocked indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        $user->blockTemporarily($now);
        $attemptsLeft = $this->configuration->getMaxTemporarilyLoginAttempts() - $user->getTemporarilyLoginAttempts();

        $logger->notice(sprintf('Increased temporarily login attempts. Attempts left %d', $attemptsLeft));

        return new RateLimitedAuthenticationResponse($result, $attemptsLeft);
    }
}
