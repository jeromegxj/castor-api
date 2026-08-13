<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Runner;

use Jolicode\CastorApi\Helper\JsonSchemaToCli;
use Jolicode\CastorApi\OpenApi\OpenApiLoader;
use Jolicode\CastorApi\OpenApi\OperationContext;
use Jolicode\CastorApi\Run\RunRecord;
use Jolicode\CastorApi\Run\RunStore;

final class AsyncRunLauncher
{
    public function __construct(
        private readonly OpenApiLoader $loader,
        private readonly string $packageRoot,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function start(OperationContext $operation, array $payload): RunRecord
    {
        $cliArgs = JsonSchemaToCli::convert($operation->requestSchema, $payload);
        $store = new RunStore($this->loader->getProjectRoot());
        $record = $store->create(
            task: $operation->taskName,
            cliArgs: $cliArgs,
            castorBinary: $this->loader->getCastorBinary(),
            workingDirectory: $operation->workingDirectory,
        );

        $this->launchWorker($record->id);

        return $record;
    }

    private function launchWorker(string $runId): void
    {
        $workerScript = $this->packageRoot . '/resources/run-worker.php';

        if (!is_file($workerScript)) {
            throw new \RuntimeException(\sprintf('Async worker script not found at "%s".', $workerScript));
        }

        $environment = [
            'CASTOR_API_PROJECT_ROOT' => $this->loader->getProjectRoot(),
            'CASTOR_API_PACKAGE_ROOT' => $this->packageRoot,
        ];

        $command = \sprintf(
            '%s %s %s %s',
            implode(' ', array_map(
                static fn (string $key, string $value): string => $key . '=' . escapeshellarg($value),
                array_keys($environment),
                array_values($environment),
            )),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($workerScript),
            escapeshellarg($runId),
        );

        if ('\\' === \DIRECTORY_SEPARATOR) {
            throw new \RuntimeException('Async task execution is not supported on Windows.');
        }

        exec($command . ' > /dev/null 2>&1 &');
    }
}
