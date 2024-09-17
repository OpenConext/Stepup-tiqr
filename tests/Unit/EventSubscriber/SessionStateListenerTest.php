<?php
/**
 * Copyright 2024 SURFnet B.V.
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

namespace Unit\EventSubscriber;

use Mockery;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Surfnet\Tiqr\EventSubscriber\SessionStateListener;
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionStateListenerTest extends KernelTestCase
{
    private const SESSION_ID = 'session-id';

    public function testItLogsWhenUserHasNoSessionCookie(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel();

        $request = new Request(server: ['REQUEST_URI' => '/route']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User made a request without a session cookie.', ['correlationId' => '', 'route' => '/route']);

        $listener = new SessionStateListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSIONID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelRequest']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testItLogsWhenUserHasNoSession(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel();

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User made a request with a session cookie.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Session not found.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);

        $listener = new SessionStateListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelRequest']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testItLogsAnErrorWhenTheSessionIdDoesNotMatchTheSessionCookie(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId('erroneous-session-id');

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User made a request with a session cookie.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User has a session.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::ERROR, 'The session cookie does not match the session id.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);

        $listener = new SessionStateListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelRequest']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testTheUserSessionMatchesTheSessionCookie(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId(self::SESSION_ID);

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);


        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User made a request with a session cookie.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User has a session.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'User session matches the session cookie.', ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']);

        $listener = new SessionStateListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelRequest']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }
}
