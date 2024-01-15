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

namespace App\Tiqr;

use App\Exception\TiqrServerRuntimeException;
use DateTimeImmutable;

interface TiqrUserInterface
{
    /**
     * Get the display name of a user.
     *
     * @return String the display name of this user
     *
     * @throws TiqrServerRuntimeException
     */
    public function getDisplayName(): string;

    /**
     * Get the user's OCRA client secret
     *
     * @return String The user's OCRA client secret
     *
     * @throws TiqrServerRuntimeException
     */
    public function getSecret(): string;

    /** Update the user's notificationType and notificationAddress
     *
     * @throws TiqrServerRuntimeException when there was en error updating the user's account
     */
    public function updateNotification(string $notificationType, string $notificationAddress): void;

    /**
     * @return string The userId
     */
    public function getId(): string;

    /**
     * Get the user's number unsuccessful login attempts
     *
     * @throws TiqrServerRuntimeException
     *
     * @return int
     */
    public function getLoginAttempts(): int;

    /**
     * Increase user's number of unsuccessful login attempts by one
     *
     * @throws TiqrServerRuntimeException
     */
    public function addLoginAttempt(): void;

    /**
     * Get the user's number of unsuccessful temporary login attempts
     *
     * @return int the number of temporary login attempts
     *
     * @throws TiqrServerRuntimeException
     */
    public function getTemporarilyLoginAttempts(): int;

    /**
     * Increase user's number of unsuccessful temporary login attempts by one
     *
     * @throws TiqrServerRuntimeException
     */
    public function addTemporarilyLoginAttempt(): void;

    /**
     * Block the user
     * This permanently blocks the user's account, isBlocked() will not return true
     *
     * @throws TiqrServerRuntimeException when there was an error blocking the account
     */
    public function block(): void;

    /**
     * Temporarily block the user and set the time at which the temporary block starts
     *
     * @param DateTimeImmutable $blockDateTime
     *   The date and time the user the temporary block starts, can later be used with $maxDuration>0 in
     *   isBlocked() to check
     *
     * @throws TiqrServerRuntimeException when there was an error setting the temporary block
     */
    public function blockTemporarily(DateTimeImmutable $blockDateTime): void;

    /**
     * Check if the user is blocked.
     * @param int $tempBlockDuration set to >0 to check for a temporary block in addition to a permanent block
     *                         If >0 this is the time in minutes after which a temporary block expires
     *                         The temporary block expiry
     *                         If set to 0, not temporary block check is performed and only the permanent block
     *                         status is checked.
     * @return bool true when the user is blocked or has a temporary block that is not expired
     *         false when the user is not blocked
     * @throws TiqrServerRuntimeException when the block state could not be determined
     */
    public function isBlocked(int $tempBlockDuration = 0): bool;

    /**
     * Resets the login attempt counter, the temporary login attempt counter and clear any temporary block
     * previously set using blockTemporarily()
     *
     * Does not affect a block set using block()
     * @throws TiqrServerRuntimeException when the reset (partially) failed
     */
    public function resetLoginAttempts(): void;

    /**
     * Return push notification type previously set with updateNotification()
     *
     * @return string
     */
    public function getNotificationType(): string;

    /**
     * Return push notification address previously set with updateNotification()
     *
     * @return string
     */
    public function getNotificationAddress(): string;
}
