# jolicode/castor-api

Expose [Castor](https://castor.jolicode.com) tasks over HTTP. Only functions marked with both `#[AsTask]` and `#[AsApi]` are published.

## Installation

In your project's `castor.php`:

```php
import('composer://jolicode/castor-api');
```

```php
#[AsTask(namespace: 'demo', description: 'Say hello')]
#[AsApi(methods: ['POST'])]
function hello(
    #[AsOption]
    string $name = 'world',
): void {
    // ...
}

#[AsTask(namespace: 'demo', description: 'Slow async task')]
#[AsApi(methods: ['POST'], async: true)]
function slow(#[AsOption] int $seconds = 2): void {
    sleep($seconds);
}
```

Tasks marked with `async: true` also expose async routes in addition to the synchronous `/run` endpoint.

### `#[AsApi]` options

| Parameter      | Default    | Description                                               |
|----------------|------------|-----------------------------------------------------------|
| `path`         | `null`     | Custom base path (default: `/tasks/{name}`)               |
| `methods`      | `['POST']` | HTTP methods for the synchronous `/run` endpoint          |
| `exposeSchema` | `true`     | Export task parameters in the OpenAPI request body schema |
| `async`        | `false`    | Also expose `/start` and `/status/{runId}` endpoints      |

Set `exposeSchema: false` to publish a task without a request body in the OpenAPI spec (useful for parameterless tasks).

### JSON body mapping

The request body is a JSON object whose keys match task parameter names:

| Castor attribute      | JSON key      | CLI mapping                               |
|-----------------------|---------------|-------------------------------------------|
| `#[AsArgument]`       | property name | positional value(s), in declaration order |
| `#[AsOption]` (value) | property name | `--name=value`                            |
| `#[AsOption]` (flag)  | property name | `--name` / `--no-name`                    |
| `#[AsOption]` (array) | property name | repeated `--name=value`                   |

Array arguments expand to multiple positional values (e.g. `{"tags": ["a", "b"]}` → `castor task a b`).

Re-export the OpenAPI spec whenever task signatures change:

```bash
castor api:export-openapi
```

## Usage

### Development

Export the OpenAPI spec and start the built-in PHP server (binds to `127.0.0.1:8080` by default):

```bash
castor api:serve-development
```

This writes `.castor/api/openapi.json` and starts a local server for development only.

Optional environment variable for authentication:

```bash
export CASTOR_API_TOKEN=your-secret-token
castor api:serve-development
```

### Production

Export the OpenAPI spec at deploy time (or whenever `#[AsApi]` tasks change):

```bash
castor api:export-openapi
```

Serve the API with Nginx, FrankenPHP, or any PHP-FPM setup using the package front controller at `public/index.php`.

Set these environment variables for the PHP worker:

| Variable                  | Description                                                        |
|---------------------------|--------------------------------------------------------------------|
| `CASTOR_API_OPENAPI`      | Absolute path to `.castor/api/openapi.json`                        |
| `CASTOR_API_PACKAGE_ROOT` | Absolute path to the installed `jolicode/castor-api` package       |
| `CASTOR_API_TOKEN`        | Bearer token required on every request (recommended in production) |
| `CASTOR_BINARY`           | Castor binary name or path (default: `castor`)                     |

The project root is deduced from the OpenAPI file location (`{projectRoot}/.castor/api/openapi.json`).

Example Nginx location:

```nginx
root /var/www/my-project/vendor/jolicode/castor-api/public;

location / {
    try_files $uri /index.php$is_args$args;
}

location ~ ^/index\.php {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME /var/www/my-project/vendor/jolicode/castor-api/public/index.php;
    fastcgi_param CASTOR_API_OPENAPI /var/www/my-project/.castor/api/openapi.json;
    fastcgi_param CASTOR_API_PACKAGE_ROOT /var/www/my-project/vendor/jolicode/castor-api;
}
```

Example Caddyfile:

```caddyfile
my-project.example.com {
    root * /var/www/my-project/vendor/jolicode/castor-api/public

    php_server {
        env CASTOR_API_OPENAPI /var/www/my-project/.castor/api/openapi.json
        env CASTOR_API_PACKAGE_ROOT /var/www/my-project/vendor/jolicode/castor-api
        env CASTOR_API_TOKEN your-secret-token
        env CASTOR_BINARY /usr/local/bin/castor
    }
}
```

The PHP worker must be able to execute the Castor binary.

### Endpoints

| Method | Path                           | Description                                                                                                              |
|--------|--------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| GET    | `/health`                      | Health check                                                                                                             |
| GET    | `/openapi.json`                | OpenAPI 3.1 specification                                                                                                |
| POST   | `/tasks/{name}/run`            | Run task synchronously (JSON body = task arguments and options). Returns `422` when the task exits with a non-zero code. |
| POST   | `/tasks/{name}/start`          | Start async run (only for `#[AsApi(async: true)]` tasks)                                                                 |
| GET    | `/tasks/{name}/status/{runId}` | Poll async run status and result. Returns `422` when the run ends with `status: failed`.                                 |

Task names containing a namespace use a colon (e.g. `demo:hello`). Example run path: `/tasks/demo:hello/run`.

Async runs are stored as JSON files in `.castor/api/runs/{runId}.json`. The API consumer chooses sync or async by calling `/run` or `/start`.

Discover available tasks and their request schemas via `GET /openapi.json`.

### Async execution

For tasks marked with `#[AsApi(async: true)]`:

1. `POST /tasks/{name}/start` with the same JSON body as `/run`
2. Response `202 Accepted` (always, even if the task will eventually fail):

```json
{ "id": "uuid", "task": "demo:slow", "status": "pending" }
```

3. Poll `GET /tasks/{name}/status/{runId}` until `status` is `completed` or `failed`

Task failures are reported on `/status` (HTTP `422` when `status` is `failed`), not on `/start`.

While the task is still running, `exitCode`, `stdout`, `stderr`, and `durationMs` are `null`.

When a task fails, the API returns `422 Unprocessable Entity` with the same JSON body as a successful response (`exitCode`, `stdout`, `stderr`, etc.). For sync runs, this applies to `POST /run`. For async runs, polling `/status` returns `200` while the run is `pending`, `running`, or `completed`, and `422` once `status` is `failed`.

### Examples

```bash
curl http://127.0.0.1:8080/health
curl http://127.0.0.1:8080/openapi.json

curl -X POST http://127.0.0.1:8080/tasks/demo:hello/run \
  -H "Content-Type: application/json" \
  -d '{"name": "Castor"}'

curl -X POST http://127.0.0.1:8080/tasks/demo:greet/run \
  -H "Content-Type: application/json" \
  -d '{"name": "Castor"}'

curl -X POST http://127.0.0.1:8080/tasks/demo:slow/start \
  -H "Content-Type: application/json" \
  -d '{"seconds": 2}'

curl http://127.0.0.1:8080/tasks/demo:slow/status/{runId}

curl -X POST http://127.0.0.1:8080/tasks/demo:hello/run \
  -H "Authorization: Bearer $CASTOR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Castor"}'
```

## Quality assurance

### Setup

```bash
composer install
```

Generate Castor stubs once (or after upgrading Castor) so PHPStan can resolve Castor types:

```bash
castor list
```

This creates `.castor.stub.php`, referenced by `phpstan.neon`.

### PHPUnit

```bash
composer test
```

### PHPStan

```bash
vendor/bin/phpstan analyse
```

### PHP CS Fixer

PHP CS Fixer is not a project dependency. Apply fixes with [Castor remote execution](https://castor.jolicode.com/docs/getting-started/remote/):

```bash
CASTOR_MEMORY_LIMIT=512M castor execute friendsofphp/php-cs-fixer fix
```

Alternatively, run PHPStan the same way if you prefer not to install dev dependencies locally:

```bash
CASTOR_MEMORY_LIMIT=512M castor execute --deps symfony/console phpstan/phpstan analyse
```

### Local demo

The `fixtures/demo/` directory contains a minimal Castor project with sample tasks. From that directory:

```bash
cd fixtures/demo
castor api:serve-development
```

## Security

- `api:serve-development` binds to `127.0.0.1` by default.
- Set `CASTOR_API_TOKEN` to require a Bearer token on every request (read at runtime by the front controller).
- Without a token, a warning is displayed when starting the development server — suitable for local development only.
- Do not expose the API on a network without authentication and a reverse proxy.
