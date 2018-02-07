#!/usr/bin/env php
<?php

require_once __DIR__ .  '/../lib/Tiqr/OATH/OCRA.php';

ini_set("allow_url_fopen=On", true);

function curl_post($url, array $post = NULL, array $options = array())
{
    $defaults = array(
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_URL => $url,
        #CURLOPT_FRESH_CONNECT => 1,
        CURLOPT_RETURNTRANSFER => 1,
        #CURLOPT_FORBID_REUSE => 1,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_HTTPHEADER => array( 'X-TIQR-Protocol-Version: 2'),
    );

    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if( ! $result = curl_exec($ch))
    {
        trigger_error(curl_error($ch));
    }
    curl_close($ch);
    return $result;
}

if( $argc < 2)
    die("need a tiqr URL\n");

$notificationType = 'APNS';
$notificationAddress = '0000000000111111111122222222223333333333';

$dbfile = __DIR__ . "/userdb.json";
if (file_exists($dbfile))
    $userdb = json_decode(file_get_contents($dbfile), true);
else {
    $userdb = [];
}

# tiqrenroll://https//<hostname>/<path>?key=<secret>
if (preg_match('#^tiqrenroll://#', $argv[1])) {

    $url = preg_replace('#^tiqrenroll://#', '', $argv[1]);
    echo $url . "\n";
    $metadata = file_get_contents($url);
    if( $metadata == 'false' ) {
        die("metadata gone\n");
    }
    $metadata = json_decode($metadata, true);
    print_r( $metadata);

    /*
    (
        [service] => stdClass Object
            (
                [displayName] => SURFconext Strong Authentication
                [identifier] => tiqr.test.surfconext.nl
                [logoUrl] => https://demo.tiqr.org/img/tiqrRGB.png
                [infoUrl] => https://tiqr.test.surfconext.nl
                [authenticationUrl] => https://tiqr.test.surfconext.nl/tiqr/tiqr.php
                [ocraSuite] => OCRA-1:HOTP-SHA1-6:QH10-S
                [enrollmentUrl] => https://tiqr.test.surfconext.nl/tiqr/tiqr.php?otp=cfac5de7c13cd16331fe6398e2d2535c892ed521053fd131215cf0eaaaf0d032
            )

        [identity] => stdClass Object
            (
                [identifier] => o5j71wo8am
                [displayName] => SURFconext
            )

    )
    */

    $service = $metadata['service'];
    $identity = $metadata['identity'];

    $enrollmentUrl = $service['enrollmentUrl'];
    $secret = bin2hex(openssl_random_pseudo_bytes(32));     // 3132333435363738393031323334353637383930313233343536373839303132

    $result = curl_post($enrollmentUrl, array(
        'operation' => 'register',
        'secret' => $secret,
        'notificationType' => $notificationType,
        'notificationAddress' => $notificationAddress,
    ));
    echo "$result\n";

    if( $result != "OK") {
	$result = json_decode($result, true); // {"responseCode":1}
	if( $result['responseCode'] != 1 )
            die("registration failed");
    }

    // store new identity
    $serviceid = $service['identifier'];
    // TODO: make sure this data is not overwriten
    $userdb[$serviceid]['authenticationUrl'] = $service['authenticationUrl'];
    $userdb[$serviceid]['ocraSuite'] = $service['ocraSuite'];

    $userid = $identity['identifier'];
    $userdb[$serviceid]['identities'][$userid] = $identity;
    $userdb[$serviceid]['identities'][$userid]['secret'] = $secret;

    file_put_contents($dbfile,json_encode($userdb));
}

# tiqrauth://[<userId>@]<serviceid>/<session>/<challenge>/<spIdentifier>/<protocolVersion>
elseif (preg_match('#^tiqrauth://#', $argv[1])) {

    $authn = $argv[1];
    $authn = preg_replace('#^tiqrauth://#', '', $authn);
    list($serviceid, $session, $challenge, $sp, $version) = explode('/', $authn);

    $userid = null;
    if( strpos($serviceid, '@') ) {
        list($userid, $serviceid) = explode('@',$serviceid);
    }

    if (! $userdb)
        die("userdb not found\n");

    $service = $userdb[$serviceid];
    $authenticationUrl  = $service['authenticationUrl'];
    $ocraSuite          = $service['ocraSuite'];
    $identities         = $service['identities'];

    if( is_null($userid) )
        $userid = array_slice(array_keys($identities),-1)[0];   // latest element
    $user = $identities[$userid];  // TODO: match userid
    $secret = $user['secret'];

    $response = OCRA::generateOCRA($ocraSuite, $secret, "", $challenge, "", $session, "");
    echo "Response: ", $response, "\n";
#$response = 0;
    $result = curl_post($authenticationUrl, array(
        'operation' => 'login',
        'sessionKey' => $session,
        'userId' => $userid,
        'response' => $response,
        'notificationType' => $notificationType,
        'notificationAddress' => $notificationAddress,
    ));
    echo "$result\n";
	// {"responseCode":201,"attemptsLeft":2}
}
