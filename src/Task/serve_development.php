<?php

declare(strict_types=1);

namespace castor_api\task;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Jolicode\CastorApi\Helper\AuthToken;
use Jolicode\CastorApi\Helper\RoutingExporter;

use function Castor\context;
use function Castor\io;
use function Castor\run_php;

#[AsTask(name: 'serve-development', namespace: 'api', description: 'Start the built-in PHP server for local development')]
function serve_development(
    #[AsOption(shortcut: 'H', description: 'Listen address')]
    string $host = '127.0.0.1',
    #[AsOption(shortcut: 'p', description: 'Port')]
    int $port = 8080,
): void {
    $authToken = AuthToken::resolve();

    if (null !== $authToken) {
        io()->note('CASTOR_API_TOKEN is set: API requests require Authorization: Bearer …');
    } else {
        io()->warning('CASTOR_API_TOKEN is not set: the API is accessible without authentication (local development only).');
    }

    if ('127.0.0.1' !== $host && 'localhost' !== $host) {
        io()->caution(\sprintf('Binding to "%s" exposes the API on the network. Use 127.0.0.1 unless you know what you are doing.', $host));
    }

    if (0 === RoutingExporter::countTasks()) {
        io()->warning('No tasks marked with #[AsApi] were found.');
    }

    $openapiPath = RoutingExporter::writeOpenApi();
    $packageRoot = \dirname(__DIR__, 2);
    $listen = $host . ':' . $port;

    io()->success(\sprintf(
        'Castor API development server on http://%s (%d task(s) exposed)',
        $listen,
        RoutingExporter::countTasks(),
    ));

    run_php(
        $packageRoot . '/resources/http-server.php',
        [$listen],
        context: context()->withEnvironment([
            'CASTOR_API_OPENAPI' => $openapiPath,
            'CASTOR_API_PACKAGE_ROOT' => $packageRoot,
        ])->withPty(false),
    );
}
