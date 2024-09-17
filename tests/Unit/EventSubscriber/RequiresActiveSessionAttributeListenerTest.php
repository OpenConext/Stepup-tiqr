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
use Surfnet\Tiqr\Attribute\RequiresActiveSession;
use Surfnet\Tiqr\EventSubscriber\RequiresActiveSessionAttributeListener;
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class RequiresActiveSessionAttributeListenerTest extends KernelTestCase
{
    private const SESSION_ID = 'session-id';

    public function testControllersWithoutTheRequiresActiveSessionAttributeAreIgnored(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel();

        $request = new Request(server: ['REQUEST_URI' => '/route']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $stubControllerFactory = fn() => new class extends AbstractController {
        };

        $event = new ControllerArgumentsEvent(
            self::$kernel,
            $stubControllerFactory,
            [], $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $listener = new RequiresActiveSessionAttributeListener(
            $mockLogger,
            new SessionCorrelationIdService(
                $requestStack,
                ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'
            ),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelControllerArguments']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testItDeniesAccessWhenThereIsNoActiveSession(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied.');

        self::bootKernel();

        $request = new Request(server: ['REQUEST_URI' => '/route']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $stubControllerFactory = fn() => new class extends AbstractController {
        };

        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $controllerEvent = new ControllerEvent(self::$kernel, $stubControllerFactory, $request, $requestType);
        $controllerEvent->setController($stubControllerFactory, [RequiresActiveSession::class => [null]]);
        $event = new ControllerArgumentsEvent(self::$kernel, $controllerEvent, [], $request, $requestType);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(
                LogLevel::ERROR,
                'Route requires active session. Active session wasn\'t found.',
                ['correlationId' => '', 'route' => '/route']
            );

        $listener = new RequiresActiveSessionAttributeListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelControllerArguments']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testItDeniesAccessWhenThereIsNoSessionCookie(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied.');

        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId(self::SESSION_ID);

        $request = new Request(server: ['REQUEST_URI' => '/route']);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $stubControllerFactory = fn() => new class extends AbstractController {
        };

        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $controllerEvent = new ControllerEvent(self::$kernel, $stubControllerFactory, $request, $requestType);
        $controllerEvent->setController($stubControllerFactory, [RequiresActiveSession::class => [null]]);
        $event = new ControllerArgumentsEvent(self::$kernel, $controllerEvent, [], $request, $requestType);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(
                LogLevel::ERROR,
                'Route requires active session. Active session wasn\'t found. No session cookie was set.',
                ['correlationId' => '', 'route' => '/route']
            );

        $listener = new RequiresActiveSessionAttributeListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelControllerArguments']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testItDeniesAccessWhenTheActiveSessionDoesNotMatchTheSessionCookie(): void
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied.');

        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId('erroneous-session-id');

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $stubControllerFactory = fn() => new class extends AbstractController {
        };

        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $controllerEvent = new ControllerEvent(self::$kernel, $stubControllerFactory, $request, $requestType);
        $controllerEvent->setController($stubControllerFactory, [RequiresActiveSession::class => [null]]);
        $event = new ControllerArgumentsEvent(self::$kernel, $controllerEvent, [], $request, $requestType);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldReceive('log')
            ->once()
            ->with(
                LogLevel::ERROR,
                'Route requires active session. Session does not match session cookie.',
                ['correlationId' => 'f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', 'route' => '/route']
            );

        $listener = new RequiresActiveSessionAttributeListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelControllerArguments']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }

    public function testItDoesNotThrowWhenTheActiveSessionMatchesTheSessionCookie(): void
    {
        $this->expectNotToPerformAssertions();

        self::bootKernel();

        $session = new Session(new MockArraySessionStorage());
        $session->setId(self::SESSION_ID);

        $request = new Request(server: ['REQUEST_URI' => '/route'], cookies: ['PHPSESSID' => self::SESSION_ID]);
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $stubControllerFactory = fn() => new class extends AbstractController {
        };

        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $controllerEvent = new ControllerEvent(self::$kernel, $stubControllerFactory, $request, $requestType);
        $controllerEvent->setController($stubControllerFactory, [RequiresActiveSession::class => [null]]);
        $event = new ControllerArgumentsEvent(self::$kernel, $controllerEvent, [], $request, $requestType);

        $dispatcher = new EventDispatcher();

        $mockLogger = Mockery::mock(LoggerInterface::class);
        $mockLogger->shouldNotReceive('log');

        $listener = new RequiresActiveSessionAttributeListener(
            $mockLogger,
            new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'),
            ['name' => 'PHPSESSID'],
        );

        $dispatcher->addListener(KernelEvents::REQUEST, [$listener, 'onKernelControllerArguments']);
        $dispatcher->dispatch($event, KernelEvents::REQUEST);
    }
}
