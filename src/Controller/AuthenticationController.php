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
use Surfnet\Tiqr\Exception\NoActiveAuthenrequestException;
use Surfnet\Tiqr\Exception\UserNotFoundException;
use Surfnet\Tiqr\Exception\UserPermanentlyBlockedException;
use Surfnet\Tiqr\Exception\UserTemporarilyBlockedException;
use Surfnet\Tiqr\Tiqr\AuthenticationRateLimitServiceInterface;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;
use Surfnet\Tiqr\Tiqr\Response\AuthenticationResponse;
use Surfnet\Tiqr\Tiqr\Response\RateLimitedAuthenticationResponse;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AuthenticationController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly StateHandlerInterface $stateHandler,
        private readonly TiqrServiceInterface $tiqrService,
        private readonly TiqrUserRepositoryInterface $userRepository,
        private readonly AuthenticationRateLimitServiceInterface $authenticationRateLimitService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws NoActiveAuthenrequestException
     * @throws UserNotFoundException
     * @throws Exception
     */
    #[Route(path: '/authentication', name: 'app_identity_authentication', methods: ['GET', 'POST'])]
    public function authentication(Request $request): Response
    {
        $nameId = $this->authenticationService->getNameId();
        $sari = $this->stateHandler->getRequestId();
        $logger = WithContextLogger::from($this->logger, ['nameId' => $nameId, 'sari' => $sari]);

        $logger->info('Verifying if there is a pending authentication request from SP');

        // Do have a valid sample AuthnRequest?
        if (!$this->authenticationService->authenticationRequired()) {
            $logger->error('There is no pending authentication request from SP');

            throw new NoActiveAuthenrequestException();
        }

        try {
            $user = $this->userRepository->getUser($nameId);
        } catch (UserNotExistsException $exception) {
            $logger->error(sprintf(
                'User with nameId "%s" not found, error "%s"',
                $nameId,
                $exception->getMessage()
            ));

            throw new UserNotFoundException();
        }

        // Verify if user is blocked.
        $logger->info('Verify if user is blocked');
        $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
        $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
        if ($blockedTemporarily || $blockedPermanently) {
            $logger->info('User is blocked');

            return $this->showUserIsBlockedErrorPage($blockedPermanently);
        }

        // Handle one time password
        if ($request->get('otp') !== null) {
            $logger->info('Handling otp');
            $response = $this->authenticationRateLimitService->authenticate(
                $this->tiqrService->getAuthenticationSessionKey(),
                $user,
                $request->get('otp')
            );
            if (!$response->isValid()) {
                return $this->handleInvalidResponse($user, $response, $logger);
            }
        }

        $logger->info('Verifying if authentication is finalized');

        // Are we already logged in with tiqr?
        if ($this->tiqrService->isAuthenticated()) {
            $logger->info('Authentication is finalized, returning to SP');
            $this->authenticationService->authenticate();

            return $this->authenticationService->replyToServiceProvider();
        }

        // Start authentication process.
        try {
            $logger->info('Start authentication');
            $this->tiqrService->startAuthentication(
                $nameId,
                $sari
            );
        } catch (Exception $e) {
            $logger->error(sprintf(
                'Failed to start authentication "%s"',
                $e->getMessage()
            ));
            $logger->info('Return authentication failed response');
            $this->authenticationService->reject($e->getMessage());

            return $this->authenticationService->replyToServiceProvider();
        }

        $logger->info('Return authentication page with QR code');

        return $this->render('default/authentication.html.twig', [
            'authenticateUrl' => $this->tiqrService->authenticationUrl()
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/authentication/status', name: 'app_identity_authentication_status', methods: ['GET'])]
    public function authenticationStatus(): JsonResponse
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
     * Authentication is pending, schedule a new poll action.
     *
     * @return JsonResponse
     */
    private function scheduleNextPollOnAuthenticationPage(): JsonResponse
    {
        return $this->generateAuthenticationStatusResponse('pending');
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
     * Generate a response for authentication.html: refresh the page.
     *
     * @return JsonResponse
     */
    private function refreshAuthenticationPage(): JsonResponse
    {
        return $this->generateAuthenticationStatusResponse('needs-refresh');
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

    /**
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/authentication/qr', name: 'app_identity_authentication_qr', methods: ['GET'])]
    public function authenticationQr(): Response
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

    /**
     *
     * @throws InvalidArgumentException
     */
    #[Route(path: '/authentication/notification', name: 'app_identity_authentication_notification', methods: ['POST'])]
    public function authenticationNotification(): Response
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


    private function handleInvalidResponse(TiqrUserInterface $user, AuthenticationResponse $response, LoggerInterface $logger): Response
    {
        try {
            $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
            $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
            if ($blockedTemporarily || $blockedPermanently) {
                $logger->notice('User is blocked');

                return $this->showUserIsBlockedErrorPage($blockedPermanently);
            }
        } catch (Exception $e) {
            $this->logger->error('Could not determine user (temporary) block state', ['exception' => $e]);
        }

        return $this->render('default/authentication.html.twig', [
            'otpError' => true,
            'attemptsLeft' => $response instanceof RateLimitedAuthenticationResponse ? $response->getAttemptsLeft() : null,
        ]);
    }

    private function showUserIsBlockedErrorPage(bool $isBlockedPermanently): Response
    {
        $exception = new UserTemporarilyBlockedException();

        if ($isBlockedPermanently) {
            $exception = new UserPermanentlyBlockedException();
        }
        // Forward to the exception controller to prevent an error being logged.
        return $this->forward(
            'Surfnet\Tiqr\Controller\ExceptionController::show',
            [
                'exception' => $exception,
            ]
        );
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
}
