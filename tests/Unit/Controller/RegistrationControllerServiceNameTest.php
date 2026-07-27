<?php
/**
 * Copyright 2026 SURFnet B.V.
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

namespace Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Surfnet\GsspBundle\Service\RegistrationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Surfnet\SamlBundle\SAML2\Extensions\MduiChunk;
use Surfnet\Tiqr\Controller\RegistrationController;
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Surfnet\Tiqr\Service\TrustedDeviceHelper;
use Surfnet\Tiqr\Service\TrustedDevice\TrustedDeviceService;
use Surfnet\Tiqr\Tiqr\Legacy\TiqrService;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Templating\EngineInterface;

class RegistrationControllerServiceNameTest extends TestCase
{
    public function testServiceNameIsResolvedFromMduiAndPassedToTheTemplate(): void
    {
        $registrationService = $this->createMock(RegistrationService::class);
        $registrationService->method('registrationRequired')->willReturn(true);
        $registrationService->method('getMdui')->willReturn(
            MduiChunk::fromXML(
                '<mdui:UIInfo xmlns:mdui="urn:oasis:names:tc:SAML:metadata:ui">'
                . '<mdui:DisplayName xml:lang="en">My Test Service</mdui:DisplayName>'
                . '</mdui:UIInfo>'
            )
        );

        $userRepository = $this->createMock(TiqrUserRepositoryInterface::class);
        $stateHandler = $this->createMock(StateHandlerInterface::class);
        $stateHandler->method('getRequestId')->willReturn('request-id');

        $tiqrService = $this->createMock(TiqrServiceInterface::class);
        $tiqrService->method('enrollmentFinalized')->willReturn(false);
        $tiqrService->method('generateEnrollmentKey')->willReturn('enrollment-key');

        // SessionCorrelationIdService is final/readonly and cannot be mocked; use a real instance instead.
        $correlationIdService = new SessionCorrelationIdService(
            new RequestStack(),
            ['name' => 'PHPSESSID']
        );

        $trustedDeviceHelper = new TrustedDeviceHelper(
            $this->createMock(TrustedDeviceService::class),
            new NullLogger(),
            false
        );

        $controller = new class(
            $registrationService,
            $userRepository,
            $stateHandler,
            $tiqrService,
            $correlationIdService,
            $trustedDeviceHelper,
            new NullLogger()
        ) extends RegistrationController {
            public array $lastRenderParameters = [];

            protected function render(string $view, array $parameters = [], ?\Symfony\Component\HttpFoundation\Response $response = null): \Symfony\Component\HttpFoundation\Response
            {
                $this->lastRenderParameters = $parameters;
                return new \Symfony\Component\HttpFoundation\Response('');
            }
        };

        $request = Request::create('https://tiqr.example.org/registration');
        $request->setLocale('en');

        $controller->registration($request);

        $this->assertSame('My Test Service', $controller->lastRenderParameters['serviceName']);
    }
}
