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

namespace Surfnet\Tiqr\Service\TrustedCookie\ValueObject;

use DateTime;
use InvalidArgumentException;

use function strtolower;
use function strtotime;

class CookieValue implements CookieValueInterface
{
    private string $notificationAddress;
    private string $userId;
    private string $authenticationTime;

    /**
     * The cookie value consists of:
     * - User id
     * - Notification address
     * - Authentication time (Atom formatted date time string)
     */
    public static function from(string $userId, string $notificationAddress): self
    {
        $cookieValue = new self;
        $cookieValue->notificationAddress = $notificationAddress;
        $cookieValue->userId = $userId;
        $dateTime = new DateTime();
        $cookieValue->authenticationTime = $dateTime->format(DATE_ATOM);
        return $cookieValue;
    }

    /**
     * @throws \JsonException
     */
    public static function deserialize(string $serializedData): CookieValueInterface
    {
        $data = json_decode($serializedData, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new InvalidArgumentException('Invalid serialized data');
        }

        if (!is_string($data['tokenId'])) {
            throw new InvalidArgumentException('tokenId is not a valid string');
        }

        if (!is_string($data['identityId'])) {
            throw new InvalidArgumentException('tokenId is not a valid string');
        }

        $cookieValue = new self;
        $cookieValue->notificationAddress = $data['tokenId'];
        $cookieValue->userId = $data['identityId'];
        $cookieValue->authenticationTime = (string) $data['authenticationTime'];

        return $cookieValue;
    }

    /**
     * @throws \JsonException
     */
    public function serialize(): string
    {
        return json_encode([
            'tokenId' => $this->notificationAddress,
            'identityId' => $this->userId,
            'authenticationTime' => $this->authenticationTime,
        ], JSON_THROW_ON_ERROR);
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function secondFactorId(): string
    {
        return $this->notificationAddress;
    }

    public function issuedTo(string $identityNameId): bool
    {
        return strtolower($identityNameId) === strtolower($this->userId);
    }

    public function authenticationTime(): int
    {
        return strtotime($this->authenticationTime) ?: throw new InvalidArgumentException('Invalid authentication time format');
    }
}
