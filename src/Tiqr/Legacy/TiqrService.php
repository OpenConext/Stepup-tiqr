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

namespace App\Tiqr\Legacy;

use App\Exception\TiqrServerRuntimeException;
use App\Tiqr\Response\AuthenticationErrorResponse;
use App\Tiqr\Response\AuthenticationResponse;
use App\Tiqr\Response\RejectedAuthenticationResponse;
use App\Tiqr\Response\ValidAuthenticationResponse;
use App\Tiqr\TiqrServiceInterface;
use App\Tiqr\TiqrUserInterface;
use Psr\Log\LoggerInterface;
use ReadWriteException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tiqr_Service;
use Tiqr_StateStorage_Abstract;
use Tiqr_StateStorage_StateStorageInterface;

/**
 * Wrapper around the legacy Tiqr service.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * It's Legacy.
 */
final class TiqrService implements TiqrServiceInterface
{
    const ENROLL_KEYS_SESSION_NAME = 'enrollment-session-keys';
    /**
     * @var \Tiqr_Service
     */
    private $tiqrService;
    private $tiqrStateStorage;
    private $session;
    private $logger;

    /**
     * @var string
     */
    private $accountName;

    public function __construct(
        Tiqr_Service $tiqrService,
        Tiqr_StateStorage_StateStorageInterface $tiqrStateStorage,
        SessionInterface $session,
        LoggerInterface $logger,
        $accountName
    ) {
        $this->tiqrService = $tiqrService;
        $this->tiqrStateStorage = $tiqrStateStorage;
        $this->session = $session;
        $this->logger = $logger;
        $this->accountName = $accountName;
    }

    public function createRegistrationQRResponse($metadataURL)
    {
        return new StreamedResponse(function () use ($metadataURL) {
            $this->tiqrService->generateEnrollmentQR($metadataURL);
        });
    }

