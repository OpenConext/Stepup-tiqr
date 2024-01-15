<?php

declare(strict_types = 1);

/**
 * Copyright 2018 SURFnet B.V.
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

namespace Surfnet\Tiqr\Tiqr\Response;

/**
 *
 */
final readonly class RateLimitedAuthenticationResponse implements AuthenticationResponse
{
    public function __construct(private AuthenticationResponse $response, private int $attemptsLeft)
    {
    }

    /**
     * If the authentication is valid.
     *
     * @return boolean
     */
    public function isValid(): bool
    {
        return false;
    }

    /**
     * Return attempts left.
     *
     * @return int
     */
    public function getAttemptsLeft(): int
    {
        return $this->attemptsLeft;
    }

    /**
     * The success or error message for the client app.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->response->getMessage() . ':'  . $this->attemptsLeft;
    }
}
