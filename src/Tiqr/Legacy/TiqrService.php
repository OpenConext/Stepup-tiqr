<?php

declare(strict_types = 1);

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

namespace Surfnet\Tiqr\Tiqr\Legacy;

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Exception\TiqrServerRuntimeException;
use Surfnet\Tiqr\HealthCheck\HealthCheckResultDto;
use Surfnet\Tiqr\Service\TimeoutHelper;
use Surfnet\Tiqr\Tiqr\Response\AuthenticationErrorResponse;
use Surfnet\Tiqr\Tiqr\Response\AuthenticationResponse;
use Surfnet\Tiqr\Tiqr\Response\RejectedAuthenticationResponse;
use Surfnet\Tiqr\Tiqr\Response\ValidAuthenticationResponse;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tiqr_HealthCheck_Interface;
use Tiqr_Service;
use Tiqr_StateStorage_StateStorageInterface;

/**
 * Wrapper around the legacy Tiqr service.
 *
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * It's Legacy.
 */
final class TiqrService implements TiqrServiceInterface
{
    public const ENROLL_KEYS_SESSION_NAME = 'enrollment-session-keys';

    public const ENROLLMENT_TIMEOUT_STATUS = 'TIMEOUT';

    /**
     * Unix timestamp when the enrollment started
     */
    private const ENROLLMENT_STARTED_AT = 'enrollment-started-at';

    /**
     * Unix timestamp when the authentication started
     */
    private const AUTHENTICATION_STARTED_AT = 'authentication-started-at';

    /**
     * The time (in seconds) that is extracted from the timeout
     * to prevent timeout issues right before the hard timeout
     * time is reached.
     */
    private const TIMEOUT_OFFSET = 2;

    private SessionInterface $session;

    public function __construct(
        private readonly Tiqr_Service $tiqrService,
        private readonly Tiqr_StateStorage_StateStorageInterface $tiqrStateStorage,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly string $appSecret,
        private readonly string $accountName
    ) {
    }

