<?php

declare(strict_types = 1);

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

namespace Surfnet\Tiqr\Tiqr;

use Surfnet\Tiqr\Exception\TiqrServerRuntimeException;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;

/**
 * Wrapper around the legacy Tiqr user repository.
 */
interface TiqrUserRepositoryInterface
{
    /**
     * Create new tiqr user.
     * @throws UserNotExistsException
     * @throws TiqrServerRuntimeException
     */
    public function createUser(string $userId, string $secret): TiqrUserInterface;

    /**
     * @throws UserNotExistsException
     * @throws TiqrServerRuntimeException
     */
    public function getUser(string $userId): TiqrUserInterface;

    public function userStorageHealthCheck(string &$message = ''): bool;

    public function userSecretStorageHealthCheck(string &$message = ''): bool;

}
