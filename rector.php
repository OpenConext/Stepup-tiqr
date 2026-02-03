<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->importNames();

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SymfonySetList::SYMFONY_64,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
};
