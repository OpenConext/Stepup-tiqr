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

namespace Surfnet\Tiqr\Service\TrustedDevice\DateTime;

use DateTime as CoreDateTime;
use Surfnet\StepupBundle\DateTime\DateTime;
use Surfnet\Tiqr\Service\TrustedDevice\Exception\InvalidAuthenticationTimeException;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\CookieValue;
use TypeError;

class ExpirationHelper implements ExpirationHelperInterface
{
    /**
     * When null, 'now' is resolved on every isExpired() call. A fixed value is only
     * used for deterministic tests: capturing it in the constructor made the helper
     * (a container singleton) compare cookies against a stale timestamp, which
     * intermittently tripped the "authentication time is from the future" guard when
     * the cookie was written after the helper was instantiated.
     */
    public function __construct(
        private readonly Configuration $configuration,
        private readonly ?CoreDateTime $now = null
    ) {
    }

    public function isExpired(CookieValue $cookieValue): bool
    {
        $now = $this->now ?? DateTime::now();

        try {
            $authenticationTimestamp = $cookieValue->authenticationTime();
        } catch (TypeError $error) {
            throw new InvalidAuthenticationTimeException(
                'The authentication time contained a non-int value',
                0,
                $error
            );
        }

        if ($authenticationTimestamp < 0) {
            throw new InvalidAuthenticationTimeException(
                'The authentication time is from before the Unix timestamp epoch'
            );
        }

        if ($authenticationTimestamp > $now->getTimestamp()) {
            throw new InvalidAuthenticationTimeException(
                'The authentication time is from the future, which indicates the clock settings ' .
                'are incorrect, or the time in the cookie value was tampered with.'
            );
        }

        $expirationTimestamp = $authenticationTimestamp + $this->configuration->lifetimeInSeconds;
        $currentTimestamp = $now->getTimestamp();

        // Is the current time greater than the expiration time?
        return $currentTimestamp > $expirationTimestamp;
    }
}
