<?php

/**
 * handle requests from tiqr client
 */

include('../../options.php');

$logger = null;

function logger() {
    global $logger, $options ;
    if( $logger )
        return $logger;
    $logger = new Monolog\Logger('tiqr');
    $logger->pushHandler(new Monolog\Handler\SyslogHandler('stepup-tiqr'));
    return $logger;
}

function base() {
    $proto = "on" === $_SERVER['HTTPS'] ? "https://" : "http://";
    /** @var $baseUrl string */
    return $proto . $_SERVER['HTTP_HOST'];
}

function metadata($key)
{
    global $options;
    $tiqr = new Tiqr_Service($options);
    // exchange the key submitted by the phone for a new, unique enrollment secret
    $enrollmentSecret = $tiqr->getEnrollmentSecret($key);
    // $enrollmentSecret is a one time password that the phone is going to use later to post the shared secret of the user account on the phone.
    $enrollmentUrl     = base() . "/tiqr/tiqr.php?otp=$enrollmentSecret"; // todo
    $authenticationUrl = base() . "/tiqr/tiqr.php";
    //Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session, the data is destroyed after your first call.
    $metadata = $tiqr->getEnrollmentMetadata($key, $authenticationUrl, $enrollmentUrl);
    return $metadata;
}

function login( $sessionKey, $userId, $response )
{
    global $options;
    global $userStorage;
    $userSecret = $userStorage->getSecret($userId);
    $tiqr = new Tiqr_Service($options);
    $result = $tiqr->authenticate($userId,$userSecret,$sessionKey,$response);
    //Note that actually blocking the user and keeping track of login attempts is a responsibility of your application,
    switch( $result ) {
        case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
            //echo 'AUTHENTICATED';
            return "OK";
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE:
            return 'INVALID_CHALLENGE';
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_REQUEST:
            return 'INVALID_REQUEST';
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE:
            return 'INVALID_RESPONSE';
//        echo “INVALID_RESPONSE:3”;  // 3 attempts left
//        echo “INVALID_RESPONSE:0”;  // blocked
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_USERID:
            return 'INVALID_USERID';
            break;
    }
}

function register( $enrollmentSecret, $secret, $notificationType, $notificationAddress )
{
    global $options;
    global $userStorage;
    $tiqr = new Tiqr_Service($options);
    // note: userid is never sent together with the secret! userid is retrieved from session
    $userid = $tiqr->validateEnrollmentSecret($enrollmentSecret); // or false if invalid
    logger()->addDebug("storing new entry for user '$userid'");
    $userStorage->createUser($userid,"anonymous"); // TODO displayName
    $userStorage->setSecret($userid,$secret);
    $userStorage->setNotificationType($userid, $notificationType);
    $userStorage->setNotificationAddress($userid, $notificationAddress);
    $tiqr->finalizeEnrollment($enrollmentSecret);
    return "OK";
}

switch( $_SERVER['REQUEST_METHOD'] ) {
    case "GET":
        // metadata request
        // retrieve the temporary reference to the user identity
        $key = $_GET['key'];
        logger()->addInfo("received metadata request (key=$key)");
        $metadata = metadata($key);
        if( $metadata == false)
            logger()->addError("ERROR: empty metadata - metadata was either lost or destroyed after retrieval");
        else
            logger()->addInfo("sending metadata", $metadata);
        Header("Content-Type: application/json");
        echo json_encode($metadata);
        break;
    case "POST":
        logger()->addInfo("tiqr client version is " . $_SERVER['HTTP_X_TIQR_PROTOCOL_VERSION']);
        logger()->addInfo("received POST request", $_POST);
        $operation = $_POST['operation'];
//        $version = $_POST['version'];
        $notificationType = $_POST['notificationType'];
        $notificationAddress = $_POST['notificationAddress'];
//        $language = $_POST['language'];

        switch( $operation ) {
            case "register":
                $enrollmentSecret = $_GET['otp']; // enrollmentsecret relayed by tiqr app
                logger()->addDebug("received enrollmentSecret");
                $secret = $_POST['secret'];
                $result = register( $enrollmentSecret, $secret, $notificationType, $notificationAddress );
                echo $result;
                break;
            case "login":
                $sessionKey = $_POST['sessionKey'];
                $userId = $_POST['userId'];
                $response = $_POST['response'];
                logger()->addInfo("received authentication response ($response) from user $userId for session $sessionKey");
                $result = login( $sessionKey, $userId, $response );
                logger()->addInfo("response $result");
                echo $result;
                break;
            default:
                logger()->addError("ERROR: unknown operation ($operation) in POST request");
                break;
        }
        break;
}