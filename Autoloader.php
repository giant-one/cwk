<?php

namespace System;

class Autoloader
{
    public static function autoload($name)
    {
        $file_path = __DIR__ .DIRECTORY_SEPARATOR. str_replace('\\',DIRECTORY_SEPARATOR,$name).'.php';
        if (file_exists($file_path)) {
            include $file_path;
        }
    }
}

\spl_autoload_register('\System\Autoloader::autoload');