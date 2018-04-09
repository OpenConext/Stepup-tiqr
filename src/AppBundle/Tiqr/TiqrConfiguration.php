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

use AppBundle\Tiqr\Exception\ConfigurationException;
use Assert\Assertion;

class TiqrConfiguration implements TiqrConfigurationInterface
{

    private $options = [];
    const TEMPORARY_BLOCK_DURATION = 'temporaryBlockDuration';
    const MAX_ATTEMPTS = 'maxAttempts';
    const MAX_TEMPORARY_BLOCKS = 'maxTemporaryBlocks';

    /**
     * @param array[] $configuration
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct($configuration)
    {
        Assertion::string($configuration['general']['identifier']);
        $this->options['identifier'] = $configuration['general']['identifier'];

        Assertion::string($configuration['general']['name']);
        $this->options['name'] = $configuration['general']['name'];

        Assertion::string($configuration['general']['auth_protocol']);
        $this->options['auth.protocol'] = $configuration['general']['auth_protocol'];

        Assertion::string($configuration['general']['enroll_protocol']);
        $this->options['enroll.protocol'] = $configuration['general']['enroll_protocol'];

        Assertion::string($configuration['general']['ocra_suite']);
        $this->options['ocra.suite'] = $configuration['general']['ocra_suite'];

        Assertion::string($configuration['general']['logoUrl']);
        $this->options['logoUrl'] = $configuration['general']['logoUrl'];

        Assertion::string($configuration['general']['infoUrl']);
        $this->options['infoUrl'] = $configuration['general']['infoUrl'];

        if (isset($configuration['library']['apns'])) {
            Assertion::string($configuration['library']['apns']['certificate']);
            $this->options['apns.certificate'] = $configuration['library']['apns']['certificate'];
            Assertion::string($configuration['library']['apns']['environment']);
            $this->options['apns.environment'] = $configuration['library']['apns']['environment'];
        }

        if (isset($configuration['library']['gcm'])) {
            Assertion::string($configuration['library']['gcm']['apikey']);
            $this->options['gcm.apikey'] = $configuration['library']['gcm']['apikey'];
            Assertion::string($configuration['library']['gcm']['environment']);
            $this->options['gcm.application'] = $configuration['library']['gcm']['application'];
        }

        if (isset($configuration['accountblocking'][self::MAX_ATTEMPTS])) {
            Assertion::digit($configuration['accountblocking'][self::MAX_ATTEMPTS]);
            $this->options[self::MAX_ATTEMPTS] = $configuration['accountblocking'][self::MAX_ATTEMPTS];
        }

        if (isset($configuration['accountblocking'][self::TEMPORARY_BLOCK_DURATION])) {
            Assertion::digit($configuration['accountblocking'][self::TEMPORARY_BLOCK_DURATION]);
            $this->options[self::TEMPORARY_BLOCK_DURATION] =
                $configuration['accountblocking'][self::TEMPORARY_BLOCK_DURATION];
        }

        if (isset($configuration['accountblocking'][self::MAX_TEMPORARY_BLOCKS])) {
            Assertion::digit($configuration['accountblocking'][self::MAX_TEMPORARY_BLOCKS]);
            $this->options[self::MAX_TEMPORARY_BLOCKS] = $configuration['accountblocking'][self::MAX_TEMPORARY_BLOCKS];
        }

        Assertion::choice($configuration['storage']['statestorage']['type'], ['file', 'memcache']);
        $this->options['statestorage']['type'] = $configuration['storage']['statestorage']['type'];
        Assertion::isArray($configuration['storage']['statestorage']['arguments']);
        $this->options['statestorage'] += $configuration['storage']['statestorage']['arguments'];

        Assertion::choice($configuration['storage']['userstorage']['type'], ['file', 'memcache']);
        $this->options['userstorage']['type'] = $configuration['storage']['userstorage']['type'];
        Assertion::isArray($configuration['storage']['userstorage']['arguments']);
        $this->options['userstorage'] += $configuration['storage']['userstorage']['arguments'];

        if (isset($configuration['storage']['usersecretstorage'])) {
            Assertion::choice($configuration['storage']['usersecretstorage']['type'], ['file', 'memcache']);
            $this->options['usersecretstorage']['type'] = $configuration['storage']['usersecretstorage']['type'];
            Assertion::isArray($configuration['storage']['usersecretstorage']['arguments']);
            $this->options['usersecretstorage'] += $configuration['storage']['usersecretstorage']['arguments'];
        }
    }

    /**
     * Please don't use this to get individual options.
     *
     * @return array
     */
    public function getTiqrOptions()
    {
        return $this->options;
    }

    /**
     * @return boolean
     *    TRUE if there is a maximum block duration.
     */
    public function temporaryBlockEnabled()
    {
        return isset($this->options[self::TEMPORARY_BLOCK_DURATION]);
    }

    /**
     * @return int
     *    The maximum block duration in minutes.
     *
     * @throws ConfigurationException
     */
    public function getTemporaryBlockDuration()
    {
        if (!$this->temporaryBlockEnabled()) {
            throw ConfigurationException::noMaximumDuration();
        }
        return $this->options[self::TEMPORARY_BLOCK_DURATION];
    }

    /**
     * @return int
     * @throws \AppBundle\Tiqr\Exception\ConfigurationException
     */
    public function getMaxAttempts()
    {
        if (!$this->hasMaxLoginAttempts()) {
            throw ConfigurationException::noMaxAttempts();
        }
        return $this->options[self::MAX_ATTEMPTS];
    }

    /**
     * @return boolean
     */
    public function hasMaxLoginAttempts()
    {
        return isset($this->options[self::MAX_ATTEMPTS]);
    }

    /**
     * @param int $attempts
     */
    public function setMaxLoginAttempts($attempts)
    {
        $this->options[self::MAX_ATTEMPTS] = $attempts;
    }

    /**
     * @return int
     *
     * @throws ConfigurationException
     */
    public function getMaxTemporaryLoginAttempts()
    {
        if (!$this->hasMaxTemporaryLoginAttempts()) {
            throw ConfigurationException::noMaxTemporaryAttempts();
        }
        return $this->options[self::MAX_TEMPORARY_BLOCKS];
    }

    /**
     *
     * @return bool
     */
    public function hasMaxTemporaryLoginAttempts()
    {
        return isset($this->options[self::MAX_TEMPORARY_BLOCKS]);
    }
}