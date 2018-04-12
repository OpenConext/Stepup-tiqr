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

namespace AppBundle\Tiqr;

use AppBundle\WithContextLogger;
use AppBundle\Tiqr\Response\AuthenticationResponse;
use AppBundle\Tiqr\Response\PermanentlyBlockedAuthenticationResponse;
use AppBundle\Tiqr\Response\RateLimitedAuthenticationResponse;
use AppBundle\Tiqr\Response\RejectedAuthenticationResponse;
use AppBundle\Tiqr\Response\TemporaryBlockedAuthenticationResponse;
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
        return !$this->configuration->temporaryBlockEnabled() && $user->isBlocked();
    }

    /**
     * @param TiqrUserInterface $user
     *
     * @return bool
     * @throws Exception\ConfigurationException
     */
    public function isBlockedTemporary(TiqrUserInterface $user)
    {
        return $this->configuration->temporaryBlockEnabled() &&
            $user->isBlockTemporary($this->now, $this->configuration->getTemporaryBlockDuration());
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

        if ($this->isBlockedTemporary($user)) {
            $logger->info('User is temporarily blocked');

            return new TemporaryBlockedAuthenticationResponse();
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

        // If temporary block functionality is not enabled, we block the user forever.
        if (!$this->configuration->temporaryBlockEnabled()) {
            $user->block();
            $logger->info('User is blocked indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        // Just block the user temporary if we don't got a limit.
        if (!$this->configuration->hasMaxTemporaryLoginAttempts()) {
            $user->blockTemporary($this->now);
            $logger->info('Increase temporary block attempt');

            return new TemporaryBlockedAuthenticationResponse();
        }

        // Block the user for always, if he has reached the maximum login attempts.
        if ($user->getTemporaryLoginAttempts() < ($this->configuration->getMaxTemporaryLoginAttempts() - 1)) {
            $user->block();
            $logger->info('User reached max login attempts, block user indefinitely');

            return new PermanentlyBlockedAuthenticationResponse();
        }

        $user->blockTemporary($this->now);
        $attemptsLeft = $this->configuration->getMaxTemporaryLoginAttempts() - $user->getTemporaryLoginAttempts();

        $logger->info(sprintf('Increase temporary login attempt. Attempts left %d', $attemptsLeft));

        return new RateLimitedAuthenticationResponse($result, $attemptsLeft);
    }
}
