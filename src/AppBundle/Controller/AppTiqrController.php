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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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
        $enrollmentUrl     = "https://tiqr.example.com/app_dev.php/tiqr.php?otp=$enrollmentSecret"; // todo
        $authenticationUrl = "https://tiqr.example.com/app_dev.php/tiqr.php";
        //Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session, the data is destroyed after your first call.
        return $this->tiqrService->tiqrService->getEnrollmentMetadata($key, $authenticationUrl, $enrollmentUrl);
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
                        $enrollmentSecret = preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['otp']); // enrollmentsecret relayed by tiqr app

                        $secret = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['secret']);
                        $result = register($enrollmentSecret, $secret, $notificationType, $notificationAddress);
                        echo $result;
                        break;
                    case "login":
                        $sessionKey = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['sessionKey']);
                        $userId = preg_replace("/[^a-zA-Z0-9_-]+/", "", $_POST['userId']);
                        $response = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['response']);

                        $result = login($sessionKey, $userId, $response, $notificationType, $notificationAddress);

                        echo $result;
                        break;
                    default:

                        break;
                }
                break;
        }

        //tiqr.php
    }

}
