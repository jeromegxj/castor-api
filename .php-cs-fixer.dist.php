<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->ignoreVCSIgnored(true)
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/resources')
    ->in(__DIR__ . '/tests')
    ->append([
        __DIR__ . '/castor.php',
        __FILE__,
    ])
;

return new PhpCsFixer\Config()
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP8x4Migration' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => false,
        'global_namespace_import' => [
            'import_classes' => false,
            'import_constants' => false,
            'import_functions' => null,
        ],
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => false,
        ],
    ])
    ->setFinder($finder)
;
