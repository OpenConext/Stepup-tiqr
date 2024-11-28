<?php declare(strict_types=1);

/**
 * Copyright 2022 SURFnet bv
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

namespace Unit\Service\TrustedCookie;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Surfnet\GsspBundle\Saml\ResponseContext;
use Surfnet\Tiqr\Service\TrustedCookie\Crypto\HaliteCryptoHelper;
use Surfnet\Tiqr\Service\TrustedCookie\DateTime\ExpirationHelperInterface;
use Surfnet\Tiqr\Service\TrustedCookie\Http\CookieHelper;
use Surfnet\Tiqr\Service\TrustedCookie\TrustedCookieService;
use Surfnet\Tiqr\Service\TrustedCookie\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedCookie\ValueObject\CookieValue;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration test
 */
class TrustedCookieServiceTest extends TestCase
{
    private TrustedCookieService $service;

    /**
     * @var Configuration
     */
    private $configuration;
    private ResponseContext $responseContext;
    private HaliteCryptoHelper $encryptionHelper;

    private ExpirationHelperInterface|MockObject $expirationHelper;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->markTestSkipped('todo cleanup and add relevant tests');
        $this->logger = new NullLogger();
        parent::setUp();
    }

    protected function buildService(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->encryptionHelper = new HaliteCryptoHelper($configuration);
        $this->expirationHelper = $this->createMock(ExpirationHelperInterface::class);
        $cookieHelper = new CookieHelper($this->configuration, $this->encryptionHelper, $this->logger);
        $this->service = new TrustedCookieService(
            $cookieHelper,
            $this->expirationHelper,
            $this->logger
        );

        $this->responseContext = $this->createConfiguredMock(ResponseContext::class, ['isForceAuthn' => false]);
    }

    public function test_storing_a_session_cookie(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',

                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);
        $request = Mockery::mock(Request::class);
        $sfMock = Mockery::mock(SecondFactor::class)->makePartial();
        $sfMock->secondFactorId = 'sf-id-1234';
        $sfMock->institution = 'institution-a';
        $sfMock->secondFactorType = 'sms';
        $sfMock->identityVetted = true;

        $this->responseContext
            ->shouldReceive('getSelectedSecondFactor')
            ->andReturn('sf-id-1234');
        $this->responseContext
            ->shouldReceive('finalizeAuthentication');
        $this->secondFactorService
            ->shouldReceive('findByUuid')
            ->with('sf-id-1234')
            ->andReturn($sfMock);
        $this->institutionService
            ->shouldReceive('ssoOn2faEnabled')
            ->with('institution-a')
            ->andReturn(true);
        $this->responseContext
            ->shouldReceive('getRequiredLoa')
            ->andReturn('example.org:loa-2.0');
        $this->responseContext
            ->shouldReceive('getIdentityNameId')
            ->andReturn('james-hoffman');
        $this->secondFactorTypeService
            ->shouldReceive('getLevel')
            ->andReturn(2.0);
        $this->responseContext
            ->shouldReceive('isVerifiedBySsoOn2faCookie')
            ->andReturn(false);

        $response = $this->service->handleSsoOn2faCookieStorage($this->responseContext, $request, $response);

        $cookieJar = $response->headers->getCookies();
        self::assertCount(1, $cookieJar);
        $cookie = reset($cookieJar);
        // The name and lifetime of the cookie should match the one we configured it to be
        self::assertEquals($this->configuration->getName(), $cookie->getName());
        self::assertEquals($this->configuration->getLifetimeInSeconds(), $cookie->getExpiresTime());
        // By default we set same-site header to none
        self::assertEquals(Cookie::SAMESITE_NONE, $cookie->getSameSite());
    }

    public function test_storing_a_persistent_cookie(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',
                3600,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);
        $request = Mockery::mock(Request::class);

        $sfMock = Mockery::mock(SecondFactor::class)->makePartial();
        $sfMock->secondFactorId = 'sf-id-1234';
        $sfMock->institution = 'institution-a';
        $sfMock->secondFactorType = 'yubikey';
        $sfMock->identityVetted = true;

        $this->responseContext
            ->shouldReceive('getSelectedSecondFactor')
            ->andReturn('sf-id-1234');
        $this->responseContext
            ->shouldReceive('finalizeAuthentication');
        $this->secondFactorService
            ->shouldReceive('findByUuid')
            ->with('sf-id-1234')
            ->andReturn($sfMock);
        $this->institutionService
            ->shouldReceive('ssoOn2faEnabled')
            ->with('institution-a')
            ->andReturn(true);
        $this->responseContext
            ->shouldReceive('getRequiredLoa')
            ->andReturn('example.org:loa-2.0');
        $this->responseContext
            ->shouldReceive('getIdentityNameId')
            ->andReturn('james-hoffman');
        $this->secondFactorTypeService
            ->shouldReceive('getLevel')
            ->andReturn(3.0);
        $this->responseContext
            ->shouldReceive('isVerifiedBySsoOn2faCookie')
            ->andReturn(false);

        $response = $this->service->handleSsoOn2faCookieStorage($this->responseContext, $request, $response);

        $cookieJar = $response->headers->getCookies();
        self::assertCount(1, $cookieJar);
        $cookie = reset($cookieJar);
        // The name and lifetime of the cookie should match the one we configured it to be
        self::assertEquals($this->configuration->getName(), $cookie->getName());
        self::assertEquals(time() + $this->configuration->getLifetimeInSeconds(), $cookie->getExpiresTime());
    }

    public function test_storing_a_session_cookie_new_authentication(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',

                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );
        $response = new Response('<html lang="en"><body><h1>hi</h1></body></html>', 200);
        $request = Mockery::mock(Request::class);
        $sfMock = Mockery::mock(SecondFactor::class)->makePartial();
        $sfMock->secondFactorId = 'sf-id-1234';
        $sfMock->institution = 'institution-a';
        $sfMock->identityId = 'james-hoffman';
        $sfMock->secondFactorType = 'sms';
        $sfMock->identityVetted = true;

        $this->responseContext
            ->shouldReceive('getSelectedSecondFactor')
            ->andReturn('sf-id-1234');
        $this->responseContext
            ->shouldReceive('finalizeAuthentication');
        $this->secondFactorService
            ->shouldReceive('findByUuid')
            ->with('sf-id-1234')
            ->andReturn($sfMock);
        $this->institutionService
            ->shouldReceive('ssoOn2faEnabled')
            ->with('institution-a')
            ->andReturn(true);
        $this->responseContext
            ->shouldReceive('getRequiredLoa')
            ->andReturn('example.org:loa-2.0');
        $this->responseContext
            ->shouldReceive('getIdentityNameId')
            ->andReturn('james-hoffman');
        $this->secondFactorTypeService
            ->shouldReceive('getLevel')
            ->andReturn(1.5);
        $this->responseContext
            ->shouldReceive('isVerifiedBySsoOn2faCookie')
            ->andReturn(false);

        $response = $this->service->handleSsoOn2faCookieStorage($this->responseContext, $request, $response);

        $cookieJar = $response->headers->getCookies();
        self::assertCount(1, $cookieJar);
        $cookie = reset($cookieJar);
        // The name and lifetime of the cookie should match the one we configured it to be
        self::assertEquals($this->configuration->getName(), $cookie->getName());
        self::assertEquals($this->configuration->getLifetimeInSeconds(), $cookie->getExpiresTime());
    }

    public function test_storing_a_session_cookie_second_factor_not_found(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',

                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );
        $response = new Response('<html><body><h1>hi</h1></body></html>', 200);
        $request = Mockery::mock(Request::class);

        $this->responseContext
            ->shouldReceive('getSelectedSecondFactor')
            ->andReturn('non-existant');
        $this->responseContext
            ->shouldReceive('finalizeAuthentication');
        $this->secondFactorService
            ->shouldReceive('findByUuid')
            ->with('non-existant')
            ->andReturnNull();

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Second Factor token not found with ID: non-existant');
        $this->service->handleSsoOn2faCookieStorage($this->responseContext, $request, $response);
    }

    public function test_skipping_authentication_fails_when_no_sso_cookie_has_too_low_of_a_loa(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',

                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );
        $cookieValue = $this->cookieValue();

        $this->logger
            ->shouldReceive('notice')
            ->with('The required LoA 4 did not match the LoA of the SSO cookie LoA 3');

        self::assertFalse(
            $this->service->maySkipAuthentication(
                4.0, // LoA required by SP is 4.0, the one in the cookie is 3.0
                'abcdef-1234',
                $cookieValue
            )
        );
    }

    public function test_skipping_authentication_fails_when_identity_id_doesnt_match(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',

                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );
        $cookieValue = $this->cookieValue();

        $this->logger
            ->shouldReceive('notice')
            ->with('The SSO on 2FA cookie was not issued to Jane Doe, but to ident-1234');

        self::assertFalse(
            $this->service->maySkipAuthentication(
                2.0,
                'Jane Doe', // Not issued to Jane Doe but to abcdef-1234
                $cookieValue
            )
        );
    }

    public function test_skipping_authentication_fails_when_token_expired(): void
    {
        $this->buildService(
            new Configuration(
                'test-cookie',
                60,
                '0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f'
            )
        );

        $cookieValue = $this->cookieValue();

        $this->expirationHelper
            ->shouldReceive('isExpired')
            ->andReturn(true);

        $this->logger
            ->shouldReceive('notice')
            ->with('The SSO on 2FA cookie has expired. Meaning [authentication time] + [cookie lifetime] is in the past');

        self::assertFalse(
            $this->service->maySkipAuthentication(
                3.0,
                'ident-1234',
                $cookieValue
            )
        );
    }

    private function cookieValue(): CookieValue
    {
        return CookieValue::from('1', '2');
    }
}
