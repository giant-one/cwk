<?php

namespace Cwk;

class Autoloader
{
    public static function autoload($name)
    {
        $file_path = dirname(__DIR__) .DIRECTORY_SEPARATOR. str_replace('\\',DIRECTORY_SEPARATOR,$name).'.php';
        if (file_exists($file_path)) {
            include $file_path;
        }
    }
}

\spl_autoload_register('\Cwk\Autoloader::autoload');