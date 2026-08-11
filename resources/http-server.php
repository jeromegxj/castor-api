<?php

declare(strict_types=1);

$listen = $argv[1] ?? '127.0.0.1:8080';
$openapiPath = getenv('CASTOR_API_OPENAPI') ?: ($argv[2] ?? null);
$packageRoot = getenv('CASTOR_API_PACKAGE_ROOT') ?: \dirname(__DIR__);
$frontController = $packageRoot . '/resources/front-controller.php';

if (null === $openapiPath || !is_file($openapiPath)) {
    fwrite(STDERR, "Usage: http-server.php <listen> — requires CASTOR_API_OPENAPI env var\n");
    exit(1);
}

if (!is_file($frontController)) {
    fwrite(STDERR, \sprintf('Front controller not found at "%s".', $frontController));
    exit(1);
}

putenv('CASTOR_API_OPENAPI=' . $openapiPath);
putenv('CASTOR_API_PACKAGE_ROOT=' . $packageRoot);

$command = sprintf(
    '%s -S %s %s',
    escapeshellarg(\PHP_BINARY),
    escapeshellarg($listen),
    escapeshellarg($frontController),
);

fwrite(STDOUT, \sprintf("Castor API development server ready on http://%s\n", $listen));

passthru($command, $exitCode);
exit((int) $exitCode);
