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
        self::bootKernel();

        $request = new Request(server: ['REQUEST_URI' => '/route']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                'User made a request without a session cookie.',
                ['correlationId' => '', 'route' => '/route']
            );
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
        self::bootKernel();

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = $this->createMock(LoggerInterface::class);

        $mockLogger->expects($this->exactly(2))
            ->method('log')
            ->willReturnCallback(function ($level, $message, $context) {
                static $calledMessages = [];

                if (isset($calledMessages[$message])) {
                    $this->fail('Log message "' . $message . '" was called more than once.');
                }
                $calledMessages[$message] = true;

                switch($message) {
                    case 'Session not found.':
                    case 'User made a request with a session cookie.':
                        $this->assertSame(LogLevel::INFO, $level);
                        $this->assertSame('f02614d0', $context['correlationId']);
                        $this->assertSame('/route', $context['route']);
                        break;
                    default:
                        $this->fail('Unexpected log message');
                }

            })
        ;

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
        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId('erroneous-session-id');

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->exactly(3))
            ->method('log')
            ->willReturnCallback(function ($level, $message, $context) {
                static $calledMessages = [];

                if (isset($calledMessages[$message])) {
                    $this->fail('Log message "' . $message . '" was called more than once.');
                }
                $calledMessages[$message] = true;

                switch($message) {
                    case 'User made a request with a session cookie.':
                    case 'User has a session.':
                    case 'The session cookie does not match the session id.':
                        $this->assertSame($level === LogLevel::ERROR ? LogLevel::ERROR : LogLevel::INFO, $level);
                        $this->assertSame('f02614d0', $context['correlationId']);
                        $this->assertSame('/route', $context['route']);
                        break;
                    default:
                        $this->fail('Unexpected log message');
                }
            });

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
        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId(self::SESSION_ID);

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);


        $event = new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $dispatcher = new EventDispatcher();

        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->exactly(3))
            ->method('log')
            ->willReturnCallback(function ($level, $message, $context) {
                static $calledMessages = [];

                if (isset($calledMessages[$message])) {
                    $this->fail('Log message "' . $message . '" was called more than once.');
                }
                $calledMessages[$message] = true;

                switch($message) {
                    case 'User made a request with a session cookie.':
                    case 'User has a session.':
                    case 'User session matches the session cookie.':
                        $this->assertSame(LogLevel::INFO, $level);
                        $this->assertSame('f02614d0', $context['correlationId']);
                        $this->assertSame('/route', $context['route']);
                        break;
                    default:
                        $this->fail('Unexpected log message');
                }
            });

        $listener = new SessionStateListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelRequest']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }
}
