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

##########

$app->get('/', function (Request $request) use ($app, $tiqr) {
        $base = $request->getUriForPath('/');
//        if (null === $user = $app['session']->get('user')) {
//            return $app->redirect($base.'login');
//        }
//        error_log(print_r($user,true));
        // starting a new enrollment session
        $sid = $app['session']->getId();
        //$uid = 'john';
	$uid = generate_id();
//        $uid = $user['username'];
        $displayName = "Stepup User";      # TODO
//        $displayName = $uid;
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

########## TODO obsolete

$app->get('/', function (Request $request) use ($app) {
        $base = $request->getUriForPath('/');
        if (null === $authn = $app['session']->get('authn')) {
            return "You are not logged in.";
        }
        $username = $authn['username'];
        return 'Hello, '.$app->escape($username);
});

$app->get('/login', function (Request $request) use ($app) {
        $loader = new Twig_Loader_Filesystem('../../views');
        $twig = new Twig_Environment($loader, array(
            'debug' => true,
        ));
        return $twig->render('login.html', array(
            'return' => $request->get('return'),
            'username' => $app['session']->get('RequestedSubject'),
        ));
});

$app->post('/login', function (Request $request) use ($app) {
        if( null === $username = $request->get('username') ) {
            return $app->redirect($request->getRequestUri());
        }
        if( true ) { // always succeed
            $app['session']->set('authn', array('username' => $username));
        }
        if( null === $return = $request->get('return') ) {
            $return = $request->getUriForPath('/');
        }
        return $app->redirect($return);
});

$app->get('/logout', function (Request $request) use ($app) {
        $app['session']->set('authn', null);
        return $app->redirect($request->getUriForPath('/'));
});

$app->run();
