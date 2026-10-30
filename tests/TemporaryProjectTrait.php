<?php

declare(strict_types=1);

namespace Castor\Api\Tests;

trait TemporaryProjectTrait
{
    /**
     * Creates an empty project root holding the `.castor/api/runs` directory.
     */
    private static function createTemporaryProject(string $prefix): string
    {
        $projectRoot = sys_get_temp_dir() . '/castor-api-' . $prefix . '-' . uniqid('', true);
        mkdir($projectRoot . '/.castor/api/runs', 0o777, true);

        return $projectRoot;
    }

    /**
     * Writes an OpenAPI spec with the given paths to `.castor/api/openapi.json`.
     *
     * @param array<string, mixed> $paths
     */
    private static function writeOpenApi(string $projectRoot, array $paths): string
    {
        $openapiPath = $projectRoot . '/.castor/api/openapi.json';
        file_put_contents($openapiPath, json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => $paths,
        ], JSON_THROW_ON_ERROR));

        return $openapiPath;
    }

    /**
     * Creates an executable standing in for Castor: it prints the task name and its
     * arguments, and the "demo:fail" task writes "boom" on stderr and exits with 3.
     */
    private static function createFakeCastorBinary(string $projectRoot): string
    {
        $binary = $projectRoot . '/fake-castor';
        file_put_contents($binary, '#!' . PHP_BINARY . "\n" . <<<'PHP'
            <?php
            echo implode(' ', array_slice($argv, 1));
            if ('demo:fail' === ($argv[1] ?? null)) {
                fwrite(STDERR, 'boom');
                exit(3);
            }

            PHP);
        chmod($binary, 0o755);

        return $binary;
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
