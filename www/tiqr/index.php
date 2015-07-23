<?php

require_once __DIR__.'/../../vendor/autoload.php';

include('../../options.php');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// use Symfony\Component\Translation\Loader\YamlFileLoader;

date_default_timezone_set('Europe/Amsterdam');

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
    'monolog.handler' => new Monolog\Handler\SyslogHandler('stepup-tiqr'),
    'monolog.name' => 'authn',
));

$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('nl'),
));

$app['translator.domains'] = array(
    'messages' => array(
        'en' => array(
            'enrol' => 'New Account',
            'idle' => 'Timeout',
            'timeout_alert' => "Timeout. Please try again by refreshing this page.",
            'initialized' => "Scan the code with the tiqr app on your phone to create a tiqr account.",
            'retrieved' => "Acticate your account on your phone.",
            'processed' => "",
            'finalized' => "Your account is ready for use.",

            'hello'     => 'Hello %name%',
            'goodbye'   => 'Goodbye %name%',
            'enrol'   => 'Enrol',
            'timeout' => 'Timeout. Please try again by refreshing this page',

        ),
        'nl' => array(
            'enrol' => 'Nieuw Account',
            'idle' => 'Timeout',
            'timeout_alert' => "Timeout. Probeer nogmaals door deze pagina te verversen.",
            'initialized' => "Scan de code met de tiqr app op uw telefoon om een tiqr account aan te maken.",
            'retrieved' => "Activeer uw account op uw telefoon.",
            'processed' => "",
            'finalized' => "Uw account is gereed voor gebruik.",
            'hello'     => 'Hallo %name%',
            'goodbye'   => 'Dag %name%',
            'enrol'   => 'Aanmaken',
            'timeout' => 'Timeout. Probeer nogmaals door deze pagina te verversen.',
        ),
    ),
    'validators' => array(
        'nl' => array(
            'This value should be a valid number.' => 'Deze waarde moet numeriek zijn.',
        ),
    ),
);

/*
// simple test for translations:
$app->get('/x/{name}', function ($name) use ($app) {
        return $app['translator']->trans('enrol', array('%name%' => $name),'messages','nl');
    });
*/

$app->before(function ($request) {
        $request->getSession()->start();
    });

$tiqr = new Tiqr_Service($options);

### tiqr Authentication ###

$app->get('/login', function (Request $request) use ($app, $tiqr, $options) {
    $base = $request->getUriForPath('/');
    $return = filter_var($request->get('return'),FILTER_VALIDATE_URL);
    if( $return == false ) {
        $return = $base;
    }
    $sid = $app['session']->getId();
    $userdata = $tiqr->getAuthenticatedUser($sid);
    $app['monolog']->addInfo(sprintf("[%s] userdata '%s'", $sid, $userdata));
    if (!is_null($userdata)) {
        $app['session']->set('authn', array('username' => $userdata));  // logged in!
        return $app->redirect($return);
    }

    // not logged in...
    $request_data = $app['session']->get('Request');
    $id = $request_data['nameid']; // do we need to log in some specific user?
    if ($id === '') $id = null;

    $loader = new Twig_Loader_Filesystem('views');
    $twig = new Twig_Environment($loader);
    $login = $twig->render('index.html', array(
        'self' => $base,
        'return_url' => $return,
        'id' => $id,
    ));
    return new Response($login);
});

