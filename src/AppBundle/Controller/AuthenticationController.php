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
use Surfnet\GsspBundle\Service\AuthenticationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthenticationController extends Controller
{
    private $tiqrService;
    private $authenticationService;
    private $session;

    public function __construct(
        AuthenticationService $authenticationService,
        TiqrFactory $factory,
        SessionInterface $session
    ) {
        $this->authenticationService = $authenticationService;
        $this->tiqrService = $factory->create();
        $this->session = $session;
    }

    /**
     * Replace this example code with whatever you need.
     *
     * See @see AuthenticationService for a more clean example.
     *
     * @Route("/authentication", name="app_identity_authentication")
     */
    public function authenticationAction(Request $request)
    {
        $nameId = $this->authenticationService->getNameId();

        if ($request->get('action') === 'error') {
            $this->authenticationService->reject($request->get('message'));

            return $this->authenticationService->replyToServiceProvider();
        }

        if ($request->get('action') === 'authenticate') {
            // The application should very if the user matches the nameId.
            $this->authenticationService->authenticate();

            return $this->authenticationService->replyToServiceProvider();
        }

        $requiresAuthentication = $this->authenticationService->authenticationRequired();
        $response = new Response(null, $requiresAuthentication ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);

        $tiqr = $this->tiqrService->tiqrService;

        $sessionKey = $tiqr->startAuthenticationSession($nameId); // prepares the tiqr library for authentication
        $this->session->set('sessionKey', $sessionKey);
        $authUrl = $tiqr->generateAuthURL($sessionKey);

        return $this->render('AppBundle:default:authentication.html.twig', [
            'requiresAuthentication' => $requiresAuthentication,
            'NameID' => $nameId ?: 'unknown',
            'authUrl' => $authUrl
        ], $response);
    }

    /**
     *
     * @Route("/qr", name="app_identity_authentication_qr")
     */
    public function qr()
    {

        $id = $this->authenticationService->getNameId(); // do we need to log in some specific user?
        if ($id === '') {
            $id = null;
        }

        $sessionKey = $this->session->get('sessionKey');

        $userStorage = $this->tiqrService->storage;
//        if ($id) {
//            $notificationType = $userStorage->getNotificationType($id);
//            $notificationAddress = $userStorage->getNotificationAddress($id);
//            // $translatedAddress = $this->tiqr->translateNotificationAddress($notificationType, $notificationAddress);
//            if ($translatedAddress) {
//                $result = $tiqr->sendAuthNotification($sessionKey, $notificationType, $translatedAddress);
//            }
//        }
        $this->tiqrService->tiqrService->generateAuthQR($sessionKey);

        exit;
    }
}
