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

namespace Dev\Command;

use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zxing\QrReader;

#[AsCommand(name: 'test:registration')]
class RegistrationCommand extends Command
{
    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Register the app with registration url.')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to QR-code image')
            ->addOption(
                'notificationType',
                'nt',
                InputOption::VALUE_OPTIONAL,
                'The push notification type APNS/GCM',
                ''
            )
            ->addOption(
                'notificationAddress',
                'na',
                InputOption::VALUE_OPTIONAL,
                'The push notification address',
                ''
            )
            ->setHelp('Give the url as argument.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Fetching the metadata from the Tiqr IDP.
        $path = $input->getArgument('path');
        $url = $this->readRegistrationUrlFromFile($path, $output);

        $matches = [];
        if (preg_match('/^tiqrenroll:\/\/(?P<url>.*)$/', $url, $matches) !== 1) {
            throw new RuntimeException('Expected url with tiqrenroll://');
        }
        $url = $matches['url'];

        $output->writeln("<comment>Fetch metadata endpoint from $url</comment>");
        $metadataResponse = $this->client->get($url);
        $metadataBody = $metadataResponse->getBody()->getContents();
        $metadata = json_decode($metadataBody);
        $output->writeln([
            'Metadata result:',
            $this->decorateResult(json_encode($metadata, JSON_PRETTY_PRINT)),
        ]);
        if ($metadata === false) {
            $output->writeln('<error>Metadata has expired</error>');

            return 1;
        }

        // Doing the actual registration.
        $secret = $this->createClientSecret();
        $registrationBody = [
            'operation' => 'register',
            'secret' => $secret,
            'notificationType' => $input->getOption('notificationType'),
            'notificationAddress' => $input->getOption('notificationAddress'),
        ];
        $output->writeln([
            sprintf(
                '<comment>Send registration data to enrollmentUrl "%s" with body:</comment>',
                $metadata->service->enrollmentUrl
            ),
            $this->decorateResult(json_encode($registrationBody, JSON_PRETTY_PRINT)),
        ]);

        $result = $this->client->post($metadata->service->enrollmentUrl, ['form_params' => $registrationBody]);
        $resultBody = $result->getBody()->getContents();
        $output->writeln([
            'Enrollment result:',
            $this->decorateResult($resultBody),
        ]);

        if ($resultBody !== 'OK' || $result->getStatusCode() !== 200) {
            $output->writeln('<error>Enrollment failed</error>');

            return 1;
        }
        $output->writeln('<info>Enrollment succeeded</info>');

        // Storing result as a new identity.
        $this->storeIdentity($metadata, $secret, $output);
        return 0;
    }

    protected function decorateResult($text): string
    {
        return "<options=bold>$text</>";
    }

    /**
     * @return string
     */
    protected function readRegistrationUrlFromFile($file, OutputInterface $output)
    {
        $qrcode = new QrReader(file_get_contents($file), QrReader::SOURCE_TYPE_BLOB);
        $link = $qrcode->text();

        $output->writeln([
            'Registration link result: ',
            $this->decorateResult($link),
        ]);

        return $link;
    }


    /**
     *
     * @return string
     */
    private function createClientSecret(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    protected function storeIdentity($metadata, $secret, OutputInterface $output)
    {
        $file = getcwd().'/userdb.json';

        if (file_exists($file)) {
            $userdb = json_decode(file_get_contents($file), true);
        }

        // Create service.
        $serviceId = $metadata->service->identifier;
        $userdb[$serviceId]['authenticationUrl'] = $metadata->service->authenticationUrl;
        $userdb[$serviceId]['ocraSuite'] = $metadata->service->ocraSuite;

        // Store new user.
        $userId = $metadata->identity->identifier;
        $userdb[$serviceId]['identities'][$userId] = (array)$metadata->identity;
        $userdb[$serviceId]['identities'][$userId]['secret'] = $secret;
        file_put_contents($file, json_encode($userdb, JSON_PRETTY_PRINT));
        $output->writeln("<info>New user of '$serviceId' with identity '$userId' is stored in file $file</info>");
    }
}
