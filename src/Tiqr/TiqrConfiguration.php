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

use Assert\Assertion;
use Surfnet\Tiqr\Tiqr\Exception\ConfigurationException;

class TiqrConfiguration implements TiqrConfigurationInterface
{

    /** @var array<string, mixed> */
    private $options = [];
    final public const TEMPORARILY_BLOCK_DURATION = 'temporarilyBlockDuration';
    final public const MAX_ATTEMPTS = 'maxAttempts';
    final public const MAX_TEMPORARILY_BLOCKS = 'maxTemporarilyBlocks';

    /**
     * @param array<string, array<string, mixed>> $tiqrConfiguration
     *
     * @throws \Assert\AssertionFailedException\
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function __construct(array $tiqrConfiguration)
    {
        Assertion::string(
            $tiqrConfiguration['general']['identifier'],
            'TiqrConfiguration: general -> identifier must be of type string'
        );
        $this->options['identifier'] = $tiqrConfiguration['general']['identifier'];

        Assertion::string(
            $tiqrConfiguration['general']['name'],
            'TiqrConfiguration: general -> name must be of type string'
        );
        $this->options['name'] = $tiqrConfiguration['general']['name'];

        Assertion::string(
            $tiqrConfiguration['general']['auth_protocol'],
            'TiqrConfiguration: general -> auth_protocol must be of type string'
        );
        $this->options['auth.protocol'] = $tiqrConfiguration['general']['auth_protocol'];

        Assertion::string(
            $tiqrConfiguration['general']['enroll_protocol'],
            'TiqrConfiguration: general -> enroll_protocol must be of type string'
        );
        $this->options['enroll.protocol'] = $tiqrConfiguration['general']['enroll_protocol'];

        Assertion::string(
            $tiqrConfiguration['general']['ocra_suite'],
            'TiqrConfiguration: general -> ocra_suite must be of type string'
        );
        $this->options['ocra.suite'] = $tiqrConfiguration['general']['ocra_suite'];

        Assertion::string(
            $tiqrConfiguration['general']['logoUrl'],
            'TiqrConfiguration: general -> logoUrl must be of type string'
        );
        $this->options['logoUrl'] = $tiqrConfiguration['general']['logoUrl'];

        Assertion::string(
            $tiqrConfiguration['general']['infoUrl'],
            'TiqrConfiguration: general -> infoUrl must be of type string'
        );
        $this->options['infoUrl'] = $tiqrConfiguration['general']['infoUrl'];

        if (isset($tiqrConfiguration['library']['apns'])) {
            Assertion::string(
                $tiqrConfiguration['library']['apns']['certificate'],
                'TiqrConfiguration: library -> apns -> certificate must be of type string'
            );
            $this->options['apns.certificate'] = $tiqrConfiguration['library']['apns']['certificate'];
            Assertion::string(
                $tiqrConfiguration['library']['apns']['environment'],
                'TiqrConfiguration: library -> apns -> environment must be of type string'
            );
            $this->options['apns.environment'] = $tiqrConfiguration['library']['apns']['environment'];
        }
        
        if (isset($tiqrConfiguration['library']['firebase']) && is_array($tiqrConfiguration['library']['firebase'])) {
            Assertion::string(
                $tiqrConfiguration['library']['firebase']['projectId'],
                'TiqrConfiguration: library -> firebase -> projectId must be of type string'
            );
            $this->options['firebase.projectId'] = $tiqrConfiguration['library']['firebase']['projectId'];
            Assertion::string(
                $tiqrConfiguration['library']['firebase']['credentialsFile'],
                'TiqrConfiguration: library -> firebase -> credentialsFile must be of type string'
            );
            $this->options['firebase.credentialsFile'] = $tiqrConfiguration['library']['firebase']['credentialsFile'];
            Assertion::boolean(
                $tiqrConfiguration['library']['firebase']['cacheTokens'],
                'TiqrConfiguration: library -> firebase ->  cacheTokens must be of type string'
            );
            $this->options['firebase.cacheTokens'] = $tiqrConfiguration['library']['firebase']['cacheTokens'];
            Assertion::string(
                $tiqrConfiguration['library']['firebase']['tokenCacheDir'],
                'TiqrConfiguration: library -> firebase ->  tokenCacheDir must be of type string'
            );
            $this->options['firebase.tokenCacheDir'] = $tiqrConfiguration['library']['firebase']['tokenCacheDir'];
        }

        if (isset($tiqrConfiguration['accountblocking'][self::MAX_ATTEMPTS])) {
            Assertion::digit(
                $tiqrConfiguration['accountblocking'][self::MAX_ATTEMPTS],
                'TiqrConfiguration: accountblocking -> maxAttempts must be of type digit'
            );
            Assertion::greaterThan(
                $tiqrConfiguration['accountblocking'][self::MAX_ATTEMPTS],
                0,
                'TiqrConfiguration: accountblocking -> maxAttempts must be greater that 0'
            );
            $this->options[self::MAX_ATTEMPTS] = $tiqrConfiguration['accountblocking'][self::MAX_ATTEMPTS];
        }

        if (isset($tiqrConfiguration['accountblocking'][self::TEMPORARILY_BLOCK_DURATION])) {
            Assertion::digit(
                $tiqrConfiguration['accountblocking'][self::TEMPORARILY_BLOCK_DURATION],
                'TiqrConfiguration: accountblocking -> temporarilyBlockDuration must be of type digit'
            );
            Assertion::greaterThan(
                $tiqrConfiguration['accountblocking'][self::TEMPORARILY_BLOCK_DURATION],
                0,
                'TiqrConfiguration: accountblocking -> temporarilyBlockDuration must be greater that 0'
            );
            $this->options[self::TEMPORARILY_BLOCK_DURATION] =
                $tiqrConfiguration['accountblocking'][self::TEMPORARILY_BLOCK_DURATION];
        }

        if (isset($tiqrConfiguration['accountblocking'][self::MAX_TEMPORARILY_BLOCKS])) {
            Assertion::digit(
                $tiqrConfiguration['accountblocking'][self::MAX_TEMPORARILY_BLOCKS],
                'TiqrConfiguration: accountblocking -> maxTemporarilyBlocks must be of type digit'
            );
            Assertion::greaterThan(
                $tiqrConfiguration['accountblocking'][self::MAX_TEMPORARILY_BLOCKS],
                0,
                'TiqrConfiguration: accountblocking -> maxTemporarilyBlocks must be greater that 0'
            );
            $this->options[self::MAX_TEMPORARILY_BLOCKS] = $tiqrConfiguration['accountblocking'][self::MAX_TEMPORARILY_BLOCKS];
        }

        $this->options['statestorage']['type'] = $tiqrConfiguration['storage']['statestorage']['type'];
        Assertion::isArray(
            $tiqrConfiguration['storage']['statestorage']['arguments'],
            'TiqrConfiguration: storage -> statestorage -> arguments must be of type array'
        );
        $this->options['statestorage'] += $tiqrConfiguration['storage']['statestorage']['arguments'];

        $this->options['userstorage']['type'] = $tiqrConfiguration['storage']['userstorage']['type'];
        Assertion::isArray(
            $tiqrConfiguration['storage']['userstorage']['arguments'],
            'TiqrConfiguration: storage -> userstorage -> arguments must be of type array'
        );
        $this->options['userstorage'] += $tiqrConfiguration['storage']['userstorage']['arguments'];

        $this->options['devicestorage']['type'] = $tiqrConfiguration['storage']['devicestorage']['type'];
        Assertion::isArray(
            $tiqrConfiguration['storage']['devicestorage']['arguments'],
            'TiqrConfiguration: storage -> devicestorage -> arguments must be of type array'
        );
        $this->options['devicestorage'] += $tiqrConfiguration['storage']['devicestorage']['arguments'];

        if (isset($tiqrConfiguration['storage']['usersecretstorage'])) {
            $this->options['usersecretstorage']['type'] = $tiqrConfiguration['storage']['usersecretstorage']['type'];
            Assertion::isArray(
                $tiqrConfiguration['storage']['usersecretstorage']['arguments'],
                'TiqrConfiguration: storage -> usersecretstorage -> arguments must be of type array'
            );
            $this->options['usersecretstorage'] += $tiqrConfiguration['storage']['usersecretstorage']['arguments'];
        }
    }

    /**
     * Please don't use this to get individual options.
     * @return array<string, array<string, mixed>>
     */
    public function getTiqrOptions(): array
    {
        return $this->options;
    }

