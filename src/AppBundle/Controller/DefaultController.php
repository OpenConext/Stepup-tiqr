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

use AppBundle\Tiqr\TiqrService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    private $authenticationService;
    private $registrationService;
    /**
     * @var TiqrService
     */
    private $tiqrService;

    public function __construct(
        AuthenticationService $authenticationService,
        RegistrationService $registrationService,
        TiqrService $tiqrSerice
    ) {
        $this->authenticationService = $authenticationService;
        $this->registrationService = $registrationService;
        $this->tiqrService = $tiqrSerice;
    }

    /**
     * Replace this example code with whatever you need/
     *
     * @Route("/", name="homepage")
     */
    public function indexAction()
    {
        return $this->render('default/index.html.twig');
    }

    /**
     * Replace this example code with whatever you need.
     *
     * See @see RegistrationService for a more clean example.
     *
     * @Route("/registration", name="app_identity_registration")
     */
    public function registrationAction(Request $request)
    {
        if ($request->get('action') === 'error') {
            $this->registrationService->reject($request->get('message'));
            return $this->registrationService->replyToServiceProvider();
        }

        if ($request->get('action') === 'register') {
            $this->registrationService->register($request->get('NameID'));
            return $this->registrationService->replyToServiceProvider();
        }

        $requiresRegistration = $this->registrationService->registrationRequired();
        $response = new Response(null, $requiresRegistration ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);

        return $this->render('AppBundle:default:registration.html.twig', [
            'requiresRegistration' => $requiresRegistration,
            'NameID' => uniqid('test-prefix-', 'test-entropy'),
        ], $response);
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

        return $this->render('AppBundle:default:authentication.html.twig', [
            'requiresAuthentication' => $requiresAuthentication,
            'NameID' => $nameId ?: 'unknown',
        ], $response);
    }
}
