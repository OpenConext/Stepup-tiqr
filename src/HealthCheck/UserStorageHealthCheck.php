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

namespace Surfnet\Tiqr\HealthCheck;

use OpenConext\MonitorBundle\HealthCheck\HealthCheckInterface;
use OpenConext\MonitorBundle\HealthCheck\HealthReportInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;

class UserStorageHealthCheck implements HealthCheckInterface
{

    public function __construct(private readonly TiqrUserRepositoryInterface $tiqrUserRepository)
    {
    }

    public function check(HealthReportInterface $report): HealthReportInterface
    {
        $result = $this->tiqrUserRepository->userStorageHealthCheck();

        if (! $result->isHealthy) {
            return $report::buildStatusDown($result->errorMessage);
        }

        return $report;
    }
}
