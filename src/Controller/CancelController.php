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

namespace App\Controller;

use App\Exception\NoActiveAuthenrequestException;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class CancelController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly RegistrationService $registrationService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Route(path: '/cancel', name: 'app_cancel', methods: ['GET'])]
    public function cancel(): Response
    {
        $this->logger->notice('User cancelled the request');
        if ($this->authenticationService->authenticationRequired()) {
            $this->authenticationService->reject('User cancelled the request');

            $this->logger->info('Redirect to sso return endpoint with authentication reject response');
            return $this->authenticationService->replyToServiceProvider();
        }
        if ($this->registrationService->registrationRequired()) {
            $this->registrationService->reject('User cancelled the request');

            $this->logger->info('Redirect to sso return endpoint with registration reject response');
            return $this->registrationService->replyToServiceProvider();
        }

        $this->logger->error('There is no pending request from SP');

        throw new NoActiveAuthenrequestException();
    }
}
