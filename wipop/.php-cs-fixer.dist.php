<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/admin',
        __DIR__ . '/core',
        __DIR__ . '/gateways',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->append([
        __DIR__ . '/wipop.php',
    ])
    ->exclude([
        'assets',
        'build',
        'node_modules',
        'vendor',
        'var',
    ])
    ->name('*.php')
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(true)
    ->setIndent("\t")
    ->setLineEnding("\n")
    ->setRules([
        '@PhpCsFixer' => true,
        '@PHP80Migration' => true,
        '@PHP81Migration' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
        ],
        'linebreak_after_opening_tag' => false,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_summary' => false,
        'single_line_empty_body' => false,
        'single_quote' => true,
        'yoda_style' => false,
        'indentation_type' => true,
        'statement_indentation' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
