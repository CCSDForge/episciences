<?php
// rector.php
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return static function (RectorConfig $rectorConfig): void {
    // register single rule
    $rectorConfig->paths([__DIR__ . '/application', __DIR__ . '/library']);

    // here we can define, what sets of rules will be applied
    // tip: use "SetList" class to autocomplete sets with your IDE
    //  $rectorConfig->sets([
    //      SetList::CODE_QUALITY
    //  ]);

    $rectorConfig->sets([
        //SetList::PHP_81,
        SetList::DEAD_CODE,
        //SetList::PRIVATIZATION
    ]);

    //$rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};

