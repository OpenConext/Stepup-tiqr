<?php

require_once __DIR__.'/../../vendor/autoload.php';

include('../../options.php');

# todo i18n options data (eg SP displayname)

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\Loader\YamlFileLoader;

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
    'monolog.handler' => $options['loghandler'],
    'monolog.name' => 'authn',
));

// Set locale
if (!$locale = $app['session']->get('locale')) $locale = $options['default_locale'];
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('nl'),
    'locale' => $locale,
));

$app['translator']->addLoader('yaml', new YamlFileLoader());
$app['translator']->addResource('yaml', __DIR__.'/locales/en.yml', 'en');
$app['translator']->addResource('yaml', __DIR__.'/locales/nl.yml', 'nl');

$app->before(function (Request $request) {
        $request->getSession()->start();
    });

$tiqr = new Tiqr_Service($options);

### tiqr Authentication ###

$app->get('/login', function (Request $request) use ($app, $tiqr, $options) {
    $locale = $app['translator']->getLocale();
    $locales = array_keys($options['translation']);
    $here = urlencode($app['request']->getUri()); // Is this allways correct?

    $sid = $app['session']->getId();

    $base = $request->getUriForPath('/');
    $return = stripslashes(filter_var($request->get('return'),FILTER_VALIDATE_URL));
    if( $return == false ) {
        $return = $base;
    }
    if(strpos($return, $request->getSchemeAndHttpHost() . '/') !== 0) {
        $app['monolog']->addInfo(sprintf("[%s] illegal return URL '%s'", $sid, $return));
        $return = $base;
    }

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

    if (!$app['session']->get('keepSessionKey') || !$sessionKey = $app['session']->get('sessionKey')) {
        $sessionKey = $tiqr->startAuthenticationSession($id,$sid); // prepares the tiqr library for authentication
        $app['session']->set('sessionKey', $sessionKey);
    }
    $app['monolog']->addInfo(sprintf("[%s] started new login session, session key = '%s", $sid, $sessionKey));

    $authUrl = $tiqr->generateAuthURL($sessionKey);
//    $authUrl = $tiqr->generateAuthURL($sessionKey).'?return='.urlencode($return);

    return $app['twig']->render('index.html', array(
        'self' => $base,
        'return_url' => $return,
        'id' => $id,
        'authUrl' => $authUrl,
        'sessionKey' => $sessionKey,
        'here' => $here,
        'locale' => $locale,
        'locales' => $locales,
    ));
});

$app->get('/qr', function (Request $request) use ($app, $tiqr, $options) {

    $sid = $app['session']->getId();

    $request_data = $app['session']->get('Request');
    $id = $request_data['nameid']; // do we need to log in some specific user?
    if ($id === '') $id = null;

    $sessionKey = $app['session']->get('sessionKey');
    $app['monolog']->addInfo(sprintf("[%s] picked-up login session, session key = '%s'", $sid, $sessionKey));
    
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
            $app['session']->remove('sessionKey');
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

$app->get('/enrol', function (Request $request) use ($app, $tiqr, $options) {
    $locale = $app['translator']->getLocale();
    $locales = array_keys($options['translation']);
    $here = urlencode($app['request']->getUri()); // Is this allways correct?

    $base = $request->getUriForPath('/');
    $return = stripslashes(filter_var($request->get('return'),FILTER_VALIDATE_URL));
    if( $return == false ) {
        $return = $base;
    }
    if(strpos($return, $request->getSchemeAndHttpHost() . '/') !== 0) {
        $app['monolog']->addInfo(sprintf("illegal return URL '%s'", $return));
        $return = $base;
    }

    return $app['twig']->render('enrol.html', array(
        'self' => $base,
        'return_url' => $return,
        'here' => $here,
        'locale' => $locale,
        'locales' => $locales,
    ));
});

$app->get('/qr_enrol', function (Request $request) use ($app, $tiqr) {
    $base = $request->getUriForPath('/');
    // starting a new enrollment session
    $sid = $app['session']->getId();
    $uid = generate_id(); // TODO uniqueness
    $app['session']->set('authn', array('username' => $uid)); // TODO check
    $displayName = "SURFconext";
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

### housekeeping
$app->post('/switch-locale', function (Request $request) use ($app, $options) {
    $return = stripslashes(filter_var($request->get('return_url'), FILTER_VALIDATE_URL));
    if(strpos($return, $request->getSchemeAndHttpHost() . '/') !== 0) {
        $app['monolog']->addInfo(sprintf("illegal return URL '%s'", $return));
        $return = $request->getBaseUrl();
    }

    $opt = array(
        'options' => array(
            'default' => 'en',
            'regexp' => '/^[a-z]{2}$/',
        ),
    );
    $locale = filter_var($request->get('tiqr_switch_locale'), FILTER_VALIDATE_REGEXP, $opt);
    if (array_key_exists($locale, $options['translation'])) {
        $app['session']->set('locale', $locale);
    }
    return $app->redirect($return);
});
    
$app->run();
