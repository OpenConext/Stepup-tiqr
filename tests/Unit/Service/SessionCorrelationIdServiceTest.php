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

namespace Unit\Service;

use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\Service\SessionCorrelationIdService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SessionCorrelationIdServiceTest extends TestCase
{
    public function testItHasNoCorrelationIdWhenThereIsNoSessionCookie(): void
    {
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $service = new SessionCorrelationIdService($requestStack);

        $this->assertNull($service->generateCorrelationId());
    }

    public function testItGeneratesACorrelationIdBasedOnTheSessionCookie(): void
    {
        $request = new Request(cookies: ['PHPSESSID' => 'session-id']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $service = new SessionCorrelationIdService($requestStack);

        $this->assertSame('f6e7cfb6f0861f577c48f171e27542236b1184f7a599dde82aca1640d86da961', $service->generateCorrelationId());
    }
}
