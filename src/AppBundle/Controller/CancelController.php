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

use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class CancelController extends Controller
{
    private $authenticationService;
    private $registrationService;
    private $logger;

    public function __construct(
        AuthenticationService $authenticationService,
        RegistrationService $registrationService,
        LoggerInterface $logger
    ) {
        $this->authenticationService = $authenticationService;
        $this->registrationService = $registrationService;
        $this->logger = $logger;
    }

    /**
     * @Route("/cancel", name="app_cancel")
     * @throws \InvalidArgumentException
     */
    public function cancelAction()
    {
        $this->logger->notice('User canceled the request');
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
        return new Response('No active authnrequest', Response::HTTP_BAD_REQUEST);
    }
}
