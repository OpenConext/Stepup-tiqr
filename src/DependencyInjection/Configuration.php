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

namespace Surfnet\Tiqr\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('app');
        $rootNode = $treeBuilder->getRootNode();
        $optionsNode = $rootNode->children()
            ->arrayNode('tiqr_library_options')
            ->isRequired();

        $optionsNode->children()
            ->append($this->createGeneralConfig())
            ->append($this->createLibraryConfig())
            ->append($this->createStorageConfig())
            ->append($this->createBlockingConfig())
        ->end();

        return $treeBuilder;
    }

    private function createGeneralConfig()
    {
        $parent = new ArrayNodeDefinition('general');
        return $parent
            ->isRequired()
            ->children()
            ->scalarNode('identifier')
                ->isRequired()
                ->info(<<<TEXT
    This is an identifier of your identity provider. This is typically a domainname and
    it's what the user sees if they enroll an account. If you're installing tiqr to enroll accounts
    at my.piggybank.com then my.piggybank.com would be a suitable identifier.'
TEXT
                )
            ->end()
            ->scalarNode('name')
                ->isRequired()
                ->info(' The name of your service, e.g. \'Piggybank\'')
            ->end()
            ->scalarNode('auth_protocol')
                ->isRequired()
                ->info(<<<TEXT
Every tiqr based mobile app is identified by a set of url specifiers. If you use the tiqr application
from the appstore, the value for this would be 'tiqrauth'. If you build your own iphone app and
that uses the url specifier 'piggyauth', then that's what you'd configure here.
It ties the identity provider to the mobile apps.
TEXT
                )
            ->end()
            ->scalarNode('enroll_protocol')
                ->isRequired()
                ->info(<<<TEXT
Similar to the previous entry but for enrollment. 'tiqrenroll' for the default tiqr app,
'piggyenroll' if that's what you used while compiling your own apps.
TEXT
                )
            ->end()
            ->scalarNode('ocra_suite')
                ->isRequired()
                ->info(<<<TEXT
The challenge response algorithm to use for authentication. Must be a valid OCRA suite value (see the OCRA spec).
Note that we don't support counter and time based input, so you can only use OCRA suites that do not contain
counter or time inputs. If you're confused by this setting, you can leave it to the default,
which results in the system using 10-digit hexadecimal challenges, a 6 digit numeric response,
and SHA1 as the hashing algorithm (OCRA-1:HOTP-SHA1-6:QH10-S).
TEXT
                )
            ->end()
            ->scalarNode('logoUrl')
                ->isRequired()
                ->info(<<<TEXT
An url that points to your logo.
The logo is automatically scaled down but to avoid high download times, try to stay under 250x250 resolution.
The logo will be displayed in the app during enrollment and authentication steps.
TEXT
            )
            ->end()
            ->scalarNode('infoUrl')
                ->isRequired()
                ->info(<<<TEXT
An url that contains a page with more information about your enrollment process.
If a user enrolls for your service, this page is where they'll go to for questions.
You can provide any url that you like but typically it's a page on your main company website.
TEXT
            )
            ->end()
            ->end();
    }

    private function createLibraryConfig()
    {
        $parent = new ArrayNodeDefinition('library');
        return $parent->isRequired()->children()
        ->arrayNode('apns')
            ->children()
                ->scalarNode('certificate')
                    ->isRequired()
                    ->info(<<<TEXT
 Your Apple push notification certificate.Note: if you use the tiqr app store app, you can't send push notifications,
 to use this feature you need your own apps and your own certificates.
TEXT
                    )
                ->end()
                ->scalarNode('environment')
                    ->isRequired()
                    ->info(<<<TEXT
Set to 'sandbox' if you're testing the push notifications, set to 'production' if you use the push notifications
in a production environment.
TEXT
                    )
                ->end()
            ->end()
        ->end()
        ->arrayNode('firebase')
            ->children()
                ->scalarNode('apikey')
                    ->info(<<<TEXT
        The API key you use for your Google Cloud Messaging (Firebase) account (android push notifications).
TEXT
                    )
                ->end()
            ->end()
        ->end()
        ->end();
    }

    private function createBlockingConfig()
    {
        $parent = new ArrayNodeDefinition('accountblocking');
        return $parent->isRequired()
            ->children()
                ->scalarNode('maxAttempts')
                    ->info(<<<TEXT
Maximum number of login attempts before a block is set, set to 0 for not using blocks at all.
TEXT
                    )
                ->end()
                ->scalarNode('temporarilyBlockDuration')
                    ->info(<<<TEXT
Duration of temporarily block in minutes, set to 0 for no blocks or permanent blocks only.
TEXT
                    )
                ->end()
                ->scalarNode('maxTemporarilyBlocks')
                    ->info(<<<TEXT
Defines the number of temporarily blocks before setting a permanent block,
set to anything other then 0 for using temporarily and permanent blocks.
TEXT
                    )
                ->end()
            ->end();
    }

    private function createStorageConfig()
    {
        $parent = new ArrayNodeDefinition('storage');
        return $parent->isRequired()
            ->children()
            ->arrayNode('statestorage')
                ->isRequired()
                ->info(<<<TEXT
This is the name of the storage class that you will be using to store temporarily session data.
The default is 'file' which stores the state information in the /tmp folder.
If you have memcache installed, you can use 'memcache' instead.
See the documentation inside the statestorage folder for
memcache or file based specific configuration options.
TEXT
                )
                ->children()
                    ->enumNode('type')
                        ->values(['file', 'memcache', 'pdo'])
                        ->isRequired()
                        ->info('Type of storage')
                    ->end()
                    ->variableNode('arguments')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('devicestorage')
                ->isRequired()
                ->info(<<<TEXT
Tiqr supports exchanging hardware based devicetokens
for more generic notification tokens.
This is only required if you use the push notifications.
You can use a tokenexchange server to handle the token swapping.
Set this to 'dummy' if you do want push notifications but do not want to use a token exchange
(not recommended).
TEXT
                )
                ->children()
                    ->enumNode('type')
                        ->values(['dummy', 'tokenexchange'])
                        ->isRequired()
                        ->info('Type of token exchange')
                    ->end()
                    ->variableNode('arguments')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('userstorage')
                ->isRequired()
                ->info(<<<TEXT
Tiqr must store user secrets and other details for a user.
By default this setting is set to 'file' which stores the data in
JSON files in the specifified directory. While this is great for testing purposes,
we recommend you implement your own user storage (e.g. your existing user database or an LDAP server).
To do this, have a look at the userstorage subdirectory in the authTiqr directory.
TEXT
                )
                ->children()
                    ->enumNode('type')
                        ->values(['file', 'ldap', 'pdo'])
                        ->isRequired()
                        ->info('Type of storage')
                    ->end()
                    ->variableNode('arguments')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('usersecretstorage')
                ->isRequired()
                ->info(<<<TEXT
Tiqr must store user secrets and other details for a user. By default secrets are stored together with other user data.
This setting can be user to store the secrets separately in a database or on a separate host.
TEXT
                )
                ->children()
                    ->enumNode('type')
                        ->values(['file', 'ldap', 'pdo', 'oathserviceclient'])
                        ->isRequired()
                        ->info('Type of storage')
                    ->end()
                    ->variableNode('arguments')
                        ->isRequired()
                    ->end()
                ->end()
            ->end()
            ->end();
    }
}
