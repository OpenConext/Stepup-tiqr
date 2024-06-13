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

namespace Surfnet\Tiqr\Tiqr\Legacy;

use Exception;
use Surfnet\Tiqr\Exception\TiqrServerRuntimeException;
use Surfnet\Tiqr\HealthCheck\HealthCheckResultDto;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Tiqr_HealthCheck_Interface;
use Tiqr_UserSecretStorage_Interface;
use Tiqr_UserStorage_Interface;

/**
 * Wrapper around the legacy Tiqr user repository.
 */
final readonly class TiqrUserRepository implements TiqrUserRepositoryInterface
{
    public function __construct(private Tiqr_UserStorage_Interface $userStorage, private Tiqr_UserSecretStorage_Interface $userSecretStorage)
    {
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

    public function userStorageHealthCheck(): HealthCheckResultDto
    {
        assert($this->userStorage instanceof  Tiqr_HealthCheck_Interface);

        $message = '';
        $result = new HealthCheckResultDto();
        $result->isHealthy = $this->userStorage->healthCheck($message);
        $result->errorMessage = $message;

        return $result;
    }

    public function userSecretStorageHealthCheck(string &$message = ''): HealthCheckResultDto
    {
        assert($this->userSecretStorage instanceof  Tiqr_HealthCheck_Interface);

        $message = '';
        $result = new HealthCheckResultDto();
        $result->isHealthy = $this->userSecretStorage->healthCheck($message);
        $result->errorMessage = $message;

        return $result;
    }
}
