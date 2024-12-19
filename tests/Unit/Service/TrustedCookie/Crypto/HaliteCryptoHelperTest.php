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

namespace Unit\Service\TrustedDevice\Crypto;

use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\Service\TrustedDevice\Crypto\HaliteCryptoHelper;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\DecryptionFailedException;
use Surfnet\Tiqr\Service\TrustedDevice\Http\CookieSameSite;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\CookieValue;

/**
 * Integration test for the Crypto helper
 */
class HaliteCryptoHelperTest extends TestCase
{
    /**
     * @var HaliteCryptoHelper
     */
    private $helper;

    protected function setUp(): void
    {
        $configuration = new Configuration(
            'tiqr-trusted-device',
            2592000,
            '567146182F847B8624C191281068AAD0567146182F847B8624C191281068AAD0',
            CookieSameSite::SAMESITE_STRICT->value
        );

        $this->helper = new HaliteCryptoHelper($configuration);
    }

    public function test_encrypt_decrypt_with_authentication(): void
    {
        $cookie = $this->createCookieValue();
        $data = $this->helper->encrypt($cookie);
        $cookieDecrypted = $this->helper->decrypt($data);

        self::assertEquals($cookie, $cookieDecrypted);
    }

    public function test_encrypt_decrypt_with_authentication_decryption_impossible_if_tampered_with(): void
    {
        $cookie = $this->createCookieValue();
        $data = $this->helper->encrypt($cookie);
        $data = substr($data, 1, strlen($data));
        $this->expectException(DecryptionFailedException::class);
        $this->helper->decrypt($data);
    }

    private function createCookieValue(): CookieValue
    {
        return CookieValue::from('abc12345');
    }
}
