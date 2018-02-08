<?php
/**
 * Copyright 2017 SURFnet B.V.
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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Tiqr_Service;
use Tiqr_UserStorage;

class TiqrFactory
{
    private $configuration;
    private $container;

    public function __construct(
        TiqrConfiguration $configuration,
        ContainerInterface $container
    ) {
        $this->configuration = $configuration;
        $this->container = $container;
    }

    public function create()
    {
        $this->loadDependencies();
        $options = $this->configuration->getTiqrOptions();
        $userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);
        return new TiqrService(new Tiqr_Service($options), $userStorage);
    }

    private function loadDependencies()
    {
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
