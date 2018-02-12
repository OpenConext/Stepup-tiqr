<?php


namespace DemoAppBundle;

use GuzzleHttp\Client;

/**
 * !! This is not a safe http client, it ignores invalid SSL certificates. !!
 */
class DemoAppHttpClientFactory
{
    public function create()
    {
        // TODO: make the hostname configurable.
        return new Client([
            'base_uri' => 'https://tiqr.example.com',
            'ssl.certificate_authority' => false,
            'verify' => false,
            'headers' => ['Accept' => 'application/json'],
            'curl.options' => [
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
            ],
        ]);
    }
}
