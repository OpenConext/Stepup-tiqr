<?php
/**
 * Copyright 2018 SURFnet B.V.
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
namespace App\Tiqr;

use App\Tiqr\Exception\ConfigurationException;

interface TiqrConfigurationInterface
{
    /**
     * Please don't use this to get individual options.
     */
    public function getTiqrOptions(): array;

    /**
     * @return boolean
     *    TRUE if there is a maximum block duration.
     */
    public function temporarilyBlockEnabled(): bool;

    /**
     * @return int
     *    The maximum block duration in minutes.
     *
     * @throws ConfigurationException
     */
    public function getTemporarilyBlockDuration(): int;

    /**
     * @throws ConfigurationException
     */
    public function getMaxAttempts(): int;

    public function hasMaxLoginAttempts(): bool;

    public function setMaxLoginAttempts(int $attempts): void;

    /**
     * @throws ConfigurationException
     */
    public function getMaxTemporarilyLoginAttempts(): int;

    public function hasMaxTemporarilyLoginAttempts(): bool;
}
