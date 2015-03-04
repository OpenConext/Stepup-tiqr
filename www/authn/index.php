<?php
require_once __DIR__.'/../../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use libTiqr\library\tiqr;

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

##########

$app->get('/', function (Request $request) use ($app) {
        $base = $request->getUriForPath('/');
        if (null === $authn = $app['session']->get('authn')) {
            return "You are not logged in.";
        }
        $username = $authn['username'];
        return 'Hello, '.$app->escape($username);
});

$app->get('/login', function (Request $request) use ($app) {
        $loader = new Twig_Loader_Filesystem('views');
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
