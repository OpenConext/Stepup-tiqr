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

use Assert\Assertion;
use Assert\AssertionFailedException;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\MinkExtension\Context\MinkContext;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use OCRA;
use RuntimeException;
use stdClass;
use Surfnet\SamlBundle\Exception\NotFound;
use Surfnet\Tiqr\Dev\FileLogger;
use Surfnet\Tiqr\Service\TrustedDevice\Crypto\HaliteCryptoHelper;
use Surfnet\Tiqr\Service\TrustedDevice\TrustedDeviceService;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\Configuration;
use Surfnet\Tiqr\Service\TrustedDevice\ValueObject\CookieValue;
use Surfnet\Tiqr\Tiqr\Exception\UserNotExistsException;
use Surfnet\Tiqr\Tiqr\TiqrConfigurationInterface;
use Surfnet\Tiqr\Tiqr\TiqrUserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Zxing\QrReader;
use Symfony\Component\BrowserKit\Cookie;

/**
 * With this context Tiqr can be tested without an active Saml AuthnNRequest.
 *
 * @SuppressWarnings(PHPMD)
 */
class TiqrContext implements Context
{
    protected MinkContext $minkContext;

    protected string $metadataUrl;

    protected string $clientSecret;

    protected string $userAgent;

    /**
     * The registration metadata.
     */
    protected stdClass $metadata;

    protected string $notificationType;
    protected string $notificationAddress;

    /**
     * The scanned QR code with the '//tiqrauth' part.
     */
    protected string $authenticationUrl;

    /**
     * The authentication result.
     */
    protected Response $authenticatioResponse;
    public function __construct(
        private readonly TiqrUserRepositoryInterface $tiqrUserRepository,
        private readonly TiqrConfigurationInterface  $configuration,
        private readonly FileLogger $fileLogger,
        private readonly KernelInterface $kernel,
        private readonly TrustedDeviceService $trustedDeviceService,
    ) {
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
        $this->minkContext->getSession()->setCookie('stepup_locale', 'en');
    }

    /**
     * Configure the tiqr mobile app user agent.
     *
     * @BeforeScenario
     */
    public function restore(BeforeScenarioScope $scope): void
    {
        $this->userAgent = 'Behat UA';
    }

    /**
     * We are not actually scanning the QR code, but downloading link from:
     *
     * @throws NotFound
     * @throws AssertionFailedException
     * @see QrLinkController::qrRegistrationAction
     *
     * @Given the registration QR code is scanned
     *
     */
    public function theRegistrationQrCodeIsScanned(): void
    {
        $this->minkContext->visitPath('/registration/qr/link');
        // Should start with tiqrenroll://
        $content = $this->minkContext->getMink()->getSession()->getPage()->getText();
        Assertion::startsWith($content, 'tiqrenroll://');
        Assertion::eq(preg_match('/^tiqrenroll:\/\/(?P<url>.*)/', $content, $matches), 1);
        if (isset($matches['url'])) {
            $this->metadataUrl = $matches['url'];
        } else {
            throw new RuntimeException('Could not get url');
        }
    }

    /**
     * We are not actually scanning the QR code, but downloading link from:
     *
     * @throws NotFound
     * @throws AssertionFailedException
     *@see QrLinkController::qrRegistrationAction
     *
     * @Given the authentication QR code is scanned
     *
     */
    public function theAuthenticationQrCodeIsScanned(): void
    {
        $this->minkContext->visitPath('/authentication/qr/' . urlencode((string) $this->metadata->identity->identifier) . '/link');
        // Should start with tiqrenroll://
        $content = $this->minkContext->getMink()->getSession()->getPage()->getText();
        Assertion::startsWith($content, 'tiqrauth://');
        Assertion::eq(preg_match('/^tiqrauth:\/\/(?P<url>.*)/', $content, $matches), 1);
        if (isset($matches['url'])) {
            $this->authenticationUrl = $matches['url'];
        } else {
            throw new RuntimeException('Could not get url');
        }
    }

