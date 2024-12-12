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

namespace Unit\Service\TrustedDevice;

use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Surfnet\Tiqr\Service\TrustedDevice\Crypto\HaliteCryptoHelper;
use Surfnet\Tiqr\Service\TrustedDevice\DateTime\ExpirationHelper;
use Surfnet\Tiqr\Service\TrustedDevice\Http\CookieHelper;
use Surfnet\Tiqr\Service\TrustedDevice\Http\CookieSameSite;
use Surfnet\Tiqr\Service\TrustedDevice\TrustedDeviceService;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\CookieValue;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration test
 */
class TrustedDeviceServiceTest extends TestCase
{
    private TrustedDeviceService $service;
    private Configuration $configuration;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
        parent::setUp();
    }

    protected function buildService(Configuration $configuration, DateTime $now = null): void
    {
        $this->configuration = $configuration;
        $encryptionHelper = new HaliteCryptoHelper($configuration);
        $expirationHelper = new ExpirationHelper($this->configuration->lifetimeInSeconds, 1, $now);
        $cookieHelper = new CookieHelper($this->configuration, $encryptionHelper, $this->logger);
        $this->service = new TrustedDeviceService(
            $cookieHelper,
            $expirationHelper,
            $this->logger
        );
    }

    public function test_storing_a_persistent_cookie(): void
    {
        $this->buildService(
            new Configuration(
                'tiqr-trusted-device-cookie_',
                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value,
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);

        $this->service->registerTrustedDevice($response, 'userId#1', '01011001');

        $cookieJar = $response->headers->getCookies();
        self::assertCount(1, $cookieJar);
        $cookie = reset($cookieJar);
        // The name and lifetime of the cookie should match the one we configured it to be
        self::assertEquals($this->configuration->prefix . hash('sha256', 'userId#1_'.'01011001'), $cookie->getName());
        self::assertEquals(time() + $this->configuration->lifetimeInSeconds, $cookie->getExpiresTime());
        // By default, we set same-site header to none
        self::assertEquals(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    /**
     * @dataProvider identityMismatchProvider
     */
    public function test_untrusted_when_id_doesnt_match(string $userId, string $notificationAddress): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',
                2592000,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value
            )
        );

        $cookieValue = CookieValue::from('userId#123', 'notAddr#321');
        self::assertFalse(
            $this->service->isTrustedDevice(
                $cookieValue,
                $userId,
                $notificationAddress
            )
        );
    }

    public function identityMismatchProvider(): array
    {
        return [
            'userId wrong' => ['userId#124', 'notAddr#321'],
            'notAdr wrong' => ['userId#123', 'notAddr#322'],
            'both wrong' => ['userId#124', 'notAddr#322'],
        ];
    }

    public function test_is_trusted_when_identity_matches(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',
                2592000,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value
            ),
            new DateTime('+2592001 seconds') // lifetime + 1 second grace
        );

        $cookieValue = CookieValue::from('userId#123', 'notAddr#321');
        self::assertTrue(
            $this->service->isTrustedDevice(
                $cookieValue,
                'userId#123',
                'notAddr#321',
            )
        );
    }

    public function test_is_untrusted_when_token_expired(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',
                2592000,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value
            ), new DateTime('+2592002 seconds') // lifetime + 1 second grace + 1
        );

        $cookieValue = CookieValue::from('userId#123', 'notAddr#321');
        self::assertFalse(
            $this->service->isTrustedDevice(
                $cookieValue,
                'userId#123',
                'notAddr#321',
            )
        );
    }

    public function test_read_write_cookie(): void
    {
        $this->buildService(
            new Configuration(
                'tiqr-trusted-device-cookie_',
                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value,
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);

        $userId = 'userId#1';
        $notificationAddress = '01011001';

        $this->service->registerTrustedDevice($response, $userId, $notificationAddress);

        $cookieJar = $response->headers->getCookies();
        self::assertCount(1, $cookieJar);

        $request = new Request();
        foreach ($cookieJar as $cookie) {
            $request->cookies->set($cookie->getName(), $cookie->getValue());
        }

        $readCookie = $this->service->read($request, $userId, $notificationAddress);
        $this->assertTrue($this->service->isTrustedDevice($readCookie, $userId, $notificationAddress));
    }

    public function test_does_not_read_tampered_cookie(): void
    {
        $this->buildService(
            new Configuration(
                'tiqr-trusted-device-cookie_',
                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value,
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);

        $userId = 'userId#1';
        $notificationAddress = '01011001';

        $this->service->registerTrustedDevice($response, $userId, $notificationAddress);

        $cookieJar = $response->headers->getCookies();
        self::assertCount(1, $cookieJar);

        $request = new Request();
        foreach ($cookieJar as $cookie) {
            $request->cookies->set($cookie->getName(), $cookie->getValue() . '1');
        }

        $readCookie = $this->service->read($request, $userId, $notificationAddress);
        $this->assertNull($readCookie);
    }

    /**
     * This test is to make sure multiple users and users with multiple devices can use the same browser without issues
     */
    public function test_it_handles_all_valid_cookies_from_browser(): void
    {
        $this->buildService(
            new Configuration(
                'qki_',
                3600,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f',
                CookieSameSite::SAMESITE_STRICT->value,
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);

        $store = [
            [
                'userId' => 'userId#1',
                'notificationAddress' => '1',
            ],
            [
                'userId' => 'userId#1',
                'notificationAddress' => '2',
            ],
            [
                'userId' => 'userId#2',
                'notificationAddress' => '1',
            ],
            [
                'userId' => 'userId#3',
                'notificationAddress' => '1',
            ],
            [
                'userId' => 'userId#1',
                'notificationAddress' => '3',
            ],
            [
                'userId' => 'userId#1',
                'notificationAddress' => '1',
            ],
        ];

        foreach ($store as $storedDevice) {
            $this->service->registerTrustedDevice($response, $storedDevice['userId'], $storedDevice['notificationAddress']);
        }

        $cookieJar = $response->headers->getCookies();
        self::assertCount(5, $cookieJar);

        $request = new Request();
        foreach ($cookieJar as $cookie) {
            $request->cookies->set($cookie->getName(), $cookie->getValue());
        }

        shuffle($store);

        foreach ($store as $storedDevice){
            $readCookie = $this->service->read($request, $storedDevice['userId'], $storedDevice['notificationAddress']);
            $this->assertTrue($this->service->isTrustedDevice($readCookie, $storedDevice['userId'], $storedDevice['notificationAddress']));
        }
    }


}
