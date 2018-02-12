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

namespace AppBundle\Tiqr;

/**
 * Wrapper around the legacy Tiqr storage.
 *
 * It's some kind of active record implementation,
 * so every change will directly be stored inside the database.
 */
class TiqrUser implements TiqrUserInterface
{
    /**
     * @var \Tiqr_UserStorage_Interface
     */
    private $userStorage;
    private $userId;

    public function __construct($userStorage, $userId)
    {
        $this->userStorage = $userStorage;
        $this->userId = $userId;
    }

    /**
     * Get the display name of a user.
     *
     * @return String the display name of this user
     */
    public function getDisplayName()
    {
        return $this->userStorage->getDisplayName($this->userId);
    }

    /**
     * Get the user's secret
     *
     * @return String The user's secret
     */
    public function getSecret()
    {
        return $this->userStorage->getSecret($this->userId);
    }

    public function updateNotification($notificationType, $notificationAddress)
    {
        $this->userStorage->setNotificationType($this->userId, $notificationType);
        $this->userStorage->setNotificationAddress($this->userId, $notificationAddress);
    }
}