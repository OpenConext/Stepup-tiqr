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

use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\Service\TimeoutHelper;

class TimeoutHelperTest extends TestCase
{
    /**
     * @dataProvider provideTimeoutExpectations
     */
    public function test_timeout_reached(
        bool $expectation,
        int $currentTime,
        int $startedAt,
        int $timeoutInSeconds,
        int $offset
    ): void{
        $isTimedOut = TimeoutHelper::isTimedOut(
            $currentTime,
            $startedAt,
            $timeoutInSeconds,
            $offset,
        );
        self::assertEquals($expectation, $isTimedOut);
    }

    public function provideTimeoutExpectations(): array
    {
        return [
            // Timed out expectations
            'way over time' => [true, 1000, 0, 300, 2], // 100 seconds ago the user started the clock
            'just over time' => [true, 303, 0, 300, 2], // 5 seconds over timeout time
            'timeout due to offset - 1' => [true, 298, 0, 300, 2], // the offset is reached
            'timeout due to offset - 2' => [true, 299, 0, 300, 2], // the offset is reached
            // In time expectations
            'very much in time' => [false, 0, 0, 300, 2], // 298 seconds to go before reaching timeout
            'just in time' => [false, 297, 0, 300, 2], // last second before reaching timeout
        ];
    }
}
