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

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
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
    public function __invoke(): JsonResponse
    {
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

        if ($this->authenticationChallengeIsExpired()) {
            return $this->timeoutNeedsManualRetry();
        }

        $logger->info('Send json response is not authenticated');

        return $this->scheduleNextPollOnAuthenticationPage();
    }

    /**
     * Generate a status response for authentication.html.
     *
     * The javascript in the authentication page expects one of three statuses:
     *
     *  - pending: waiting for user action, schedule next poll
     *  - needs-refresh: refresh the page (the /authentication page will handle the success or error)
     *  - challenge-expired: Message user challenge is expired, let the user give the option to retry.
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
     * Check if the authentication challenge is expired.
     *
     * If the challenge is expired, the page should be refreshed so a new
     * challenge and QR code is generated.
     *
     * @return bool
     */
    private function authenticationChallengeIsExpired(): bool
    {
        // The use of authenticationUrl() here is a hack, because it depends on an implementation detail
        // of this function.
        // Effectively this does a $this->_stateStorage->getValue(self::PREFIX_CHALLENGE . $sessionKey);
        // To check that the session key still exists in the Tiqr_Service's state storage
        try {
            $this->tiqrService->authenticationUrl();
        } catch (Exception) {
            return true;
        }
        return false;
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
