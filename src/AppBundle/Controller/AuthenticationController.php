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

    public function __construct(
        AuthenticationService $authenticationService,
        TiqrServiceInterface $tiqrService,
        TiqrUserRepositoryInterface $userRepository,
        AuthenticationRateLimitServiceInterface $authenticationRateLimitService,
        LoggerInterface $logger
    ) {
        $this->authenticationService = $authenticationService;
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

            return $this->render('AppBundle:default:authenticationError.html.twig', [
                'userNotFound' => true,
                'permanentlyBlocked' => false,
                'temporarilyBlocked' => false,
            ]);
        }

        // Verify if user is blocked.
        $logger->info('Verify if user is blocked');
        $blockedTemporarily = $this->authenticationRateLimitService->isBlockedTemporarily($user);
        $blockedPermanently = $this->authenticationRateLimitService->isBlockedPermanently($user);
        if ($blockedTemporarily || $blockedPermanently) {
            $logger->info('User is blocked');
            return $this->render('AppBundle:default:authenticationError.html.twig', [
                'permanentlyBlocked' => $blockedPermanently,
                'temporarilyBlocked' => $blockedTemporarily,
            ]);
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
            $this->tiqrService->startAuthentication($nameId);
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
            return new JsonResponse(false, Response::HTTP_BAD_REQUEST);
        }

        $isAuthenticated = $this->tiqrService->isAuthenticated();

        if ($isAuthenticated) {
            $this->logger->info('Send json response is authenticated');

            return new JsonResponse(true);
        }
        $this->logger->info('Send json response is not authenticated');

        return new JsonResponse(false);
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

            return $this->render('AppBundle:default:authenticationError.html.twig', [
                'permanentlyBlocked' => $blockedPermanently,
                'temporarilyBlocked' => $blockedTemporarily,
            ]);
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
}
