<?php

declare(strict_types=1);

use function Castor\import;

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', false);

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

import($packageRoot . '/src/Listener/register_api_tasks.php');
import($packageRoot . '/src/Task/export_openapi.php');
import($packageRoot . '/src/Task/serve_development.php');
