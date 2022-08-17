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

namespace App\Tiqr\Legacy;

use App\Exception\TiqrServerRuntimeException;
use App\Tiqr\Exception\UserNotExistsException;
use App\Tiqr\TiqrUserRepositoryInterface;
use Exception;
use ReadWriteException;
use Tiqr_UserSecretStorage_Interface;
use Tiqr_UserStorage_Interface;

/**
 * Wrapper around the legacy Tiqr user repository.
 */
final class TiqrUserRepository implements TiqrUserRepositoryInterface
{
    /**
     * @var \Tiqr_UserStorage_Interface
     */
    private $userStorage;

    /**
     * @var \Tiqr_UserSecretStorage_Interface
     */
    private $userSecretStorage;

    public function __construct(
        Tiqr_UserStorage_Interface $userStorage,
        Tiqr_UserSecretStorage_Interface $userSecretStorage
    ) {
        $this->userStorage = $userStorage;
        $this->userSecretStorage = $userSecretStorage;
    }

    /**
     * @see TiqrUserRepositoryInterface::createUser()
     */
    public function createUser(string $userId, string $secret): TiqrUser
    {
        try {
            $this->userStorage->createUser($userId, 'anonymous');
            $this->userSecretStorage->setSecret($userId, $secret);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
        return $this->getUser($userId);
    }

    /**
     * @see TiqrUserRepositoryInterface::createUser()
     */
    public function getUser(string $userId): TiqrUser
    {
        try {
            $userExists = $this->userStorage->userExists($userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
        if (!$userExists) {
            throw UserNotExistsException::createFromId($userId);
        }

        return new TiqrUser($this->userStorage, $this->userSecretStorage, $userId);
    }
}
