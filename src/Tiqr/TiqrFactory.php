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

namespace App\Tiqr;

use App\Tiqr\Legacy\TiqrService;
use App\Tiqr\Legacy\TiqrUserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tiqr_Service;
use Tiqr_StateStorage;
use Tiqr_UserStorage;

class TiqrFactory
{
    private $configuration;
    private $container;
    private $session;
    private $logger;
    private $loaded = false;

    public function __construct(
        TiqrConfigurationInterface $configuration,
        ContainerInterface $container,
        SessionInterface $session,
        LoggerInterface $logger
    ) {
        $this->configuration = $configuration;
        $this->container = $container;
        $this->session = $session;
        $this->logger = $logger;
    }

    public function createService()
    {
        $this->loadDependencies();
        $options = $this->configuration->getTiqrOptions();

        $userStorageType = "file";
        $userStorageOptions = [];

        if (isset($options["statestorage"])) {
            $userStorageType = $options["statestorage"]["type"];
            $userStorageOptions = $options["statestorage"];
        }

        return new TiqrService(
            new Tiqr_Service($options),
            Tiqr_StateStorage::getStorage($userStorageType, $userStorageOptions),
            $this->session,
            $this->logger,
            $options['name']
        );
    }

    public function createUserRepository()
    {
        $this->loadDependencies();
        $options = $this->configuration->getTiqrOptions();
        $userStorage = Tiqr_UserStorage::getStorage(
            $options['userstorage']['type'],
            $options['userstorage']
        );
        $userSecretStorage = Tiqr_UserStorage::getStorage(
            $options['usersecretstorage']['type'],
            $options['usersecretstorage']
        );
        return new TiqrUserRepository($userStorage, $userSecretStorage);
    }

    private function loadDependencies()
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        $projectDirectory = $this->container->getParameter('kernel.project_dir');
        $vendorPath = $projectDirectory.'/vendor';
        require_once $vendorPath.'/tiqr/tiqr-server-libphp/library/tiqr/Tiqr/AutoLoader.php';
        $autoloader = \Tiqr_AutoLoader::getInstance([
            'tiqr.path' => $vendorPath.'/tiqr/tiqr-server-libphp/library/tiqr',
            'zend.path' => $vendorPath.'/zendframework/zendframework1/library',
            'phpqrcode.path' => $vendorPath.'/kairos/phpqrcode',
        ]);
        $autoloader->setIncludePath();
    }
}
