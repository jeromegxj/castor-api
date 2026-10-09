#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

SYNC=2a5f244
FINAL=backup-squashed-prototype

extract_sync() {
  local f="$1"
  mkdir -p "$(dirname "$f")"
  git show "${SYNC}:${f}" | sed \
    -e 's/Jolicode\\CastorApi/Castor\\Api/g' \
    -e 's/jolicode\/castor-api/jeromegxj\/castor-api/g' \
    > "$f"
}

copy_final() {
  git show "${FINAL}:${1}" > "$1"
}

commit_msg() {
  git add -A
  git status --short
  git commit -m "$1"
}

# Remove everything from index; keep working tree for manual cleanup between steps
reset_tree() {
  git rm -rf --cached . 2>/dev/null || true
  rm -rf src tests public resources castor.php README.md 2>/dev/null || true
}

reset_tree

# 1. Package skeleton
git show "${SYNC}:.gitignore" > .gitignore
git show "${SYNC}:.php-cs-fixer.dist.php" > .php-cs-fixer.dist.php
git show "${SYNC}:phpstan.neon" > phpstan.neon
git show "${SYNC}:phpunit.xml.dist" > phpunit.xml.dist
copy_final composer.json
copy_final castor.composer.json
# Drop async-only dependency until commit 6
php -r '
$j = json_decode(file_get_contents("composer.json"), true);
unset($j["require"]["symfony/uid"]);
file_put_contents("composer.json", json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
php -r '
$j = json_decode(file_get_contents("castor.composer.json"), true);
unset($j["require"]["symfony/uid"]);
file_put_contents("castor.composer.json", json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
'
cat > castor.php <<'PHP'
<?php

declare(strict_types=1);

use function Castor\import;

$packageRoot = __DIR__;

foreach ([
    $packageRoot . '/vendor/autoload.php',
    $packageRoot . '/.castor/vendor/autoload.php',
] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        break;
    }
}
PHP
commit_msg "$(cat <<'EOF'
chore: bootstrap castor-api package

Add Composer metadata, Castor integration entrypoint, and QA tooling (PHPUnit, PHPStan, CS Fixer config).
EOF
)"

# 2. AsApi discovery
for f in \
  src/Attribute/AsApi.php \
  src/Descriptor/ApiEndpoint.php \
  src/Registry/ApiTaskRegistry.php \
  src/Listener/register_api_tasks.php \
  src/Helper/SchemaExporter.php \
  src/Helper/TaskName.php \
  src/Helper/Paths.php
do
  extract_sync "$f"
done
cat >> castor.php <<'PHP'

import($packageRoot . '/src/Listener/register_api_tasks.php');
PHP
commit_msg "$(cat <<'EOF'
feat: discover #[AsApi] tasks at Castor boot

Register API endpoints from TaskCommand metadata via an AfterBoot listener and in-memory registry.
EOF
)"

# 3. OpenAPI export
for f in \
  src/OpenApi/CastorSchemaExtensions.php \
  src/OpenApi/OpenApiExporter.php \
  src/OpenApi/OpenApiSchemaBuilder.php \
  src/OpenApi/OperationContext.php \
  src/Helper/RoutingExporter.php \
  src/Task/export_openapi.php \
  tests/OpenApi/OpenApiSchemaBuilderTest.php
do
  extract_sync "$f"
done
cat >> castor.php <<'PHP'
import($packageRoot . '/src/Task/export_openapi.php');
PHP
commit_msg "$(cat <<'EOF'
feat: export OpenAPI spec for published tasks

Build OpenAPI 3.1 from the registry and expose the api:export-openapi Castor task.
EOF
)"

# 4. Sync execution
for f in \
  src/Helper/JsonSchemaToCli.php \
  src/Runner/SubprocessTaskRunner.php \
  src/Event/ApiTaskRunEvent.php \
  tests/Helper/JsonSchemaToCliTest.php
do
  extract_sync "$f"
done
commit_msg "$(cat <<'EOF'
feat: run tasks synchronously via subprocess

Map JSON request bodies to Castor CLI arguments and execute tasks with captured stdout/stderr.
EOF
)"

# 5. HTTP API (production)
for f in \
  src/OpenApi/OpenApiLoader.php \
  src/Http/AuthMiddleware.php \
  src/Http/Router.php \
  src/Http/TaskRunHandler.php \
  src/Helper/AuthToken.php \
  resources/autoload.php \
  resources/front-controller.php \
  public/index.php \
  tests/Http/AuthMiddlewareTest.php \
  tests/Http/RouterTest.php
do
  extract_sync "$f"
done
commit_msg "$(cat <<'EOF'
feat: serve sync task endpoints over HTTP

Route requests from exported OpenAPI, authenticate with optional bearer token, and run tasks via POST /tasks/{name}/run.
EOF
)"

# 6. Async runs — final versions of evolved files + new modules
for f in \
  src/Attribute/AsApi.php \
  src/Descriptor/ApiEndpoint.php \
  src/Registry/ApiTaskRegistry.php \
  src/Listener/register_api_tasks.php \
  src/Helper/Paths.php \
  src/Helper/TaskName.php \
  src/OpenApi/CastorSchemaExtensions.php \
  src/OpenApi/OpenApiExporter.php \
  src/OpenApi/OpenApiSchemaBuilder.php \
  src/OpenApi/OpenApiLoader.php \
  src/OpenApi/OperationContext.php \
  src/OpenApi/OperationKind.php \
  src/Runner/SubprocessTaskRunner.php \
  src/Http/Router.php \
  src/Http/TaskRunHandler.php \
  src/Http/RequestPayloadParser.php \
  src/Http/TaskHttpStatus.php \
  src/Http/TaskStartHandler.php \
  src/Http/TaskStatusHandler.php \
  src/Run/RunExecutor.php \
  src/Run/RunRecord.php \
  src/Run/RunStatus.php \
  src/Run/RunStore.php \
  src/Runner/AsyncRunLauncher.php \
  resources/run-worker.php \
  resources/autoload.php \
  resources/front-controller.php \
  tests/OpenApi/OpenApiSchemaBuilderTest.php \
  tests/OpenApi/OpenApiExporterTest.php \
  tests/Helper/JsonSchemaToCliTest.php \
  tests/Http/RequestPayloadParserTest.php \
  tests/Http/TaskHttpStatusTest.php \
  tests/Http/TaskStatusHandlerTest.php \
  tests/Run/RunExecutorTest.php \
  tests/Run/RunStoreTest.php \
  composer.json \
  castor.composer.json
do
  copy_final "$f"
done
commit_msg "$(cat <<'EOF'
feat: async runs with /start and /status endpoints

Persist runs on disk, launch background workers, expose async OpenAPI operations, and return HTTP 422 on task failure.
EOF
)"

# 7. Dev server
for f in \
  src/Task/serve_development.php \
  resources/http-server.php
do
  copy_final "$f"
done
cat >> castor.php <<'PHP'
import($packageRoot . '/src/Task/serve_development.php');
PHP
commit_msg "$(cat <<'EOF'
feat: add api:serve-development for local HTTP

Export OpenAPI and start the PHP built-in server bound to localhost by default.
EOF
)"

echo "Done. Log:"
git log --oneline
