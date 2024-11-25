<?php
declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;


return static function (RectorConfig $rectorConfig): void {
    // register single rule
    $rectorConfig->paths([__DIR__ . '/application', __DIR__ . '/library']);

    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets with your IDE
    //  $rectorConfig->sets([
    //      SetList::CODE_QUALITY
    //  ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
    ]);


    //$rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
    $rectorConfig->cacheClass(FileCacheStorage::class);
    $rectorConfig->cacheDirectory(__DIR__ . '/cache/rector');

};

