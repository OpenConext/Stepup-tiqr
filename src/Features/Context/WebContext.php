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

namespace Surfnet\Tiqr\Features\Context;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\MinkExtension\Context\MinkContext;
use Exception;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\AuthnRequest;
use SAML2\Certificate\PrivateKeyLoader;
use SAML2\Configuration\PrivateKey;
use SAML2\Constants;
use SAML2\XML\saml\Issuer;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Entity\StaticServiceProviderRepository;
use Surfnet\SamlBundle\Exception\NotFound;
use Surfnet\SamlBundle\SAML2\AuthnRequest as SamlAuthnRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class WebContext implements Context
{
    /**
     * @var MinkContext
     */
    protected $minkContext;

    /**
     * @var string
     */
    protected $previousMinkSession;

    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    /**
     * Fetch the required contexts.
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $environment = $scope->getEnvironment();
        $this->minkContext = $environment->getContext(MinkContext::class);
    }

    /**
     * Set mink driver to goutte
     *
     * @BeforeScenario @remote
     */
    public function setGoutteDriver(): void
    {
        $this->previousMinkSession = $this->minkContext->getMink()->getDefaultSessionName();
        $this->minkContext->getMink()->setDefaultSessionName('goutte');
    }

    /**
     * Set mink driver to goutte
     *
     * @AfterScenario @remote
     */
    public function resetGoutteDriver(): void
    {
        $this->minkContext->getMink()->setDefaultSessionName($this->previousMinkSession);
    }

    public function getIdentityProvider(): IdentityProvider
    {
        /** @var RequestStack $stack */
        $stack = $this->kernel->getContainer()->get('request_stack');
        $stack->push(Request::create('https://tiqr.stepup.example.com'));
        $identityProvider = $this->kernel->getContainer()->get('surfnet_saml.hosted.identity_provider');
        $stack->pop();

        if (!$identityProvider instanceof IdentityProvider) {
            throw new Exception('No Hosted Identity Provider could be found');
        }

        return $identityProvider;
    }

    /**
     * @throws NotFound
     */
    public function getServiceProvider(): ServiceProvider
    {
        /** @var StaticServiceProviderRepository $serviceProviders */
        $serviceProviders = $this->kernel->getContainer()->get('surfnet_saml.remote.service_providers');
        return $serviceProviders->getServiceProvider(
            'https://pieter.aai.surfnet.nl/simplesamlphp/module.php/saml/sp/metadata.php/default-sp'
        );
    }

    /**
     * @Given /^a normal SAML 2.0 AuthnRequest form a unknown service provider$/
     *
     * @throws Exception
     */
    public function aNormalSAMLAuthnRequestFormAUnknownServiceProvider(): void
    {
        $authnRequest = new AuthnRequest();
        $authnRequest->setAssertionConsumerServiceURL('https://service_provider_unkown/saml/acs');
        $authnRequest->setDestination($this->getIdentityProvider()->getSsoUrl());
        $issuer = new Issuer();
        $issuer->setValue('https://service_provider_unkown/saml/metadata');
        $authnRequest->setIssuer($issuer);
        $authnRequest->setProtocolBinding(Constants::BINDING_HTTP_REDIRECT);

        // Sign with random key, does not mather for now.
        $authnRequest->setSignatureKey(
            $this->loadPrivateKey($this->getIdentityProvider()->getPrivateKey(PrivateKey::NAME_DEFAULT))
        );

        $request = SamlAuthnRequest::createNew($authnRequest);
        $query = $request->buildRequestQuery();
        $this->minkContext->visitPath('/saml/sso?' . $query);
    }

    /**
     * @return XMLSecurityKey
     * @throws Exception
     */
    private function loadPrivateKey(PrivateKey $key): XMLSecurityKey
    {
        $keyLoader = new PrivateKeyLoader();
        $privateKey = $keyLoader->loadPrivateKey($key);

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey->getKeyAsString());

        return $key;
    }
}
