<?php


namespace DemoAppBundle\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppRegistrationCommand extends Command
{
    private $client;

    public function __construct(Client $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    protected function configure()
    {
        $this
            ->setName('app:demo:register')
            ->setDescription('Register the app with registration url.')
            ->addArgument('url', InputArgument::OPTIONAL, <<<TEXT
'The registration url if not given automatically fetched from /app_dev.php/registration/qr/link .'
TEXT
                , false)
            ->addArgument(
                'notificationType',
                InputArgument::OPTIONAL,
                'The push notification type APNS/GCM',
                'APNS'
            )
            ->addArgument(
                'notificationAddress',
                InputArgument::OPTIONAL,
                'The push notification address',
                '0000000000111111111122222222223333333333'
            )
            ->setHelp('Give the url as argument.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Fetching the metadata from the Tiqr IDP.
        $url = $input->getArgument('url');
        if (!$url) {
            $url = $this->fetchRegistrationUrlFromRemote($output);
        }
        $output->writeln("<comment>Fetch metadata endpoint from $url</comment>");
        $metadataResponse = $this->client->get($url);
        $metadataBody = $metadataResponse->getBody()->getContents();
        $metadata = json_decode($metadataBody);
        $output->writeln([
            'Metadata result:',
            $this->decorateResult(json_encode($metadata, JSON_PRETTY_PRINT)),
        ]);
        if ($metadata === false) {
            $output->writeln('<error>Metadata has expire and returns false</error>');
            return;
        }

        // Doing the actual registration.
        $secret = $this->createClientSecret();
        $registrationBody = [
            'operation' => 'register',
            'secret' => $secret,
            'notificationType' => $input->getArgument('notificationType'),
            'notificationAddress' => $input->getArgument('notificationAddress'),
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
            $output->writeln('<error>Enrollment failed "%s"</error>');

            return;
        }
        $output->writeln('<info>Enrollment succeeded</info>');

        // Storing result as a new identity.
        $this->storeIdentity($metadata, $secret, $output);
    }

    protected function decorateResult($text)
    {
        return "<options=bold>$text</>";
    }

    /**
     * @param OutputInterface $output
     *
     * @return string
     */
    protected function fetchRegistrationUrlFromRemote(OutputInterface $output)
    {
        $registrationQRLink = '/app_dev.php/registration/qr/link';
        $output->writeln('<comment>Fetch registration link from </comment>'.$registrationQRLink);
        $json = $this->client
            ->get($registrationQRLink)
            ->getBody()
            ->getContents();
        $result = json_decode($json);
        $output->writeln([
            'Registration link result: ',
            $this->decorateResult(json_encode($result, JSON_PRETTY_PRINT)),
        ]);

        return $result->url;
    }

    /**
     *
     * @return string
     */
    private function createClientSecret()
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
        $userdb[$serviceId]['identities'][$userId] = (array) $metadata->identity;
        $userdb[$serviceId]['identities'][$userId]['secret'] = $secret;
        file_put_contents($file, json_encode($userdb, JSON_PRETTY_PRINT));
        $output->writeln("<info>New user of '$serviceId' with identity '$userId' is stored in file $file</info>");
    }
}
