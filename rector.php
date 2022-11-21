<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Php71\Rector\FuncCall\CountOnNullRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);
    $rectorConfig->skip([
        CountOnNullRector::class,
    ]);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_80,
    ]);
};
