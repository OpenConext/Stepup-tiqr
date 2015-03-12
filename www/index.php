<?php

require_once __DIR__.'/../vendor/autoload.php';

require_once('../vendor/joostd/tiqr-server/libTiqr/library/tiqr/Tiqr/AutoLoader.php');

$path = array(
    'tiqr.path' => '../vendor/joostd/tiqr-server/libTiqr/library/tiqr',
    'phpqrcode.path' => '.',
    'zend.path' => '.',
);
$autoloader = Tiqr_AutoLoader::getInstance($path);
$autoloader->setIncludePath();

$tiqr = new Tiqr_Service($options);
echo ':'.$tiqr->getAuthenticatedUser(session_id());
