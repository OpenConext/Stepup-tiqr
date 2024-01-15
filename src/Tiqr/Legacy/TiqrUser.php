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

use DateTimeImmutable;
use Exception;
use Surfnet\Tiqr\Exception\TiqrServerRuntimeException;
use Surfnet\Tiqr\Tiqr\TiqrUserInterface;
use Tiqr_UserSecretStorage_Interface;
use Tiqr_UserStorage_Interface;

/**
 * Wrapper around the legacy Tiqr storage.
 *
 * It's some kind of active record implementation,
 * so every change will directly be stored inside the database.
 */
class TiqrUser implements TiqrUserInterface
{
    /**
     * @param string $userId
     */
    public function __construct(
        private readonly Tiqr_UserStorage_Interface $userStorage,
        private readonly Tiqr_UserSecretStorage_Interface $userSecretStorage,
        /**
         * @var string The userId
         */
        private $userId
    ) {
    }

    /**
     * @see TiqrUserInterface::getDisplayName()
     */
    public function getDisplayName(): string
    {
        try {
            return $this->userStorage->getDisplayName($this->userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::getSecret()
     */
    public function getSecret(): string
    {
        try {
            return $this->userSecretStorage->getSecret($this->userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::updateNotification()
     */
    public function updateNotification(string $notificationType, string $notificationAddress): void
    {
        try {
            if ($notificationType && $notificationAddress) {
                $this->userStorage->setNotificationType($this->userId, $notificationType);
                $this->userStorage->setNotificationAddress($this->userId, $notificationAddress);
            }
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::resetLoginAttempts()
     */
    public function resetLoginAttempts(): void
    {
        try {
            $this->userStorage->setLoginAttempts($this->userId, 0);
            $this->userStorage->setTemporaryBlockAttempts($this->userId, 0);
            $this->userStorage->setTemporaryBlockTimestamp($this->userId, 0);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::getId()
     */
    public function getId(): string
    {
        return $this->userId;
    }

    /**
     * @see TiqrUserInterface::getLoginAttempts()
     */
    public function getLoginAttempts(): int
    {
        try {
            return $this->userStorage->getLoginAttempts($this->userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::addLoginAttempt()
     */
    public function addLoginAttempt(): void
    {
        try {
            // Not this is not transactional and requires two SQL queries when using PDO driver
            $this->userStorage->setLoginAttempts($this->userId, $this->getLoginAttempts() + 1);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::block()
     */
    public function block(): void
    {
        try {
            $this->userStorage->setBlocked($this->userId, true);
            $this->resetLoginAttempts();
            $this->userStorage->setTemporaryBlockTimestamp($this->userId, 0);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::getTemporarilyLoginAttempts()
     */
    public function getTemporarilyLoginAttempts(): int
    {
        try {
            return $this->userStorage->getTemporaryBlockAttempts($this->userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::addTemporarilyLoginAttempt()
     */
    public function addTemporarilyLoginAttempt(): void
    {
        try {
            // Note: this is not transactional and requires two SQL queries when using the PDO driver
            $this->userStorage->setTemporaryBlockAttempts($this->userId, $this->getTemporarilyLoginAttempts() + 1);
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::blockTemporarily()
     */
    public function blockTemporarily(DateTimeImmutable $blockDateTime): void
    {
        // Order is important, with setting the BlockTimestamp we knows it's a temporarily block.
        $this->block();
        try {
            $this->userStorage->setTemporaryBlockTimestamp($this->userId, $blockDateTime->getTimestamp());
            $this->addTemporarilyLoginAttempt();
        } catch (Exception $e) {
            // Catch errors from the tiqr-server and up-cycle them to  exceptions that are meaningful to our domain
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::isBlocked()
     */
    public function isBlocked(int $tempBlockDuration = 0): bool
    {
        try {
            return $this->userStorage->isBlocked($this->userId, $tempBlockDuration);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::getNotificationType()
     */
    public function getNotificationType(): string
    {
        try {
            return $this->userStorage->getNotificationType($this->userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }

    /**
     * @see TiqrUserInterface::getNotificationAddress()
     */
    public function getNotificationAddress(): string
    {
        try {
            return $this->userStorage->getNotificationAddress($this->userId);
        } catch (Exception $e) {
            throw TiqrServerRuntimeException::fromOriginalException($e);
        }
    }
}
