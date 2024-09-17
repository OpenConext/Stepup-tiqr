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

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Surfnet\Tiqr\Attribute\RequiresActiveSession;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthenticationQrController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly StateHandlerInterface $stateHandler,
        private readonly TiqrServiceInterface $tiqrService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/authentication/qr', name: 'app_identity_authentication_qr', methods: ['GET'])]
    #[RequiresActiveSession]
    public function __invoke(): Response
    {
        $nameId = $this->authenticationService->getNameId();
        $sari = $this->stateHandler->getRequestId();
        $logger = WithContextLogger::from($this->logger, ['nameId' => $nameId, 'sari' => $sari]);
        $logger->info('Client request QR image');

        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            $logger->error('There is no pending authentication request from SP');

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        $logger->info('Return QR image response');

        return $this->tiqrService->createAuthenticationQRResponse();
    }
}
