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
use App\Exception\UserNotFoundException;
use App\Exception\UserPermanentlyBlockedException;
use App\Exception\UserTemporarilyBlockedException;
use App\WithContextLogger;
use App\Tiqr\AuthenticationRateLimitServiceInterface;
use App\Tiqr\Exception\UserNotExistsException;
use App\Tiqr\Response\RateLimitedAuthenticationResponse;
use App\Tiqr\TiqrServiceInterface;
use App\Tiqr\TiqrUserInterface;
use App\Tiqr\TiqrUserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AuthenticationController extends AbstractController
{
    private $authenticationService;
    private $tiqrService;
    private $logger;
    private $authenticationRateLimitService;
    private $userRepository;
    private $stateHandler;

    public function __construct(
        AuthenticationService $authenticationService,
        StateHandlerInterface $stateHandler,
        TiqrServiceInterface $tiqrService,
        TiqrUserRepositoryInterface $userRepository,
        AuthenticationRateLimitServiceInterface $authenticationRateLimitService,
        LoggerInterface $logger
    ) {
        $this->authenticationService = $authenticationService;
        $this->stateHandler = $stateHandler;
        $this->tiqrService = $tiqrService;
        $this->logger = $logger;
        $this->authenticationRateLimitService = $authenticationRateLimitService;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/authentication", name="app_identity_authentication", methods={"GET", "POST"})
     * @throws \InvalidArgumentException
     */
    public function authenticationAction(Request $request)
    {
        $nameId = $this->authenticationService->getNameId();
        $logger = WithContextLogger::from($this->logger, ['nameId' => $nameId]);

        $logger->info('Verifying if there is a pending authentication request from SP');

        // Do have a valid sample AuthnRequest?.
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
                $this->stateHandler->getRequestId()
            );
        } catch (\Exception $e) {
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
     * @Route("/authentication/status", name="app_identity_authentication_status", methods={"GET"})
     * @throws \InvalidArgumentException
     */
    public function authenticationStatusAction()
    {
        $this->logger->info('Request for authentication status');

        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error('There is no pending authentication request from SP');
            return $this->refreshAuthenticationPage();
        }

        $isAuthenticated = $this->tiqrService->isAuthenticated();

        if ($isAuthenticated) {
            $this->logger->info('Send json response is authenticated');

            return $this->refreshAuthenticationPage();
        }

        if ($this->authenticationChallengeIsExpired()) {
            return $this->timeoutNeedsManualRetry();
        }

        $this->logger->info('Send json response is not authenticated');

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
    private function authenticationChallengeIsExpired()
    {
        return $this->tiqrService->authenticationUrl() === false;
    }

    /**
     * Authentication is pending, schedule a new poll action.
     *
     * @return JsonResponse
     */
    private function scheduleNextPollOnAuthenticationPage()
    {
        return $this->generateAuthenticationStatusResponse('pending');
    }

    /**
     * Generate a response for authentication.html: Ask the user to retry.
     *
     * @return JsonResponse
     */
    private function timeoutNeedsManualRetry()
    {
        return $this->generateAuthenticationStatusResponse('challenge-expired');
    }

    /**
     * Generate a response for authentication.html: refresh the page.
     *
     * @return JsonResponse
     */
    private function refreshAuthenticationPage()
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
     * @param string $status
     * @return JsonResponse
     */
    private function generateAuthenticationStatusResponse($status)
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
     * @param string $status
     * @return JsonResponse
     */
    private function generateNotificationResponse($status)
    {
        return new JsonResponse($status);
    }

    /**
     *
     * @Route("/authentication/qr", name="app_identity_authentication_qr", methods={"GET"})
     * @throws \InvalidArgumentException
     */
    public function authenticationQrAction()
    {
        $this->logger->info('Client request QR image');

        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error('There is no pending authentication request from SP');

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Return QR image response');

        return $this->tiqrService->createAuthenticationQRResponse();
    }

    /**
     *
     * @Route("/authentication/notification", name="app_identity_authentication_notification", methods={"POST"})
     * @throws \InvalidArgumentException
     */
    public function authenticationNotificationAction()
    {
        $this->logger->info('Client request QR image');

        // Do have a valid sample AuthnRequest?.
        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error('There is no pending authentication request from SP');

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Return QR image response');

        // Get user.
        $nameId = $this->authenticationService->getNameId();
        try {
            $user = $this->userRepository->getUser($nameId);
        } catch (UserNotExistsException $exception) {
            $this->logger->error(sprintf(
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
            $result = $this->sendNotification($notificationType, $notificationAddress);
            if ($result) {
                return $this->generateNotificationResponse('success');
            }
            return $this->generateNotificationResponse('error');
        }

        return $this->generateNotificationResponse('no-device');
    }


    private function handleInvalidResponse(TiqrUserInterface $user, $response, LoggerInterface $logger)
    {
        $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
        $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
        if ($blockedTemporarily || $blockedPermanently) {
            $logger->info('User is blocked');

            return $this->showUserIsBlockedErrorPage($blockedPermanently);
        }

        return $this->render('default/:authentication.html.twig', [
            'otpError' => true,
            'attemptsLeft' => $response instanceof RateLimitedAuthenticationResponse ? $response->getAttemptsLeft() : null,
        ]);
    }

    private function showUserIsBlockedErrorPage($isBlockedPermanently)
    {
        $exception = new UserTemporarilyBlockedException();

        if ($isBlockedPermanently) {
            $exception = new UserPermanentlyBlockedException();
        }
        // Forward to the exception controller to prevent an error being logged.
        return $this->forward(
            'App:Exception:show',
            [
                'exception'=> $exception,
            ]
        );
    }

    /**
     * @param $notificationType
     * @param $notificationAddress
     * @return bool
     */
    private function sendNotification($notificationType, $notificationAddress)
    {
        $this->logger->notice(sprintf(
            'Sending client notification for type "%s" and address "%s"',
            $notificationType,
            $notificationAddress
        ));

        if ($notificationType == 'GCM') {
            $notificationType = 'FCM';

            $this->logger->notice(
                sprintf(
                    'GCM is not supported with address "%s" retrying with FCM',
                    $notificationAddress
                )
            );
        }

        $result = $this->tiqrService->sendNotification($notificationType, $notificationAddress);
        if (!$result) {
            $this->logger->warning(
                sprintf(
                    'Failed to send push notification for type "%s" and address "%s"',
                    $notificationType,
                    $notificationAddress
                ),
                [
                    'error_info' => $this->tiqrService->getNotificationError(),
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
