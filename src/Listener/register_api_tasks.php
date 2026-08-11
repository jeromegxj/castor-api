<?php

declare(strict_types=1);

namespace castor_api\listener;

use Castor\Attribute\AsListener;
use Castor\Console\Command\TaskCommand;
use Castor\Event\AfterBootEvent;
use Jolicode\CastorApi\Attribute\AsApi;
use Jolicode\CastorApi\Helper\SchemaExporter;
use Jolicode\CastorApi\Registry\ApiTaskRegistry;

#[AsListener(event: AfterBootEvent::class)]
function register_api_tasks(AfterBootEvent $event): void
{
    ApiTaskRegistry::reset();

    foreach ($event->application->all() as $command) {
        if (!$command instanceof TaskCommand) {
            continue;
        }

        if (!$command->isEnabled()) {
            continue;
        }

        if ([] === $command->getAttributes(AsApi::class)) {
            continue;
        }

        /** @var AsApi $api */
        $api = $command->getAttributes(AsApi::class)[0]->newInstance();
        $schema = $api->exposeSchema ? SchemaExporter::fromCommand($command) : null;

        $workingDirectory = null;
        $reflection = new \ReflectionClass($command);
        if ($reflection->hasProperty('taskDescriptor')) {
            $property = $reflection->getProperty('taskDescriptor');
            $descriptor = $property->getValue($command);
            $workingDirectory = $descriptor->workingDirectory ?? null;
        }

        $taskName = $command->getName();

        if (null === $taskName) {
            continue;
        }

        ApiTaskRegistry::registerEndpoint(
            taskName: $taskName,
            api: $api,
            description: $command->getDescription(),
            workingDirectory: $workingDirectory,
            schema: $schema,
        );
    }
}
