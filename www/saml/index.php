<?php
require_once __DIR__.'/../../vendor/autoload.php';
include_once './../../config.php';
include_once './../../options.php';

require_once 'saml.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


function newid($length = 42) {
    $id = '_';
    for ($i = 0; $i < $length; $i++ ) $id .= dechex( rand(0,15) );
    return $id;
}

date_default_timezone_set('Europe/Amsterdam');

$app = new Silex\Application(); 
$app['debug'] = true;

$app->register(new Silex\Provider\SessionServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

$app->register(new Silex\Provider\MonologServiceProvider(), array(
        'monolog.logfile' => __DIR__.'/../../saml.log',
    ));

########## SAML ##########

$app->get('/', function (Request $request) use ($app) {
        $url = $request->getUriForPath('/') . 'metadata';
        return "This is a SAML endpoint<br/>See also the SAML 2.0 <a href='$url'>Metadata</a>";
    });

# SAML 2.0 Metadata

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

# send SAML request (SP initiated SAML Web SSO) - builtin SP for testing purposes

$app->get('/login/{nameid}', function (Request $request, $nameid) use ($config, $app) {
    $base = $request->getUriForPath('/');
    # remote IDP
    $sso_url = $base . "sso";   // default

    $loader = new Twig_Loader_Filesystem('views');
    $twig = new Twig_Environment($loader, array(
    	'debug' => true,
    ));
    $request = $twig->render('AuthnRequest.xml', array(
    	'ID' => newid(),
    	'Issuer' => $base . 'metadata',     // convention
    	'IssueInstant' => gmdate("Y-m-d\TH:i:s\Z", time()),
    	'Destination' => $sso_url,
    	'AssertionConsumerServiceURL' => $base . 'acs',
        'NameID' => $nameid,
    ));
    # use HTTP-Redirect binding
    $query  = 'SAMLRequest=' . urlencode(base64_encode(gzdeflate($request)));
    $query .= "&RelayState=$base"."session";
    $key = $config['keyfile']; // reuse key
    $location = $sso_url . '?' . saml20_sign_query($query, $key);
    return $app->redirect($location);
})->value('nameid', '');    // default nameid is empty, i.e. do not send a NameID in the request


# receive SAML request (IDP)

$app->get('/sso', function (Request $request) use ($config, $app) {

    $relayState = $request->get('RelayState');
    $app['session']->set('RelayState', $relayState);

        # TODO: check request contents, etc
    $samlrequest = $request->get('SAMLRequest');
    $samlrequest = gzinflate(base64_decode($samlrequest));
    $dom = new DOMDocument();
    $dom->loadXML($samlrequest);

	if ($dom->getElementsByTagName('AuthnRequest')->length === 0) {
	  throw new Exception('Unknown request on saml20 endpoint!');
	}

	$requestor = $dom->getElementsByTagName('Issuer')->item(0)->textContent;
    $app['monolog']->addInfo(sprintf("Requestor is '%s' .", $requestor));

        $md = $config['sp'][$requestor];
        if( !$md )
            throw new Exception("Unknown SP with entityID $requestor");

        // verify signature
        if( ($md['certfile']))
            saml20_verify_request($_SERVER['QUERY_STRING'], $config['certfile']); // use raw QS instead of normalized version!

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', "urn:oasis:names:tc:SAML:2.0:protocol" );
        $xpath->registerNamespace('saml', "urn:oasis:names:tc:SAML:2.0:assertion" );
        $query = "string(//saml:Subject/saml:NameID)";
        $nameid = $xpath->evaluate($query, $dom);
        $app['session']->set('RequestedSubject', $nameid);

    $authnrequest = $dom->getElementsByTagName('AuthnRequest')->item(0);
	$sprequestid = $authnrequest->getAttribute('ID');
        $app['session']->set('Requestor', $requestor);
        $app['session']->set('RequestID', $sprequestid);
        $url = $request->getUriForPath('/') . 'sso_return';
        if( $nameid ) {
            return $app->redirect("/tiqr/login?return=$url");
        } else {
            return $app->redirect("/tiqr/enrol?return=$url");
        }
//        return $app->redirect("/authn/login?return=$url");
});

$app->get('/sso_return', function (Request $request) use ($config, $app) {

        $relayState = $app['session']->get('RelayState');
        $authn = $app['session']->get('authn');
        $username = $authn['username'];
        $app['session']->set('authn', null);
        $expected = $app['session']->get('RequestedSubject');
        if( $expected and $username != $app['session']->get('RequestedSubject') ) {
            throw new Exception("Authentication requested for '$expected', but authenticated user is '$username'.");
        }

        $app['session']->set('RequestedSubject', null);

        $attrnameformat = NULL;
	    # assume solicited responses
        $requestor = $app['session']->get('Requestor');
    	$inResponseTo = htmlspecialchars( $app['session']->get('RequestID') );
        $app['session']->set('Requestor', null);
        $app['session']->set('RequestID', null);

        $base = $request->getUriForPath('/');
        $issuer = $base . 'metadata';       // convention
        $app['monolog']->addInfo(sprintf("Requestor was '%s' .", $requestor));
        if( !array_key_exists( $requestor, $config['sp']) ) {
            throw new Exception("Unknown SP with entityID '$requestor'");
        }
        $acs_url = $config['sp'][$requestor]['acs'];
        $app['monolog']->addInfo(sprintf("ACS URL is '%s' .", $acs_url));

        $loader = new Twig_Loader_Filesystem('views');
        $twig = new Twig_Environment($loader, array(
            'debug' => true,
        ));
        $response = $twig->render('Response.xml', array(
            'ResponseID' => newID(),
            'AssertionID' => newID(),
    	    'Audience' => $requestor,
            'Destination' => $acs_url,
            'InResponseTo' => $inResponseTo,
            'Issuer' => $issuer,
            'IssueInstant' => gmdate("Y-m-d\TH:i:s\Z", time() ),
            'NotBefore' => gmdate("Y-m-d\TH:i:s\Z", time() - 30),
            'NotOnOrAfter' => gmdate("Y-m-d\TH:i:s\Z", time() + 60 * 5),
            'NameID' => $username,
        ));

        # sign
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = FALSE;
        $dom->loadXML($response);
        $dom->formatOutput = TRUE;
        // sign the assertion
        // do not add certificate
        $dom = utils_xml_sign($dom, $config['keyfile'], $config['certfile']);
        $response = $dom->saveXML();

        # use POST binding
        $params = array(
            'Destination' => $acs_url,
            'SAMLResponse' => base64_encode($response),
        );
        if ($relayState !== NULL) {
            $params['RelayState'] = $relayState;
        }
        $app['session']->set('RelayState', null);
        $app['session']->set('authn', null); // disable SSO TODO re-enable

        return $twig->render('autosubmit.html', $params);

    });

# receive SAML response (SP)

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
        $location = $request->getUriForPath('/') . 'login';
        return "<a href='$location'>$nameid</a>";
});

$app->run();