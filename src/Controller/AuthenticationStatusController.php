<?php

declare(strict_types = 1);

/**
 * Copyright 2024 SURFnet B.V.
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

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Surfnet\Tiqr\Attribute\RequiresActiveSession;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AuthenticationStatusController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly StateHandlerInterface $stateHandler,
        private readonly TiqrServiceInterface $tiqrService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/authentication/status', name: 'app_identity_authentication_status', methods: ['GET'])]
    #[RequiresActiveSession]
    public function __invoke(): JsonResponse
    {
        try {
            $nameId = $this->authenticationService->getNameId();
            $sari = $this->stateHandler->getRequestId();
            $logger = WithContextLogger::from($this->logger, ['nameId' => $nameId, 'sari' => $sari]);
            $logger->info('Request for authentication status');

            if (!$this->authenticationService->authenticationRequired()) {
                $logger->error('There is no pending authentication request from SP');
                return $this->refreshAuthenticationPage();
            }

            $isAuthenticated = $this->tiqrService->isAuthenticated();

            if ($isAuthenticated) {
                $logger->info('Send json response is authenticated');

                return $this->refreshAuthenticationPage();
            }

            if ($this->tiqrService->isAuthenticationTimedOut()) {
                $this->logger->info('The authentication timed out');
                return $this->timeoutNeedsManualRetry();
            }

            $logger->info('Send json response is not authenticated');

            return $this->scheduleNextPollOnAuthenticationPage();
        } catch (Exception $e) {
            $this->logger->error(
                sprintf(
                    'An unexpected authentication error occurred. Responding with "invalid-request", '.
                    'original exception message: "%s"',
                    $e->getMessage()
                )
            );
            return $this->generateAuthenticationStatusResponse('invalid-request');
        }
    }

    /**
     * Generate a status response for authentication.html.
     *
     * The javascript in the authentication page expects one of four statuses:
     *
     *  - pending: waiting for user action, schedule next poll
     *  - needs-refresh: refresh the page (the /authentication page will handle the success or error)
     *  - challenge-expired: Message user challenge is expired, let the user give the option to retry.
     *  - invalid-request: There was a state issue, or another reason why authentication failed
     *
     * @return JsonResponse
     */
    private function generateAuthenticationStatusResponse(string $status): JsonResponse
    {
        return new JsonResponse($status);
    }

    /**
     * Generate a response for authentication.html: refresh the page.
     *
     * @return JsonResponse
     */
    private function refreshAuthenticationPage(): JsonResponse
    {
        return $this->generateAuthenticationStatusResponse('needs-refresh');
    }

    /**
     * Generate a response for authentication.html: Ask the user to retry.
     *
     * @return JsonResponse
     */
    private function timeoutNeedsManualRetry(): JsonResponse
    {
        return $this->generateAuthenticationStatusResponse('challenge-expired');
    }

    /**
     * Authentication is pending, schedule a new poll action.
     *
     * @return JsonResponse
     */
    private function scheduleNextPollOnAuthenticationPage(): JsonResponse
    {
        return $this->generateAuthenticationStatusResponse('pending');
    }
}
