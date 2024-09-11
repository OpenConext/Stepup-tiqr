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

declare(strict_types = 1);

namespace Surfnet\Tiqr\Dev\Command;

use GuzzleHttp\Client;
use OCRA;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zxing\QrReader;

require_once __DIR__.'/../../vendor/tiqr/tiqr-server-libphp/library/tiqr/Tiqr/OATH/OCRA.php';

#[AsCommand(name: 'test:authentication')]
class AuthenticationCommand extends Command
{
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Register the app with authentication url.')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to QR-code image')
            ->addOption(
                'notificationType',
                'nt',
                InputOption::VALUE_OPTIONAL,
                'The push notification type APNS/GCM',
                'APNS'
            )
            ->addOption(
                'notificationAddress',
                'na',
                InputOption::VALUE_OPTIONAL,
                'The push notification address',
                '0000000000111111111122222222223333333333'
            )
            ->addOption(
                'offline',
                'ol',
                InputOption::VALUE_OPTIONAL,
                'Don\'t send otp directly'
            )
            ->addOption(
                'nameId',
                'id',
                InputOption::VALUE_REQUIRED,
                'Name id/user id of the user'
            )
            ->setHelp('Give the url as argument.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Fetching the metadata from the Tiqr IDP.
        $path = $input->getArgument('path');
        $url = $this->readAuthenticationLinkFromFile($path, $output);

        $matches = [];
        if (preg_match('/^tiqrauth:\/\/(?P<url>.*)$/', $url, $matches) !== 1) {
            throw new RuntimeException('Expected url with tiqrauth://');
        }
        $authn = $matches['url'];
        [$serviceId, $session, $challenge, $sp, $version] = explode('/', $authn);

        $userId = null;
        if (str_contains($serviceId, '@')) {
            [$userId, $serviceId] = explode('@', $serviceId);
        }

        $output->writeln([
            '<comment>Authentication url data:</comment>',
            $this->decorateResult(json_encode([
                'serviceId' => $serviceId,
                'session' => $session,
                'challenge' => $challenge,
                'sp' => $sp,
                'version' => $version,
                'userId' => $userId,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->getService($serviceId);
        $authenticationUrl = $service['authenticationUrl'];
        $ocraSuite = $service['ocraSuite'];
        $identities = $service['identities'];

        // Fetch first user, if none is provided.
        if ($userId === null) {
            $ids = array_keys($identities);
            $userId = reset($ids);
        }

        if (!isset($identities[$userId])) {
            throw new RuntimeException(sprintf('User with id "%s" not found ', $userId));
        }

        $user = $identities[$userId];
        $secret = $user['secret'];

        unset($service['identities']);
        $service['id'] = $serviceId;
        $output->writeln([
            '<comment>Generate OCRA for service:</comment>',
            $this->decorateResult(json_encode($service, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)),
        ]);

        $response = OCRA::generateOCRA($ocraSuite, $secret, '', $challenge, '', $session, '');
        if ($input->getOption('offline')) {
            $output->writeln([
                '<info>Please login manually:</info>',
                $this->decorateResult($response),
            ]);
            return Command::FAILURE;
        }

        $authenticationBody = [
            'operation' => 'login',
            'sessionKey' => $session,
            'userId' => $userId,
            'response' => $response,
            'notificationType' => $input->getOption('notificationType'),
            'notificationAddress' => $input->getOption('notificationAddress'),
        ];

        $output->writeln([
            sprintf(
                '<comment>Send authentication data to "%s" with body:</comment>',
                $authenticationUrl
            ),
            $this->decorateResult(json_encode($authenticationBody, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->client->post($authenticationUrl, [
            'form_params' => $authenticationBody,
        ])->getBody()->getContents();

        $output->writeln([
            '<info>Authentication result:</info>',
            $this->decorateResult($result),
        ]);
        return Command::SUCCESS;
    }

    protected function decorateResult(string $text): string
    {
        return "<options=bold>$text</>";
    }

    protected function readAuthenticationLinkFromFile(string $file, OutputInterface $output): string
    {
        $qrcode = new QrReader(file_get_contents($file), QrReader::SOURCE_TYPE_BLOB);
        /** @phpstan-var mixed $link */
        $link = $qrcode->text();

        if (!is_string($link)) {
            throw new RuntimeException('Unable to read a link from the QR code');
        }

        $output->writeln([
            'Registration link result: ',
            $this->decorateResult($link),
        ]);

        return $link;
    }

    /**
     * @throws \RuntimeException
     */
    protected function getService(string $serviceId): mixed
    {
        $file = getcwd().'/userdb.json';
        $userdb = json_decode(file_get_contents($file), true);
        if (!isset($userdb[$serviceId])) {
            throw new RuntimeException(sprintf('Service with id "%s" is unkown', $serviceId));
        }

        return $userdb[$serviceId];
    }
}
