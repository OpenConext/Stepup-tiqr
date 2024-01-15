<?php

/**
 * Copyright 2019 SURFnet B.V.
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

use DateTime;
use DateTimeInterface;
use Surfnet\StepupBundle\Request\RequestId;
use Symfony\Component\HttpFoundation\Request;

final readonly class ErrorPageHelper
{
    public function __construct(private RequestId $requestId)
    {
    }

    /**
     * @return array<string, string|null>
     */
    public function generateMetadata(Request $request): array
    {
        $timestamp = (new DateTime)->format(DateTimeInterface::ATOM);
        $hostname = $request->getHost();
        $userAgent = $request->headers->get('User-Agent');
        $ipAddress = $request->getClientIp();
        return [
            'timestamp' => $timestamp,
            'hostname' => $hostname,
            'request_id' => $this->requestId->get(),
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
        ];
    }
}
