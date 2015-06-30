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
        $this->logger->warning("unexpected invocation of Saml2Container::redirect method");
    }

    /**
     * @param string $url
     * @param array $data
     */
    public function postRedirect($url, $data = array())
    {
        $this->logger->warning("invocation of Saml2Container::postRedirect method");
        # use POST binding
        $params = array(
            'url' => $url,
            'data' => $data,
        );

        $loader = new Twig_Loader_Filesystem( __DIR__.'/views');
        $twig = new Twig_Environment($loader, array(
//            'cache' => '/path/to/compilation_cache',
        ));
        echo $twig->render('post.html', $params);
    }
}


//include_once  __DIR__ . '/../../vendor/fr3d/xmlseclibs/xmlseclibs.php';

function utils_xml_create($xml, $preserveWhiteSpace = FALSE) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = $preserveWhiteSpace;
        $dom->loadXML($xml);
        $dom->formatOutput = TRUE;
        return $dom;
}

function utils_xml_sign($dom, $keyfile, $certfile) {
        // remove whitespace without breaking signature
        $dom = utils_xml_create($dom->saveXML(), TRUE);
        $dsig = new XMLSecurityDSig();
        $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $root = $dom->getElementsByTagName('Assertion')->item(0);
        assert('$root instanceof DOMElement');
        $insert_into = $dom->getElementsByTagName('Assertion')->item(0);
        $insert_before = $insert_into->getElementsByTagName('Subject')->item(0);
        $dsig->addReferenceList(array($root), XMLSecurityDSig::SHA1,
                        array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
                        array('id_name' => 'ID'));
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
        $objKey->loadKey($keyfile, TRUE);
        $dsig->sign($objKey);
        $cert = $certfile;
        $contents = file_get_contents($cert);
        $dsig->add509Cert($contents, TRUE);
        $dsig->insertSignature($insert_into, $insert_before);
        return $dom;
}

function utils_xml_sign_generic($dom, $key, $id_name, $root_name, $insert_into_name = NULL, $insert_before_name = NULL, $cert = NULL) {
    // remove whitespace without breaking signature
    $dom = utils_xml_create($dom->saveXML(), TRUE);
    $dsig = new XMLSecurityDSig();
    $dsig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
    $root = $dom->getElementsByTagName($root_name)->item(0);
    assert('$root instanceof DOMElement');
    $insert_into = isset($insert_into_name) ? $dom->getElementsByTagName($insert_into_name)->item(0) : NULL;
    if (isset($insert_into)) {
        assert('$insert_into instanceof DOMElement');
        $insert_before = isset($insert_before_name) ? $insert_into->getElementsByTagName($insert_before_name)->item(0) : $insert_into->firstChild;
    } else {
        $insert_into = $root;
        $insert_before = NULL;
    }
    $dsig->addReferenceList(array($root), XMLSecurityDSig::SHA1,
        array('http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N),
        array('id_name' => $id_name));
    $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
    $objKey->loadKey($key, TRUE);
    $dsig->sign($objKey);
    if (isset($cert)) {
        if (is_array($cert)) $cert = $cert[0];
        $contents = file_get_contents($cert);
        if ($contents === FALSE) {
            throw new Exception('Could not read certificate file: ' . $cert);
        }
        $dsig->add509Cert($contents, TRUE);
    }
    $dsig->insertSignature($insert_into, $insert_before);
    return $dom;
}

function saml20_sign_query($query, $key) {
    $algURI = XMLSecurityKey::RSA_SHA256;
    $query = $query . "&" . "SigAlg=" . urlencode($algURI);
    $xmlseckey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
    $xmlseckey->loadKey($key, TRUE);
    $signature = $xmlseckey->signData($query);
    $query = $query . "&" . "Signature=" . urlencode(base64_encode($signature));
    return $query;
}

/**
 * Verify a SAML 2.0 redirect request (authn, logout) by checking the optional signature.
 *
 * @param       string  querystring   the query string containing all relevant signing parameters
 * @param       string  $cert   the filename of the certificate with the public key used for verifying the signature
 */
function saml20_verify_request($querystring, $certs) {
    $relayState = ''; $sigAlg = ''; $signedQuery = ''; $signature = ''; $sigQuery = ''; $algorithm = NULL;

    foreach (explode('&', $querystring) as $e) {
        list($name, $value) = explode('=', $e, 2);
        switch ($name) {
            case 'SAMLRequest':
            case 'SAMLResponse':
                $sigQuery = $name . '=' . $value;
                break;
            case 'Signature':
                $signature = base64_decode(urldecode($value));
                break;
            case 'RelayState':
                $relayState = '&RelayState=' . $value;
                break;
            case 'SigAlg':
                $algorithm = urldecode($value);
                $sigAlg = '&SigAlg=' . $value;
                break;
        }
    }

    if ($algorithm == NULL ) {
        throw new Exception('Request should be signed but no signature was found!');
    }

    $signedQuery = $sigQuery . $relayState . $sigAlg;

    if (!is_array($certs)) $certs = array($certs);
    $result = FALSE;
    foreach ($certs as $cert) {
        if (!file_exists($cert)) {
            throw new Exception('Could not find verification certificate file: ' . $cert);
        }

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'public'));
        $key->loadKey($cert, TRUE, TRUE);

        switch ($algorithm) {
            case XMLSecurityKey::RSA_SHA256:
                if ($key->type !== XMLSecurityKey::RSA_SHA1) {
                    throw new Exception('Invalid key type for validating signature on query string.');
                }
                $result = ($key->verifySignature($signedQuery, $signature) == 1);
                break;
            default:
                throw new Exception('Unknown signature algorithm: ' . var_export($algorithm, TRUE));
        }

        if ($result === TRUE) break;
    }
    if ($result === FALSE) {
        throw new Exception('Unable to validate Signature on query string.');
    }
}