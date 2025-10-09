<?php

require_once __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/src/Tools/functions.php';

use Aventus\Transpiler\Parser\Parser;
use Aventus\Transpiler\Writer\FileToWrite;

// $parser = new Parser("D:\\Aventus\\LarAventus\\Laraventus\\aventus.php.avt");
$parser = new Parser("C:\\Projets\\vet\\aventus.php.avt");
$parser->parse();
FileToWrite::writeAll();

// packaging : clue/phar-composer
