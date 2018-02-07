<?php

namespace AppBundle\Tiqr;

use Psr\Log\LoggerInterface;
use Tiqr_Service;

class TiqrFactory
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function create()
    {
        $options = $this->getOptions();
        $vendorPath = __DIR__.'/../../../vendor';
        require $vendorPath . '/tiqr/tiqr-server-libphp/library/tiqr/Tiqr/AutoLoader.php';

        $autoloader = \Tiqr_AutoLoader::getInstance($options); // needs {tiqr,zend,phpqrcode}.path
        $autoloader->setIncludePath();

        return new TiqrService(new Tiqr_Service($options));
    }

    private function getOptions()
    {
        $vendorPath = __DIR__.'/../../../vendor';

        return [
            //"identifier"      => "pilot.stepup.coin.surf.net",
            "name" => "SURFconext Strong Authentication", // todo i18n
            "auth.protocol" => "tiqrauth",
            "enroll.protocol" => "tiqrenroll",
            "ocra.suite" => "OCRA-1:HOTP-SHA1-6:QH10-S",
            "logoUrl" => "https://tiqr.example.com/tiqrRGB.png",
            "infoUrl" => "https://tiqr.example.com/info.html",
            "tiqr.path" => $vendorPath.'/tiqr/tiqr-server-libphp/library/tiqr/',
            'phpqrcode.path' => '.',
            'zend.path' => $vendorPath.'/zendframework/zendframework1/library',
            'statestorage' => ["type" => "file"],
            'userstorage' => ["type" => "file", "path" => "/tmp", "encryption" => ['type' => 'dummy']],
            "usersecretstorage" => ["type" => "file"],
            "apns.certificate" => '',
            "apns.environment" => 'production',
            "debug" => false,
            "default_locale" => 'en',
            "translation" => [
                "en" => true,
                "nl" => true,
            ],
            'domain' => '', // The domain for this application, used for the 'stepup_locale' cookie
            "loghandler" => $this->logger,
            "trusted_proxies" => ["127.0.0.1"],
            "default_timezone" => "Europe/Amsterdam",
            'maxAttempts' => 5,
        ];
    }
}
