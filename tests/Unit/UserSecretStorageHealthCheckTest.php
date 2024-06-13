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
use OpenConext\MonitorBundle\Value\HealthReport;
use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\HealthCheck\HealthCheckResultDto;
use Surfnet\Tiqr\HealthCheck\UserSecretStorageHealthCheck;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;

class UserSecretStorageHealthCheckTest extends TestCase
{
    public function testCheckReturnsReportWhenUserSecretStorageHealthCheckPasses(): void
    {
        $tiqrUserRepository = $this->createMock(TiqrUserRepositoryInterface::class);
        $result = new HealthCheckResultDto();
        $tiqrUserRepository->method('userSecretStorageHealthCheck')->willReturn($result);

        $report = HealthReport::buildStatusUp();

        $healthCheck = new UserSecretStorageHealthCheck($tiqrUserRepository);
        $result = $healthCheck->check($report);

        $this->assertFalse($healthCheck->check($report)->isDown());

        $this->assertSame($report, $result);
    }

    public function testCheckReturnsStatusDownWhenUserSecretStorageHealthCheckFails(): void
    {
        $tiqrUserRepository = $this->createMock(TiqrUserRepositoryInterface::class);
        $result = new HealthCheckResultDto();
        $result->isHealthy = false;
        $result->errorMessage = 'This is an error';
        $tiqrUserRepository->method('userSecretStorageHealthCheck')->willReturn($result);

        $report = HealthReport::buildStatusUp();

        $healthCheck = new UserSecretStorageHealthCheck($tiqrUserRepository);

        $this->assertTrue($healthCheck->check($report)->isDown());

    }
}
