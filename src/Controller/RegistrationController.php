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

namespace App\Controller;

use App\Exception\NoActiveAuthenrequestException;
use App\Tiqr\TiqrServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\RegistrationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends AbstractController
{
    private $registrationService;
    private $tiqrService;
    private $logger;
    private $stateHandler;

    public function __construct(
        RegistrationService $registrationService,
        StateHandlerInterface $stateHandler,
        TiqrServiceInterface $tiqrService,
        LoggerInterface $logger
    ) {
        $this->registrationService = $registrationService;
        $this->stateHandler = $stateHandler;
        $this->tiqrService = $tiqrService;
        $this->logger = $logger;
    }

    /**
     * Returns the registration page with QR code that is generated in 'qrRegistrationAction'.
     *
     * @Route("/registration", name="app_identity_registration", methods={"GET", "POST"})
     *
     * @throws \InvalidArgumentException
     */
    public function registrationAction(Request $request)
    {
        $this->logger->info('Verifying if there is a pending registration from SP');

        // Do have a valid sample AuthnRequest?.
        if (!$this->registrationService->registrationRequired()) {
            $this->logger->warning('Registration is not required');
            throw new NoActiveAuthenrequestException();
        }

        $this->logger->info('There is a pending registration');

        $this->logger->info('Verifying if registration is finalized');

        if ($this->tiqrService->enrollmentFinalized()) {
            $this->logger->info('Registration is finalized returning to service provider');
            $this->registrationService->register($this->tiqrService->getUserId());
            return $this->registrationService->replyToServiceProvider();
        }

        $this->logger->info('Registration is not finalized return QR code');

        $this->logger->info('Generating enrollment key');
        $key = $this->tiqrService->generateEnrollmentKey(
            $this->stateHandler->getRequestId()
        );
        $metadataUrl = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));

        return $this->render(
            'default/registration.html.twig',
            [
                'metadataUrl' => sprintf("tiqrenroll://%s", $metadataUrl),
                'enrollmentKey' => $key
            ]
        );
    }

    /**
     * For client-side polling retrieving the status.
     *
     * @Route("/registration/status", name="app_identity_registration_status", methods={"GET"})
     *
     * @throws \InvalidArgumentException
     */
    public function registrationStatusAction(Request $request)
    {
        $this->logger->info('Request for registration status');

        // Do have a valid sample AuthnRequest?.
        if (!$this->registrationService->registrationRequired()) {
            $this->logger->error('There is no pending registration request');

            return new Response('No registration required', Response::HTTP_BAD_REQUEST);
        }

        $status = $this->tiqrService->getEnrollmentStatus();
        $this->logger->info(sprintf('Send json response status "%s"', $status));

        return new Response($this->tiqrService->getEnrollmentStatus());
    }

    /**
     * Returns the QR code img for registration.
     *
     * @see /registration/qr/link
     *
     * @Route("/registration/qr/{enrollmentKey}", name="app_identity_registration_qr", methods={"GET"})
     *
     * @throws \InvalidArgumentException
     */
    public function registrationQrAction(Request $request, $enrollmentKey)
    {
        $this->logger->info('Request for registration QR img');

        if (!$this->registrationService->registrationRequired()) {
            $this->logger->error('There is no pending registration request');

            return new Response('No registration required', Response::HTTP_BAD_REQUEST);
        }
        $metadataUrl = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($enrollmentKey)));
        $this->logger->info('Returning registration QR response');
        return $this->tiqrService->createRegistrationQRResponse($metadataUrl);
    }
}
