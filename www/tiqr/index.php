<?php
require_once __DIR__.'/../../vendor/autoload.php';

include('../../options.php');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

date_default_timezone_set('Europe/Amsterdam');

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.path' => __DIR__.'/views',
    ));
$app->register(new Silex\Provider\MonologServiceProvider(), array(
        'monolog.logfile' => __DIR__.'/../../authn.log',
    ));

$app->before(function ($request) {
        $request->getSession()->start();
    });

$tiqr = new Tiqr_Service($options);

//error_log("john :".$userStorage->getNotificationType("john"));

$app->get('/login', function (Request $request) use ($app, $tiqr) {
        $base = $request->getUriForPath('/');
        if( null === $return = $request->get('return') ) {
            $return = $base;
        }
        $sid = $app['session']->getId();
        $userdata = $tiqr->getAuthenticatedUser($sid);
        $app['monolog']->addInfo(sprintf("[%s] userdata '%s'", $sid, $userdata));
        if( !is_null($userdata) )
        {
            $app['session']->set('authn', array('username' => $userdata));
            return $app->redirect($return);
        }
        $id = $app['session']->get('RequestedSubject');
        if( $id === '' ) $id = null;
	if( !$id ) {	// when no id requested, first enrol:
            $return = $request->getRequestUri();
            return $app->redirect($base . "/enrol.php?return=" . urlencode($return));
	}
//        $tiqr->sendAuthNotification($sid);
        $sessionKey = $tiqr->startAuthenticationSession($id,$sid); // prepares the tiqr library for authentication
        $app['monolog']->addInfo(sprintf("[%s] started new login session, session key = '%s", $sid, $sessionKey));
        $url = $tiqr->generateAuthURL($sessionKey);
        $qr = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $url;
        $loader = new Twig_Loader_Filesystem('views');
        $twig = new Twig_Environment($loader, array(
            'debug' => true,
        ));
        $login = $twig->render('index.html', array(
                'qr' => $qr,
                'self' => $base,
                'return_url' => $return,
                'id' => $id,
            ));
        $response = new Response($login);
        return $response;
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

##########

$app->get('/enrol', function (Request $request) use ($app, $tiqr) {
        $base = $request->getUriForPath('/');
        if( null === $return = $request->get('return') ) {
            $return = $base;
        }
        // starting a new enrollment session
        $sid = $app['session']->getId();
        $uid = generate_id(); // TODO uniqueness
        $displayName = "Stepup User";      # TODO
        $app['monolog']->addInfo(sprintf("[%s] enrol uid '%s' (%s).", $sid, $uid, $displayName));
        $key = $tiqr->startEnrollmentSession($uid, $displayName, $sid);
        $app['monolog']->addInfo(sprintf("[%s] start enrol uid '%s' with session key '%s'.", $sid, $uid, $key));
        $metadataURL = base() . "/tiqr/tiqr.php?key=$key";       # TODO
        $app['monolog']->addInfo(sprintf("[%s] metadata URL for uid '%s' is '%s'.", $sid, $uid, $metadataURL));
        $url = $tiqr->generateEnrollString($metadataURL);
        # TODO: use js qr lib
        $qr = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . $url;

        $loader = new Twig_Loader_Filesystem('views');
        $twig = new Twig_Environment($loader, array(
            'debug' => true,
        ));
        $enrol = $twig->render('enrol.html', array(
                'self' => $base,
                'qr' => $qr,
                'return_url' => $base . 'login?return=' . $return,
            ));
        $response = new Response($enrol);
        return $response;
    });

### status

$app->get('/status', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $status = $tiqr->getEnrollmentStatus($sid);
        error_log("[$sid] status is $status");
        return $status;
    });

$app->get('/done', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $tiqr->resetEnrollmentSession($sid);
        error_log("[$sid] reset enrollment");
        return "done";
    });


$app->run();
