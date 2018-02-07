<?php
/**
 * Copyright 2017 SURFnet B.V.
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

use AppBundle\Tiqr\TiqrFactory;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tiqr_Service;

class AppTiqrController extends Controller
{
    private $tiqrService;

    public function __construct(
        TiqrFactory $factory,
        SessionInterface $session
    ) {
        $this->tiqrService = $factory->create();
    }

    function metadata($key)
    {
        // exchange the key submitted by the phone for a new, unique enrollment secret
        $enrollmentSecret = $this->tiqrService->tiqrService->getEnrollmentSecret($key);
        // $enrollmentSecret is a one time password that the phone is going to use later to post the shared secret of the user account on the phone.
        $enrollmentUrl = "https://tiqr.example.com/app_dev.php/tiqr.php?otp=$enrollmentSecret"; // todo
        $authenticationUrl = "https://tiqr.example.com/app_dev.php/tiqr.php";

        //Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session, the data is destroyed after your first call.
        return $this->tiqrService->tiqrService->getEnrollmentMetadata($key, $authenticationUrl, $enrollmentUrl);
    }

    function login($sessionKey, $userId, $response, $notificationType, $notificationAddress)
    {
        $options = $this->tiqrService->options;
        $userStorage = $this->tiqrService->storage;

        $tiqr = $this->tiqrService->tiqrService;

        $tempBlockDuration = array_key_exists('temporaryBlockDuration',
            $options) ? $options['temporaryBlockDuration'] : 0;
        $maxTempBlocks = array_key_exists('maxTemporaryBlocks', $options) ? $options['maxTemporaryBlocks'] : 0;
        $maxAttempts = array_key_exists('maxAttempts',
            $options) ? $options['maxAttempts'] : 0; //default to 0, don't destroy secrets

        if (!$userStorage->userExists($userId)) {
            return new Response('INVALID_USER');
        } elseif ($userStorage->isBlocked($userId, $tempBlockDuration)) {
            return new Response('ACCOUNT_BLOCKED');
        }
        $userSecret = $userStorage->getSecret($userId);
        $result = $tiqr->authenticate($userId, $userSecret, $sessionKey, $response);
        switch ($result) {
            case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
                // Reset the login attempts counter
                $userStorage->setLoginAttempts($userId, 0);
                // update notification information if given, on successful login
                if (isset($notificationType)) {
                    $userStorage->setNotificationType($userId, $notificationType);
                    if (isset($notificationAddress)) {
                        $userStorage->setNotificationAddress($userId, $notificationAddress);
                    }
                }

                return new Response("OK");
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE:
                return new Response('INVALID_CHALLENGE');
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_REQUEST:
                return new Response('INVALID_REQUEST');
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE:
                $attempts = $userStorage->getLoginAttempts($userId);
                if (0 == $maxAttempts) { // unlimited
                    return new Response('INVALID_RESPONSE');
                } elseif ($attempts < ($maxAttempts - 1)) {
                    $userStorage->setLoginAttempts($userId, $attempts + 1);
                } else {
                    // Block user and destroy secret
                    $userStorage->setBlocked($userId, true);
                    $userStorage->setLoginAttempts($userId, 0);

                    if ($tempBlockDuration > 0) {
                        $tempAttempts = $userStorage->getTemporaryBlockAttempts($userId);
                        if (0 == $maxTempBlocks) {
                            // always a temporary block
                            $userStorage->setTemporaryBlockTimestamp($userId, date("Y-m-d H:i:s"));
                        } else {
                            if ($tempAttempts < ($maxTempBlocks - 1)) {
                                // temporary block which could turn into a permanent block
                                $userStorage->setTemporaryBlockAttempts($userId, $tempAttempts + 1);
                                $userStorage->setTemporaryBlockTimestamp($userId, date("Y-m-d H:i:s"));
                            } else {
                                // remove timestamp to make this a permanent block
                                $userStorage->setTemporaryBlockTimestamp($userId, false);
//                        $userStorage->setSecret($userId, NULL); // TODO more testing
                            }
                        }
                    }
                }
                $attemptsLeft = ($maxAttempts - 1) - $attempts;

                return new Response('INVALID_RESPONSE:'.$attemptsLeft);
                break;
            case Tiqr_Service::AUTH_RESULT_INVALID_USERID:
                return new Response('INVALID_USERID'); // INVALID_USER ?
                break;
            default:
                return new Response('ERROR');
                break;
        }
    }


    /**
     * @Route("/tiqr.php", name="app_identity_registration_qr_app")
     */
    public function tiqr(Request $request)
    {
        switch ($_SERVER['REQUEST_METHOD']) {
            case "GET":
                // metadata request
                // retrieve the temporary reference to the user identity
                $key = preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['key']);
                $metadata = $this->metadata($key);

                return new JsonResponse($metadata);

            case "POST":
                $version = array_key_exists('HTTP_X_TIQR_PROTOCOL_VERSION',
                    $_SERVER) ? $_SERVER['HTTP_X_TIQR_PROTOCOL_VERSION'] : "1";

                $operation = preg_replace("/[^a-z]+/", "", $_POST['operation']);

                $notificationType = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['notificationType']);
                $notificationAddress = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['notificationAddress']);

                switch ($operation) {
                    case "register":
                        $enrollmentSecret = preg_replace("/[^a-zA-Z0-9]+/", "",
                            $_GET['otp']); // enrollmentsecret relayed by tiqr app

                        $secret = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['secret']);
                        $result = $this->register($enrollmentSecret, $secret, $notificationType, $notificationAddress);

                        return new Response($result);

                    case "login":
                        $sessionKey = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['sessionKey']);
                        $userId = preg_replace("/[^a-zA-Z0-9_-]+/", "", $_POST['userId']);
                        $response = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['response']);

                        $result = $this->login($sessionKey, $userId, $response, $notificationType,
                            $notificationAddress);

                        return $result;
                    default:

                        break;
                }
                break;
        }

        //tiqr.php
    }

    private function register($enrollmentSecret, $secret, $notificationType, $notificationAddress)
    {

        $userStorage = $this->tiqrService->storage;

        // note: userid is never sent together with the secret! userid is retrieved from session
        $userid = $this->tiqrService->tiqrService->validateEnrollmentSecret($enrollmentSecret); // or false if invalid

        $userStorage->createUser($userid, "anonymous");
        $userStorage->setSecret($userid, $secret);
        $userStorage->setNotificationType($userid, $notificationType);
        $userStorage->setNotificationAddress($userid, $notificationAddress);
        $this->tiqrService->tiqrService->finalizeEnrollment($enrollmentSecret);

        return "OK";

    }

}
