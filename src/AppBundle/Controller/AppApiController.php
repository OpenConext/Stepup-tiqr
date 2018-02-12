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

use AppBundle\Tiqr\TiqrServiceInterface;
use AppBundle\Tiqr\TiqrUserRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AppApiController extends Controller
{
    private $tiqrService;
    private $userRepository;

    public function __construct(
        TiqrServiceInterface $tiqrService,
        TiqrUserRepository $userRepository
    ) {
        $this->tiqrService = $tiqrService;
        $this->userRepository = $userRepository;
    }

    /**
     * Metadata endpoint.
     *
     * The endpoint where the app get's it's information from. TODO: improve description.
     *
     * @Route("/tiqr.php", name="app_identity_registration_metadata")
     * @Method({"GET"})
     *
     * @throws \InvalidArgumentException
     */
    public function metadataAction(Request $request)
    {
        $key = $this->stripSpecialChars($request->get('key'));
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
     * @Route("/tiqr.php", name="app_identity_registration_authentication")
     * @Method({"POST"})
     *
     * @param Request $request
     * @return Response
     * @throws \InvalidArgumentException
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
     * @param $notificationType
     * @param $notificationAddress
     *
     * @return mixed
     */
    private function loginAction(Request $request, $notificationType, $notificationAddress)
    {
//        $sessionKey = $request->get('sessionKey');
//        $userId = $request->get('userId');
//        $response = $request->get('response');
//        $result = $this->login($sessionKey, $userId, $response, $notificationType, $notificationAddress);
//        return $result;
    }

    private function stripSpecialChars($text)
    {
        return preg_replace('/[^a-zA-Z0-9]+/', '', $text);
    }
}
