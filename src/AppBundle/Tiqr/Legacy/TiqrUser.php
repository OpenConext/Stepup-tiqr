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

use AppBundle\Tiqr\TiqrUserInterface;
use Assert\Assertion;

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

    public function resetLoginAttempts()
    {
        $this->userStorage->setLoginAttempts($this->userId, 0);
        $this->userStorage->setTemporaryBlockAttempts($this->userId, 0);
        $this->userStorage->setTemporaryBlockTimestamp($this->userId, null);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->userId;
    }

    /**
     * Get the amount of unsuccessful login attempts.
     *
     * @return int
     */
    public function getLoginAttempts()
    {
        return $this->userStorage->getLoginAttempts($this->userId);
    }

    /**
     * Increase the the amount of unsuccessful login attempts by one.
     */
    public function addLoginAttempt()
    {
        $this->userStorage->setLoginAttempts($this->userId, $this->getLoginAttempts() + 1);
    }

    /**
     * Block the user.
     */
    public function block()
    {
        $this->userStorage->setBlocked($this->userId, true);
        $this->resetLoginAttempts();
        $this->userStorage->setTemporaryBlockTimestamp($this->userId, null);
    }

    /**
     * Get the amount of unsuccessful login attempts.
     *
     * @return int
     */
    public function getTemporaryLoginAttempts()
    {
        return $this->userStorage->getTemporaryBlockAttempts($this->userId);
    }

    /**
     * Increase the the amount of unsuccessful login attempts by one.
     */
    public function addTemporaryLoginAttempt()
    {
        $this->userStorage->setTemporaryBlockAttempts($this->userId, $this->getTemporaryLoginAttempts() + 1);
    }

    /**
     * Block the user on the current date.
     *
     * @param \DateTimeImmutable $blockDate
     *   The date the user is blocked.
     */
    public function blockTemporary(\DateTimeImmutable $blockDate)
    {
        // Order is important, with setting the BlockTimestamp we knows it's a temporary block.
        $this->block();
        $this->userStorage->setTemporaryBlockTimestamp($this->userId, $blockDate->format('Y-m-d H:i:s'));
        $this->addTemporaryLoginAttempt();
    }

    /**
     * If the user is blocked.
     *
     * @return boolean
     */
    public function isBlocked()
    {
        return $this->userStorage->isBlocked($this->userId, false);
    }

    /**
     * If the user is blocked.
     *
     * @param \DateTimeImmutable $now
     *   The current date.
     * @param int $maxDuration
     *   The maximum duration on minutes
     *
     * @return boolean
     * @throws \Assert\AssertionFailedException
     */
    public function isBlockTemporary(\DateTimeImmutable $now, $maxDuration)
    {
        Assertion::digit($maxDuration);

        if (!$this->isBlocked()) {
            return false;
        }
        $timestamp = $this->userStorage->getTemporaryBlockTimestamp($this->userId);

        // If the TemporaryBlock Timestamp is empty, the use is blocked forever.
        if (empty($timestamp)) {
            return true;
        }

        $timestamp = $this->userStorage->getTemporaryBlockTimestamp($this->userId);
        return (strtotime($timestamp) + $maxDuration * 60) < $now->getTimestamp();
    }
}
