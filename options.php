<?php
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/vendor/tiqr/tiqr-server-libphp/library/tiqr/Tiqr/AutoLoader.php';

$options = array(
    //"identifier"      => "pilot.stepup.coin.surf.net",
    "name"            => "Stepup Authentication Service",
    "auth.protocol"       => "tiqrauth",
    "enroll.protocol"     => "tiqrenroll",
    "ocra.suite"          => "OCRA-1:HOTP-SHA1-6:QH10-S",
    "logoUrl"         => "https://demo.tiqr.org/img/tiqrRGB.png",
    "infoUrl"         => $_SERVER['HTTP_HOST'], // base(),
    "tiqr.path"         => __DIR__ . '/vendor/tiqr/tiqr-server-libphp/library/tiqr/',
    'phpqrcode.path' => '.',
    'zend.path' => __DIR__ . '/vendor/zendframework/zendframework1/library',
    'statestorage'        => array("type" => "file"),
    'userstorage'         => array("type" => "file", "path" => "/tmp", "encryption" => array('type' => 'dummy')),
    "usersecretstorage" => array("type" => "file"),
    "apns.certificate" => '',
    "apns.environment" => 'production',
    "debug" => false,
    "default_locale" => 'en',
    "translation"  =>  array(
        "en" => true,
        "nl" => true,
    )
);

// override options locally. TODO merge with config
if( file_exists(dirname(__FILE__) . "/local_options.php") ) {
    include(dirname(__FILE__) . "/local_options.php");
}

set_include_path(get_include_path() . PATH_SEPARATOR . $options['zend.path']);

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();

$autoloader = Tiqr_AutoLoader::getInstance($options); // needs {tiqr,zend,phpqrcode}.path
$autoloader->setIncludePath();

$userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);

function generate_id($length = 8) {
    $chars = "0123456789";
    $count = mb_strlen($chars);
    for ($i = 0, $result = ''; $i < $length; $i++) {
        $index = rand(0, $count - 1);
        $result .= mb_substr($chars, $index, 1);
    }
    return $result;
}
