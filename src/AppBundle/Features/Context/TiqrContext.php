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

use AppBundle\Tiqr\TiqrConfiguration;
use AppBundle\Tiqr\TiqrConfigurationInterface;
use AppBundle\Tiqr\TiqrUserRepositoryInterface;
use Assert\Assertion;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Behat\Context\Context;
use GuzzleHttp\Client;
use OCRA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * With this context Tiqr can be tested without an active Saml AuthnNRequest.
 */
class TiqrContext implements Context, KernelAwareContext
{
    /**
     * @var MinkContext
     */
    protected $minkContext;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    protected $metadataUrl;

    protected $clientSecret;

    /**
     * The registration metadata.
     * @var mixed
     */
    protected $metadata;

    protected $notificationType;
    protected $notificationAddress;

    /**
     * The scanned QR code with the '//tiqrauth' part.
     *
     * @var string
     */
    protected $authenticationUrl;

    /**
     * The authentication result.
     *
     * @var Response
     */
    protected $authenticatioResponse;

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
     * We are not actually scanning the QR code, but downloading link from:
     *
     * @see QrLinkController::qrRegistrationAction
     *
     * @Given the registration qr code is scanned
     *
     * @throws \Surfnet\SamlBundle\Exception\NotFound
     * @throws \Assert\AssertionFailedException
     */
    public function theRegistrationQrCodeIsScanned()
    {
        /** @var Client $client */
        $this->minkContext->visitPath('/registration/qr/link');
        // Should start with tiqrenroll://
        $content = $this->minkContext->getMink()->getSession()->getPage()->getText();
        Assertion::startsWith($content, 'tiqrenroll://');
        Assertion::eq(preg_match('/^tiqrenroll:\/\/(?P<url>.*)/', $content, $matches), 1);
        $this->metadataUrl = $matches['url'];
    }

    /**
     * We are not actually scanning the QR code, but downloading link from:
     *
     * @see QrLinkController::qrRegistrationAction
     *
     * @Given the authentication qr code is scanned
     *
     * @throws \Surfnet\SamlBundle\Exception\NotFound
     * @throws \Assert\AssertionFailedException
     */
    public function theAuthenticationQrCodeIsScanned()
    {
        /** @var Client $client */
        $this->minkContext->visitPath('/authentication/qr/'.urlencode($this->metadata->identity->identifier).'/link');
        // Should start with tiqrenroll://
        $content = $this->minkContext->getMink()->getSession()->getPage()->getText();
        Assertion::startsWith($content, 'tiqrauth://');
        Assertion::eq(preg_match('/^tiqrauth:\/\/(?P<url>.*)/', $content, $matches), 1);
        $this->authenticationUrl = $matches['url'];
    }

    /**
     * This does the app registration logic.
     *
     * @When the user registers the service with notification type :notificationType address: :notificationAddress
     * @When the user registers the service
     * @param string $notificationType
     * @param string $notificationAddress
     * @throws \Assert\AssertionFailedException
     */
    public function userRegisterTheService(
        $notificationType = 'APNS',
        $notificationAddress = '0000000000111111111122222222223333333333'
    ) {
        $this->notificationType = $notificationType;
        $this->notificationAddress = $notificationAddress;
        $this->minkContext->visitPath($this->metadataUrl);
        $metadataBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();

        $metadata = json_decode($metadataBody);
        Assertion::notEq($metadata, false, 'Metadata has expire and returns false');
        $this->metadata = $metadata;

        // Doing the actual registration.
        $secret = $this->createClientSecret();
        $this->clientSecret = $secret;
        $registrationBody = [
            'operation' => 'register',
            'secret' => $secret,
            'notificationType' => $notificationType,
            'notificationAddress' => $notificationAddress,
        ];

        /** @var \Symfony\Bundle\FrameworkBundle\Client $client */
        $client = $this->minkContext->getSession()->getDriver()->getClient();
        $client->request('POST', $metadata->service->enrollmentUrl, $registrationBody);
        $resultBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();
        Assertion::eq($resultBody, 'OK', 'Enrollment failed');
    }

