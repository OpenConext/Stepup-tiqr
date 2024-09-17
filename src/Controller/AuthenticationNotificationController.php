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
use Surfnet\Tiqr\Attribute\RequiresActiveSession;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AuthenticationNotificationController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly StateHandlerInterface $stateHandler,
        private readonly TiqrServiceInterface $tiqrService,
        private readonly TiqrUserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/authentication/notification', name: 'app_identity_authentication_notification', methods: ['POST'])]
    #[RequiresActiveSession]
    public function __invoke(): Response
    {
        $nameId = $this->authenticationService->getNameId();
        $sari = $this->stateHandler->getRequestId();
        $logger = WithContextLogger::from($this->logger, ['nameId' => $nameId, 'sari' => $sari]);
        $logger->info('Client requests sending push notification');

        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            $logger->error('There is no pending authentication request from SP');

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        $logger->info('Sending push notification');

        // Get user.
        try {
            $user = $this->userRepository->getUser($nameId);
        } catch (UserNotExistsException $exception) {
            $logger->error(sprintf(
                'User with nameId "%s" not found, error "%s"',
                $nameId,
                $exception->getMessage()
            ));

            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        // Send notification.
        $notificationType = $user->getNotificationType();
        $notificationAddress = $user->getNotificationAddress();

        if ($notificationType && $notificationAddress) {
            $this->logger->notice(sprintf(
                'Sending push notification for user "%s" with type "%s" and (untranslated) address "%s"',
                $nameId,
                $notificationType,
                $notificationAddress
            ));

            $result = $this->sendNotification($notificationType, $notificationAddress);
            if ($result) {
                return $this->generateNotificationResponse('success');
            }
            return $this->generateNotificationResponse('error');
        }

        $this->logger->notice(sprintf('No notification address for user "%s", no notification was sent', $nameId));

        return $this->generateNotificationResponse('no-device');
    }

    /**
     * @return bool True when the notification was successfully sent, false otherwise
     */
    private function sendNotification(string $notificationType, string $notificationAddress): bool
    {
        try {
            $this->tiqrService->sendNotification($notificationType, $notificationAddress);
        } catch (Exception $e) {
            $this->logger->warning(
                sprintf(
                    'Failed to send push notification for type "%s" and address "%s"',
                    $notificationType,
                    $notificationAddress
                ),
                [
                    'exception' => $e,
                ]
            );
            return false;
        }

        $this->logger->notice(
            sprintf(
                'Successfully sent push notification for type "%s" and address "%s"',
                $notificationType,
                $notificationAddress
            )
        );

        return true;
    }

    /**
     * Generate a notification response for authentication.html.
     *
     * The javascript in the authentication page expects one of three statuses:
     *
     *  - success: Notification send successfully
     *  - error: Notification was not send successfully
     *  - no-device: There was no device to send the notification
     *
     * @return JsonResponse
     */
    private function generateNotificationResponse(string $status): JsonResponse
    {
        return new JsonResponse($status);
    }
}
