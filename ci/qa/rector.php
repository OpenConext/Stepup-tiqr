<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
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
