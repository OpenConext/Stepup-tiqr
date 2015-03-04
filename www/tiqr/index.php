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

$app->get('/login', function (Request $request) use ($app, $tiqr) {
        $base = $request->getUriForPath('/');
        if( null === $return = $request->get('return') ) {
            $return = $base;
        }
//        $self = $request->getRequestUri();
        $sid = $app['session']->getId();
        $userdata = $tiqr->getAuthenticatedUser($sid);
        $app['monolog']->addInfo(sprintf("[%s] userdata '%s'", $sid, $userdata));
        if( !is_null($userdata) )
        {
            $app['session']->set('authn', array('username' => $userdata));
            return $app->redirect($return);
        }
        $sessionKey = $tiqr->startAuthenticationSession(null,$sid); // prepares the tiqr library for authentication
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
            ));
        $response = new Response($login);
        return $response;
});

$app->get('/verify', function (Request $request) use ($app, $tiqr) {
        $sid = $app['session']->getId();
        $userdata = $tiqr->getAuthenticatedUser($sid);
        if( isset($userdata) )
            $app['monolog']->addInfo(sprintf("[%s] verified authenticated user '%s'", $sid, $userdata));
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

$app->run();
