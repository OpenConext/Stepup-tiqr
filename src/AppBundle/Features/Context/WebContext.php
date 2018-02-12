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

namespace AppBundle\Features\Context;

use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Driver\GoutteDriver;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Behat\Context\Context;
use SAML2_AuthnRequest;
use SAML2_Certificate_PrivateKeyLoader;
use SAML2_Configuration_PrivateKey;
use SAML2_Const;
use Surfnet\SamlBundle\SAML2\AuthnRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use XMLSecurityKey;

class WebContext implements Context, KernelAwareContext
{
    /**
     * @var MinkContext
     */
    protected $minkContext;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var string
     */
    protected $previousMinkSession;

    /**
     * Sets HttpKernel instance.
     * This method will be automatically called by Symfony2Extension
     * ContextInitializer.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Fetch the required contexts.
     *
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();
        $this->minkContext = $environment->getContext(MinkContext::class);
    }

    /**
     * Set mink driver to goutte
     *
     * @BeforeScenario @remote
     */
    public function setGoutteDriver()
    {
        $this->previousMinkSession = $this->minkContext->getMink()->getDefaultSessionName();
        $this->minkContext->getMink()->setDefaultSessionName('goutte');
    }

    /**
     * Set mink driver to goutte
     *
     * @AfterScenario @remote
     */
    public function resetGoutteDriver()
    {
        $this->minkContext->getMink()->setDefaultSessionName($this->previousMinkSession);
    }

    /**
     * Create AuthnRequest from demo IdP.
     *
     * @When the service provider send the AuthnRequest with HTTP-Redirect binding
     *
     * @throws \Surfnet\SamlBundle\Exception\NotFound
     */
    public function callIdentityProviderSSOActionWithAuthnRequest()
    {
        $this->minkContext->visit('https://pieter.aai.surfnet.nl/simplesamlphp/sp.php?sp=default-sp');
        $this->minkContext->selectOption('idp', 'https://tiqr.example.com/app_dev.php/saml/metadata');
        $this->minkContext->pressButton('Login');
    }

    /**
     * @return \Surfnet\SamlBundle\Entity\IdentityProvider
     */
    public function getIdentityProvider()
    {
        /** @var RequestStack $stack */
        $stack = $this->kernel->getContainer()->get('request_stack');
        $stack->push(Request::create('https://tiqr.example.com'));
        $ip = $this->kernel->getContainer()->get('surfnet_saml.hosted.identity_provider');
        $stack->pop();

        return $ip;
    }

    /**
     * @return \Surfnet\SamlBundle\Entity\ServiceProvider
     *
     * @throws \Surfnet\SamlBundle\Exception\NotFound
     */
    public function getServiceProvider()
    {
        $serviceProviders = $this->kernel->getContainer()->get('surfnet_saml.remote.service_providers');
        return $serviceProviders->getServiceProvider(
            'https://pieter.aai.surfnet.nl/simplesamlphp/module.php/saml/sp/metadata.php/default-sp'
        );
    }

    /**
     * @Given /^a normal SAML 2.0 AuthnRequest form a unknown service provider$/
     *
     * @throws \Exception
     */
    public function aNormalSAMLAuthnRequestFormAUnknownServiceProvider()
    {
        $authnRequest = new SAML2_AuthnRequest();
        $authnRequest->setAssertionConsumerServiceURL('https://service_provider_unkown/saml/acs');
        $authnRequest->setDestination($this->getIdentityProvider()->getSsoUrl());
        $authnRequest->setIssuer('https://service_provider_unkown/saml/metadata');
        $authnRequest->setProtocolBinding(SAML2_Const::BINDING_HTTP_REDIRECT);

        // Sign with random key, does not mather for now.
        $authnRequest->setSignatureKey(
            $this->loadPrivateKey($this->getIdentityProvider()->getPrivateKey(SAML2_Configuration_PrivateKey::NAME_DEFAULT))
        );

        $request = AuthnRequest::createNew($authnRequest);
        $query = $request->buildRequestQuery();
        $this->minkContext->visitPath('/saml/sso?' . $query);
    }

    /**
     * @param SAML2_Configuration_PrivateKey $key
     * @return SAML2_Configuration_PrivateKey|XMLSecurityKey
     * @throws \Exception
     */
    private static function loadPrivateKey(SAML2_Configuration_PrivateKey $key)
    {
        $keyLoader = new SAML2_Certificate_PrivateKeyLoader();
        $privateKey = $keyLoader->loadPrivateKey($key);

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey->getKeyAsString());

        return $key;
    }
}
