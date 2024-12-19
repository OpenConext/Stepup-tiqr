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

namespace Unit\Service\TrustedDevice\DateTime;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Surfnet\Tiqr\Service\TrustedDevice\DateTime\ExpirationHelper;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\InvalidAuthenticationTimeException;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\CookieValue;

class ExpirationHelperTest extends TestCase
{
    /**
     * @dataProvider expirationExpectations
     */
    public function test_is_expired(bool $isExpired, ExpirationHelper $helper, CookieValue $cookieValue): void
    {
        self::assertEquals($isExpired, $helper->isExpired($cookieValue));
    }

    /**
     * @dataProvider expirationValues
     */
    public function test_expiration_period(bool $isExpired, ExpirationHelper $helper, CookieValue $cookieValue): void
    {
        self::assertEquals($isExpired, $helper->isExpired($cookieValue));
    }

    public function expirationValues(): array
    {
        // Cookie lifetime 3600
        $helper = $this->makeExpirationHelper(3600, time());
        return [
            'within period' => [false, $helper, $this->makeCookieValue(time() - 3600)],
            'outside period' => [true, $helper, $this->makeCookieValue(time() - 3601)],
        ];
    }

    public function invalidTimeExpectations(): array
    {
        $goodOldHelper = $this->makeExpirationHelper(3600, time());
        return [
            'from the future' => [$goodOldHelper, $this->makeCookieValue(time() + 42)],
        ];
    }

    public function invalidTimeArgumentExpectations(): array
    {
        $goodOldHelper = $this->makeExpirationHelper(3600, time());
        return [
            'before epoch' => [$goodOldHelper, fn() => $this->makeCookieValue(-1)],
            'invalid time input 1' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime('aint-no-time')],
            'invalid time input 2' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime('9999-01-01')],
            'invalid time input 3' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime('0001-01-01')],
            'invalid time input 4' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime(-1.0)],
            'invalid time input 5' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime(2.999)],
            'invalid time input 6' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime(42)],
            'invalid time input 7' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime(true)],
            'invalid time input 8' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime(false)],
            'invalid time input 9' => [$goodOldHelper, fn() => $this->makeCookieValueUnrestrictedAuthTime(null)],
        ];
    }

    /**
     * @dataProvider invalidTimeExpectations
     */
    public function test_strange_authentication_time_values(ExpirationHelper $helper, CookieValue $cookieValue): void
    {
        $this->expectException(InvalidAuthenticationTimeException::class);
        $helper->isExpired($cookieValue);
    }

    /**
     * @dataProvider invalidTimeArgumentExpectations
     */
    public function test_strange_authentication_time_arguments(ExpirationHelper $helper, callable $callback): void
    {
        $this->expectException(InvalidArgumentException::class);
        $helper->isExpired($callback());
    }

    public function expirationExpectations(): array
    {
        return [
            'not expired' => [false, $this->makeExpirationHelper(3600, time()), $this->makeCookieValue(time())],
            'not expired but about to be' => [false, $this->makeExpirationHelper(3600, time() + 3600), $this->makeCookieValue(time())],
            'expired' => [true, $this->makeExpirationHelper(3600, time() + 3601), $this->makeCookieValue(time())],
            'expired more' => [true, $this->makeExpirationHelper(3600, time() + 36000), $this->makeCookieValue(time())],
        ];
    }

    private function makeExpirationHelper(int $expirationTime, int $now) : ExpirationHelper
    {
        $time = new \DateTime();
        $time->setTimestamp($now);

        $config = new Configuration(
            'trusted-device-cookie',
            $expirationTime,
            '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
            'none',
        );

        return new ExpirationHelper($config, $time);
    }

    private function makeCookieValue(int $authenticationTime) : CookieValue
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($authenticationTime);
        $data = [
            'userId' => 'userId',
            'notificationAddress' => 'notificationAddress',
            'authenticationTime' => $dateTime->format(DATE_ATOM),
        ];
        return CookieValue::deserialize(json_encode($data));
    }

    private function makeCookieValueUnrestrictedAuthTime($authenticationTime) : CookieValue
    {
        $data = [
            'userId' => 'userId',
            'notificationAddress' => 'notificationAddress',
            'authenticationTime' => $authenticationTime,
        ];
        return CookieValue::deserialize(json_encode($data));
    }
}
