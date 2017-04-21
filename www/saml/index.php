<?php
require_once __DIR__.'/../../vendor/autoload.php';
include_once './../../config.php';
include_once './../../options.php';

require_once 'container.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

function newID($length = 42) {
    $id = '_';
    for ($i = 0; $i < $length; $i++ ) $id .= dechex( rand(0,15) );
    return $id;
}

function sign($response, $keyfile, $certfile)
{
    $document = new DOMDocument();
    $previous = libxml_disable_entity_loader(true);
    $document->loadXML($response);
    libxml_disable_entity_loader($previous);
    $xml = $document->firstChild;
    $r = SAML2_Message::fromXML($xml);
    $algo = XMLSecurityKey::RSA_SHA256;
    $privateKey = new XMLSecurityKey($algo, array('type' => 'private'));
    $privateKey->loadKey($keyfile, true);
    $r->setSignatureKey($privateKey);
    $cert = file_get_contents($certfile);
    $r->setCertificates(array($cert));
    return $r->toSignedXML()->ownerDocument->saveXML();
}

Request::setTrustedProxies(array("127.0.0.1"));
if( isset($options["default_timezone"]) )
    date_default_timezone_set($options["default_timezone"]);


$app = new Silex\Application(); 
$app['debug'] = $options['debug'];

$app->register(new Silex\Provider\SessionServiceProvider(), array(
    'session.storage.options' => array(
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ),
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.handler' => $options['loghandler'],
    'monolog.name' => 'saml',
));

########## SAML ##########

$app->get('/', function (Request $request) use ($app) {
        $url = $request->getUriForPath('/') . 'metadata';
        return "This is a SAML endpoint<br/>See also the SAML 2.0 <a href='$url'>Metadata</a>";
    });

/*
 * SAML 2.0 IDP Metadata
 */
$app->get('/metadata', function (Request $request) use ($app, $config) {
    $loader = new Twig_Loader_Filesystem('views');
    $twig = new Twig_Environment($loader, array(
    	'debug' => true,
    ));
    $base = $request->getUriForPath('/');
    $metadata = $twig->render('metadata.xml', array(
    	'entityID' => $base . "metadata",	// convention: use metadata URL as entity ID
    	'SSO_Location' => $base . "sso",
        'certificate' => XMLSecurityDSig::staticGet509XCerts(file_get_contents($config['certfile']))[0],
    ));
    $response = new Response($metadata);
    $response->headers->set('Content-Type', 'text/xml');
    return $response;
});

/*
 * IDP: receive SAML request
 * Not a compliant SAML implementation: designed to work with stepup-gateway
 */
$app->get('/sso', function (Request $request) use ($config, $app) {

    SAML2_Compat_ContainerSingleton::setContainer(new Saml2Container(
        $app['monolog']
    ));

    // make sure external entities are disabled
    $previous = libxml_disable_entity_loader(true);
    // Make sure we're dealing with an AuthN request
    $binding = SAML2_Binding::getCurrentBinding();
    $samlrequest = $binding->receive();
    libxml_disable_entity_loader($previous);

    if (!($samlrequest instanceof SAML2_AuthnRequest)) {
        throw new Exception('Message received on authentication request endpoint wasn\'t an authentication request.');
    }

    $request_data = array(
        'relaystate' => $samlrequest->getRelayState(),
    );

    // Check for known issuer
    $issuer = $samlrequest->getIssuer();
    if ($issuer === NULL) {
        throw new Exception('Received message on authentication request endpoint without issuer.');
    }
    if( !array_key_exists( $issuer, $config['sp']) ) {
        throw new Exception("Unknown SP with entityID '$issuer'");
    }
    $md = $config['sp'][$issuer];
    $app['monolog']->addInfo(sprintf("Issuer is '%s' .", $issuer));
    $request_data['issuer'] = $issuer;

    // verify signature
    if( file_exists($md['certfile']))
    {
        if( $request->get('Signature') == null) {
            throw new Exception("SAML Authnrequest must be signed");
        }
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'public'));
        $key->loadKey($md['certfile'], true, true);
        $res = $samlrequest->validate($key);
        if(!$res)
            throw new Exception( "invalid signature" );
    } else {
        throw new Exception("Cannot load certificate from file " . $md['certfile']);
    }

    $nameid = $samlrequest->getNameId();
    $request_data['nameid'] = $nameid['Value'];

    $request_data['id'] = $samlrequest->getId();

    // save state for later when generating a response
    $app['session']->set('Request', $request_data );

    $url = $request->getUriForPath('/') . 'sso_return';
    if( $nameid ) { // login
        return $app->redirect("/tiqr/login?return=$url");
    } else {
        return $app->redirect("/tiqr/enrol?return=$url");
    }
});

/*
 * IDP: Finish SSO
 * Assumes saved request is validated
 */

$app->get('/sso_return', function (Request $request) use ($config, $app) {

    SAML2_Compat_ContainerSingleton::setContainer(new Saml2Container(
        $app['monolog']
    ));

    $request_data = $app['session']->get('Request');

    $rsp_params = array(
        'ResponseID' => newID(),
        'AssertionID' => newID(),
        'IssueInstant' => gmdate("Y-m-d\TH:i:s\Z", time() ),
        'NotBefore' => gmdate("Y-m-d\TH:i:s\Z", time() - 30),
        'NotOnOrAfter' => gmdate("Y-m-d\TH:i:s\Z", time() + 60 * 5),
    );

    // determine ACS URL from requestor
    $requestor = $request_data['issuer'];
    $app['monolog']->addInfo(sprintf("Requestor was '%s' .", $requestor));
    $rsp_params['Audience'] = $requestor;

    $acs_url = $config['sp'][$requestor]['acs'];
    $app['monolog']->addInfo(sprintf("ACS URL is '%s' .", $acs_url));
    $rsp_params['Destination'] = $acs_url;

    // check username of authenticated user
    $authn = $app['session']->get('authn');
    $username = $authn['username'];
    $nameid = $request_data['nameid'];
    if( $nameid and $username != $nameid ) {
        throw new Exception("Authentication requested for '$nameid', but authenticated user is '$username'.");
    }
    $rsp_params['NameID'] = $username;

    # assume solicited responses
   	$inResponseTo = htmlspecialchars( $request_data['id'] );
    $rsp_params['InResponseTo'] = $inResponseTo;

    $base = $request->getUriForPath('/');
    $issuer = $base . 'metadata';       // by convention
    $rsp_params['Issuer'] = $issuer;

    // render SAML response message
    $loader = new Twig_Loader_Filesystem('views');
    $twig = new Twig_Environment($loader);
    $response = $twig->render('Response.xml', $rsp_params);

//    # sign

    if( !file_exists( $config['keyfile']) ) {
        $app['monolog']->addWarning(sprintf("Cannot read key from file '%s' - sending Response unsigned", $config['keyfile']));
    } else {
        $response = sign($response,  $config['keyfile'],  $config['certfile']);
    }

    # use POST binding
    $params = array(
        'Destination' => $acs_url,
        'SAMLResponse' => base64_encode($response),
    );

    // restore relayState
    $relayState = $request_data['relaystate'];
    if ($relayState !== NULL) {
        $params['RelayState'] = $relayState;
    }

    $app['session']->set('authn', null); // no SSO
    $app['session']->set('Request', null);

    return $twig->render('autosubmit.html', $params);

});

$app->run();