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

/**
 * Wrapper around the legacy Tiqr user repository.
 */
interface TiqrUserRepositoryInterface
{
    /**
     * Create new tiqr user.
     *
     * @param string $userId
     * @param string $secret
     *
     * @return TiqrUserInterface
     */
    public function createUser($userId, $secret);

    /**
     * @param string $userId
     *
     * @return TiqrUserInterface
     *
     * @throws \App\Tiqr\Exception\UserNotExistsException
     */
    public function getUser($userId);
}
