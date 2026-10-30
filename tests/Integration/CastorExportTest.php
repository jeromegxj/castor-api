<?php

declare(strict_types=1);

namespace Castor\Api\Tests\Integration;

use Castor\Api\Runner\SubprocessTaskRunner;
use Castor\Api\Tests\TemporaryProjectTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real Castor binary (CASTOR_BINARY, or "castor" from the PATH) against a project importing this package.
 *
 * @internal
 */
#[CoversNothing]
#[Group('castor')]
final class CastorExportTest extends TestCase
{
    use TemporaryProjectTrait;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = self::createTemporaryProject('castor');
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->projectRoot);
    }

    public function testExportsTheTasksMarkedAsApi(): void
    {
        $packageCastorFile = var_export(\dirname(__DIR__, 2) . '/castor.php', true);
        file_put_contents($this->projectRoot . '/castor.php', <<<PHP
            <?php

            use Castor\\Api\\Attribute\\AsApi;
            use Castor\\Attribute\\AsOption;
            use Castor\\Attribute\\AsTask;

            use function Castor\\import;
            use function Castor\\io;

            defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', false);

            import({$packageCastorFile});

            #[AsTask(namespace: 'demo', description: 'Say hello')]
            #[AsApi(async: true, lock: 'greeting:{name}')]
            function hello(#[AsOption] string \$name = 'world'): void
            {
                io()->writeln('Hello ' . \$name);
            }

            #[AsTask(namespace: 'demo', description: 'Not exposed')]
            function hidden(): void
            {
            }

            PHP);

        $result = SubprocessTaskRunner::execute([self::castorBinary(), 'api:export-openapi', '--no-interaction'], $this->projectRoot, 120);

        self::assertSame(0, $result['exitCode'], $result['stdout'] . $result['stderr']);

        /** @var array{paths: array<string, array<string, array<string, mixed>>>} $spec */
        $spec = json_decode((string) file_get_contents($this->projectRoot . '/.castor/api/openapi.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            '/health',
            '/runs',
            '/tasks/demo%3Ahello/run',
            '/tasks/demo%3Ahello/start',
            '/tasks/demo%3Ahello/status/{runId}',
        ], array_keys($spec['paths']));
        self::assertSame('Say hello (async start)', $spec['paths']['/tasks/demo%3Ahello/start']['post']['description']);
        self::assertSame(['greeting:{name}'], $spec['paths']['/tasks/demo%3Ahello/start']['post']['x-castor-locks']);
    }

    private static function castorBinary(): string
    {
        $binary = getenv('CASTOR_BINARY') ?: 'castor';
        exec('command -v ' . escapeshellarg($binary), $output, $exitCode);

        if (0 !== $exitCode) {
            self::markTestSkipped(\sprintf('The Castor binary "%s" is not available.', $binary));
        }

        return $binary;
    }
}
