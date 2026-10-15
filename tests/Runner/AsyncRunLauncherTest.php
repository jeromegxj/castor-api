<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Runner;

use Castor\Api\Helper\PhpBinary;
use Castor\Api\Runner\AsyncRunLauncher;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AsyncRunLauncherTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CASTOR_API_PHP_BINARY');
    }

    public function testWorkerCommandRunsInBackgroundAndLogsToTheRunLog(): void
    {
        $command = AsyncRunLauncher::buildWorkerCommand(
            ['CASTOR_API_PROJECT_ROOT' => '/srv/app'],
            '/usr/bin/php8.5',
            '/srv/app/run-worker.php',
            'abc',
            '/srv/app/.castor/api/runs/abc.log',
        );

        self::assertSame(
            "CASTOR_API_PROJECT_ROOT='/srv/app' '/usr/bin/php8.5' '/srv/app/run-worker.php' 'abc' >> '/srv/app/.castor/api/runs/abc.log' 2>&1 &",
            $command,
        );
    }

    public function testPhpBinaryCanBeConfigured(): void
    {
        putenv('CASTOR_API_PHP_BINARY=/usr/bin/php8.5');

        self::assertSame('/usr/bin/php8.5', PhpBinary::resolve('fpm-fcgi'));
    }

    public function testPhpBinaryIsOnlyTrustedUnderCli(): void
    {
        self::assertSame(\PHP_BINARY, PhpBinary::resolve('cli'));
        self::assertSame(\PHP_BINARY, PhpBinary::resolve('cli-server'));
        self::assertSame('php', PhpBinary::resolve('fpm-fcgi'));
        self::assertSame('php', PhpBinary::resolve('frankenphp'));
    }
}
