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

namespace Surfnet\Tiqr\Service;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SessionCorrelationIdService
{
    private string $sessionName;
    private ?string $correlationIdSalt;

    /**
     * @param array<string, string> $sessionOptions
     */
    public function __construct(
        private RequestStack $requestStack,
        array $sessionOptions,
        ?string $correlationIdSalt = null,
    ) {
        if (!array_key_exists('name', $sessionOptions)) {
            throw new RuntimeException(
                'The session name (PHP session cookie identifier) could not be found in the session configuration.'
            );
        }
        $this->correlationIdSalt = is_null($correlationIdSalt) || strlen($correlationIdSalt) < 16 ? null : $correlationIdSalt;
        $this->sessionName = $sessionOptions['name'];
    }

    public function generateCorrelationId(): ?string
    {
        if ($this->correlationIdSalt === null) {
            return null;
        }

        $sessionCookie = $this->requestStack->getMainRequest()?->cookies->get($this->sessionName);

        if ($sessionCookie === null) {
            return null;
        }

        return substr(hash('sha256', $sessionCookie.$this->correlationIdSalt), 0, 8);
    }
}
