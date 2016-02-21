<?php

// we must include autoloader from symfony/console
define("DS", DIRECTORY_SEPARATOR);
require_once( __DIR__ . DS . 'vendor' . DS . 'autoload.php');

// our own autoloader
function autoloader($class)
{
    $patch_class = str_replace('\\', DS, $class);
    $filename = sprintf("%s%s%s%s%s.php", __DIR__, DS, 'src', DS, $patch_class);
    if (file_exists($filename)) {
        require_once $filename;
    }
}

spl_autoload_register('autoloader');