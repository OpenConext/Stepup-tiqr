<?php

/**
 * handle requests from tiqr client
 */

include('../../options.php');

date_default_timezone_set('Europe/Amsterdam'); // TODO

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
    $proto = "http://";
    if( array_key_exists('HTTPS', $_SERVER))
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

function login( $sessionKey, $userId, $response, $notificationType, $notificationAddress )
{
    global $options;
    global $userStorage;

    $tempBlockDuration = array_key_exists('temporaryBlockDuration', $options) ? $options['temporaryBlockDuration'] : 0;
    $maxTempBlocks = array_key_exists('maxTemporaryBlocks', $options) ? $options['maxTemporaryBlocks'] : 0;
    $maxAttempts = array_key_exists('maxAttempts', $options) ? $options['maxAttempts'] : 3;
    logger()->addInfo(sprintf("tempBlockDuration: %s, maxTempBlocks: %s, maxAttempts: %s, )", $tempBlockDuration, $maxTempBlocks, $maxAttempts));

    if( !$userStorage->userExists( $userId ) ) {
        return 'INVALID_USER';
    } elseif( $userStorage->isBlocked($userId, $tempBlockDuration) ) {
        return 'ACCOUNT_BLOCKED:'.$tempBlockDuration;
    }
    $userSecret = $userStorage->getSecret($userId);
    $tiqr = new Tiqr_Service($options);
    $result = $tiqr->authenticate($userId,$userSecret,$sessionKey,$response);
    switch( $result ) {
        case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
            // Reset the login attempts counter
            $userStorage->setLoginAttempts($userId, 0);
            // update notification information if given, on successful login
            if( isset($notificationType) ) {
                $userStorage->setNotificationType($userId, $notificationType);
                if( isset($notificationAddress) ) {
                    $userStorage->setNotificationAddress($userId, $notificationAddress);
                }
            }
            return "OK";
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE:
            return 'INVALID_CHALLENGE';
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_REQUEST:
            return 'INVALID_REQUEST';
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE:
            $attempts = $userStorage->getLoginAttempts($userId);
            if (0 == $maxAttempts) { // unlimited
                return  'INVALID_RESPONSE';
            }
            else if ($attempts < ($maxAttempts-1)) {
                $userStorage->setLoginAttempts($userId, $attempts+1);
            } else {
                // Block user and destroy secret
                $userStorage->setBlocked($userId, true);
                $userStorage->setSecret($userId, NULL);
                $userStorage->setLoginAttempts($userId, 0);

                if ($tempBlockDuration > 0) {
                    $tempAttempts = $userStorage->getTemporaryBlockAttempts($userId);
                    if (0 == $maxTempBlocks) {
                        // always a temporary block
                        $userStorage->setTemporaryBlockTimestamp($userId, date("Y-m-d H:i:s"));
                    }
                    else if ($tempAttempts < ($maxTempBlocks - 1)) {
                        // temporary block which could turn into a permanent block
                        $userStorage->setTemporaryBlockAttempts($userId, $tempAttempts+1);
                        $userStorage->setTemporaryBlockTimestamp($userId, date("Y-m-d H:i:s"));
                    }
                    else {
                        // remove timestamp to make this a permanent block
                        $userStorage->setTemporaryBlockTimestamp($userId, false);
                    }
                }
            }
            $attemptsLeft = ($maxAttempts-1)-$attempts;
            return 'INVALID_RESPONSE:'.$attemptsLeft;
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_USERID:
            return 'INVALID_USERID'; // INVALID_USER ?
            break;
        default:
            return 'ERROR';
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
    logger()->addDebug(sprintf("storing new entry for user '%s'", $userid));
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
        $key = preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['key']);

        logger()->addInfo(sprintf("received metadata request (key='%s')", $key));
        $metadata = metadata($key);
        if( $metadata == false)
            logger()->addError("ERROR: empty metadata - metadata was either lost or destroyed after retrieval");

        Header("Content-Type: application/json");
        echo json_encode($metadata);
        logger()->addInfo("sent metadata");
        break;
    case "POST":
        $version = array_key_exists('HTTP_X_TIQR_PROTOCOL_VERSION', $_SERVER) ? $_SERVER['HTTP_X_TIQR_PROTOCOL_VERSION'] : "1";
        logger()->addDebug(sprintf("Received POST request from tiqr client version %s", $version));
        $operation = preg_replace("/[^a-z]+/", "", $_POST['operation']);
        logger()->addInfo(sprintf("received operation '%s'", $operation));
        $notificationType = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['notificationType']);
        $notificationAddress = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['notificationAddress']);

        switch( $operation ) {
            case "register":
                $enrollmentSecret = preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['otp']); // enrollmentsecret relayed by tiqr app
                logger()->addDebug("received enrollmentSecret");
                $secret = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['secret']);
                $result = register( $enrollmentSecret, $secret, $notificationType, $notificationAddress );
                echo $result;
                break;
            case "login":
                $sessionKey = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['sessionKey']);
                $userId = preg_replace("/[^a-zA-Z0-9_-]+/", "", $_POST['userId']);
                $response = preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['response']);
                logger()->addInfo(sprintf("received authentication response (%s) from user '%s' for session '%s'", $response, $userId, $sessionKey));
                $result = login( $sessionKey, $userId, $response, $notificationType, $notificationAddress );
                logger()->addInfo(sprintf("Authentication response is '%d'", $result));
                echo $result;
                break;
            default:
                logger()->addError(sprintf("ERROR: unknown operation (%s) in POST request", $operation));
                break;
        }
        break;
}