    /**
     * This does the app registration logic.
     *
     * @When the user registers the service with notification type :notificationType address: :notificationAddress
     * @When the user registers the service
     * @throws AssertionFailedException
     */
    public function userRegisterTheService(
        string $notificationType = '',
        string $notificationAddress = ''
    ): void {
        $this->notificationType = $notificationType;
        $this->notificationAddress = $notificationAddress;
        $this->minkContext->visitPath($this->metadataUrl);
        $metadataBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();

        $metadata = json_decode($metadataBody);
        Assertion::notEq($metadata, false, 'Metadata has expired');
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
        if ($notificationType === 'NULL') {
            unset($registrationBody['notificationType']);
        }
        if ($notificationAddress === 'NULL') {
            unset($registrationBody['notificationAddress']);
        }

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
     */
    public function mobileAppUsesUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * This does the app authentication logic.
     *
     * @When the app authenticates to the service with notification type :notificationType address: :notificationAddress
     * @When the app authenticates to the service
     * @throws Exception
     */
    public function appAuthenticates(
        string $notificationType = '',
        string $notificationAddress = ''
    ): void {
        [$serviceId, $session, $challenge, $sp, $version] = explode('/', $this->authenticationUrl);
        [$userId, $serviceId] = explode('@', $serviceId);
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
        if ($notificationType === 'NULL') {
            unset($authenticationBody['notificationType']);
        }
        if ($notificationAddress === 'NULL') {
            unset($authenticationBody['notificationAddress']);
        }
        // Internal request does not like an absolute path.
        $authenticationUrl = str_replace('https://tiqr.dev.openconext.local', '', (string) $authenticationUrl);

        $this->authenticatioResponse = $this->kernel->handle(
            Request::create($authenticationUrl, Request::METHOD_POST, $authenticationBody)
        );
    }

    /**
     * This does the app authentication logic.
     *
     * @When the app authenticates to the service with wrong password
     * @throws Exception
     */
    public function appAuthenticatesWithWrongPassword(
        string $notificationType = '',
        string $notificationAddress = ''
    ): void {
        $secret = $this->clientSecret;
        // We scramble the secret key, normally the user does this with his password
        $this->clientSecret = $this->createClientSecret();
        $this->appAuthenticates($notificationType, $notificationAddress);
        $this->clientSecret = $secret;
    }

    /**
     * @Then tiqr errors with a message telling the user agent was wrong
     *
     * @throws AssertionFailedException
     * @throws Exception
     */
    public function userRegisteredWithWrongUserAgent(): void
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
     * @throws AssertionFailedException
     * @throws Exception
     */
    public function userRegisterTheServiceWithSameQr(): void
    {
        // The first registration attempt should succeed.
        $resultBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();
        Assertion::eq($resultBody, 'OK', 'Enrollment failed');

        // The second attempt should fail.
        try {
            $this->userRegisterTheService($this->notificationType, $this->notificationAddress);
        } catch (Exception $exception) {
            Assertion::eq($exception->getMessage(), 'Metadata has expired');

            return;
        }
        throw new Exception('It should not be valid');
    }

    /**
     * @Then we have a registered user
     *
     * @throws UserNotExistsException
     * @throws AssertionFailedException
     */
    public function weHaveARegisteredUser(): void
    {
        $resultBody = $this->minkContext->getMink()->getSession()->getPage()->getContent();
        Assertion::eq($resultBody, 'OK', 'Enrollment failed');

        /** @var TiqrUserRepositoryInterface $userRepository */
        $userRepository = $this->tiqrUserRepository;
        // we have a registered user
        $user = $userRepository->getUser($this->metadata->identity->identifier);
        Assertion::eq($user->getSecret(), $this->clientSecret);
    }

    /**
     * @Then we have a authenticated user
     * @Then we have a authenticated app
     *
     * @throws AssertionFailedException
     */
    public function weHaveAAuthenticatedUser(): void
    {
        Assertion::eq("OK", $this->authenticatioResponse->getContent());
        Assertion::eq($this->authenticatioResponse->getStatusCode(), 200);
    }

    /**
     * @Given we have a trusted cookie for address: :arg1
     */
    public function weHaveATrustedDevice(string $notificationAddress): void
    {
        $userId = $this->metadata->identity->identifier;

        $cookieJar = $this->authenticatioResponse->headers->getCookies();

        $request = new Request();
        foreach ($cookieJar as $cookie) {
            $request->cookies->set($cookie->getName(), $cookie->getValue());
        }
        $cookieValue = $this->trustedDeviceService->read($request);
        Assertion::isInstanceOf($cookieValue, CookieValue::class);
        Assertion::true($this->trustedDeviceService->isTrustedDevice($cookieValue, $userId, $notificationAddress));
    }

    /**
     * @Then we have the authentication error :error
     *
     * @throws AssertionFailedException
     */
    public function weHaveTheAuthenticationError(string $error): void
    {
        Assertion::eq($error, $this->authenticatioResponse->getContent());
        Assertion::eq($this->authenticatioResponse->getStatusCode(), Response::HTTP_FORBIDDEN);
    }

    /**
     * @Given tiqr users is permanently blocked after :attempts attempts
     * @throws AssertionFailedException
     */
    public function tiqrUserIsPermentlyBlockedConfiguration(int $attempts): void
    {
        $this->configuration->setMaxLoginAttempts($attempts);
    }

    private function createClientSecret(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    /**
     * Read image and set to this context.
     *
     * @Then I scan the tiqr registration qrcode
     *
     * @throws AssertionFailedException
     * @throws GuzzleException
     */
    public function iScanTheTiqrRegistrationQrcode(): void
    {
        $session = $this->minkContext->getMink()->getSession();
        $page = $session->getPage();
        $img = $page->find('css', 'div.qr > a > img');
        $src = $img->getAttribute('src');

        $qrcode = new QrReader($this->getFileContentsInsecure($src), QrReader::SOURCE_TYPE_BLOB);
        $content = $qrcode->text();
        Assertion::startsWith($content, 'tiqrenroll://');
        Assertion::eq(preg_match('/^tiqrenroll:\/\/(?P<url>.*)/', $content, $matches), 1);
        if (isset($matches['url'])) {
            $this->metadataUrl = $matches['url'];
        } else {
            throw new RuntimeException('Could not get url');
        }
    }

    /**
     * Click the enrollment Url instead of scanning the QR code
     *
     * @Then I click the tiqr registration qrcode
     *
     * @throws AssertionFailedException
     * @throws GuzzleException
     */
    public function iClickTheTiqrRegistrationQrcode(): void
    {
        $session = $this->minkContext->getMink()->getSession();
        $page = $session->getPage();
        $anchor = $page->find('css', 'div.qr > a');
        $this->metadataUrl = str_replace('tiqrenroll://', '', $anchor->getAttribute('href'));
    }

    /**
     * Read image and set to this context.
     *
     * @Then I scan the tiqr authentication qrcode
     *
     * @throws AssertionFailedException
     * @throws GuzzleException
     */
    public function iScanTheTiqrAuthenticationQrcode(): void
    {
        $session = $this->minkContext->getMink()->getSession();
        $page = $session->getPage();
        $img = $page->find('css', 'div.qr img');
        $src = $img->getAttribute('src');
        $qrcode = new QrReader($this->getFileContentsInsecure($src), QrReader::SOURCE_TYPE_BLOB);
        $content = $qrcode->text();
        Assertion::startsWith($content, 'tiqrauth://');
        Assertion::eq(preg_match('/^tiqrauth:\/\/(?P<url>.*)/', $content, $matches), 1);
        if (isset($matches['url'])) {
            $this->authenticationUrl = $matches['url'];
        } else {
            throw new RuntimeException('Could not get url');
        }
    }

    /**
     * @When /^I clear the logs$/
     */
    public function clearTheLogs(): void
    {
        $this->fileLogger->cleanLogs();
    }

    /**
     * @Given /^the logs are:$/
     *
     * @throws AssertionFailedException
     * @throws Exception
     */
    public function theLogsAre(TableNode $table): void
    {
        $logs = $this->fileLogger->cleanLogs();
        $rows = array_values($table->getColumnsHash());

        try {
            foreach ($rows as $index => $row) {
                Assertion::true(isset($logs[$index]), sprintf('Missing message %s', $row['message']));
                [$level, $message, $context] = $logs[$index];
                if (preg_match('/^\/.*\/$/', (string) $row['message']) === 1) {
                    Assertion::regex($message, $row['message']);
                } else {
                    Assertion::eq(
                        $message,
                        $row['message'],
                        "\n"
                        . "At row:   " .  ($index+1) . "\n"
                        . "Expected: " . $row['message'] . "\n"
                        . "Got:      " . $message . "\n"
                        . "If this message *is* expected include in the feature test by adding:\n"
                        . "| " . $level . " | " . $message . " | present |\n\n"
                    );
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
            $yml = implode(PHP_EOL, array_map(fn($log): string => sprintf(
                '| %s | %s | %s |',
                $log[0],
                $log[1],
                isset($log[2]['sari']) ? 'present' : ''
            ), $logs));

            throw new Exception($exception->getMessage() . PHP_EOL . $yml, $exception->getCode(), $exception);
        }
    }

    /**
     * @Given /^the logs are dumped:$/
     *
     * @throws AssertionFailedException
     * @throws Exception
     */
    public function theLogsAreDumped(TableNode $table): void
    {
        $logs = $this->fileLogger->cleanLogs();
        $output = '';

        foreach ($logs as $index => $row) {
            [$level, $message] = $row;
            $sari = !empty($row[2]['sari']) ? 'present' : '     ';
            $output .= "| " . $level . " | " . $message . " | " . $sari . " |\n";
        }

        dd($output);
    }

    /**
     * Return file from stream response.
     *
     * This support the strange way how tiqr sends the qr code.
     *
     * @throws GuzzleException
     */
    private function getFileContentsInsecure(string $src): string|false
    {
        $session = $this->minkContext->getMink()->getSession();
        $driver = $session->getDriver();
        $client = $driver->getClient();
        $client->request('get', $src);

        // retrieving streamed content is pretty finicky, but this works: https://github.com/symfony/symfony/issues/25005#issuecomment-1564417224
        return $client->getInternalResponse()->getContent();
    }

    /**
     * @Given I fill in :field with my identifier
     */
    public function iFillInWithMyIdentifier(string $field): void
    {
        $this->minkContext->fillField($field, $this->metadata->identity->identifier);
    }

    /**
     * @Given I fill in :field with my one time password and press ok
     */
    public function iFillInWithMyOTP(string $field): void
    {
        [$serviceId, $session, $challenge] = explode('/', $this->authenticationUrl);
        $service = (array)$this->metadata->service;
        $ocraSuite = $service['ocraSuite'];
        $response = OCRA::generateOCRA($ocraSuite, $this->clientSecret, '', $challenge, '', $session, '');
        $this->minkContext->visit('/authentication?otp=' . urlencode($response));
    }

    /**
     * @When /^a push notification is sent$/
     */
    public function aPushNotificationIsSent(): void
    {
        $session = $this->minkContext->getMink()->getSession();
        $driver = $session->getDriver();
        $client = $driver->getClient();
        $client->request('POST', '/authentication/notification');
    }

    /**
     * @When /^push notification is sent with a trusted\-device cookie with address "([^"]*)"$/
     * @When /^push notification is sent with a trusted\-device cookie with address "([^"]*)" and cookie userId "([^"]*)"$/
     */
    public function aPushNotificationIsSentWithATrustedDevice(string $notificationAddress, string $cookieUserId = null): void
    {
        $userId = $this->metadata->identity->identifier;
        $cookieUserId = $cookieUserId ?? $userId;

        $config = new Configuration('tiqr-trusted-device', 3600, '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f', 'none');
        $cryptoHelper = new HaliteCryptoHelper($config);

        $cookieValue = CookieValue::from($cookieUserId, $notificationAddress);
        $cookieName = 'tiqr-trusted-device';

        $encryptedValue = $cryptoHelper->encrypt($cookieValue);

        $session = $this->minkContext->getMink()->getSession();
        $driver = $session->getDriver();
        $client = $driver->getClient();

        $client->getCookieJar()->set(new Cookie(
            $cookieName,
            $encryptedValue,
            '' . (time() + 3600),
            '/',
            '',
            true,
            true,
            false,
            'strict'
        ));

        $client->request('POST', '/authentication/notification');
    }


    /**
     * @Then /^it should fail with "([^"]*)"$/
     */
    public function itShouldFailWith(string $errorCode): void
    {
        $session = $this->minkContext->getMink()->getSession();
        $driver = $session->getDriver();
        $client = $driver->getClient();
        $response = $client->getResponse();
        Assertion::eq($response->getStatusCode(), 200);
        Assertion::eq($response->getContent(), '"' . $errorCode . '"');
    }

    /**
     * @Then /^it should send a notification for the user with type "([^"]*)" and address "([^"]*)"$/
     */
    public function itShouldSendANotification(string $type, string $address): void
    {
        $id = $this->metadata->identity->identifier;
        $session = $this->minkContext->getMink()->getSession();
        /** @var BrowserKitDriver $driver */
        $driver = $session->getDriver();
        $client = $driver->getClient();
        $response = $client->getResponse();
        /** @var \Symfony\Component\HttpFoundation\JsonResponse $response */
        Assertion::eq($response->getStatusCode(), 200);

        $this->logsContain('Sending push notification for user "' . $id . '" with type "' . $type . '" and (untranslated) address "' . $address .'"');
    }

    private function logsContain(string $string): void
    {
        $logs = $this->fileLogger->cleanLogs();
        foreach ($logs as $log) {
            if ($log[1] === $string) {
                return;
            }
        }

        Assertion::eq($string, '', sprintf('The logs do not contain %s', $string));
    }

    private function logsContainLineStartingWith(string $string): void
    {
        /** @var array<array<string>> $logs */
        $logs = $this->fileLogger->cleanLogs();
        foreach ($logs as $log) {
            if (str_contains($log[1], $string)) {
                return;
            }
        }

        Assertion::eq($string, '', sprintf('The logs do not contain a line starting with "%s"', $string));
    }

    /**
     * @Then /^the logs should say: no trusted cookie for address "([^"]*)"$/
     */
    public function theLogsShouldSayNoTrustedDevice(string $address): void
    {
        $userId = $this->metadata->identity->identifier;
        $this->logsContain(
            'No trusted device cookie stored for notification address "' . $address . '" and user "' . $userId . '". No notification was sent'
        );
    }

    /**
     * @Then /^the logs should mention a signature mismatch for address "([^"]*)"$/
     */
    public function theLogsShouldMentionSignatureMismatch(string $address): void
    {
        $userId = $this->metadata->identity->identifier;
        $this->logsContain(
            'A trusted device cookie is found for notification address "'.$address.'" and user "'.$userId.'", but has signature mismatch'
        );
    }

    /**
     * @Given /^the logs should mention: Writing a trusted\-device cookie with fingerprint$/
     */
    public function theLogsShouldMentionWritingATrustedDeviceCookieWithFingerprint(): void
    {
        $this->logsContainLineStartingWith('Writing a trusted-device cookie with fingerprint ');
    }

    /**
     * @Then /^I dump the page$/
     */
    public function iDumpThePage(): void
    {
        $session = $this->minkContext->getSession();
        $driver = $session->getDriver();
        /** @var BrowserKitDriver $driver */
        $client = $driver->getClient();
        $response = $client->getResponse();

        dump($response);
    }

    /**
     * @Then /^I dump the auth response$/
     */
    public function iDumpTheAuthResponse(): void
    {
        dump($this->authenticatioResponse);
    }

    /**
     * @When /^the trusted device cookie is cleared$/
     */
    public function theTrustedDeviceCookieIsCleared(): void
    {
        $this->minkContext->getSession()->getDriver()->getClient()->getCookieJar()->expire('tiqr-trusted-device');
    }
}
