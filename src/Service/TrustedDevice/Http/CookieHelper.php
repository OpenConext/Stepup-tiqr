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

namespace Surfnet\Tiqr\Service\TrustedDevice\Http;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Service\TrustedDevice\Crypto\CryptoHelperInterface;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\CookieNotFoundException;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\CookieValueInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CookieHelper implements CookieHelperInterface
{

    public function __construct(
        private readonly Configuration $configuration,
        private readonly CryptoHelperInterface $encryptionHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function write(Response $response, CookieValueInterface $value): void
    {
        $cookieName = $this->buildCookieName($value->getUserId(), $value->getNotificationAddress());

        // The CookieValue is encrypted
        $encryptedCookieValue = $this->encryptionHelper->encrypt($value);
        $fingerprint = $this->hashFingerprint($encryptedCookieValue);
        $this->logger->notice(sprintf('Writing a trusted-device cookie with fingerprint %s', $fingerprint));
        // Create a Symfony HttpFoundation cookie object
        $cookie = $this->createCookieWithValue($encryptedCookieValue, $cookieName);
        // Which is added to the response headers
        $response->headers->setCookie($cookie);
    }

    /**
     * Retrieve the current cookie from the Request if it exists.
     */
    public function read(Request $request, string $userId, string $notificationAddress): CookieValueInterface
    {
        $cookieName = $this->buildCookieName($userId, $notificationAddress);
        if (!$request->cookies->has($cookieName)) {
            throw new CookieNotFoundException();
        }
        $cookie = $request->cookies->get($cookieName);
        if (!is_string($cookie)) {
            throw new InvalidArgumentException('Cookie payload must be string.');
        }
        $fingerprint = $this->hashFingerprint($cookie);
        $this->logger->notice(sprintf('Reading a trusted-device cookie with fingerprint %s', $fingerprint));
        return $this->encryptionHelper->decrypt($cookie);
    }

    public function fingerprint(Request $request, string $userId, string $notificationAddress): string
    {
        $cookieName = $this->buildCookieName($userId, $notificationAddress);
        if (!$request->cookies->has($cookieName)) {
            throw new CookieNotFoundException();
        }
        $cookie = $request->cookies->get($cookieName);
        if (!is_string($cookie)) {
            throw new InvalidArgumentException('Cookie payload must be string.');
        }
        return $this->hashFingerprint($cookie);
    }

    private function createCookieWithValue(string $value, string $name): Cookie
    {
        return new Cookie(
            $name,
            $value,
            $this->getTimestamp($this->configuration->lifetimeInSeconds),
            '/',
            null,
            true,
            true,
            false,
            $this->configuration->sameSite->value
        );
    }

    private function hashFingerprint(string $encryptedCookieValue): string
    {
        return hash('sha256', $encryptedCookieValue);
    }

    private function getTimestamp(int $expiresInSeconds): int
    {
        $currentTimestamp = time();
        return $currentTimestamp + $expiresInSeconds;
    }

    public function buildCookieName(string $userId, string $notificationAddress): string
    {
        return $this->configuration->prefix . hash('sha256', $userId . '_' . $notificationAddress);
    }
}
