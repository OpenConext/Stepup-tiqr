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
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listen to all incoming requests and log the session state information.
 */
final readonly class SessionStateListener implements EventSubscriberInterface
{
    private string $sessionName;

    /**
     * @param array<string, string> $sessionOptions
     */
    public function __construct(
        private LoggerInterface $logger,
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

    public function onKernelRequest(RequestEvent $event): void
    {
        $logger = WithContextLogger::from($this->logger, [
            'correlationId' => $this->sessionCorrelationIdService->generateCorrelationId() ?? '',
            'route' => $event->getRequest()->getRequestUri(),
        ]);

        $sessionCookieId = $event->getRequest()->cookies->get($this->sessionName);
        if ($sessionCookieId === null) {
            $logger->info('User made a request without a session cookie.');
            return;
        }

        $logger->info('User made a request with a session cookie.');

        try {
            $sessionId = $event->getRequest()->getSession()->getId();
            $logger->info('User has a session.');

            if ($sessionId !== $sessionCookieId) {
                $logger->error('The session cookie does not match the session id.');
                return;
            }
        } catch (SessionNotFoundException) {
            $logger->info('Session not found.');
            return;
        }

        $logger->info('User session matches the session cookie.');
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }
}
