<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Runner;

use Castor\Api\Runner\SubprocessTaskRunner;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class SubprocessTaskRunnerTest extends TestCase
{
    public function testCapturesStdoutStderrAndExitCode(): void
    {
        $result = SubprocessTaskRunner::execute(
            [PHP_BINARY, '-r', 'echo "out"; fwrite(STDERR, "err"); exit(3);'],
            sys_get_temp_dir(),
        );

        self::assertSame(3, $result['exitCode']);
        self::assertSame('out', $result['stdout']);
        self::assertSame('err', $result['stderr']);
    }

    public function testDoesNotDeadlockWhenStderrExceedsThePipeBuffer(): void
    {
        $script = 'fwrite(STDERR, str_repeat("e", 1024 * 1024)); echo str_repeat("o", 1024 * 1024);';

        $result = SubprocessTaskRunner::execute([PHP_BINARY, '-r', $script], sys_get_temp_dir(), 20);

        self::assertSame(0, $result['exitCode']);
        self::assertSame(1024 * 1024, \strlen($result['stdout']));
        self::assertSame(1024 * 1024, \strlen($result['stderr']));
    }

    public function testKillsTheTaskAfterTheTimeout(): void
    {
        $result = SubprocessTaskRunner::execute([PHP_BINARY, '-r', 'sleep(30);'], sys_get_temp_dir(), 1);

        self::assertSame(SubprocessTaskRunner::TIMEOUT_EXIT_CODE, $result['exitCode']);
        self::assertStringContainsString('timeout', $result['stderr']);
        self::assertLessThan(10_000, $result['durationMs']);
    }
}
