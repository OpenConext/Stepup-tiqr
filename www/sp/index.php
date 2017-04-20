<?php
require_once __DIR__.'/../../vendor/autoload.php';
include_once './../../config.php';
include_once './../../options.php';

require_once 'container.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// TODO: move
//date_default_timezone_set('Europe/Amsterdam');
Request::setTrustedProxies(array("127.0.0.1"));

$app = new Silex\Application(); 
$app['debug'] = $options['debug'];

//$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.handler' => $options['loghandler'],
    'monolog.name' => 'saml-sp',

));

########## SAML ##########

$app->get('/', function (Request $request) use ($app) {
        return "This is a SAML endpoint<br/><a href='login'>Testing only!</a>";
    });

/*
 * SP: Generate SAML AuthnRequest (for testing purposes)
 */
$app->get('/login/{nameid}', function (Request $request, $nameid) use ($config, $app)
{
    SAML2_Compat_ContainerSingleton::setContainer(new Saml2Container(
        $app['monolog']
    ));
    $base = $request->getUriForPath('/');
    $saml_request = new SAML2_AuthnRequest();
    $saml_request->setIssuer($base . 'metadata');
    $saml_request->setDestination($base . "../saml/sso");
    $saml_request->setAssertionConsumerServiceURL($base . 'acs');
    $saml_request->setRelayState($base . "session");
    if( $nameid )
        $saml_request->setNameId(array('Value' => $nameid, 'Format' => SAML2_Const::NAMEID_UNSPECIFIED));
    // Sign request
    $keyfile = $config['keyfile']; // reuse key
    if( !file_exists($keyfile) ) {
        $app['monolog']->addWarning("Cannot read key from file $keyfile - sending unsigned SAML AuthnRequest");
    } else {
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($keyfile, true);
        $saml_request->setSignatureKey($key);
    }
    // Use Redirect binding regardless of what the SP asked for
    $binding = new SAML2_HTTPRedirect();
    $destination = $binding->getRedirectURL($saml_request);
    $app['monolog']->addDebug('Redirect to ' . $destination);
    return $app->redirect($destination);
})->value('nameid', '');    // default nameid is empty, i.e. do not send a NameID in the request


/*
 * SP: receive SAML response
 */

$app->post('/acs', function (Request $request) use ($app) {
    # TODO: check signature, response, etc
    $response = $request->get('SAMLResponse');
    $response = base64_decode($response);
    $dom = new DOMDocument();
    $dom->loadXML($response);
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('saml', "urn:oasis:names:tc:SAML:2.0:assertion" );
    $query = "string(//saml:Assertion[1]/saml:Subject/saml:NameID)";
    $nameid = $xpath->evaluate($query, $dom);
    if (!$nameid) {
        throw new Exception('Could not locate nameid element.');
    }
        $location = $request->getUriForPath('/') . 'login/' . $nameid;
        return "<a href='$location'>$nameid</a>";
});

$app->run();