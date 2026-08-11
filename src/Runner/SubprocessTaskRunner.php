<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Runner;

use Jolicode\CastorApi\Event\ApiTaskRunEvent;
use Jolicode\CastorApi\Helper\JsonSchemaToCli;

final class SubprocessTaskRunner
{
    /**
     * @param array<string, mixed>|null $requestSchema
     * @param array<string, mixed>      $payload
     *
     * @return array{exitCode: int, stdout: string, stderr: string, durationMs: int}
     */
    public static function run(
        string $castorBinary,
        string $projectRoot,
        ?string $workingDirectory,
        string $taskName,
        ?array $requestSchema,
        array $payload,
    ): array {
        if (\function_exists('Castor\dispatch')) {
            \Castor\dispatch(new ApiTaskRunEvent($taskName, $payload));
        }

        $cliArgs = JsonSchemaToCli::convert($requestSchema, $payload);
        $command = array_merge([$castorBinary, $taskName], $cliArgs);

        $startedAt = hrtime(true);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cwd = $workingDirectory ?? $projectRoot;
        $process = proc_open($command, $descriptorSpec, $pipes, $cwd, self::subprocessEnvironment());

        if (!\is_resource($process)) {
            throw new \RuntimeException('Unable to start Castor subprocess.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        if (\function_exists('Castor\dispatch')) {
            \Castor\dispatch(new ApiTaskRunEvent($taskName, $payload, $exitCode, $stdout, $stderr));
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'durationMs' => $durationMs,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function subprocessEnvironment(): array
    {
        $environment = getenv();

        unset(
            $environment['CASTOR_PHP_REPLACE'],
            $environment['CASTOR_API_REGISTRY'],
            $environment['CASTOR_API_PACKAGE_ROOT'],
        );

        return $environment;
    }
}
