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

use Exception;
use ParagonIE\ConstantTime\Binary;
use SensitiveParameter;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\InvalidCookieLifetimeException;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\InvalidEncryptionKeyException;
use Surfnet\Tiqr\Service\TrustedDevice\Http\CookieSameSite;

readonly class Configuration
{

    public string $encryptionKey;

    public CookieSameSite $sameSite;

    public function __construct(
        public string $prefix,
        public int $lifetimeInSeconds,
        #[SensitiveParameter] string $encryptionKey,
        string $sameSite,
    ) {
        $this->sameSite = CookieSameSite::from($sameSite);
        if ($lifetimeInSeconds === 0) {
            throw new InvalidCookieLifetimeException(
                'When using a persistent cookie, you must configure a non zero cookie lifetime'
            );
        }

        // Convert the key from the configuration from hex to binary. sodium_hex2bin
        try {
            $this->encryptionKey = sodium_hex2bin($encryptionKey);
        } catch (Exception $e) {
            // The key contains non-hexadecimal values. Show a custom error message in logs.
            throw new InvalidEncryptionKeyException(
                'The configured trusted device encryption key contains illegal characters. It should be a 64 digits long ' .
                'hexadecimal value. Example value: 000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
                0,
                $e
            );
        }

        // The key length, converted back to binary must be 32 bytes long
        if (Binary::safeStrlen($this->encryptionKey) < SODIUM_CRYPTO_STREAM_KEYBYTES) {
            throw new InvalidEncryptionKeyException(
                sprintf(
                    'The configured trusted device encryption key must be exactly %d bytes. ' .
                    'This comes down to 64 hex digits value, configured in the trusted_device_encryption_key configuration option',
                    SODIUM_CRYPTO_STREAM_KEYBYTES
                )
            );
        }
    }
}
