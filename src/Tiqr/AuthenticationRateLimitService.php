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

use App\WithContextLogger;
use App\Tiqr\Response\AuthenticationResponse;
use App\Tiqr\Response\PermanentlyBlockedAuthenticationResponse;
use App\Tiqr\Response\RateLimitedAuthenticationResponse;
use App\Tiqr\Response\RejectedAuthenticationResponse;
use App\Tiqr\Response\TemporarilyBlockedAuthenticationResponse;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
final class AuthenticationRateLimitService implements AuthenticationRateLimitServiceInterface
{
    private $now;
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
        $this->now = new DateTimeImmutable();
    }

    /**
     * @param TiqrUserInterface $user
     *
     * @return bool
     */
    public function isBlockedPermanently(TiqrUserInterface $user)
    {
        return !$this->configuration->temporarilyBlockEnabled() && $user->isBlocked();
    }

    /**
     * @param TiqrUserInterface $user
     *
     * @return bool
     * @throws Exception\ConfigurationException
     */
    public function isBlockedTemporarily(TiqrUserInterface $user)
    {
        return $this->configuration->temporarilyBlockEnabled() &&
            $user->isBlockTemporarily($this->now, $this->configuration->getTemporarilyBlockDuration());
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
     * @throws \Assert\AssertionFailedException
     */
    public function authenticate($sessionKey, TiqrUserInterface $user, $response)
    {
        $logger = WithContextLogger::from(
            $this->logger,
            ['key' => $sessionKey, 'userId' => $user->getId(), 'sessionKey' => $sessionKey]
        );

        if ($this->isBlockedTemporarily($user)) {
            $logger->info('User is temporarily blocked');

            return new TemporarilyBlockedAuthenticationResponse();
        }

        if ($this->isBlockedPermanently($user)) {
            $logger->info('User is blocked indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        // Verify the app's response.
        $logger->info('Validate user login attempt');
        $result = $this->tiqrService->authenticate($user, $response, $sessionKey);
        if ($result->isValid()) {
            $user->resetLoginAttempts();
            $logger->info('User login attempt is valid');

            return $result;
        }

        // The user did something wrong.
        if ($result instanceof RejectedAuthenticationResponse) {
            return $this->handleAuthenticationRejectResponse($logger, $result, $user);
        }

        $logger->error(
            'Unexpected error occurred '.$result->getMessage()
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
    ) {
        $logger->info('User login attempt is rejected');

        // If there is no limit how many times the user can try to login.
        if (!$this->configuration->hasMaxLoginAttempts()) {
            $logger->info('Ignore attempt');

            return $result;
        }

        // Does the user still have login attempts left?.
        if ($user->getLoginAttempts() < ($this->configuration->getMaxAttempts() - 1)) {
            $user->addLoginAttempt();
            $attemptsLeft = $this->configuration->getMaxAttempts() - $user->getLoginAttempts();
            $logger->info(sprintf(
                'Increase login attempt. Attempts left %s',
                $attemptsLeft
            ));

            return new RateLimitedAuthenticationResponse($result, $attemptsLeft);
        }

        $logger->info('No login attempts left');

        // If temporarily block functionality is not enabled, we block the user forever.
        if (!$this->configuration->temporarilyBlockEnabled()) {
            $user->block();
            $logger->info('User is blocked indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        // Just block the user temporarily if we don't got a limit.
        if (!$this->configuration->hasMaxTemporarilyLoginAttempts()) {
            $user->blockTemporarily($this->now);
            $logger->info('Increase temporarily block attempt');

            return new TemporarilyBlockedAuthenticationResponse();
        }

        // Block the user for always, if he has reached the maximum login attempts.
        if ($user->getTemporarilyLoginAttempts() < ($this->configuration->getMaxTemporarilyLoginAttempts() - 1)) {
            $user->block();
            $logger->info('User reached max login attempts, block user indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        $user->blockTemporarily($this->now);
        $attemptsLeft = $this->configuration->getMaxTemporarilyLoginAttempts() - $user->getTemporarilyLoginAttempts();

        $logger->info(sprintf('Increase temporarily login attempt. Attempts left %d', $attemptsLeft));

        return new RateLimitedAuthenticationResponse($result, $attemptsLeft);
    }
}
