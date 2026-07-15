<?php

declare(strict_types=1);

/*
 * ----------------------------------------------------------------
 * Bus Booking System - Configuration PHP-CS-Fixer
 * ----------------------------------------------------------------
 * Normes : PSR-12 + @Symfony + strict_types partout
 * Usage :
 *   vendor/bin/php-cs-fixer fix --dry-run --diff   (vérification)
 *   vendor/bin/php-cs-fixer fix                    (correction auto)
 * ----------------------------------------------------------------
 */

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['var', 'vendor'])
    ->notPath('bootstrap.php')
    ->notPath('#/Migrations/#');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Typage strict obligatoire
        'declare_strict_types' => true,
        'strict_param' => true,
        'strict_comparison' => true,

        // Qualité / lisibilité
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_order' => true,
        'void_return' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'no_superfluous_phpdoc_tags' => ['allow_mixed' => true],

        // Sécurité / cohérence
        'no_alias_functions' => true,
        'modernize_types_casting' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache');
