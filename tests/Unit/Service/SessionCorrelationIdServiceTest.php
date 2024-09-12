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

        $service = new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ');

        $this->assertNull($service->generateCorrelationId());
    }

    public function testItGeneratesACorrelationIdBasedOnTheSessionCookie(): void
    {
        $request = new Request(cookies: ['PHPSESSID' => 'session-id']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $service = new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], 'Mr6LpJYtuWRDdVR2_7VgTChFhzQ'
        );

        $this->assertSame('f02614d0', $service->generateCorrelationId());
    }


    /**
     * @dataProvider saltProvider
     */
    public function testItWillNotGenerateACorrelationIdWithoutAdequateSalt(?string $salt): void
    {
        $request = new Request(cookies: ['PHPSESSID' => 'session-id']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $service = new SessionCorrelationIdService($requestStack, ['name' => 'PHPSESSID'], $salt);

        $this->assertNull($service->generateCorrelationId());
    }

    public function saltProvider(): array
    {
        return [
            'empty salt' => [''],
            'null salt' => [null],
            'short salt' => ['abc'],
            'almost_long_enough salt' => ['1234567890ABCDE'],
        ];
    }
}
