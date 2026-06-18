<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;

return RectorConfig::configure()
    ->withSkip([
        // phpmd/pdepend 2.x cannot parse `new Foo()->method()` syntax (PHP 8.4+)
        NewMethodCallWithoutParenthesesRector::class,
    ])
    ->withPaths([
         __DIR__ . '/../../ci',
         __DIR__ . '/../../config',
         __DIR__ . '/../../src',
         __DIR__ . '/../../templates',
    ])
    ->withPhpSets()
    ->withAttributesSets(all: true)
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon'])
    ->withPreparedSets(deadCode: true)
;
