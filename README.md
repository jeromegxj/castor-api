# jeromegxj/castor-api

Expose [Castor](https://castor.jolicode.com) tasks over HTTP. Only functions marked with both `#[AsTask]` and `#[AsApi]` are published.

The package works in three layers:

1. **Discovery** — at Castor boot, `#[AsApi]` tasks are registered in memory.
2. **Contract** — `castor api:export-openapi` writes `.castor/api/openapi.json`, which defines routes and request schemas.
3. **Execution** — the HTTP front controller loads that spec, maps JSON bodies to Castor CLI arguments, and runs tasks synchronously or in the background.

## Installation

In your project's `castor.php`:

```php
import('composer://jeromegxj/castor-api');
```

Mark tasks you want to expose:

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

Tasks with `async: true` get `/start` and `/status/{runId}` routes in addition to the synchronous `/run` endpoint.

### `#[AsApi]` options

| Parameter      | Default    | Description                                               |
|----------------|------------|-----------------------------------------------------------|
| `path`         | `null`     | Custom base path (default: `/tasks/{name}`)               |
| `methods`      | `['POST']` | HTTP methods for the synchronous `/run` endpoint          |
| `exposeSchema` | `true`     | Export task parameters in the OpenAPI request body schema |
| `async`        | `false`    | Also expose `/start` and `/status/{runId}` endpoints      |

Set `exposeSchema: false` for parameterless tasks that should not appear with a request body in OpenAPI.

### JSON body mapping

The request body is a JSON object whose keys match task parameter names:

| Castor attribute      | JSON key      | CLI mapping                               |
|-----------------------|---------------|-------------------------------------------|
| `#[AsArgument]`       | property name | positional value(s), in declaration order |
| `#[AsOption]` (value) | property name | `--name=value`                            |
| `#[AsOption]` (flag)  | property name | `--name` / `--no-name`                    |
| `#[AsOption]` (array) | property name | repeated `--name=value`                   |

Array arguments expand to multiple positional values (e.g. `{"tags": ["a", "b"]}` → `castor task a b`).

Re-export OpenAPI whenever task signatures change:

```bash
castor api:export-openapi
```

## Development

Export the spec and start the built-in PHP server (default: `127.0.0.1:8080`):

```bash
castor api:serve-development
```

This writes `.castor/api/openapi.json` and is intended for local use only.

Optional authentication:

```bash
export CASTOR_API_TOKEN=your-secret-token
castor api:serve-development
```

## Production

Export OpenAPI at deploy time (or when `#[AsApi]` tasks change):

```bash
castor api:export-openapi
```

Serve with Nginx, FrankenPHP, or PHP-FPM using the package front controller at `public/index.php`.

| Variable                  | Description                                                        |
|---------------------------|--------------------------------------------------------------------|
| `CASTOR_API_OPENAPI`      | Absolute path to `.castor/api/openapi.json`                        |
| `CASTOR_API_PACKAGE_ROOT` | Absolute path to the installed `jeromegxj/castor-api` package       |
| `CASTOR_API_TOKEN`        | Bearer token required on every request (recommended in production) |
| `CASTOR_BINARY`           | Castor binary name or path (default: `castor`)                     |

The project root is inferred from the OpenAPI path (`{projectRoot}/.castor/api/openapi.json`).

Example Nginx location:

```nginx
root /var/www/my-project/vendor/jeromegxj/castor-api/public;

location / {
    try_files $uri /index.php$is_args$args;
}

location ~ ^/index\.php {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME /var/www/my-project/vendor/jeromegxj/castor-api/public/index.php;
    fastcgi_param CASTOR_API_OPENAPI /var/www/my-project/.castor/api/openapi.json;
    fastcgi_param CASTOR_API_PACKAGE_ROOT /var/www/my-project/vendor/jeromegxj/castor-api;
}
```

Example Caddyfile:

```caddyfile
my-project.example.com {
    root * /var/www/my-project/vendor/jeromegxj/castor-api/public

    php_server {
        env CASTOR_API_OPENAPI /var/www/my-project/.castor/api/openapi.json
        env CASTOR_API_PACKAGE_ROOT /var/www/my-project/vendor/jeromegxj/castor-api
        env CASTOR_API_TOKEN your-secret-token
        env CASTOR_BINARY /usr/local/bin/castor
    }
}
```

The PHP worker must be able to execute the Castor binary.

## HTTP API

| Method | Path                           | Description                                                                                                              |
|--------|--------------------------------|--------------------------------------------------------------------------------------------------------------------------|
| GET    | `/health`                      | Health check                                                                                                             |
| GET    | `/openapi.json`                | OpenAPI 3.1 specification                                                                                                |
| POST   | `/tasks/{name}/run`            | Run task synchronously (JSON body = task arguments and options). Returns `422` when the task exits with a non-zero code. |
| POST   | `/tasks/{name}/start`          | Start async run (only for `#[AsApi(async: true)]` tasks)                                                                 |
| GET    | `/tasks/{name}/status/{runId}` | Poll async run status and result. Returns `422` when the run ends with `status: failed`.                                 |

Namespaced tasks use a colon in the path (e.g. `demo:hello` → `/tasks/demo:hello/run`).

Discover tasks and schemas via `GET /openapi.json`.

### Synchronous runs

`POST /tasks/{name}/run` blocks until the Castor process finishes. The response includes `exitCode`, `stdout`, `stderr`, and `durationMs`. A non-zero exit code yields HTTP `422` with the same JSON shape.

### Async runs

For `#[AsApi(async: true)]` tasks:

1. `POST /tasks/{name}/start` with the same JSON body as `/run`
2. Response `202 Accepted`:

```json
{ "id": "uuid", "task": "demo:slow", "status": "pending" }
```

3. Poll `GET /tasks/{name}/status/{runId}` until `status` is `completed` or `failed`

Failures are reported on `/status` (HTTP `422` when `status` is `failed`), not on `/start`. While running, `exitCode`, `stdout`, `stderr`, and `durationMs` are `null`.

Async state is stored as JSON in `.castor/api/runs/{runId}.json`. Runs older than 24 hours are removed when a new async run starts.

### Examples

```bash
curl http://127.0.0.1:8080/health
curl http://127.0.0.1:8080/openapi.json

curl -X POST http://127.0.0.1:8080/tasks/demo:hello/run \
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

## Security

- `api:serve-development` binds to `127.0.0.1` by default.
- Set `CASTOR_API_TOKEN` to require `Authorization: Bearer …` on every request.
- Without a token, the dev server prints a warning — local use only.
- Do not expose the API on a network without authentication and a reverse proxy.

## Quality assurance

```bash
composer install
castor list   # generates .castor.stub.php for PHPStan
composer test
vendor/bin/phpstan analyse
```

PHP CS Fixer is not bundled; run it via [Castor remote execution](https://castor.jolicode.com/docs/getting-started/remote/) if needed:

```bash
CASTOR_MEMORY_LIMIT=512M castor execute friendsofphp/php-cs-fixer fix
```

### Local demo

The `fixtures/demo/` directory is a minimal Castor project with sample tasks:

```bash
cd fixtures/demo
castor api:serve-development
```
