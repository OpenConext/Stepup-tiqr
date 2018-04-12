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
use Psr\Log\LoggerInterface;
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
    private $logger;

    public function __construct(
        AuthenticationService $authenticationService,
        TiqrServiceInterface $tiqrService,
        LoggerInterface $logger
    ) {
        $this->authenticationService = $authenticationService;
        $this->tiqrService = $tiqrService;
        $this->logger = $logger;
    }

    /**
     * @Route("/authentication", name="app_identity_authentication")
     * @throws \InvalidArgumentException
     */
    public function authenticationAction(Request $request)
    {
        $this->logger->info('Verifying if there is a pending authentication request from SP');

        $nameId = $this->authenticationService->getNameId();
        $logContext = ['nameId' => $nameId];


        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error(
                'there is no pending authentication request from SP',
                $logContext
            );

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }


        // Handle one time password
        if ($request->get('otp') !== null) {
            $this->logger->info('handling otp', $logContext);

            // Implement otp
            return new Response('One time passord: '.$request->get('otp'), Response::HTTP_I_AM_A_TEAPOT);
        }

        $this->logger->info('Verify if the user is already authenticated', $logContext);

        // Are we already logged in with tiqr?
        if ($this->tiqrService->isAuthenticated()) {
            $this->logger->info('Authentication is finalized, returning to SP', $logContext);
            $this->authenticationService->authenticate();

            return $this->authenticationService->replyToServiceProvider();
        }

        // Start authentication process.
        try {
            $this->logger->info('start authentication', $logContext);
            $this->tiqrService->startAuthentication($nameId);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Failed to start authentication "%s"',
                $e->getMessage()
            ), $logContext);
            $this->logger->info(
                'Returning authentication failed response',
                $logContext
            );
            $this->authenticationService->reject($e->getMessage());

            return $this->authenticationService->replyToServiceProvider();
        }

        $this->logger->info(
            'Returning authentication page with QR code',
            $logContext
        );

        return $this->render('AppBundle:default:authentication.html.twig', []);
    }

    /**
     * @Route("/authentication/status", name="app_identity_authentication_status")
     * @throws \InvalidArgumentException
     */
    public function authenticationStatusAction()
    {
        $this->logger->info('Request for authentication status');

        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error('there is no pending authentication request from SP');

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        $isAuthenticated = $this->tiqrService->isAuthenticated();

        if ($isAuthenticated) {
            $this->logger->info('Send json response is authenticated');
            return new JsonResponse(true);
        }
        $this->logger->info('Send json response is not authenticated');
        return new JsonResponse(false);
    }

    /**
     *
     * @Route("/authentication/qr", name="app_identity_authentication_qr")
     * @throws \InvalidArgumentException
     */
    public function authenticationQrAction()
    {
        $this->logger->info('client request QR image');

        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error('there is no pending authentication request from SP');

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Returning QR image response');

        return $this->tiqrService->createAuthenticationQRResponse();
    }
}
