<?php

/**
 * Copyright 2025 SURFnet B.V.
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

use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Service\TrustedDevice\TrustedDeviceService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

readonly class TrustedDeviceHelper
{

    public function __construct(
        private TrustedDeviceService $cookieService,
        private LoggerInterface $logger,
    ) {
    }

    public function handleRegisterTrustedDevice(
        string $notificationAddress,
        Response $responseObject
    ): void {
        if (trim($notificationAddress) === '') {
            return;
        }

        try {
            $this->cookieService->registerTrustedDevice(
                $responseObject,
                $notificationAddress
            );
        } catch (Throwable $e) {
            $this->logger->warning('Could not register trusted device on registration', ['exception' => $e]);
        }
    }
}
