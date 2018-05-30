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
use Assert\AssertionFailedException;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
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
 *
 * @SuppressWarnings(PHPMD)
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

    protected $userAgent;

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
     * Configure the tiqr mobile app user agent.
     *
     * @BeforeScenario
     */
    public function restore(BeforeScenarioScope $scope)
    {
        $this->userAgent = 'Behat UA';
    }

    /**
     * We are not actually scanning the QR code, but downloading link from:
     *
     * @see QrLinkController::qrRegistrationAction
     *
     * @Given the registration QR code is scanned
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
     * @Given the authentication QR code is scanned
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
        $notificationType = null,
        $notificationAddress = null
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
        $client->request(
            'POST',
            $metadata->service->enrollmentUrl,
            $registrationBody,
            [],
            [
                'HTTP_USER_AGENT' => $this->userAgent,
            ]
        );
    }

    /**
     * @Given the mobile tiqr app identifies itself with the user agent :userAgent
     *
     * @param string $userAgent
     */
    public function mobileAppUsesUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
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
        $notificationType = null,
        $notificationAddress = null
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
        // Internal request does not like an absolute path.
        $authenticationUrl = str_replace('https://tiqr.example.com/app_test.php', '', $authenticationUrl);

        $this->authenticatioResponse = $this->kernel->handle(
            Request::create($authenticationUrl, 'POST', $authenticationBody)
        );
    }

    /**
     * This does the app authentication logic.
     *
     * @When the app authenticates to the service with wrong password
     * @param string $notificationType
     * @param string $notificationAddress
     * @throws \Exception
     */
    public function appAuthenticatesWithWrongPassword(
        $notificationType = null,
        $notificationAddress = null
    ) {
        $secret = $this->clientSecret;
        // We scramble the secret key, normally the user does this with his password
        $this->clientSecret = $this->createClientSecret();
        $this->appAuthenticates($notificationType, $notificationAddress);
        $this->clientSecret = $secret;
    }

    /**
     * @Then tiqr errors with a message telling the user agent was wrong
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Exception
     */
    public function userRegisteredWithWrongUserAgent()
    {
        $resultBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();
        Assertion::eq(
            $resultBody,
            sprintf(
                'Received request from unsupported mobile app with user agent: "%s"',
                'Bad UA'
            ),
            'Enrollment with wrong user agent should have failed'
        );
    }

    /**
     * @Then we register with the same QR code it should not work anymore.
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Exception
     */
    public function userRegisterTheServiceWithSameQr()
    {
        // The first registration attempt should succeed.
        $resultBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();
        Assertion::eq($resultBody, 'OK', 'Enrollment failed');

        // The second attempt should fail.
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
        $resultBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();
        Assertion::eq($resultBody, 'OK', 'Enrollment failed');

        /** @var TiqrUserRepositoryInterface $userRepository */
        $userRepository = $this->kernel->getContainer()->get(TiqrUserRepositoryInterface::class);
        // we have a registered user
        $user = $userRepository->getUser($this->metadata->identity->identifier);
        Assertion::eq($user->getSecret(), $this->clientSecret);
    }

    /**
     * @Then we have a authenticated user
     * @Then we have a authenticated app
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

    /**
     * Read image and set to this context.
     *
     * @Then I scan the tiqr registration qrcode
     *
     * @throws \Assert\AssertionFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function iScanTheTiqrRegistrationQrcode()
    {
        $session = $this->minkContext->getMink()->getSession();
        /** @var Client $client */
        $page = $session->getPage();
        $img = $page->find('css', 'div.qr > img');
        $src = $img->getAttribute('src');

        $qrcode = new \QrReader($this->getFileContentsInsecure($src), \QrReader::SOURCE_TYPE_BLOB);
        $content = $qrcode->text();
        Assertion::startsWith($content, 'tiqrenroll://');
        Assertion::eq(preg_match('/^tiqrenroll:\/\/(?P<url>.*)/', $content, $matches), 1);
        $this->metadataUrl = $matches['url'];
    }

    /**
     * Read image and set to this context.
     *
     * @Then I scan the tiqr authentication qrcode
     *
     * @throws \Assert\AssertionFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function iScanTheTiqrAuthenticationQrcode()
    {
        $session = $this->minkContext->getMink()->getSession();
        /** @var Client $client */
        $page = $session->getPage();
        $img = $page->find('css', 'div.qr > img');
        $src = $img->getAttribute('src');

        $qrcode = new \QrReader($this->getFileContentsInsecure($src), \QrReader::SOURCE_TYPE_BLOB);
        $content = $qrcode->text();
        Assertion::startsWith($content, 'tiqrauth://');
        Assertion::eq(preg_match('/^tiqrauth:\/\/(?P<url>.*)/', $content, $matches), 1);
        $this->authenticationUrl = $matches['url'];
    }

    /**
     * @When /^I clear the logs$/
     */
    public function clearTheLogs()
    {
        /** @var FileLogger $logger */
        $logger = $this->kernel->getContainer()->get(FileLogger::class);
        $logger->cleanLogs();
    }

    /**
     * @Given /^the logs are:$/
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Exception
     */
    public function theLogsAre(TableNode $table)
    {
        /** @var FileLogger $logger */
        $logger = $this->kernel->getContainer()->get(FileLogger::class);
        $logs = $logger->cleanLogs();
        $rows = array_values($table->getColumnsHash());

        try {
            foreach ($rows as $index => $row) {
                Assertion::true(isset($logs[$index]), sprintf('Missing message %s', $row['message']));
                list($level, $message, $context) = $logs[$index];
                if (preg_match('/^\/.*\/$/', $row['message']) === 1) {
                    Assertion::regex($message, $row['message']);
                } else {
                    Assertion::eq($row['message'], $message);
                }
                Assertion::eq($row['level'], $level, sprintf('Level does not match for %s', $row['message']));
                Assertion::choice($row['sari'], ['', 'present']);
                if ($row['sari'] === 'present') {
                    Assertion::keyExists($context, 'sari', sprintf('Missing sari for message %s', $row['message']));
                    Assertion::notEmpty($context['sari']);
                } else {
                    Assertion::keyNotExists(
                        $context,
                        'sari',
                        sprintf('Having unexpected sari for message %s', $row['message'])
                    );
                }
            }
            $logs = array_slice($logs, count($rows));
            Assertion::noContent($logs, var_export($logs, true));
        } catch (AssertionFailedException $exception) {
            $yml = implode(PHP_EOL, array_map(function ($log) {
                return sprintf(
                    '| %s | %s | %s |',
                    $log[0],
                    $log[1],
                    isset($log[2]['sari']) ? 'present' : ''
                );
            }, $logs));

            throw new \Exception($exception->getMessage() . PHP_EOL . $yml);
        }
    }

    /**
     * Return file from stream response.
     *
     * This support the strange way how tiqr sends the qr code.
     *
     * @param string $src
     *
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getFileContentsInsecure($src)
    {
        $session = $this->minkContext->getMink()->getSession();
        $driver = $session->getDriver();
        /** @var Client $client */
        $client = $driver->getClient();
        ob_start();
        $client->request('get', $src);

        return ob_get_clean();
    }

    /**
     * @Given I fill in :field with my identifier
     */
    public function iFillInWithMyIdentifier($field)
    {
        $this->minkContext->fillField($field, $this->metadata->identity->identifier);
    }

    /**
     * @Given I fill in :field with my one time password and press ok
     */
    public function iFillInWithMyOTP($field)
    {
        list($serviceId, $session, $challenge) = explode('/', $this->authenticationUrl);
        $service = (array)$this->metadata->service;
        $ocraSuite = $service['ocraSuite'];
        $response = OCRA::generateOCRA($ocraSuite, $this->clientSecret, '', $challenge, '', $session, '');
        $this->minkContext->visit('/authentication?otp=' . urlencode($response));
    }
}
