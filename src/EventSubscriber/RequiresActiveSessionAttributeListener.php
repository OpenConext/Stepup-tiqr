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

declare(strict_types = 1);

namespace Surfnet\Tiqr\EventSubscriber;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Surfnet\Tiqr\Attribute\RequiresActiveSession;
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use function is_array;

/**
 * This listener acts when the given route has a #[RequiresActiveSession] attribute.
 * When a route is marked as to have a required active session this listener will deny access when there is none.
 */
final readonly class RequiresActiveSessionAttributeListener implements EventSubscriberInterface
{
    private string $sessionName;

    public function __construct(
        private LoggerInterface             $logger,
        private SessionCorrelationIdService $sessionCorrelationIdService,
        private array $sessionOptions,
    ) {
        if (!array_key_exists('name', $this->sessionOptions)) {
            throw new RuntimeException(
                'The session name (PHP session cookie identifier) could not be found in the session configuration.'
            );
        }
        $this->sessionName = $this->sessionOptions['name'];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!is_array($event->getAttributes()[RequiresActiveSession::class] ?? null)) {
            return;
        }

        $logger = WithContextLogger::from($this->logger, [
            'correlationId' => $this->sessionCorrelationIdService->generateCorrelationId() ?? '',
            'route' => $event->getRequest()->getRequestUri(),
        ]);

        try {
            $sessionId = $event->getRequest()->getSession()->getId();
            $sessionCookieId = $event->getRequest()->cookies->get($this->sessionName);

            if (!$sessionCookieId) {
                $logger->error('Route requires active session. Active session wasn\'t found. No session cookie was set.');

                throw new AccessDeniedException();
            }

            if ($sessionId !== $sessionCookieId) {
                $logger->error('Route requires active session. Session does not match session cookie.');

                throw new AccessDeniedException();
            }
        } catch (SessionNotFoundException) {
            $logger->error('Route requires active session. Active session wasn\'t found.');

            throw new AccessDeniedException();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments', 20]];
    }
}
