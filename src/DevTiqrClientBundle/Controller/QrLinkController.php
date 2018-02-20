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
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @Route("/registration/qr/link", name="app_identity_registration_qr_link")
     * @param Request $request
     * @return Response
     */
    public function qrRegistrationAction(Request $request)
    {
        $key = $this->tiqrService->generateEnrollmentKey();
        $metadataUrl = $request->getUriForPath(sprintf('/tiqr.php?key=%s', urlencode($key)));
        $metadataAppURL = 'tiqrenroll://'.$metadataUrl;

        // Return json if requested.
        if (in_array('application/json', $request->getAcceptableContentTypes(), true)) {
            return new JsonResponse([
                'appUrl' => $metadataAppURL,
                'url' => $metadataUrl
            ]);
        }

        // Simply return a html link, so they can click it and see the metadata result.
        return new Response(sprintf(
            '<a href="%s">%s</a>',
            htmlentities($metadataUrl),
            htmlentities($metadataAppURL)
        ));
    }
}
