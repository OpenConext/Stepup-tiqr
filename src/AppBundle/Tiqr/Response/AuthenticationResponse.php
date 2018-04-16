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

namespace AppBundle\Tiqr\Response;

interface AuthenticationResponse
{
    /**
     * If the authentication is valid.
     *
     * @return boolean
     */
    public function isValid();

    /**
     * The success or error message for the client app.
     *
     * !!! keep in mind the client is depended on these response messages. (not something obvious like status codes)
     *
     * @return string
     */
    public function getMessage();
}
