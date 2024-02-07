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

namespace Surfnet\Tiqr\Tiqr;

use Psr\Log\LoggerInterface;
use Surfnet\Tiqr\Tiqr\Legacy\TiqrService;
use Surfnet\Tiqr\Tiqr\Legacy\TiqrUserRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Tiqr_Service;
use Tiqr_StateStorage;
use Tiqr_UserSecretStorage;
use Tiqr_UserStorage;

class TiqrFactory
{
    // Created from services.yaml
    public static function createService(
        TiqrConfigurationInterface $configuration,
        RequestStack $requestStack,
        LoggerInterface $logger,
        string $appSecret
    ): TiqrService {
        $options = $configuration->getTiqrOptions();

        $storageType = "file";
        $storageOptions = [];

        if (isset($options["statestorage"])) {
            $storageType = $options["statestorage"]["type"];
            $storageOptions = $options["statestorage"];
        }

        return new TiqrService(
            new Tiqr_Service($logger, $options),
            Tiqr_StateStorage::getStorage($storageType, $storageOptions, $logger),
            $requestStack,
            $logger,
            $appSecret,
            $options['name']
        );
    }

    // Created from services.yaml
    public static function createUserRepository(
        TiqrConfigurationInterface $configuration,
        LoggerInterface $logger
    ): TiqrUserRepository {
        $options = $configuration->getTiqrOptions();
        $userStorage = Tiqr_UserStorage::getStorage(
            $options['userstorage']['type'],
            $options['userstorage'],
            $logger
        );

        $userSecretStorage = Tiqr_UserSecretStorage::getSecretStorage(
            $options['usersecretstorage']['type'],
            $logger,
            $options['usersecretstorage']
        );
        return new TiqrUserRepository($userStorage, $userSecretStorage);
    }
}
