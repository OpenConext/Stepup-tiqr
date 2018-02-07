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
use Surfnet\GsspBundle\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RegistrationController extends Controller
{
    private $authenticationService;
    private $registrationService;
    private $tiqrService;
    private $session;

    public function __construct(
        AuthenticationService $authenticationService,
        RegistrationService $registrationService,
        TiqrFactory $factory,
        SessionInterface $session
    ) {
        $this->authenticationService = $authenticationService;
        $this->registrationService = $registrationService;
        $this->tiqrService = $factory->create();
        $this->session = $session;
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
     * @Route("/registration/qr", name="app_identity_registration_qr")
     */
    public function qrRegistrationAction(Request $request)
    {
        $key = $this->tiqrService->startEnrollmentSession('SURFconext');
        $metadataURL = htmlentities("https://tiqr.example.com/app_dev.php/tiqr.php?key=$key");
        // NOTE: this call will generate literal PNG data. This makes it harder to intercept the enrolment key
        // This is also the reason why enrolment cannot be performed an the phone (by clicking the image, as with authN)
        // as it would expose the enrolment key to the client in plaintext next to the "PNG-encoded" version.
        // $this->tiqrService->generateEnrollmentQR($metadataURL);
        $html = <<<HTML
<a href="$metadataURL">$metadataURL</a>
HTML;
        return new Response($html);
    }

}