$app->get('/qr', function (Request $request) use ($app, $tiqr, $options) {
    $base = $request->getUriForPath('/');
    $return = filter_var($request->get('return'),FILTER_VALIDATE_URL);
    if( $return == false ) {
        $return = $base;
    }
    $sid = $app['session']->getId();
    $userdata = $tiqr->getAuthenticatedUser($sid);
    if( !is_null($userdata) ) {
        $app['monolog']->addInfo(sprintf("[%s] userdata '%s'", $sid, $userdata));
        $app['session']->set('authn', array('username' => $userdata));
        return $app->redirect($return);
    }

    $request_data = $app['session']->get('Request');
    $id = $request_data['nameid']; // do we need to log in some specific user?
    if ($id === '') $id = null;

    $sessionKey = $tiqr->startAuthenticationSession($id,$sid); // prepares the tiqr library for authentication
    $app['monolog']->addInfo(sprintf("[%s] started new login session, session key = '%s", $sid, $sessionKey));

    $userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);
    if( $id ) {
        $notificationType = $userStorage->getNotificationType($id);
        $notificationAddress = $userStorage->getNotificationAddress($id);
        $app['monolog']->addInfo(sprintf("client has notification type [%s], address [%s]", $notificationType, $notificationAddress));
        $translatedAddress = $tiqr->translateNotificationAddress($notificationType, $notificationAddress);
        $app['monolog']->addInfo(sprintf("client translated address is [%s]", $translatedAddress));
        if ($translatedAddress) {
            $result = $tiqr->sendAuthNotification($sessionKey, $notificationType, $translatedAddress);
            if( $result ) {
                $app['monolog']->addInfo(sprintf("sent push notification to [%s]", $translatedAddress));
            } else {
                $app['monolog']->addWarning(sprintf("Failure sending push notification to [%s]", $translatedAddress));
            }
        } else {
            $app['monolog']->addWarning(sprintf("No %s translated address available for [%s]", $notificationType, $notificationAddress));
        }
    }
    $tiqr->generateAuthQR($sessionKey);
    return "";
});

$app->get('/verify', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $userdata = $tiqr->getAuthenticatedUser($sid);
        if( isset($userdata) ) {
            $app['session']->set('authn', array('username' => $userdata));
            $tiqr->logout($sid);
            $app['monolog']->addInfo(sprintf("[%s] verified authenticated user '%s'", $sid, $userdata));
        }
        return new Response(
            $userdata,
            Response::HTTP_OK,
            array('content-type' => 'text/plain')
        );
});

$app->get('/', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $userdata = $tiqr->getAuthenticatedUser($sid);
        if( $userdata ) {
            return $userdata;
        } else {
            return "n/a";
        }
    });

$app->get('/logout', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $tiqr->logout($sid);
        $app['session']->set('authn', null);
        return "You are logged out";
    });

### tiqr Enrolment ###

$app->get('/enrol', function (Request $request) use ($app, $tiqr) {
    $base = $request->getUriForPath('/');
    $return = filter_var($request->get('return'),FILTER_VALIDATE_URL);
    if( $return == false ) {
        $return = $base;
    }
    $loader = new Twig_Loader_Filesystem('views');
    $twig = new Twig_Environment($loader);
    $enrol = $twig->render('enrol.html', array(
        'self' => $base,
        'return_url' => $return,
    ));
    $response = new Response($enrol);
    return $response;
});

$app->get('/qr_enrol', function (Request $request) use ($app, $tiqr) {
    $base = $request->getUriForPath('/');
    // starting a new enrollment session
    $sid = $app['session']->getId();
    $uid = generate_id(); // TODO uniqueness
    $app['session']->set('authn', array('username' => $uid)); // TODO check
    $displayName = "Stepup User";      # TODO
    $app['monolog']->addInfo(sprintf("[%s] enrol uid '%s' (%s).", $sid, $uid, $displayName));
    $key = $tiqr->startEnrollmentSession($uid, $displayName, $sid);
    $app['monolog']->addInfo(sprintf("[%s] start enrol uid '%s' with session key '%s'.", $sid, $uid, $key));
    $metadataURL = $base . "tiqr.php?key=$key";
    $app['monolog']->addInfo(sprintf("[%s] metadata URL for uid '%s' is '%s'.", $sid, $uid, $metadataURL));
    $tiqr->generateEnrollmentQR($metadataURL);
    return "";
});

### status

$app->get('/status', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $status = $tiqr->getEnrollmentStatus($sid);
        $app['monolog']->addInfo(sprintf("[%s] status is %d", $sid, $status));
        return $status;
    });

$app->get('/done', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $tiqr->resetEnrollmentSession($sid);
        $app['monolog']->addInfo(sprintf("[%s] reset enrollment", $sid));
        return "done";
    });


$app->run();
