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

namespace Unit;
use OpenConext\MonitorBundle\HealthCheck\HealthReportInterface;
use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\HealthCheck\StateStorageHealthCheck;
use Surfnet\Tiqr\HealthCheck\UserStorageHealthCheck;
use Surfnet\Tiqr\Tiqr\Legacy\TiqrUserRepository;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;

class UserStorageHealthCheckTest extends TestCase
{
    public function testCheckReturnsReportWhenStateStorageHealthCheckPasses(): void
    {
        $tiqrUserRepository = $this->createMock(TiqrUserRepositoryInterface::class);
        $tiqrUserRepository->method('userStorageHealthCheck')->willReturn(true);

        $report = $this->createMock(HealthReportInterface::class);

        $healthCheck = new UserStorageHealthCheck($tiqrUserRepository);
        $result = $healthCheck->check($report);

        $this->assertSame($report, $result);
    }

    public function testCheckReturnsStatusDownWhenStateStorageHealthCheckFails(): void
    {
        $tiqrUserRepository = $this->createMock(TiqrUserRepositoryInterface::class);
        $tiqrUserRepository->method('userStorageHealthCheck')->willReturn(false);

        $report = $this->createMock(HealthReportInterface::class);

        $healthCheck = new UserStorageHealthCheck($tiqrUserRepository);

        $this->expectExceptionMessage("Static method \"buildStatusDown\" cannot be invoked on mock object");
        $healthCheck->check($report);

    }
}