    /**
     * @return boolean TRUE if there is a maximum block duration.
     */
    public function temporarilyBlockEnabled(): bool
    {
        return isset($this->options[self::TEMPORARILY_BLOCK_DURATION]);
    }

    /**
     * @return int The maximum block duration in minutes.
     *
     * @throws ConfigurationException
     */
    public function getTemporarilyBlockDuration(): int
    {
        if (!$this->temporarilyBlockEnabled()) {
            throw ConfigurationException::noMaximumDuration();
        }
        return $this->options[self::TEMPORARILY_BLOCK_DURATION];
    }

    /**
     * @throws ConfigurationException
     */
    public function getMaxAttempts(): int
    {
        if (!$this->hasMaxLoginAttempts()) {
            throw ConfigurationException::noMaxAttempts();
        }
        return $this->options[self::MAX_ATTEMPTS];
    }

    public function hasMaxLoginAttempts(): bool
    {
        return isset($this->options[self::MAX_ATTEMPTS]);
    }

    public function setMaxLoginAttempts(int $attempts): void
    {
        $this->options[self::MAX_ATTEMPTS] = $attempts;
    }

    /**
     * @throws ConfigurationException
     */
    public function getMaxTemporarilyLoginAttempts(): int
    {
        if (!$this->hasMaxTemporarilyLoginAttempts()) {
            throw ConfigurationException::noMaxTemporarilyAttempts();
        }
        return $this->options[self::MAX_TEMPORARILY_BLOCKS];
    }

    public function hasMaxTemporarilyLoginAttempts(): bool
    {
        return isset($this->options[self::MAX_TEMPORARILY_BLOCKS]);
    }
}
