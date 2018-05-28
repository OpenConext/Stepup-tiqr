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

use AppBundle\Exception\UserNotFoundException;
use AppBundle\Exception\UserPermanentlyBlockedException;
use AppBundle\Exception\UserTemporarilyBlockedException;
use AppBundle\WithContextLogger;
use AppBundle\Tiqr\AuthenticationRateLimitServiceInterface;
use AppBundle\Tiqr\Exception\UserNotExistsException;
use AppBundle\Tiqr\Response\RateLimitedAuthenticationResponse;
use AppBundle\Tiqr\TiqrServiceInterface;
use AppBundle\Tiqr\TiqrUserInterface;
use AppBundle\Tiqr\TiqrUserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AuthenticationController extends Controller
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
     * @Route("/authentication", name="app_identity_authentication")
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

            return new Response('No authentication required', Response::HTTP_BAD_REQUEST);
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

        return $this->render('AppBundle:default:authentication.html.twig', []);
    }

    /**
     * @Route("/authentication/status", name="app_identity_authentication_status")
     * @throws \InvalidArgumentException
     */
    public function authenticationStatusAction()
    {
        $this->logger->info('Request for authentication status');

        if (!$this->authenticationService->authenticationRequired()) {
            $this->logger->error('There is no pending authentication request from SP');
            return $this->refreshAuthenticationPage();
        }

        if ($this->authenticationChallengeIsExpired()) {
            return $this->refreshAuthenticationPage();
        }

        $isAuthenticated = $this->tiqrService->isAuthenticated();

        if ($isAuthenticated) {
            $this->logger->info('Send json response is authenticated');

            return $this->refreshAuthenticationPage();
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
     * Generate a response for authentication.html: refresh the page.
     *
     * @return JsonResponse
     */
    private function refreshAuthenticationPage()
    {
        return $this->generateAuthenticationStatusResponse('needs-refresh');
    }

    /**
     * Generate a response for authentication.html.
     *
     * The javascript in the authentication page expects one of three statusses:
     *
     *  - pending: waiting for user action, schedule next poll
     *  - needs-refresh: refresh the page (the /authentication page will handle the succes or error)
     *
     * @param string $status
     * @return JsonResponse
     */
    private function generateAuthenticationStatusResponse($status)
    {
        return new JsonResponse($status);
    }

    /**
     *
     * @Route("/authentication/qr", name="app_identity_authentication_qr")
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

        $response = $this->tiqrService->createAuthenticationQRResponse();

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
            $this->logger->info(sprintf(
                'Sending client notification for type "%s" and address "%s"',
                $notificationType,
                $notificationAddress
            ));
            $result = $this->tiqrService->sendNotification($notificationType, $notificationAddress);
            $this->logNotificationResponse($result, $notificationType, $notificationAddress);
        }

        return $response;
    }


    private function handleInvalidResponse(TiqrUserInterface $user, $response, LoggerInterface $logger)
    {
        $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
        $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
        if ($blockedTemporarily || $blockedPermanently) {
            $logger->info('User is blocked');

            return $this->showUserIsBlockedErrorPage($blockedPermanently);
        }

        return $this->render('AppBundle:default:authentication.html.twig', [
            'otpError' => true,
            'attemptsLeft' => $response instanceof RateLimitedAuthenticationResponse ? $response->getAttemptsLeft() : null,
        ]);
    }

    /**
     * @param boolean $result
     * @param string $notificationType
     * @param string $notificationAddress
     */
    private function logNotificationResponse($result, $notificationType, $notificationAddress)
    {
        if ($result) {
            $this->logger->info(sprintf(
                'Push notification successfully send for type "%s" and address "%s"',
                $notificationType,
                $notificationAddress
            ));

            return;
        }
        $this->logger->warning(sprintf(
            'Failed to send push notification for type "%s" and address "%s"',
            $notificationType,
            $notificationAddress
        ));
    }

    private function showUserIsBlockedErrorPage($isBlockedPermanently)
    {
        $exception = new UserTemporarilyBlockedException();

        if ($isBlockedPermanently) {
            $exception = new UserPermanentlyBlockedException();
        }
        // Forward to the exception controller to prevent an error being logged.
        return $this->forward(
            'AppBundle:Exception:show',
            [
                'exception'=> $exception,
            ]
        );
    }
}
