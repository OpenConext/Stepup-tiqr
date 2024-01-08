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

namespace Dev\Controller;

use App\Tiqr\TiqrServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exposes the qr codes as links.
 */
final class QrLinkController extends AbstractController
{
    public function __construct(private readonly TiqrServiceInterface $tiqrService)
    {
    }

    /**
     * Returns the QR for registration without an active authNRequest.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    #[Route(path: '/registration/qr/dev', name: 'app_identity_registration_qr_dev', methods: ['GET'])]
    public function registrationQr(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $key = $this->tiqrService->generateEnrollmentKey('dev');
        $metadataURL = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));
        return $this->tiqrService->createRegistrationQRResponse($metadataURL);
    }

    /**
     * Returns the link for registration without an active authNRequest.
     *
     * @return Response
     * @throws \InvalidArgumentException
     */
    #[Route(path: '/registration/qr/link', name: 'app_identity_registration_qr_link', methods: ['GET'])]
    public function registrationLink(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $key = $this->tiqrService->generateEnrollmentKey('dev');
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
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    #[Route(path: '/authentication/qr/{nameId}', name: 'app_identity_authentication_qr_dev', methods: ['GET'])]
    public function authenticationQr(string $nameId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->tiqrService->startAuthentication($nameId, 'dev');
        return $this->tiqrService->createAuthenticationQRResponse();
    }

    /**
     * Returns the link without an active authNRequest.
     *
     * @return Response
     */
    #[Route(path: '/authentication/qr/{nameId}/link', name: 'app_identity_authentication_qr_link', methods: ['GET'])]
    public function authenticationQrLink(string $nameId): \Symfony\Component\HttpFoundation\Response
    {
        $this->tiqrService->startAuthentication($nameId, 'dev');
        $challengeUrl = $this->tiqrService->authenticationUrl();

        // Simply return a html link, so they can click it and see the metadata result.
        return new Response(htmlentities($challengeUrl));
    }
}
