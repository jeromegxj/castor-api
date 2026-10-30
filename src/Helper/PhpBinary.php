<?php

declare(strict_types=1);

namespace Castor\Api\Helper;

final class PhpBinary
{
    /**
     * PHP CLI used to launch the async worker.
     *
     * CASTOR_API_PHP_BINARY wins. Otherwise PHP_BINARY is only trusted under the CLI
     * and built-in server SAPIs: under FPM or FrankenPHP it points to the server binary,
     * which cannot run a script.
     */
    public static function resolve(?string $sapi = null): string
    {
        $configured = getenv('CASTOR_API_PHP_BINARY');

        if (\is_string($configured) && '' !== trim($configured)) {
            return trim($configured);
        }

        $sapi ??= \PHP_SAPI;

        if (\in_array($sapi, ['cli', 'cli-server'], true)) {
            return PHP_BINARY;
        }

        return 'php';
    }
}
