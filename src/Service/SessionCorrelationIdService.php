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

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SessionCorrelationIdService
{
    private const SALT = 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ';

    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public function generateCorrelationId(): ?string
    {
        $sessionCookie = $this->requestStack->getMainRequest()?->cookies->get('PHPSESSID');

        if ($sessionCookie === null) {
            return null;
        }

        return hash('sha256', self::SALT . substr($sessionCookie, 0, 10));
    }
}
