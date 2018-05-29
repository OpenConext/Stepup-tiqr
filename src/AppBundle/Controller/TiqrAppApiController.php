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

use AppBundle\Service\UserAgentMatcherInterface;
use AppBundle\WithContextLogger;
use AppBundle\Tiqr\AuthenticationRateLimitServiceInterface;
use AppBundle\Tiqr\Exception\UserNotExistsException;
use AppBundle\Tiqr\TiqrServiceInterface;
use AppBundle\Tiqr\TiqrUserRepositoryInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The api that connects to the Tiqr app.
 *
 * Keep in mind that the endpoint routers cannot change because of the 'old'
 * clients are depending on this.
 */
class TiqrAppApiController extends Controller
{
    private $tiqrService;
    private $userRepository;
    private $logger;
    private $authenticationRateLimitService;

    public function __construct(
        TiqrServiceInterface $tiqrService,
        TiqrUserRepositoryInterface $userRepository,
        AuthenticationRateLimitServiceInterface $authenticationRateLimitService,
        LoggerInterface $logger
    ) {
        $this->tiqrService = $tiqrService;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->authenticationRateLimitService = $authenticationRateLimitService;
    }

    /**
     * Metadata endpoint.
     *
     * The endpoint where the Tiqr app get's it's registration information from.
     *
     * @Route("/tiqr.php", name="app_identity_registration_metadata")
     * @Route("/tiqr/tiqr.php")
     * @Method({"GET"})
     *
     * @throws \InvalidArgumentException
     */
    public function metadataAction(Request $request)
    {
        $key = $request->get('key');
        if (empty($key)) {
            $this->logger->info('Without enrollment');

            return new Response('Missing enrollment key', Response::HTTP_BAD_REQUEST);
        }

        $logger = WithContextLogger::from($this->logger, [
            'sari' => $this->tiqrService->getSariForSessionIdentifier($key),
        ]);

        $logger->info('With enrollment key', ['key' => $key]);

        // Exchange the key submitted by the phone for a new, unique enrollment secret.
        $enrollmentSecret = $this->tiqrService->getEnrollmentSecret($key);

        $logger->info('Enrollment secret created', ['key' => $key]);

        // $enrollmentSecret is a one time password that the phone is going to use later to post
        // the shared secret of the user account on the phone.
        $enrollmentUrl = $request->getUriForPath(sprintf('/tiqr.php?otp=%s', urlencode($enrollmentSecret)));

        $logger->info('Enrollment url created for enrollment secret', ['key' => $key]);

        // Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session,
        // the data is destroyed after your first call.
        $metadata = $this->tiqrService->getEnrollmentMetadata(
            $key,
            $request->getUriForPath('/tiqr.php'),
            $enrollmentUrl
        );

        $logger->info('Return metadata response', ['key' => $key]);

        return new JsonResponse($metadata);
    }

    /**
     * This is the route where the app authenticates or registers.
     *
     * @Route("/tiqr.php", name="app_identity_registration_authentication")
     * @Route("/tiqr/tiqr.php")
     * @Method({"POST"})
     *
     * @param UserAgentMatcherInterface $userAgentMatcher
     * @param Request $request
     * @return Response
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function tiqr(UserAgentMatcherInterface $userAgentMatcher, Request $request)
    {
        $operation = $request->get('operation');
        $notificationType = $request->get('notificationType');
        $notificationAddress = $request->get('notificationAddress');
        if ($operation === 'register') {
            return $this->registerAction($userAgentMatcher, $request, $notificationType, $notificationAddress);
        }
        if ($operation === 'login') {
            return $this->loginAction($request, $notificationType, $notificationAddress);
        }

        return new Response('Operation not allowed', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param UserAgentMatcherInterface $userAgentMatcher
     * @param Request $request
     * @param $notificationType
     * @param $notificationAddress
     *
     * @return Response
     * @throws \InvalidArgumentException
     */
    private function registerAction(
        UserAgentMatcherInterface $userAgentMatcher,
        Request $request,
        $notificationType,
        $notificationAddress
    ) {
        $enrollmentSecret = $request->get('otp'); // enrollment secret relayed by tiqr app
        $secret = $request->get('secret');

        $logger = WithContextLogger::from($this->logger, [
            'sari' => $this->tiqrService->getSariForSessionIdentifier($secret),
        ]);

        if (!$userAgentMatcher->isOfficialTiqrMobileApp($request)) {
            $message = sprintf(
                'Received request from unsupported mobile app with user agent: "%s"',
                $request->headers->get('User-Agent')
            );

            $logger->warning($message);

            return new Response($message, Response::HTTP_NOT_ACCEPTABLE);
        }

        $logger->info('Start validating enrollment secret');

        // note: userId is never sent together with the secret! userId is retrieved from session
        $userId = $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);

        if ($userId === false) {
            $logger->info('Invalid enrollment secret');

            return new Response('Enrollment failed', Response::HTTP_FORBIDDEN);
        }

        $this->userRepository
            ->createUser($userId, $secret)
            ->updateNotification($notificationType, $notificationAddress);

        $logger->info('Finalizing enrollment');

        $this->tiqrService->finalizeEnrollment($enrollmentSecret);

        $logger->info('Enrollment finalized');

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param string $notificationType
     * @param string $notificationAddress
     *
     * @return Response
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    private function loginAction(Request $request, $notificationType, $notificationAddress)
    {
        $userId = $request->get('userId');
        $sessionKey = $request->get('sessionKey');

        $logger = WithContextLogger::from($this->logger, [
            'userId' => $userId,
            'sari' => $this->tiqrService->getSariForSessionIdentifier($sessionKey),
        ]);

        $logger->notice('Login attempt from app');

        try {
            $user = $this->userRepository->getUser($userId);
        } catch (UserNotExistsException $e) {
            $logger->error('User does not exists');
            return new Response('INVALID_USER', Response::HTTP_BAD_REQUEST);
        }

        $result = $this->authenticationRateLimitService->authenticate(
            $sessionKey,
            $user,
            $request->get('response')
        );

        if ($result->isValid()) {
            $logger->info('User authenticated ' . $result->getMessage());

            $user->updateNotification($notificationType, $notificationAddress);
            return new Response($result->getMessage(), Response::HTTP_OK);
        }

        $logger->info('User denied authenticated ' . $result->getMessage());

        return new Response($result->getMessage(), Response::HTTP_FORBIDDEN);
    }
}
