<?php

declare(strict_types=1);

namespace Castor\Api\Runner;

use Castor\Api\Event\ApiTaskRunEvent;
use Castor\Api\Helper\JsonSchemaToCli;

final class SubprocessTaskRunner
{
    /**
     * Exit code reported when a task is killed because it exceeded the timeout (same as timeout(1)).
     */
    public const TIMEOUT_EXIT_CODE = 124;

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
        $cliArgs = JsonSchemaToCli::convert($requestSchema, $payload);

        return self::runWithCliArgs($castorBinary, $projectRoot, $workingDirectory, $taskName, $cliArgs, $payload);
    }

    /**
     * @param list<string>         $cliArgs
     * @param array<string, mixed> $payload
     *
     * @return array{exitCode: int, stdout: string, stderr: string, durationMs: int}
     */
    public static function runWithCliArgs(
        string $castorBinary,
        string $projectRoot,
        ?string $workingDirectory,
        string $taskName,
        array $cliArgs,
        array $payload = [],
        ?int $timeoutSeconds = null,
    ): array {
        if (\function_exists('Castor\dispatch')) {
            \Castor\dispatch(new ApiTaskRunEvent($taskName, $payload));
        }

        $command = array_merge([$castorBinary, $taskName], $cliArgs);

        $result = self::execute($command, $workingDirectory ?? $projectRoot, $timeoutSeconds ?? self::timeoutFromEnvironment());

        if (\function_exists('Castor\dispatch')) {
            \Castor\dispatch(new ApiTaskRunEvent($taskName, $payload, $result['exitCode'], $result['stdout'], $result['stderr']));
        }

        return $result;
    }

    /**
     * Runs a command and drains stdout and stderr together, so a process writing a lot
     * on one stream never blocks while the other one is being read.
     *
     * @param list<string> $command
     *
     * @return array{exitCode: int, stdout: string, stderr: string, durationMs: int}
     */
    public static function execute(array $command, string $cwd, int $timeoutSeconds = 0): array
    {
        $startedAt = hrtime(true);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd, self::subprocessEnvironment());

        if (!\is_resource($process)) {
            throw new \RuntimeException('Unable to start Castor subprocess.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = [1 => '', 2 => ''];
        $open = [1 => $pipes[1], 2 => $pipes[2]];
        $timedOut = false;

        while ([] !== $open) {
            if ($timeoutSeconds > 0 && (hrtime(true) - $startedAt) / 1_000_000_000 > $timeoutSeconds) {
                $timedOut = true;
                proc_terminate($process);

                break;
            }

            $read = array_values($open);
            $write = null;
            $except = null;

            if (false === stream_select($read, $write, $except, 1)) {
                break;
            }

            foreach ($open as $index => $pipe) {
                $chunk = fread($pipe, 65_536);

                if (\is_string($chunk) && '' !== $chunk) {
                    $output[$index] .= $chunk;
                }

                if (feof($pipe)) {
                    fclose($pipe);
                    unset($open[$index]);
                }
            }
        }

        foreach ($open as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        if ($timedOut) {
            $exitCode = self::TIMEOUT_EXIT_CODE;
            $output[2] .= \sprintf("\nTask killed after exceeding the %d s timeout.\n", $timeoutSeconds);
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => $output[1],
            'stderr' => $output[2],
            'durationMs' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ];
    }

    /**
     * CASTOR_API_TASK_TIMEOUT, in seconds. 0 or unset means no timeout.
     */
    private static function timeoutFromEnvironment(): int
    {
        $timeout = getenv('CASTOR_API_TASK_TIMEOUT');

        return false !== $timeout && is_numeric($timeout) ? max(0, (int) $timeout) : 0;
    }

    /**
     * @return array<string, string>
     */
    private static function subprocessEnvironment(): array
    {
        $environment = getenv();

        unset(
            $environment['CASTOR_PHP_REPLACE'],
            $environment['CASTOR_API_PACKAGE_ROOT'],
        );

        return $environment;
    }
}
