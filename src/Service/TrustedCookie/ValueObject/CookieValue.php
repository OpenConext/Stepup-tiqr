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
    private string $tokenId;
    private string $identityId;
    private string $authenticationTime;

    /**
     * The cookie value consists of:
     * - Token used: SecondFactorId from SecondFactor
     * - Identifier: IdentityId from SecondFactor
     * - Authentication time (Atom formatted date time string)
     */
    public static function from(string $identityId, string $secondFactorId): self
    {
        $cookieValue = new self;
        $cookieValue->tokenId = $secondFactorId;
        $cookieValue->identityId = $identityId;
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
        $cookieValue->tokenId = $data['tokenId'];
        $cookieValue->identityId = $data['identityId'];
        $cookieValue->authenticationTime = (string) $data['authenticationTime'];

        return $cookieValue;
    }

    /**
     * @throws \JsonException
     */
    public function serialize(): string
    {
        return json_encode([
            'tokenId' => $this->tokenId,
            'identityId' => $this->identityId,
            'authenticationTime' => $this->authenticationTime,
        ], JSON_THROW_ON_ERROR);
    }

    public function getIdentityId(): string
    {
        return $this->identityId;
    }

    public function secondFactorId(): string
    {
        return $this->tokenId;
    }

    public function issuedTo(string $identityNameId): bool
    {
        return strtolower($identityNameId) === strtolower($this->identityId);
    }

    public function authenticationTime(): int
    {
        return strtotime($this->authenticationTime) ?: throw new InvalidArgumentException('Invalid authentication time format');
    }
}
