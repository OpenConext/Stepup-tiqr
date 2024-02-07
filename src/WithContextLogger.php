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

namespace App;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class WithContextLogger extends AbstractLogger
{
    private function __construct(
        private readonly LoggerInterface $logger,
        /** @var array<string, string> $context */
        private readonly array $context
    ) {
    }

    /**
     * @param array<string,string> $context
     */
    public static function from(
        LoggerInterface $logger,
        array $context
    ): self {
        return new self($logger, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param array<string, string> $context
     *
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, array_merge($this->context, $context));
    }
}
