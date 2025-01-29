<?php
/**
 * Copyright 2025 SURFnet B.V.
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
use Surfnet\GsspBundle\Service\AuthenticationService;
use Surfnet\GsspBundle\Service\StateHandlerInterface;
use Surfnet\Tiqr\Controller\AuthenticationNotificationController;
use Surfnet\Tiqr\Service\TrustedDevice\TrustedDeviceService;
use Surfnet\Tiqr\Service\TrustedDeviceHelper;
use Surfnet\Tiqr\Tiqr\Legacy\TiqrUser;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

class AuthenticationNotificationControllerTest extends TestCase
{
    private AuthenticationService $authService;
    private StateHandlerInterface $stateHandler;
    private TiqrServiceInterface $tiqrService;
    private TiqrUserRepositoryInterface $userRepository;
    private TrustedDeviceService $trustedDeviceService;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        $this->authService = $this->createMock(AuthenticationService::class);
        $this->stateHandler = $this->createMock(StateHandlerInterface::class);
        $this->tiqrService = $this->createMock(TiqrServiceInterface::class);
        $this->userRepository = $this->createMock(TiqrUserRepositoryInterface::class);
        $this->trustedDeviceService = $this->createMock(TrustedDeviceService::class);

        parent::__construct($name, $data, $dataName);
    }

    public function provideTrustedDeviceCookieEnforcementEnabledScenarios(): array
    {
        return [
          [false, '"success"'],
          [true, '"no-trusted-device"'],
        ];
    }

    /**
     * @dataProvider provideTrustedDeviceCookieEnforcementEnabledScenarios
     */
    public function testTrustedDeviceCookieEnforcement(bool $trustedDeviceCookieEnforcementEnabled, string $expectedResponse): void
    {
        $controller = $this->makeController($trustedDeviceCookieEnforcementEnabled);
        $this->authService->method('authenticationRequired')->willReturn(true);

        $user = $this->mockUser('ACN', '01011001');

        $this->userRepository->method('getUser')->willReturn($user);
        $this->trustedDeviceService->method('read')->willReturn(null);

        $request = $this->createMock(Request::class);

        $response = $controller->__invoke($request);

        $this->assertSame($expectedResponse, $response->getContent());
    }

    private function makeController(bool $trustedDeviceCookieEnforcementEnabled): AuthenticationNotificationController
    {
        return new AuthenticationNotificationController(
            $this->authService,
            $this->stateHandler,
            $this->tiqrService,
            $this->userRepository,
            new NullLogger(),
            $this->trustedDeviceService,
            new TrustedDeviceHelper($this->trustedDeviceService, new NullLogger(), $trustedDeviceCookieEnforcementEnabled),
        );
    }

    private function mockUser(string $notificationType, string $notificationAddress): TiqrUser
    {
        $user = $this->createMock(TiqrUser::class);
        $user->expects($this->once())->method('getNotificationType')->willReturn($notificationType);
        $user->method('getNotificationAddress')->willReturn($notificationAddress);
        return $user;
    }
}