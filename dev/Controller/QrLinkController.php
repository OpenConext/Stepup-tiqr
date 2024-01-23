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

declare(strict_types = 1);

namespace Surfnet\Tiqr\Dev\Controller;

use InvalidArgumentException;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

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
     */
    #[Route(path: '/registration/qr/dev', name: 'app_identity_registration_qr_dev', methods: ['GET'])]
    public function registrationQr(Request $request): StreamedResponse
    {
        $key = $this->tiqrService->generateEnrollmentKey('dev');
        $metadataURL = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));
        return $this->tiqrService->createRegistrationQRResponse($metadataURL);
    }

    /**
     * Returns the link for registration without an active authNRequest.
     * @throws InvalidArgumentException
     */
    #[Route(path: '/registration/qr/link', name: 'app_identity_registration_qr_link', methods: ['GET'])]
    public function registrationLink(Request $request): Response
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
     */
    #[Route(path: '/authentication/qr/{nameId}', name: 'app_identity_authentication_qr_dev', methods: ['GET'])]
    public function authenticationQr(string $nameId): StreamedResponse
    {
        $this->tiqrService->startAuthentication($nameId, 'dev');
        return $this->tiqrService->createAuthenticationQRResponse();
    }

    /**
     * Returns the link without an active authNRequest.
     */
    #[Route(path: '/authentication/qr/{nameId}/link', name: 'app_identity_authentication_qr_link', methods: ['GET'])]
    public function authenticationQrLink(string $nameId): Response
    {
        $this->tiqrService->startAuthentication($nameId, 'dev');
        $challengeUrl = $this->tiqrService->authenticationUrl();

        // Simply return a html link, so they can click it and see the metadata result.
        return new Response(htmlentities($challengeUrl));
    }
}