    /**
     * This does the app authentication logic.
     *
     * @When the app authenticates to the service with notification type :notificationType address: :notificationAddress
     * @When the app authenticates to the service
     * @param string $notificationType
     * @param string $notificationAddress
     * @throws \Exception
     */
    public function appAuthenticates(
        $notificationType = 'APNS',
        $notificationAddress = '0000000000111111111122222222223333333333'
    ) {
        list($serviceId, $session, $challenge, $sp, $version) = explode('/', $this->authenticationUrl);
        list($userId, $serviceId) = explode('@', $serviceId);
        $service = (array)$this->metadata->service;
        $authenticationUrl = $service['authenticationUrl'];
        $ocraSuite = $service['ocraSuite'];

        $response = OCRA::generateOCRA($ocraSuite, $this->clientSecret, '', $challenge, '', $session, '');
        $authenticationBody = [
            'operation' => 'login',
            'sessionKey' => $session,
            'userId' => $userId,
            'response' => $response,
            'notificationType' => $notificationType,
            'notificationAddress' => $notificationAddress,
        ];

        $this->authenticatioResponse = $this->kernel->handle(
            Request::create($authenticationUrl, 'POST', $authenticationBody)
        );
    }

    //

    /**
     * This does the app authentication logic.
     *
     * @When the app authenticates to the service with wrong password
     * @param string $notificationType
     * @param string $notificationAddress
     * @throws \Exception
     */
    public function appAuthenticatesWithWrongPassword(
        $notificationType = 'APNS',
        $notificationAddress = '0000000000111111111122222222223333333333'
    ) {
        $secret = $this->clientSecret;
        // We scramble the secret key, normally the user does this with his password
        $this->clientSecret = $this->createClientSecret();
        $this->appAuthenticates($notificationType, $notificationAddress);
        $this->clientSecret = $secret;
    }

    /**
     * @Then we register with the same qr code it should not work anymore.
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Exception
     */
    public function userRegisterTheServiceWithSameQr()
    {
        try {
            $this->userRegisterTheService($this->notificationType, $this->notificationAddress);
        } catch (\Exception $exception) {
            Assertion::eq($exception->getMessage(), 'Metadata has expire and returns false');

            return;
        }
        throw new \Exception('It should not be valid');
    }

    /**
     * @Then we have a registered user
     *
     * @throws \AppBundle\Tiqr\Exception\UserNotExistsException
     * @throws \Assert\AssertionFailedException
     */
    public function weHaveARegisteredUser()
    {
        /** @var TiqrUserRepositoryInterface $userRepository */
        $userRepository = $this->kernel->getContainer()->get(TiqrUserRepositoryInterface::class);
        // we have a registered user
        $user = $userRepository->getUser($this->metadata->identity->identifier);
        Assertion::eq($user->getSecret(), $this->clientSecret);
    }

    /**
     * @Then we have a authenticated user
     *
     * @throws \Assert\AssertionFailedException
     */
    public function weHaveAAuthenticatedUser()
    {
        Assertion::eq("OK", $this->authenticatioResponse->getContent());
        Assertion::eq($this->authenticatioResponse->getStatusCode(), 200);
    }

    /**
     * @Then we have the authentication error :error
     *
     * @throws \Assert\AssertionFailedException
     */
    public function weHaveTheAuthenticationError($error)
    {
        Assertion::eq($error, $this->authenticatioResponse->getContent());
        Assertion::eq($this->authenticatioResponse->getStatusCode(), Response::HTTP_FORBIDDEN);
    }

    /**
     * @Given tiqr users is permanently blocked after :attempts attempts
     * @param int $attempts
     * @throws \Assert\AssertionFailedException
     */
    public function tiqrUserIsPermentlyBlockedConfiguration($attempts)
    {
        $container = $this->kernel->getContainer();
        /** @var TiqrConfiguration $config */
        $config = $container->get(TiqrConfigurationInterface::class);
        $config->setMaxLoginAttempts($attempts);
    }

    /**
     *
     * @return string
     */
    private function createClientSecret()
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
}
