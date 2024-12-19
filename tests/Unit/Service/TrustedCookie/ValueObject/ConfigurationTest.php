<?php declare(strict_types=1);

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

namespace Unit\Service\TrustedDevice\ValueObject;

use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\InvalidCookieLifetimeException;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\InvalidEncryptionKeyException;
use Surfnet\Tiqr\Service\TrustedDevice\Http\CookieSameSite;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;

class ConfigurationTest extends TestCase
{
    public function test_cookie_requires_non_zero_lifetime(): void
    {
        $this->expectException(InvalidCookieLifetimeException::class);
        $this->expectExceptionMessage('When using a persistent cookie, you must configure a non zero cookie lifetime');
        new Configuration('name',  0, 'LORUM IPSUM DOLOR SIT AMOR VINCIT OMIA', CookieSameSite::SAMESITE_STRICT->value);
    }

    public function test_encryption_key_must_be_hexadecimal(): void
    {
        $this->expectException(InvalidEncryptionKeyException::class);
        $this->expectExceptionMessage('The configured trusted device encryption key contains illegal characters. It should be a 64 digits long hexadecimal value. Example value: 000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f');
        new Configuration('name',  60, 'Monkey nut Mies', CookieSameSite::SAMESITE_STRICT->value);
    }

    public function test_encryption_key_must_be_amply_strong(): void
    {
        $this->expectException(InvalidEncryptionKeyException::class);
        $this->expectExceptionMessage('The configured trusted device encryption key must be exactly 32 bytes. This comes down to 64 hex digits value, configured in the trusted_device_encryption_key configuration option');
        new Configuration('name', 60, '0f0f0f', CookieSameSite::SAMESITE_STRICT->value);
    }
}
