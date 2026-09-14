<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Set\ValueObject\LevelSetList;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withCache(
        __DIR__ . '/storage/framework/cache/rector',
        null,
        __DIR__ . '/storage/framework/cache/rector/container',
    )
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/routes',
        __DIR__ . '/database',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        ReadOnlyClassRector::class,
        __DIR__ . '/bootstrap/cache',
        __DIR__ . '/storage',
        __DIR__ . '/vendor',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_85,
        LaravelLevelSetList::UP_TO_LARAVEL_130,
    ]);
