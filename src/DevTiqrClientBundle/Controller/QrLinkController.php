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

namespace DevTiqrClientBundle\Controller;

use AppBundle\Tiqr\TiqrServiceInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exposes the qr codes as links.
 */
final class QrLinkController extends Controller
{
    private $tiqrService;

    public function __construct(TiqrServiceInterface $tiqrService)
    {
        $this->tiqrService = $tiqrService;
    }

    /**
     * Returns the QR for registration without an active authNRequest.
     *
     * @Route("/registration/qr/dev", name="app_identity_registration_qr_dev")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function registrationQrAction(Request $request)
    {
        $key = $this->tiqrService->generateEnrollmentKey();
        $metadataURL = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));
        return $this->tiqrService->createRegistrationQRResponse($metadataURL);
    }

    /**
     * Returns the link for registration without an active authNRequest.
     *
     * @Route("/registration/qr/link", name="app_identity_registration_qr_link")
     * @param Request $request
     * @return Response
     * @throws \InvalidArgumentException
     */
    public function registrationLinkAction(Request $request)
    {
        $key = $this->tiqrService->generateEnrollmentKey();
        $metadataUrl = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));
        $metadataAppURL = 'tiqrenroll://'.$metadataUrl;

        // Simply return a html link, so they can click it and see the metadata result.
        return new Response(sprintf(
            '<a href="%s">%s</a>',
            htmlentities($metadataUrl),
            htmlentities($metadataAppURL)
        ));
    }

    /**
     * Returns the QR without an active authNRequest.
     *
     * @Route("/authentication/qr/{nameId}", name="app_identity_authentication_qr_dev")
     * @param string $nameId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function authenticationQrAction($nameId)
    {
        $this->tiqrService->startAuthentication($nameId);
        return $this->tiqrService->createAuthenticationQRResponse();
    }

    /**
     * Returns the link without an active authNRequest.
     *
     * @Route("/authentication/qr/{nameId}/link", name="app_identity_authentication_qr_link")
     * @param string $nameId
     * @return Response
     */
    public function authenticationQrLinkAction($nameId)
    {
        $this->tiqrService->startAuthentication($nameId);
        $challengeUrl = $this->tiqrService->authenticationUrl();

        // Simply return a html link, so they can click it and see the metadata result.
        return new Response(htmlentities($challengeUrl));
    }
}
