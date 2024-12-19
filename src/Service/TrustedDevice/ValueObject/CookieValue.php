<?php

declare(strict_types = 1);

/**
 * Copyright 2024 SURFnet bv
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

namespace Surfnet\Tiqr\Service\TrustedDevice\ValueObject;

use DateTime;
use InvalidArgumentException;

use JsonException;

use function strtotime;

readonly class CookieValue
{
    private function __construct(
        private string $notificationAddress,
        /** Authentication time (Atom formatted date time string) */
        private string $authenticationTime
    ) {
    }


    public static function from(string $notificationAddress): self
    {
        if (trim($notificationAddress) === '') {
            throw new InvalidArgumentException('Cannot create a trusted device cookie. NotificationAddress is empty.');
        }

        return new self($notificationAddress, (new DateTime())->format(DATE_ATOM));
    }

    /**
     * @throws JsonException
     */
    public static function deserialize(string $serializedData): CookieValue
    {
        $data = json_decode($serializedData, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid serialized data');
        }

        if (!is_string($data['notificationAddress'])) {
            throw new InvalidArgumentException('notificationAddress is not a valid string');
        }

        if (!is_string($data['authenticationTime'])) {
            throw new InvalidArgumentException('authenticationTime is not a valid string');
        }

        return new self($data['notificationAddress'], $data['authenticationTime']);
    }

    /**
     * @throws JsonException
     */
    public function serialize(): string
    {
        return json_encode([
            'notificationAddress' => $this->notificationAddress,
            'authenticationTime' => $this->authenticationTime,
        ], JSON_THROW_ON_ERROR);
    }

    public function getNotificationAddress(): string
    {
        return $this->notificationAddress;
    }

    public function authenticationTime(): int
    {
        return strtotime($this->authenticationTime) ?: throw new InvalidArgumentException('Invalid authentication time format');
    }
}
