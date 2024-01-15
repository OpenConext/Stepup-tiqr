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

namespace Surfnet\Tiqr\Controller;

use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\RegistrationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Surfnet\Tiqr\Exception\NoActiveAuthenrequestException;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly StateHandlerInterface $stateHandler,
        private readonly TiqrServiceInterface $tiqrService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Returns the registration page with QR code that is generated in 'qrRegistrationAction'.
     *
     * @throws \InvalidArgumentException
     */
    #[Route(path: '/registration', name: 'app_identity_registration', methods: ['GET', 'POST'])]
    public function registration(Request $request): Response
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
     *
     * @throws \InvalidArgumentException
     */
    #[Route(path: '/registration/status', name: 'app_identity_registration_status', methods: ['GET'])]
    public function registrationStatus() : Response
    {
        $this->logger->info('Request for registration status');
        // Do have a valid sample AuthnRequest?.
        if (!$this->registrationService->registrationRequired()) {
            $this->logger->error('There is no pending registration request');

            return new Response('No registration required', Response::HTTP_BAD_REQUEST);
        }
        $status = $this->tiqrService->getEnrollmentStatus();
        $this->logger->info(sprintf('Send json response status "%s"', $status));
        return new Response((string) $this->tiqrService->getEnrollmentStatus());
    }

    /**
     * Returns the QR code img for registration.
     *
     * @see /registration/qr/link
     *
     *
     * @throws \InvalidArgumentException
     */
    #[Route(path: '/registration/qr/{enrollmentKey}', name: 'app_identity_registration_qr', methods: ['GET'])]
    public function registrationQr(Request $request, string $enrollmentKey): Response
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
