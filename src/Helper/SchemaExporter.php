<?php

declare(strict_types=1);

namespace Jolicode\CastorApi\Helper;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

final class SchemaExporter
{
    /**
     * @return array{arguments: list<array<string, mixed>>, options: list<array<string, mixed>>}
     */
    public static function fromCommand(Command $command): array
    {
        $definition = $command->getDefinition();
        $arguments = [];
        $options = [];

        foreach ($definition->getArguments() as $argument) {
            $arguments[] = self::exportArgument($argument);
        }

        foreach ($definition->getOptions() as $option) {
            if (\in_array($option->getName(), ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'profile'], true)) {
                continue;
            }

            $options[] = self::exportOption($option);
        }

        return [
            'arguments' => $arguments,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function exportArgument(InputArgument $argument): array
    {
        return [
            'name' => $argument->getName(),
            'description' => $argument->getDescription(),
            'required' => $argument->isRequired(),
            'isArray' => $argument->isArray(),
            'default' => $argument->getDefault(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function exportOption(InputOption $option): array
    {
        return [
            'name' => $option->getName(),
            'shortcut' => $option->getShortcut(),
            'description' => $option->getDescription(),
            'acceptValue' => $option->acceptValue(),
            'isValueRequired' => $option->isValueRequired(),
            'isArray' => $option->isArray(),
            'isFlag' => !$option->acceptValue(),
            'default' => $option->getDefault(),
        ];
    }
}
