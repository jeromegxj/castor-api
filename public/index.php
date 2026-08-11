<?php

declare(strict_types=1);

$packageRoot = \dirname(__DIR__);

if (false === getenv('CASTOR_API_PACKAGE_ROOT')) {
    putenv('CASTOR_API_PACKAGE_ROOT=' . $packageRoot);
}

require $packageRoot . '/resources/front-controller.php';
