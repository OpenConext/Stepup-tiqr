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

use AppBundle\Tiqr\TiqrUserRepository;
use AppBundle\Tiqr\TiqrUserRepositoryInterface;
use Assert\Assertion;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Behat\Context\Context;
use GuzzleHttp\Client;
use Symfony\Component\HttpKernel\KernelInterface;

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

    protected $metadata;

    protected $notificationType;

    protected $notificationAddress;

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
     * @Given the qr code is scanned
     *
     * @throws \Surfnet\SamlBundle\Exception\NotFound
     * @throws \Assert\AssertionFailedException
     */
    public function theQrCodeIsScanned()
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
     * This does the app logic.
     *
     * @When the user registers the service with notification type :notificationType address: :notificationAddress
     * @throws \Assert\AssertionFailedException
     */
    public function userRegisterTheService($notificationType, $notificationAddress)
    {
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
        $userRepository = $this->kernel->getContainer()->get(TiqrUserRepository::class);
        // we have a registered user
        $user = $userRepository->getUser($this->metadata->identity->identifier);
        Assertion::eq($user->getSecret(), $this->clientSecret);
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
