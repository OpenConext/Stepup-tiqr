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

namespace AppBundle\Tiqr\Legacy;

use AppBundle\Tiqr\Exception\UserNotExistsException;
use AppBundle\Tiqr\Legacy\TiqrUser;
use AppBundle\Tiqr\TiqrUserRepositoryInterface;

/**
 * Wrapper around the legacy Tiqr user repository.
 */
final class TiqrUserRepository implements TiqrUserRepositoryInterface
{
    /**
     * @var \Tiqr_UserStorage_Interface
     */
    private $userStorage;

    public function __construct($userStorage)
    {
        $this->userStorage = $userStorage;
    }

    public function createUser($userId, $secret)
    {
        $this->userStorage->createUser($userId, 'anonymous');
        $this->userStorage->setSecret($userId, $secret);
        return $this->getUser($userId);
    }

    public function getUser($userId)
    {
        if (!$this->userStorage->userExists($userId)) {
            throw UserNotExistsException::createFromId($userId);
        }
        return new TiqrUser($this->userStorage, $userId);
    }
}
