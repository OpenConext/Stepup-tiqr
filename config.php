<?php

$config = array(
    'keyfile' => dirname(__FILE__) . "/key.pem",
    'certfile' => dirname(__FILE__) . "/cert.pem",
) ;

$config['sp']['http://' . $_SERVER['HTTP_HOST'] . '/sp/metadata'] = array(
        'acs' =>  'http://' . $_SERVER['HTTP_HOST'] . '/sp/acs',
        'certfile' => dirname(__FILE__) . '/cert.pem',
);

// override config locally
if( file_exists(dirname(__FILE__) . "/local_config.php") ) {
    include(dirname(__FILE__) . "/local_config.php");
}
