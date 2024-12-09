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

namespace Surfnet\Tiqr\Service\TrustedCookie;

use Exception;
use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Service\TrustedCookie\DateTime\ExpirationHelperInterface;
use Surfnet\Tiqr\Service\TrustedCookie\Exception\CookieNotFoundException;
use Surfnet\Tiqr\Service\TrustedCookie\Exception\DecryptionFailedException;
use Surfnet\Tiqr\Service\TrustedCookie\Exception\InvalidAuthenticationTimeException;
use Surfnet\Tiqr\Service\TrustedCookie\Http\CookieHelperInterface;
use Surfnet\Tiqr\Service\TrustedCookie\ValueObject\CookieValue;
use Surfnet\Tiqr\Service\TrustedCookie\ValueObject\CookieValueInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) | Coupling is high as we are integrating logic into the infrastructure
 */
class TrustedCookieService
{
    public function __construct(
        private readonly CookieHelperInterface $cookieHelper,
        private readonly ExpirationHelperInterface $expirationHelper,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function registerTrustedAuthentication(Response $response, string $userId, string $notificationAddress): void
    {
        $this->store($response, CookieValue::from($userId, $notificationAddress));
    }

    public function isTrustedDevice(
        CookieValueInterface $cookie,
        string $userId,
        string $notificationAddress,
    ): bool {

        // Perform validation on the cookie and its contents
        if (!$this->isCookieValid($cookie, $userId, $notificationAddress)) {
            return false;
        }

        $this->logger->notice('Push-notification MFA is allowed for the device based on the cookie.');
        return true;
    }


    private function store(Response $response, CookieValueInterface $cookieValue): void
    {
        $this->cookieHelper->write($response, $cookieValue);
    }

    public function read(Request $request, string $userId, string $notificationAddress): ?CookieValueInterface
    {
        try {
            return $this->cookieHelper->read($request, $userId, $notificationAddress);
        } catch (CookieNotFoundException $e) {
            $this->logger->notice('A trusted-device cookie is not found');
            return null;
        } catch (DecryptionFailedException $e) {
            $this->logger->notice('Decryption of the trusted-device cookie failed');
            return null;
        } catch (Exception $e) {
            $this->logger->notice(
                'Decryption failed, see original message in context',
                ['original-exception-message' => $e->getMessage()]
            );
            return null;
        }
    }

    private function isCookieValid(CookieValueInterface $cookie, string $userId, string $notificationAddress): bool
    {
        if ($cookie instanceof CookieValue && ($cookie->getUserId() !== $userId || $cookie->getNotificationAddress() !== $notificationAddress)) {
            $this->logger->error(
                sprintf(
                    'This trusted-device cookie was not issued to %s,%s, but to %s,%s',
                    $userId,
                    $notificationAddress,
                    $cookie->getUserId(),
                    $cookie->getNotificationAddress(),
                )
            );
            return false;
        }
        try {
            $isExpired = $this->expirationHelper->isExpired($cookie);
            if ($isExpired) {
                $this->logger->notice(
                    'The trusted-device cookie has expired. Meaning [authentication time] + [cookie lifetime] is in the past'
                );
                return false;
            }
        } catch (InvalidAuthenticationTimeException $e) {
            $this->logger->error('The trusted-device cookie contained an invalid authentication time', [$e->getMessage()]);
            return false;
        }
        return true;
    }
}
