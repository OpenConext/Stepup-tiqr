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

namespace AppBundle\Tiqr;

use AppBundle\Tiqr\Legacy\TiqrService;
use AppBundle\Tiqr\Legacy\TiqrUserRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Tiqr_Service;
use Tiqr_UserStorage;

class TiqrFactory
{
    private $configuration;
    private $container;
    private $session;
    private $loaded = false;

    public function __construct(
        TiqrConfigurationInterface $configuration,
        ContainerInterface $container,
        SessionInterface $session
    ) {
        $this->configuration = $configuration;
        $this->container = $container;
        $this->session = $session;
    }

    public function createService()
    {
        $this->loadDependencies();
        $options = $this->configuration->getTiqrOptions();
        return new TiqrService(new Tiqr_Service($options), $this->session);
    }

    public function createUserRepository()
    {
        $this->loadDependencies();
        $options = $this->configuration->getTiqrOptions();
        $userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);
        return new TiqrUserRepository($userStorage);
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
