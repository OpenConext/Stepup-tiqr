<?php

require_once __DIR__.'/../../vendor/autoload.php';

use Psr\Log\LoggerInterface;

class Saml2Container extends SAML2_Compat_AbstractContainer
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Generate a random identifier for identifying SAML2 documents.
     */
    public function generateId()
    {
        return '_' . base64_encode(openssl_random_pseudo_bytes(30));
    }

    public function debugMessage($message, $type)
    {
        $this->logger->debug($message, ['type' => $type]);
    }

    public function redirect($url, $data = array())
    {
        // dummy method
        throw new Exception("unexpected invocation of Saml2Container::redirect method");
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function postRedirect($url, $data = array())
    {
        throw Exception("unexpected invocation of Saml2Container::postRedirect method");
    }
}
