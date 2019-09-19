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

namespace Dev\Twig;

use Surfnet\SamlBundle\Entity\HostedEntities;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GsspExtension extends AbstractExtension
{
    private $hostedEntities;

    public function __construct(HostedEntities $hostedEntities)
    {
        $this->hostedEntities = $hostedEntities;
    }

    public function getFunctions()
    {
        return array(
            new TwigFunction('demoSpUrl', array($this, 'generateDemoSPUrl')),
        );
    }

    public function generateDemoSPUrl()
    {
        return sprintf(
            'https://pieter.aai.surfnet.nl/simplesamlphp/sp.php?idp=%s',
            urlencode($this->hostedEntities->getIdentityProvider()->getEntityId())
        );
    }
}
