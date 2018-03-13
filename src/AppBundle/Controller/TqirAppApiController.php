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

use AppBundle\Tiqr\Exception\UserNotExistsException;
use AppBundle\Tiqr\Response\RejectedAuthenticationResponse;
use AppBundle\Tiqr\TiqrConfigurationInterface;
use AppBundle\Tiqr\TiqrServiceInterface;
use AppBundle\Tiqr\TiqrUserRepositoryInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The api that connects to the Tiqr app.
 *
 * Keep in mind that the endpoint routers cannot change becuase 'old'
 * clients are depending on this.
 */
class TqirAppApiController extends Controller
{
    private $tiqrService;
    private $userRepository;
    private $configuration;

    public function __construct(
        TiqrServiceInterface $tiqrService,
        TiqrUserRepositoryInterface $userRepository,
        TiqrConfigurationInterface $configuration
    ) {
        $this->tiqrService = $tiqrService;
        $this->userRepository = $userRepository;
        $this->configuration = $configuration;
    }

    /**
     * Metadata endpoint.
     *
     * The endpoint where the Tiqr app get's it's registration information from.
     *
     * @Route("/tiqr.php", name="app_identity_registration_metadata")
     * @Method({"GET"})
     *
     * @throws \InvalidArgumentException
     */
    public function metadataAction(Request $request)
    {
        $key = $request->get('key');
        if (empty($key)) {
            return new Response('Missing enrollment key', Response::HTTP_BAD_REQUEST);
        }
        // Exchange the key submitted by the phone for a new, unique enrollment secret.
        $enrollmentSecret = $this->tiqrService->getEnrollmentSecret($key);

        // $enrollmentSecret is a one time password that the phone is going to use later to post
        // the shared secret of the user account on the phone.
        $enrollmentUrl = $request->getUriForPath(sprintf('/tiqr.php?otp=%s', urlencode($enrollmentSecret)));

        // Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session,
        // the data is destroyed after your first call.
        $metadata = $this->tiqrService->getEnrollmentMetadata(
            $key,
            $request->getUriForPath('/tiqr.php'),
            $enrollmentUrl
        );

        return new JsonResponse($metadata);
    }

    /**
     * This is the route where the app authenticates or registers.
     *
     * @Route("/tiqr.php", name="app_identity_registration_authentication")
     * @Method({"POST"})
     *
     * @param Request $request
     * @return Response
     * @throws \InvalidArgumentException
     * @throws \AppBundle\Tiqr\Exception\ConfigurationException
     */
    public function tiqr(Request $request)
    {
        $operation = $request->get('operation');
        $notificationType = $request->get('notificationType');
        $notificationAddress = $request->get('notificationAddress');
        if ($operation === 'register') {
            return $this->registerAction($request, $notificationType, $notificationAddress);
        }
        if ($operation === 'login') {
            return $this->loginAction($request, $notificationType, $notificationAddress);
        }

        return new Response('Operation not allowed', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @param $notificationType
     * @param $notificationAddress
     *
     * @return Response
     * @throws \InvalidArgumentException
     */
    private function registerAction(Request $request, $notificationType, $notificationAddress)
    {
        $enrollmentSecret = $request->get('otp'); // enrollment secret relayed by tiqr app
        $secret = $request->get('secret');

        // note: userId is never sent together with the secret! userId is retrieved from session
        $userId = $this->tiqrService->validateEnrollmentSecret($enrollmentSecret);

        if ($userId === false) {
            return new Response('Enrollment failed', Response::HTTP_FORBIDDEN);
        }

        $this->userRepository
            ->createUser($userId, $secret)
            ->updateNotification($notificationType, $notificationAddress);

        $this->tiqrService->finalizeEnrollment($enrollmentSecret);

        return new Response('OK', Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @param string $notificationType
     * @param string $notificationAddress
     *
     * @return Response
     * @throws \AppBundle\Tiqr\Exception\ConfigurationException
     */
    private function loginAction(Request $request, $notificationType, $notificationAddress)
    {
        $now = new \DateTimeImmutable();
        $sessionKey = $request->get('sessionKey');
        $userId = $request->get('userId');
        $response = $request->get('response');

        try {
            $user = $this->userRepository->getUser($userId);
        } catch (UserNotExistsException $e) {
            return new Response('INVALID_USER', Response::HTTP_BAD_REQUEST);
        }

        // Check if user is blocked. (TODO: maybe do this in AuthenticationController?)
        if ($this->configuration->temporaryBlockEnabled() &&
            $user->isBlockTemporary($now, $this->configuration->getTemporaryBlockDuration())) {
            return new Response('ACCOUNT_BLOCKED', Response::HTTP_FORBIDDEN);
        }

        if (!$this->configuration->temporaryBlockEnabled() && $user->isBlocked()) {
            return new Response('ACCOUNT_BLOCKED', Response::HTTP_FORBIDDEN);
        }

        // Verify the app's response.
        $result = $this->tiqrService->authenticate($user, $response, $sessionKey);
        if ($result->isValid()) {
            $user->resetLoginAttempts();
            $user->updateNotification($notificationType, $notificationAddress);

            return new Response($result->getMessage(), Response::HTTP_OK);
        }

        // The user did something wrong.
        if ($result instanceof RejectedAuthenticationResponse) {
            // If there is no limit how many times the user can try to login.
            if (!$this->configuration->hasMaxLoginAttempts()) {
                return new Response($result->getMessage(), Response::HTTP_FORBIDDEN);
            }

            // Does the user still have login attempts left?.
            if ($user->getLoginAttempts() < ($this->configuration->getMaxAttempts() - 1)) {
                $user->addLoginAttempt();
                $attemptsLeft = $this->configuration->getMaxAttempts() - $user->getLoginAttempts();
                return new Response($result->getMessage().':'.$attemptsLeft, Response::HTTP_FORBIDDEN);
            }

            // If temporary block functionality is not enabled, we block the user forever.
            if (!$this->configuration->temporaryBlockEnabled()) {
                $user->block();
                return new Response('ACCOUNT_BLOCKED', Response::HTTP_FORBIDDEN);
            }

            // Just block the user temporary if we don't got a limit.
            if (!$this->configuration->hasMaxTemporaryLoginAttempts()) {
                $user->blockTemporary($now);
                return new Response('ACCOUNT_BLOCKED', Response::HTTP_FORBIDDEN);
            }

            // Block the user for always, if he has reached the maximum login attempts.
            if ($user->getTemporaryLoginAttempts() < ($this->configuration->getMaxTemporaryLoginAttempts() - 1)) {
                $user->block();
                return new Response('ACCOUNT_BLOCKED', Response::HTTP_FORBIDDEN);
            }

            $user->blockTemporary($now);
            $attemptsLeft = $this->configuration->getMaxTemporaryLoginAttempts() - $user->getTemporaryLoginAttempts();

            return new Response($result->getMessage().':'.$attemptsLeft, Response::HTTP_FORBIDDEN);
        }

        // An unexpected error occurred.
        return new Response($result->getMessage(), Response::HTTP_FORBIDDEN);
    }
}
