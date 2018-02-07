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

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocaleController extends Controller
{
    /**
     * @Route("/local/{lang}", name="local")
     */
    public function localeAction(Request $request, $lang)
    {
        // Make sure we redirect back to the this host.
        $referer = $request->headers->get('referer');
        if ($request->getHost() !== parse_url($referer, PHP_URL_HOST)) {
            return new Response(sprintf('Cannot be requested from %s', $referer), Response::HTTP_BAD_REQUEST);
        }

        // Set local.
        $request->setLocale($lang);

        return new RedirectResponse($referer);
    }
}
