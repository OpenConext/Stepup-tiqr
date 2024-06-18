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
use Surfnet\Tiqr\HealthCheck\StateStorageHealthCheck;
use Surfnet\Tiqr\Tiqr\TiqrServiceInterface;

class StateStorageHealthCheckTest extends TestCase
{
    public function testCheckReturnsReportWhenStateStorageHealthCheckPasses(): void
    {
        $tiqrService = $this->createMock(TiqrServiceInterface::class);
        $result = new HealthCheckResultDto();
        $tiqrService->method('stateStorageHealthCheck')->willReturn($result);

        $report = HealthReport::buildStatusUp();

        $healthCheck = new StateStorageHealthCheck($tiqrService);
        $result = $healthCheck->check($report);

        $this->assertFalse($healthCheck->check($report)->isDown());
        $this->assertSame($report, $result);
    }

    public function testCheckReturnsStatusDownWhenStateStorageHealthCheckFails(): void
    {
        $tiqrService = $this->createMock(TiqrServiceInterface::class);
        $result = new HealthCheckResultDto();
        $result->isHealthy = false;
        $result->errorMessage = 'This is an error';
        $tiqrService->method('stateStorageHealthCheck')->willReturn($result);

        $report = HealthReport::buildStatusUp();

        $healthCheck = new StateStorageHealthCheck($tiqrService);

        $healthCheck->check($report);
        $this->assertTrue($healthCheck->check($report)->isDown());
    }
}
