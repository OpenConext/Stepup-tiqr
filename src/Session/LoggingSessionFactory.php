<?php

declare(strict_types = 1);

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

namespace Surfnet\Tiqr\Session;

use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Surfnet\Tiqr\WithContextLogger;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

/**
 * This class serves as a decorated version of Symfony's SessionFactory class.
 * Every time a session is created, we'll add a log entry that can be identified by the correlation id.
 *
 * For this logging function to work we need a stateless logger, therefore we inject the default logger by using the variable name $monoLogger.
 * The variable name $logger has been bound to the Surfnet GSSP logger in the Symfony container, so we'll need to use another variable name.
 * The Surfnet GSSP Logger isn't stateless as it's dependent on the current session, which has not been created when the logging occurs.
 *
 * @see SessionFactory::createSession()
 */
#[AsDecorator('session.factory')]
final class LoggingSessionFactory extends SessionFactory
{
    private LoggerInterface $logger;

    public function __construct(
        RequestStack                   $requestStack,
        #[Autowire(service: 'session.storage.factory')]
        SessionStorageFactoryInterface $storageFactory,
        LoggerInterface                $monologLogger,
        SessionCorrelationIdService    $sessionCorrelationIdService,
        ?callable                      $usageReporter = null,
    ) {
        $this->logger = WithContextLogger::from(
            $monologLogger,
            ['correlationId' => $sessionCorrelationIdService->generateCorrelationId() ?? ''],
        );

        parent::__construct($requestStack, $storageFactory, $usageReporter);
    }

    public function createSession(): SessionInterface
    {
        $this->logger->info('Created new session.');

        return parent::createSession();
    }
}
