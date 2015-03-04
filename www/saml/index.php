<?php
require_once __DIR__.'/../../vendor/autoload.php';

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

##########

$app->get('/', function (Request $request) use ($app) {
    $url = $request->getUriForPath('/') . 'metadata';
    return "This is a SAML endpoint<br/>See also the SAML 2.0 <a href='$url'>Metadata</a>";
});

$app->get('/logout', function (Request $request) use ($app) {
    $app['session']->set('user', null);
    $base = $request->getUriForPath('/');
    return $app->redirect($base);
    #$app->abort(404, "not implemented.");
});

$app->get('/session', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return "n/a";
    }
    $nameid = $user['username'];
    return 'NameID: '.$app->escape($nameid);
});

########## SAML ##########

# SAML 2.0 Metadata

$app->get('/metadata', function (Request $request) use ($app) {
    $loader = new Twig_Loader_Filesystem('views');
    $twig = new Twig_Environment($loader, array(
    	'debug' => true,
    ));
    $base = $request->getUriForPath('/');
    $metadata = $twig->render('metadata.xml', array(
    	'entityID' => $base . "metadata",	// convention: use metadata URL as entity ID
    	'SSO_Location' => $base . "sso",
    	'ACS_Location' => $base . "acs",
            // TODO: certificates
    ));
    $response = new Response($metadata);
    $response->headers->set('Content-Type', 'text/xml');
    return $response;
});

# send SAML request (SP initiated SAML Web SSO)

$app->get('/login', function (Request $request) use ($app) {
        // TODO: sign request
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
         //   'NameID' => 'joost',
    ));
    # use HTTP-Redirect binding
    $query  = 'SAMLRequest=' . urlencode(base64_encode(gzdeflate($request)));
    $query .= "&RelayState=$base"."session";
    $location = "$sso_url?$query";
    return $app->redirect($location);
});

# receive SAML request (IDP)

$app->get('/sso', function (Request $request) use ($app) {
// TODO verify signature
    $relaystate = $request->get('RelayState');
    $app['session']->set('RelayState', $relaystate);

        # TODO: check response, etc
    $samlrequest = $request->get('SAMLRequest');
    $samlrequest = gzinflate(base64_decode($samlrequest));
    $dom = new DOMDocument();
    $dom->loadXML($samlrequest);

	if ($dom->getElementsByTagName('AuthnRequest')->length === 0) {
	  throw new Exception('Unknown request on saml20 endpoint!');
	}

	$requestor = $dom->getElementsByTagName('Issuer')->item(0)->textContent;
    $app['monolog']->addInfo(sprintf("Requestor is '%s' .", $requestor));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', "urn:oasis:names:tc:SAML:2.0:protocol" );
        $xpath->registerNamespace('saml', "urn:oasis:names:tc:SAML:2.0:assertion" );
        $query = "string(//saml:Subject/saml:NameID)";
        $nameid = $xpath->evaluate($query, $dom);
        $app['session']->set('RequestedSubject', $nameid);

    $authnrequest = $dom->getElementsByTagName('AuthnRequest')->item(0);
	$sprequestid = $authnrequest->getAttribute('ID');
        $app['session']->set('RequestID', $sprequestid);
        $url = $request->getUriForPath('/') . 'sso_return';
        return $app->redirect("/tiqr/login?return=$url"); // TODO return URL
//        return $app->redirect("/authn/login?return=$url"); // TODO return URL

});

$app->get('/sso_return', function (Request $request) use ($app) {

        $relayState = $app['session']->get('RelayState');
        $authn = $app['session']->get('authn');
        $username = $authn['username'];
        $expected = $app['session']->get('RequestedSubject');
        if( $expected and $username != $app['session']->get('RequestedSubject') ) {
            throw new Exception("Authentication requested for '$expected', but authenticated user is '$username'.");
        }

        $app['session']->set('RequestedSubject', null);

        $attrnameformat = NULL;
	    # assume solicited responses
    	$inResponseTo = htmlspecialchars( $app['session']->get('RequestID') );
        $app['session']->set('RequestID', null);

        $base = $request->getUriForPath('/');
        $issuer = $base . 'metadata';       // convention
        $acs_url = $base . "acs";      # TODO config

        $loader = new Twig_Loader_Filesystem('views');
        $twig = new Twig_Environment($loader, array(
            'debug' => true,
        ));
        $response = $twig->render('Response.xml', array(
            'ResponseID' => newID(),
            'AssertionID' => newID(),
//	    	'Audience' => $audience,
            'Destination' => $acs_url,
            'InResponseTo' => $inResponseTo,
            'Issuer' => $issuer,
            'IssueInstant' => gmdate("Y-m-d\TH:i:s\Z", time() ),
            'NotBefore' => gmdate("Y-m-d\TH:i:s\Z", time() - 30),
            'NotOnOrAfter' => gmdate("Y-m-d\TH:i:s\Z", time() + 60 * 5),
            'NameID' => $username,
        ));
        # use POST binding
        $params = array(
            'Destination' => $acs_url,
            'SAMLResponse' => base64_encode($response),
        );
        if ($relaystate !== NULL) {
            $params['RelayState'] = $relaystate;
        }
        $app['session']->set('RelayState', null);

        $app['session']->set('authn', null); // disable SSO TODO re-enable

        return $twig->render('autosubmit.html', $params);
        // TODO: sign
});

# receive SAML response (SP)

$app->post('/acs', function (Request $request) use ($app) {
    # TODO: check signature, response, etc
    $relayState = $request->get('RelayState');
    if (!$relayState) $relayState = $request->getUriForPath('/') . 'session';

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
    $app['session']->set('user', array('username' => $nameid));
    return $app->redirect($relayState);
});

$app->run();