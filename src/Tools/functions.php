<?php

use Aventus\Laraventus\Tools\Console;

if (!function_exists("error")) {
    function error($txt)
    {
        Console::log("Error : " . $txt);
    }
}
if (!function_exists("realpath_safe")) {
    function realpath_safe($path)
    {
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $absolutes = [];

        // Si le chemin est relatif, on le base sur getcwd()
        if (!preg_match('#^([a-zA-Z]:)?[/\\\\]#', $path)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
            $parts = explode(DIRECTORY_SEPARATOR, $path);
        }

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            } elseif ($part === '..') {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        $prefix = DIRECTORY_SEPARATOR;
        // Windows drive letter handling
        if (preg_match('#^[a-zA-Z]:$#', $absolutes[0] ?? '')) {
            $prefix = array_shift($absolutes) . DIRECTORY_SEPARATOR;
        }

        return $prefix . implode(DIRECTORY_SEPARATOR, $absolutes);
    }
}
