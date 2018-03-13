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
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Surfnet\GsspBundle\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends Controller
{
    private $registrationService;
    private $tiqrService;

    public function __construct(
        RegistrationService $registrationService,
        TiqrServiceInterface $tiqrService
    ) {
        $this->registrationService = $registrationService;
        $this->tiqrService = $tiqrService;
    }

    /**
     * Returns the registration page with qr code that is generated in 'qrRegistrationAction'.
     *
     * @Route("/registration", name="app_identity_registration")
     *
     * @throws \InvalidArgumentException
     */
    public function registrationAction(Request $request)
    {
        // Do have a valid sample AuthnRequest?.
        if (!$this->registrationService->registrationRequired()) {
            return new Response('No registration required', Response::HTTP_BAD_REQUEST);
        }

        if ($this->tiqrService->enrollmentFinalized()) {
            $this->registrationService->register($this->tiqrService->getUserId());
            return $this->registrationService->replyToServiceProvider();
        }

        return $this->render('AppBundle:default:registration.html.twig');
    }

    /**
     * For client-side polling retrieving the status.
     *
     * @Route("/registration/status", name="app_identity_registration_status")
     *
     * @throws \InvalidArgumentException
     */
    public function registrationStatusAction(Request $request)
    {
        // Do have a valid sample AuthnRequest?.
        if (!$this->registrationService->registrationRequired()) {
            return new Response('No registration required', Response::HTTP_BAD_REQUEST);
        }

        return new Response($this->tiqrService->getEnrollmentStatus());
    }

    /**
     * Returns the QR code img for registration.
     *
     * @see /registration/qr/link
     *
     * @Route("/registration/qr", name="app_identity_registration_qr")
     */
    public function qrRegistrationAction(Request $request)
    {
        if (!$this->registrationService->registrationRequired()) {
            return new Response('No registration required', Response::HTTP_BAD_REQUEST);
        }
        $key = $this->tiqrService->generateEnrollmentKey();
        $metadataURL = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));
        $this->tiqrService->exitWithRegistrationQR($metadataURL);
    }
}
