<?php
/**
 * Balloon
 *
 * @category    balloon
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   copryright (c) 2012-2016 gyselroth GmbH
 */

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'));

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

set_include_path(implode(PATH_SEPARATOR, [
    APPLICATION_PATH.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'lib',
    APPLICATION_PATH.DIRECTORY_SEPARATOR,
    get_include_path(),
]));

$composer = require 'vendor/autoload.php';

if (extension_loaded('apc') && apc_exists('config')) {
    $config = apc_fetch('config');
} else {
    $xml = new \Micro\Config\Xml(APPLICATION_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'config.xml', APPLICATION_ENV);
    if (is_readable(APPLICATION_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'local.xml')) {
        $local = new \Micro\Config\Xml(APPLICATION_PATH.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'local.xml', APPLICATION_ENV);
        $xml->merge($local);
    }
    
    $config = new \Micro\Config($xml);

    if(extension_loaded('apc')) {
        apc_store('config', $config);
    }
}

new \Balloon\Bootstrap\Http($composer, $config);
