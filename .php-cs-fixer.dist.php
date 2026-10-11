<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__DIR__ . '/bin/lexware-php-mcp', __FILE__]);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        '@PHP84Migration' => true,
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'final_class' => true,
    ])
    ->setFinder($finder);
