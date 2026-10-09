<?php

declare(strict_types=1);

use function Castor\import;

$packageRoot = __DIR__;

foreach ([
    $packageRoot . '/vendor/autoload.php',
    $packageRoot . '/.castor/vendor/autoload.php',
] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        break;
    }
}
