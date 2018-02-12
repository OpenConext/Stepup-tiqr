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

namespace AppBundle\Tiqr\Legacy;

use AppBundle\Tiqr\Response\AuthenticationErrorResponse;
use AppBundle\Tiqr\Response\AuthenticationResponse;
use AppBundle\Tiqr\Response\RejectedAuthenticationResponse;
use AppBundle\Tiqr\Response\ValidAuthenticationResponse;
use AppBundle\Tiqr\TiqrServiceInterface;
use AppBundle\Tiqr\TiqrUserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tiqr_Service;

/**
 * Wrapper around the legacy Tiqr service.
 */
final class TiqrService implements TiqrServiceInterface
{
    /**
     * @var \Tiqr_Service
     */
    private $tiqrService;
    private $session;

    public function __construct($tiqrService, SessionInterface $session)
    {
        $this->tiqrService = $tiqrService;
        $this->session = $session;
    }

    public function exitWithRegistrationQR($metadataURL)
    {
        $this->tiqrService->generateEnrollmentQR($metadataURL);
        exit(200);
    }

    public function getEnrollmentSecret($key)
    {
        return $this->tiqrService->getEnrollmentSecret($key);
    }

    /**
     * Starts and generates an enrollment key.
     *
     * @return string
     */
    public function generateEnrollmentKey()
    {
        $id = $this->generateId();

        return $this->tiqrService->startEnrollmentSession($id, 'SURFconext', $this->session->getId());
    }

    public function getEnrollmentMetadata($key, $loginUri, $enrollmentUrl)
    {
        return $this->tiqrService->getEnrollmentMetadata($key, $loginUri, $enrollmentUrl);
    }

    public function validateEnrollmentSecret($enrollmentSecret)
    {
        return $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);
    }

    public function finalizeEnrollment($enrollmentSecret)
    {
        $this->tiqrService->finalizeEnrollment($enrollmentSecret);
    }

    public function startAuthentication($nameId)
    {
        $sessionKey = $this->tiqrService->startAuthenticationSession($nameId, $this->session->getId());
        $this->session->set('sessionKey', $sessionKey);

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
        $sessionKey = $this->session->get('sessionKey');

        return $this->tiqrService->generateAuthURL($sessionKey);
    }

    /**
     * Generate an exit and authentication challenge QR code.
     *
     * This URL can be used to link directly to the authentication
     * application, for example to create a link in a mobile website on the
     * same device as where the application is installed
     */
    public function exitWithAuthenticationQR()
    {
        $sessionKey = $this->session->get('sessionKey');
        $this->tiqrService->generateAuthQR($sessionKey);
        exit(200);
    }

    /**
     * @param TiqrUserInterface $user
     * @param string $response
     *
     * @return AuthenticationResponse
     */
    public function authenticate(TiqrUserInterface $user, $response, $sessionKey)
    {
        $result = $this->tiqrService->authenticate($user->getId(), $user->getSecret(), $sessionKey, $response);
        switch ($result) {
            case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
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
}