    public function getEnrollmentSecret($key)
    {
        try {
            return $this->tiqrService->getEnrollmentSecret($key);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * Starts and generates an enrollment key.
     *
     * @param string $sari
     *
     * @return string
     */
    public function generateEnrollmentKey($sari)
    {
        $this->logger->debug('Generating userId');
        $userId = $this->generateId();
        $this->logger->debug('Storing the userId to session state');
        $this->session->set('userId', $userId);
        $sessionId = $this->session->getId();
        $this->logger->debug('Clearing the previous enrollment state(s)');

        try {
            $this->clearPreviousEnrollmentState();
            $this->logger->debug('Starting the new enrollment session');
            $enrollmentKey = $this->tiqrService->startEnrollmentSession($userId, $this->accountName, $sessionId);
            $this->logger->debug('Storing the enrollemnt key for future reference');
            $this->storeEnrollmentKey($enrollmentKey);
            $this->setSariForSessionIdentifier($enrollmentKey, $sari);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
        $this->logger->debug('Returning the new enrollment key');
        return $enrollmentKey;
    }

    /**
     * When the registration key is generated (enrollemnt key), it
     * should be stored in session. This to be able to clear it when
     * a new registration starts (in another browser tab).
     */
    private function storeEnrollmentKey(string $key): void
    {
        $keys = [];
        if ($this->session->has(self::ENROLL_KEYS_SESSION_NAME)) {
            $keys = $this->session->get(self::ENROLL_KEYS_SESSION_NAME);
        }
        $keys[] = $key;
        $this->session->set(self::ENROLL_KEYS_SESSION_NAME, $keys);
    }

    /**
     * Read the session for previously started registrations (enrollments) and
     * clear them in the tiqr-server.
     */
    private function clearPreviousEnrollmentState(): void
    {
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
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
        $format = "Reset enroll session keys, remaining keys: %d";
        $this->logger->debug(sprintf($format, count($keys)));

        $this->session->set(self::ENROLL_KEYS_SESSION_NAME, $keys);
    }

    public function getUserId()
    {
        return $this->session->get('userId');
    }

    public function getEnrollmentMetadata($key, $loginUri, $enrollmentUrl)
    {
        try {
            return $this->tiqrService->getEnrollmentMetadata($key, $loginUri, $enrollmentUrl);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    public function validateEnrollmentSecret($enrollmentSecret)
    {
        try {
            return $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    public function finalizeEnrollment($enrollmentSecret)
    {
        try {
            $this->tiqrService->finalizeEnrollment($enrollmentSecret);
            $this->unsetSariForSessionIdentifier($enrollmentSecret);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    public function startAuthentication($nameId, $sari)
    {
        try {
            $sessionKey = $this->tiqrService->startAuthenticationSession($nameId, $this->session->getId());
            $this->session->set('sessionKey', $sessionKey);

            $this->setSariForSessionIdentifier($sessionKey, $sari);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }

        return $this->tiqrService->generateAuthURL($sessionKey);
    }

    /**
     * @return boolean
     */
    public function isAuthenticated()
    {
        return $this->tiqrService->getAuthenticatedUser($this->session->getId()) !== null;
    }

    /**
     * Generate an authentication challenge URL.
     *
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     */
    public function authenticationUrl()
    {
        $sessionKey = $this->getAuthenticationSessionKey();

        return $this->tiqrService->generateAuthURL($sessionKey);
    }

    /**
     * Response with authentication challenge QR code.
     *
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     */
    public function createAuthenticationQRResponse()
    {
        $sessionKey = $this->getAuthenticationSessionKey();
        return new StreamedResponse(function () use ($sessionKey) {
            $this->tiqrService->generateAuthQR($sessionKey);
        });
    }

    /**
     * @param TiqrUserInterface $user
     * @param string $response
     *
     * @return AuthenticationResponse
     */
    public function authenticate(TiqrUserInterface $user, $response, $sessionKey)
    {
        try {
            $result = $this->tiqrService->authenticate($user->getId(), $user->getSecret(), $sessionKey, $response);
            switch ($result) {
                case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
                    $this->unsetSariForSessionIdentifier($sessionKey);

                    return new ValidAuthenticationResponse('OK');
                case Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE:
                    return new AuthenticationErrorResponse('INVALID_CHALLENGE');
                case Tiqr_Service::AUTH_RESULT_INVALID_REQUEST:
                    return new AuthenticationErrorResponse('INVALID_REQUEST');
                case Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE:
                    return new RejectedAuthenticationResponse('INVALID_RESPONSE');
                case Tiqr_Service::AUTH_RESULT_INVALID_USERID:
                    return new AuthenticationErrorResponse('INVALID_USER');
                default:
                    return new AuthenticationErrorResponse('ERROR');
            }
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * Currently the legacy way to generate the user Tiqr id.
     *
     * TODO:maybe use something like UUID?
     *
     * @param int $length
     *
     * @return string
     */
    private function generateId($length = 4)
    {
        return base_convert(time(), 10, 36).'-'.base_convert(mt_rand(0, pow(36, $length)), 10, 36);
    }

    /**
     * Returns the current enrollment status.
     *
     * @return string
     */
    public function getEnrollmentStatus()
    {
        return $this->tiqrService->getEnrollmentStatus($this->session->getId());
    }

    /**
     * If the user is enrolled
     *
     * @return boolean
     */
    public function enrollmentFinalized()
    {
        return $this->getEnrollmentStatus() === Tiqr_Service::ENROLLMENT_STATUS_FINALIZED;
    }

    /**
     * Return the authentication session id.
     *
     * @return string
     */
    public function getAuthenticationSessionKey()
    {
        return $this->session->get('sessionKey');
    }

    /**
     * Send authentication notification.
     *
     * @param string $notificationType
     * @param string $notificationAddress
     * @return bool
     */
    public function sendNotification($notificationType, $notificationAddress)
    {
        $translatedAddress = $this->tiqrService->translateNotificationAddress($notificationType, $notificationAddress);
        return $this->tiqrService->sendAuthNotification($this->getAuthenticationSessionKey(), $notificationType, $translatedAddress);
    }

    /**
     * Return error information about the last failed push notification attempt.
     *
     * @return array
     */
    public function getNotificationError()
    {
        return $this->tiqrService->getNotificationError();
    }

    /**
     * @param string $identifier Enrollment key or session key
     *
     * @return string
     */
    public function getSariForSessionIdentifier($identifier)
    {
        return $this->tiqrStateStorage->getValue('sari_' . $identifier);
    }


    /**
     * @param string $identifier Enrollment key or session key
     */
    private function unsetSariForSessionIdentifier($identifier)
    {
        try {
            $this->tiqrStateStorage->unsetValue('sari_' . $identifier);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @param string $identifier Session identifier: enrollment key or session key
     * @param string $sari
     */
    private function setSariForSessionIdentifier($identifier, $sari)
    {
        try {
            $this->tiqrStateStorage->setValue('sari_' . $identifier, $sari);
        } catch (ReadWriteException $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }
}
