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
use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Service\TrustedDevice\TrustedDeviceService;
use Surfnet\Tiqr\Service\UserAgentMatcherInterface;
use Surfnet\Tiqr\Tiqr\AuthenticationRateLimitServiceInterface;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * The api that connects to the Tiqr app.
 *
 * Keep in mind that the endpoint routers cannot change because of the 'old'
 * clients are depending on this.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TiqrAppApiController extends AbstractController
{
    public function __construct(
        private readonly TiqrServiceInterface $tiqrService,
        private readonly TiqrUserRepositoryInterface $userRepository,
        private readonly AuthenticationRateLimitServiceInterface $authenticationRateLimitService,
        private readonly LoggerInterface $logger,
        private readonly TrustedDeviceService $cookieService,
    ) {
    }

    /**
     * Metadata endpoint.
     *
     * The endpoint where the Tiqr app gets it's registration information from during enrollment
     */
    #[Route(path: '/tiqr.php', name: 'app_identity_registration_metadata', methods: ['GET'])]
    #[Route(path: '/tiqr/tiqr.php', methods: ['GET'])]
    public function metadata(Request $request): Response
    {
        $enrollmentKey = $request->get('key');
        if (empty($enrollmentKey)) {
            $this->logger->error('Missing "key" parameter in GET request to metadata endpoint');

            return new Response('Missing enrollment key in GET request to the metadata endpoint', Response::HTTP_BAD_REQUEST);
        }

        $sari = $this->tiqrService->getSariForSessionIdentifier($enrollmentKey);
        $logger = WithContextLogger::from($this->logger, [
            'sari' => $sari,
        ]);

        $logger->notice('Got GET request to metadata endpoint with enrollment key', ['key' => $enrollmentKey]);

        try {
            // Exchange the key submitted by the phone for a new, unique enrollment secret.
            $enrollmentSecret = $this->tiqrService->getEnrollmentSecret($enrollmentKey, $sari);

            $logger->debug('Enrollment secret created', ['key' => $enrollmentKey]);

            // $enrollmentSecret is a one time password that the phone is going to use later to post
            // the shared secret of the user account on the phone.
            $enrollmentUrl = $request->getUriForPath(sprintf('/tiqr.php?otp=%s', urlencode($enrollmentSecret)));

            $logger->debug('Enrollment url created for enrollment secret', ['key' => $enrollmentKey]);

            // Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session,
            // the data is destroyed after your first call.
            $metadata = $this->tiqrService->getEnrollmentMetadata(
                $enrollmentKey,
                $request->getUriForPath('/tiqr.php'),
                $enrollmentUrl
            );

            $logger->notice('Returned metadata response', ['key' => $enrollmentKey]);

            return new JsonResponse($metadata);
        } catch (Exception $e) {
            $this->logger->error('Error handling metadata GET request, returning HTTP 500', ['exception' => $e]);
            return new Response('Error handling metadata GET request', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * This is the route where the app authenticates or registers.
     */
    #[Route(path: '/tiqr.php', name: 'app_identity_registration_authentication', methods: ['POST'])]
    #[Route(path: '/tiqr/tiqr.php', methods: ['POST'])]
    public function tiqr(UserAgentMatcherInterface $userAgentMatcher, Request $request): Response
    {
        $operation = $request->get('operation');
        if (empty($operation)) {
            $this->logger->error('Missing "operation" parameter in POST request to the authentication/enrollment endpoint');
            return new Response('Missing "operation" parameter in POST', Response::HTTP_BAD_REQUEST);
        }

        $notificationType = $request->get('notificationType', '');
        if (!is_string($notificationType)) {
            $notificationType = '';
        }
        $notificationAddress = $request->get('notificationAddress', '');
        if (!is_string($notificationAddress)) {
            $notificationAddress = '';
        }

        if ($operation === 'register') {
            $this->logger->notice(
                'Got POST with registration response',
                ['notificationType' => $notificationType, 'notificationAddress' => $notificationAddress]
            );
            return $this->registerAction($userAgentMatcher, $request, $notificationType, $notificationAddress);
        }
        if ($operation === 'login') {
            $this->logger->notice(
                'Got POST with login response',
                ['notificationType' => $notificationType, 'notificationAddress' => $notificationAddress]
            );
            return $this->loginAction($request, $notificationType, $notificationAddress);
        }

        $this->logger->error(sprintf('Unsupported operation: "%s"', $operation));
        return new Response('Operation not allowed', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @throws \InvalidArgumentException
     */
    private function registerAction(
        UserAgentMatcherInterface $userAgentMatcher,
        Request $request,
        string $notificationType,
        string $notificationAddress
    ): Response {
        $enrollmentSecret = $request->get('otp'); // enrollment secret relayed by tiqr app
        if (empty($enrollmentSecret)) {
            $this->logger->error('Missing "otp" parameter');
            return new Response('Missing "otp" parameter', Response::HTTP_BAD_REQUEST);
        }
        $secret = $request->get('secret');
        if (empty($secret)) {
            $this->logger->error('Missing "secret" parameter');
            return new Response('Missing "secret" parameter', Response::HTTP_BAD_REQUEST);
        }


        $logger = WithContextLogger::from($this->logger, [
            'sari' => $this->tiqrService->getSariForSessionIdentifier($enrollmentSecret),
        ]);

        $version = $request->get('version');
        $userAgent = $request->headers->get('User-Agent');
        $logger->notice(
            sprintf(
                'Received register action from client with User-Agent "%s" and version "%s"',
                $userAgent,
                $version
            )
        );

        if (!$userAgentMatcher->isOfficialTiqrMobileApp($request)) {
            $message = sprintf(
                'Received request from unsupported mobile app with user agent: "%s"',
                $userAgent
            );

            $logger->warning($message);

            return new Response($message, Response::HTTP_NOT_ACCEPTABLE);
        }

        $logger->info('Start validating enrollment secret');

        try {
            // note: userId is never sent together with the secret! userId is retrieved from session
            $userId = $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);

            $logger = WithContextLogger::from($this->logger, [
                'userId' => $userId,
                'sari' => $this->tiqrService->getSariForSessionIdentifier($enrollmentSecret),
            ]);
        } catch (Exception $e) {
            $logger->error(sprintf('Validation of the enrollment secret "%s" failed', $enrollmentSecret), ['exception' => $e]);
            return new Response('Enrollment failed', Response::HTTP_FORBIDDEN);
        }

        // The Tiqr client generates the shared secret that is used in the subsequent authentications
        // Check whether the secret that the client sent looks sensible
        // Note: historically both uppercase and lowercase hex strings are used

        // 1. Assert that the secret is a valid hex string
        $decodedSecret = hex2bin((string) $secret);
        if (false === $decodedSecret) {
            $logger->error('Invalid secret, secret must be a hex string');
            return new Response('Invalid secret', Response::HTTP_FORBIDDEN);
        }
         // 2. Assert that the secret has a minimum length of 32 bytes.
        if (strlen($decodedSecret) < 32) {
            $logger->error('Invalid secret, secret must be at least 32 bytes (64 hex digits) long');
            return new Response('Invalid secret', Response::HTTP_FORBIDDEN);
        }

        $logger->info("Setting user secret and notification type and address");

        try {
            $this->userRepository
                ->createUser($userId, $secret)
                ->updateNotification($notificationType, $notificationAddress);
        } catch (Exception $e) {
            $logger->error('Error setting user secret and/or notification address and type', ['exception' => $e]);
            return new Response('Error creating user', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $logger->info('Finalizing enrollment');
        try {
            $this->tiqrService->finalizeEnrollment($enrollmentSecret);
            $logger->notice('Enrollment finalized');
        } catch (Exception $e) {
            $logger->warning('Error finalizing enrollment', ['exception' => $e]);
        }

        return new Response('OK', Response::HTTP_OK);
    }

    /** Handle login operation from the app, returns response for the app
     *
     * @return Response
     *
     * Does not throw
     */
    private function loginAction(Request $request, string $notificationType, string $notificationAddress): Response
    {
        $userId = $request->get('userId');
        if (empty($userId)) {
            $this->logger->error('Missing "userId" parameter');
            return new Response('Missing "userId" parameter', Response::HTTP_BAD_REQUEST);
        }
        $sessionKey = $request->get('sessionKey');
        if (empty($sessionKey)) {
            $this->logger->error('Missing "sessionKey" parameter');
            return new Response('Missing "sessionKey" parameter', Response::HTTP_BAD_REQUEST);
        }
        $response = $request->get('response');
        if (empty($response)) {
            $this->logger->error('Missing "response" parameter');
            return new Response('Missing "response" parameter', Response::HTTP_BAD_REQUEST);
        }

        $logger = WithContextLogger::from($this->logger, [
            'userId' => $userId,
            'sessionKey' =>  $sessionKey,
            'sari' => $this->tiqrService->getSariForSessionIdentifier($sessionKey),
        ]);

        $version = $request->get('version');
        $userAgent = $request->headers->get('User-Agent');
        $logger->notice(
            sprintf(
                'Received login action from client with User-Agent "%s" and version "%s"',
                $userAgent,
                $version
            )
        );

        try {
            $user = $this->userRepository->getUser($userId);
        } catch (UserNotExistsException $e) {
            $logger->error('User not found', ['exception' => $e]);
            return new Response('INVALID_USER', Response::HTTP_FORBIDDEN);
        }

        try {
            $result = $this->authenticationRateLimitService->authenticate(
                $sessionKey,
                $user,
                $response
            );

            if ($result->isValid()) {
                $logger->notice('User authenticated ' . $result->getMessage());

                $responseObject = new Response($result->getMessage(), Response::HTTP_OK);
                try {
                    $user->updateNotification($notificationType, $notificationAddress);
                } catch (Exception $e) {
                    $this->logger->warning('Error updating notification type and address', ['exception' => $e]);
                    // Continue
                }

                try {
                    $this->registerTrustedDevice($notificationAddress, $user, $responseObject);
                } catch (Throwable $e) {
                    $this->logger->warning('Could not create trusted device cookie.', ['exception' => $e]);
                }

                return $responseObject;
            }

            $logger->notice('User authentication denied: ' . $result->getMessage());
            return new Response($result->getMessage(), Response::HTTP_FORBIDDEN);
        } catch (Exception $e) {
            $this->logger->error('Authentication failed', ['exception' => $e]);
        }

        return new Response('AUTHENTICATION_FAILED', Response::HTTP_FORBIDDEN);
    }

    private function registerTrustedDevice(
        string $notificationAddress,
        TiqrUserInterface $user,
        Response $responseObject
    ): void {
        if (trim($notificationAddress) !== '') {
            $this->cookieService->registerTrustedDevice(
                $responseObject,
                $user->getId(),
                $notificationAddress
            );
        }
    }
}
