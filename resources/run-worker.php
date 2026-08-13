<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use Jolicode\CastorApi\Run\RunExecutor;
use Jolicode\CastorApi\Run\RunStore;

$runId = $argv[1] ?? null;
$projectRoot = getenv('CASTOR_API_PROJECT_ROOT') ?: null;
$packageRoot = getenv('CASTOR_API_PACKAGE_ROOT') ?: \dirname(__DIR__);

if (!is_string($runId) || '' === $runId || !is_string($projectRoot)) {
    fwrite(STDERR, "Usage: CASTOR_API_PROJECT_ROOT=/path/to/project run-worker.php <run-id>\n");
    exit(1);
}

if (!castor_api_load_autoload(null, $packageRoot)) {
    fwrite(STDERR, "Unable to load Castor API autoload.\n");
    exit(1);
}

$store = new RunStore($projectRoot);
$record = $store->get($runId);

if (null === $record) {
    fwrite(STDERR, \sprintf('Run "%s" not found.', $runId));
    exit(1);
}

new RunExecutor()->execute($record);
