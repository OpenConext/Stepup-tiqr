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

declare(strict_types = 1);

namespace Surfnet\Tiqr\Dev;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * !! This is not a safe http client, it ignores invalid SSL certificates. !!
 */
class HttpClientFactory
{
    public static function create(): HttpClientInterface
    {
        return HttpClient::create([
            'base_uri' => 'https://tiqr.stepup.example.com',
            'verify_peer' => false,
            'verify_host' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }
}
