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

namespace AppBundle\Controller;

use AppBundle\Tiqr\TiqrServiceInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationController extends Controller
{
    private $authenticationService;
    private $tiqrService;

    public function __construct(
        AuthenticationService $authenticationService,
        TiqrServiceInterface $tiqrService
    ) {
        $this->authenticationService = $authenticationService;
        $this->tiqrService = $tiqrService;
    }

    /**
     * @Route("/authentication", name="app_identity_authentication")
     */
    public function authenticationAction(Request $request)
    {
        $nameId = $this->authenticationService->getNameId();

        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        // Handle one time password
        if ($request->get('otp') !== null) {
            // Implement otp
            return new Response('One time passord: '. $request->get('otp'), Response::HTTP_I_AM_A_TEAPOT);
        }

        // Are we already logged in with tiqr?
        if ($this->tiqrService->isAuthenticated()) {
            $this->authenticationService->authenticate();

            return $this->authenticationService->replyToServiceProvider();
        }

        // Start authentication process.
        try {
            $this->tiqrService->startAuthentication($nameId);
        } catch (\Exception $e) {
            $this->authenticationService->reject($e->getMessage());

            return $this->authenticationService->replyToServiceProvider();
        }

        return $this->render('AppBundle:default:authentication.html.twig', []);
    }

    /**
     * @Route("/authentication/status", name="app_identity_authentication_status")
     */
    public function authenticationStatusAction()
    {
        if (!$this->authenticationService->authenticationRequired()) {
            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->tiqrService->isAuthenticated());
    }

    /**
     *
     * @Route("/authentication/qr", name="app_identity_authentication_qr")
     */
    public function qr()
    {
        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }
        $this->tiqrService->exitWithAuthenticationQR();
    }
}