    /**
     * @see TiqrServiceInterface::createRegistrationQRResponse()
     */
    public function createRegistrationQRResponse(string $metadataURL): StreamedResponse
    {
        try {
            return new StreamedResponse(function () use ($metadataURL): void {
                $this->tiqrService->generateEnrollmentQR($metadataURL);
            });
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::getEnrollmentSecret()
     */
    public function getEnrollmentSecret(string $enrollmentKey, string $sari): string
    {
        try {
            $enrollmentSecret = $this->tiqrService->getEnrollmentSecret($enrollmentKey);
            $this->setSariForSessionIdentifier($enrollmentSecret, $sari);
            return $enrollmentSecret;
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::generateEnrollmentKey()
     */
    public function generateEnrollmentKey(string $sari): string
    {
        $this->initSession();
        // We use a randomly generated user ID
        $this->logger->debug('Generating tiqr userId');
        $userId = $this->generateId();
        $this->logger->debug('Storing the userId=' . $userId . ' to session state');
        $this->session->set('userId', $userId);

        $this->recordStartTime(self::ENROLLMENT_STARTED_AT);
        // The session ID is used to link the tiqr library's enrollment session to the user's browser session
        $sessionId = $this->session->getId();
        $this->logger->debug('Clearing the previous enrollment state(s)');

        try {
            $this->clearPreviousEnrollmentState();
            $this->logger->notice('Starting new enrollment session with sessionId ' . $sessionId .
                ' and userId ' . $userId);
            // accountName is the display name of the account that is shown in the tiqr app
            // However, we set it to the name of the tiqr service.
            $enrollmentKey = $this->tiqrService->startEnrollmentSession($userId, $this->accountName, $sessionId);
            $this->logger->debug('Storing the enrollment key for future reference');
            $this->storeEnrollmentKey($enrollmentKey);
            $this->setSariForSessionIdentifier($enrollmentKey, $sari);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
        $this->logger->debug('Returning the new enrollment key');
        return $enrollmentKey;
    }

    /**
     * When the registration key is generated (enrollment key), it
     * should be stored in session. This to be able to clear it when
     * a new registration starts (in another browser tab).
     */
    private function storeEnrollmentKey(string $key): void
    {
        $this->initSession();
        $keys = [];
        if ($this->session->has(self::ENROLL_KEYS_SESSION_NAME)) {
            $keys = $this->session->get(self::ENROLL_KEYS_SESSION_NAME);
        }
        $keys[] = $key;
        $this->session->set(self::ENROLL_KEYS_SESSION_NAME, $keys);
    }

    /**
     * @see TiqrServiceInterface::getEnrollmentMetadata()
     * @return array<string, mixed>
     */
    public function getEnrollmentMetadata(string $key, string $loginUri, string $enrollmentUrl): array
    {
        try {
            return $this->tiqrService->getEnrollmentMetadata($key, $loginUri, $enrollmentUrl);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::validateEnrollmentSecret()
     */
    public function validateEnrollmentSecret(string $enrollmentSecret): string
    {
        try {
            return $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::finalizeEnrollment()
     */
    public function finalizeEnrollment(string $enrollmentSecret): void
    {
        try {
            $this->tiqrService->finalizeEnrollment($enrollmentSecret);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::startAuthentication()
     */
    public function startAuthentication(string $userId, string $sari): string
    {
        $this->initSession();
        try {
            $sessionKey = $this->tiqrService->startAuthenticationSession($userId, $this->session->getId());
            $this->session->set('sessionKey', $sessionKey);

            $this->setSariForSessionIdentifier($sessionKey, $sari);
            $this->recordStartTime(self::AUTHENTICATION_STARTED_AT);
            return $this->tiqrService->generateAuthURL($sessionKey);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::isAuthenticated()
     */
    public function isAuthenticated(): bool
    {
        $this->initSession();
        return $this->tiqrService->getAuthenticatedUser($this->session->getId()) !== null;
    }

    /**
     * Response with authentication challenge QR code.
     *
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     */
    public function createAuthenticationQRResponse(): StreamedResponse
    {
        $sessionKey = $this->getAuthenticationSessionKey();
        try {
            return new StreamedResponse(function () use ($sessionKey): void {
                $this->tiqrService->generateAuthQR($sessionKey);
            });
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /***
     * @see TiqrServiceInterface::authenticationUrl()
     */
    public function authenticationUrl(): string
    {
        try {
            $sessionKey = $this->getAuthenticationSessionKey();

            return $this->tiqrService->generateAuthURL($sessionKey);
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Error generating authentication URL for sessionKey %s: %s', $sessionKey, $e->getMessage())
            );
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::authenticate()
     */
    public function authenticate(TiqrUserInterface $user, string $response, string $sessionKey): AuthenticationResponse
    {
        try {
            $result = $this->tiqrService->authenticate($user->getId(), $user->getSecret(), $sessionKey, $response);
            return match ($result) {
                Tiqr_Service::AUTH_RESULT_AUTHENTICATED => new ValidAuthenticationResponse('OK'),
                Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE => new AuthenticationErrorResponse('INVALID_CHALLENGE'),
                Tiqr_Service::AUTH_RESULT_INVALID_REQUEST => new AuthenticationErrorResponse('INVALID_REQUEST'),
                Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE => new RejectedAuthenticationResponse('INVALID_RESPONSE'),
                Tiqr_Service::AUTH_RESULT_INVALID_USERID => new AuthenticationErrorResponse('INVALID_USER'),
                default => new AuthenticationErrorResponse('ERROR'),
            };
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Error authenticating user "%s": %s', $user->getId(), $e->getMessage())
            );
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::getEnrollmentStatus()
     */
    public function getEnrollmentStatus(): int
    {
        $this->initSession();
        try {
            return $this->tiqrService->getEnrollmentStatus($this->session->getId());
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::enrollmentFinalized()
     */
    public function enrollmentFinalized(): bool
    {
        try {
            return $this->getEnrollmentStatus() === Tiqr_Service::ENROLLMENT_STATUS_FINALIZED;
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrServiceInterface::enrollmentFinalized()
     */
    public function getUserId(): string
    {
        $this->initSession();
        return $this->session->get('userId');
    }

    /**
     * @see TiqrServiceInterface::getAuthenticationSessionKey()
     */
    public function getAuthenticationSessionKey(): string
    {
        $this->initSession();
        return $this->session->get('sessionKey');
    }

    /**
     * @see TiqrServiceInterface::sendNotification()
     */
    public function sendNotification(string $notificationType, string $notificationAddress): void
    {
        try {
            $translatedAddress = $this->tiqrService->translateNotificationAddress($notificationType, $notificationAddress);
            if (false === $translatedAddress) {
                throw new TiqrServerRuntimeException(sprintf('Error translating address for "%s"', $notificationAddress));
            }
            $this->tiqrService->sendAuthNotification($this->getAuthenticationSessionKey(), $notificationType, (string) $translatedAddress);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }


    //////////
    // Private
    /**
     * Currently the legacy way to generate the user Tiqr id.
     *
     * TODO:maybe use something like UUID?
     *
     *
     * @return string
     */
    private function generateId(int $length = 4): string
    {
        return base_convert((string) time(), 10, 36) . '-' . base_convert((string) mt_rand(0, 36 ** $length), 10, 36);
    }

    /** Create a stable hash from $identifier
     * @return string The hashed version of $identifier
     */
    private function getHashedIdentifier(string $identifier): string
    {
        return hash_hmac('sha256', $identifier, $this->appSecret);
    }

    public function getSariForSessionIdentifier(string $identifier): string
    {
        try {
            $hashedIdentifier = $this->getHashedIdentifier($identifier);
            $res = $this->tiqrStateStorage->getValue('sari_' . $hashedIdentifier);
        } catch (Exception) {
            $this->logger->error(sprintf('Error getting SARI for identifier "%s"', substr($identifier, 0, 8)));
            return '';
        }
        if (!is_string($res)) {
            $this->logger->info(sprintf('No SARI set for "%s"', substr($identifier, 0, 8)));
            return '';
        }

        return $res;
    }

    /**
     * Associate $identifier with the provided stepup authentication request id (SARI)
     * Associations expire after one hour
     * Use getSariForSessionIdentifier($sari) later to retrieve the SARI for an $identifier
     * Use unsetSariForSessionIdentifier($sari) to remove te association
     *
     * The goal of the *SariForSessionIdentifier family of functions is to keep track of the
     * different identifiers that are used in the tiqr protocol with the tiqr client
     * (3 for enrollment, 1 for authentication). This allows us to correlate the actions of the
     * user's browser with those of the user's phone.
     *
     * Because the enrollment identifier is sensitive two measures are implemented:
     * - Only the first 8 characters of the identifiers are logged
     * - The identifiers are stored as a stable hash of the identifier to hide the rest of the identifier.
     *
     * @param string $identifier Session identifier: enrollment key or session key
     * @param string $sari       The to associate with $identifier
     * @throws TiqrServerRuntimeException
     */
    private function setSariForSessionIdentifier(string $identifier, string $sari): void
    {
        $this->logger->info(
            sprintf("Setting SARI '%s' for identifier '%s...'", $sari, substr($identifier, 0, 8))
        );
        try {
            $hashedIdentifier = $this->getHashedIdentifier($identifier);
            $this->tiqrStateStorage->setValue('sari_' . $hashedIdentifier, $sari, 60 * 60);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * Read the session for previously started registrations (enrollments) and
     * clear them in the tiqr-server.
     */
    private function clearPreviousEnrollmentState(): void
    {
        $this->initSession();

        $keys = [];
        if ($this->session->has(self::ENROLL_KEYS_SESSION_NAME)) {
            $keys = $this->session->get(self::ENROLL_KEYS_SESSION_NAME);
        }
        $format = "Removing %d keys from the enrollment session states";
        $this->logger->debug(sprintf($format, count($keys)));
        try {
            foreach ($keys as $key) {
                $this->tiqrService->clearEnrollmentState($key);
                unset($keys[$key]);
            }
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
        $format = "Reset enroll session keys, remaining keys: %d";
        $this->logger->debug(sprintf($format, count($keys)));

        $this->session->set(self::ENROLL_KEYS_SESSION_NAME, $keys);
    }

    private function initSession(): void
    {
        $this->session = $this->requestStack->getSession();
    }

    public function stateStorageHealthCheck(): HealthCheckResultDto
    {
        assert($this->tiqrStateStorage instanceof  Tiqr_HealthCheck_Interface);

        return HealthCheckResultDto::fromHealthCheckInterface($this->tiqrStateStorage);
    }

    protected function getAuthenticationTimeout(): int
    {
        return Tiqr_Service::CHALLENGE_EXPIRE;
    }

    protected function getEnrollmentTimeout(): int
    {
        return Tiqr_Service::ENROLLMENT_EXPIRE;
    }

    public function isAuthenticationTimedOut(): bool
    {
        $this->initSession();
        $this->logger->debug('Checking if authentication timeout is reached');
        $startedAt = $this->session->get(self::AUTHENTICATION_STARTED_AT);
        assert(is_int($startedAt));
        return TimeoutHelper::isTimedOut(
            time(),
            $startedAt,
            $this->getAuthenticationTimeout(),
            self::TIMEOUT_OFFSET
        );
    }

    public function isEnrollmentTimedOut(): bool
    {
        $this->initSession();
        $this->logger->debug('Checking if enrollment timeout is reached');
        $startedAt = $this->session->get(self::ENROLLMENT_STARTED_AT);
        assert(is_int($startedAt));
        return TimeoutHelper::isTimedOut(
            time(),
            $startedAt,
            $this->getEnrollmentTimeout(),
            self::TIMEOUT_OFFSET
        );
    }

    private function recordStartTime(string $sessionFieldIdentifier): void
    {
        $startedAt = time();
        $this->logger->debug(sprintf('Storing the %s = %s', $sessionFieldIdentifier, $startedAt));
        $this->session->set($sessionFieldIdentifier, $startedAt);
    }
}
