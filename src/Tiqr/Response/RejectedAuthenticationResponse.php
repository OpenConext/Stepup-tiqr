<?php
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

namespace App\Tiqr\Response;

/**
 * Response when the user has given a wrong password.
 */
class RejectedAuthenticationResponse implements AuthenticationResponse
{
    public function __construct(private readonly string $error)
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
     * The success or error message for the client app.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->error;
    }
}
