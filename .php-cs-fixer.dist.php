<?php

declare(strict_types=1);

// Code style: PER Coding Style, plus sorted and no unused imports.
return new PhpCsFixer\Config()
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin'])
            ->append([__FILE__])
            ->name(['*.php', 'wp-app-framework']),
    )
    ->setCacheFile(sys_get_temp_dir() . '/php-cs-fixer-wp-app-framework.cache');
