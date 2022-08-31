<?php
// rector.php
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Set\ValueObject\SetList;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void {
// paths to refactor; solid alternative to CLI arguments
    $rectorConfig->paths([__DIR__ . '/application', __DIR__ . '/library']);


    $rectorConfig->sets([
        SetList::PHP_74,
        SetList::DEAD_CODE
    ]);


// is your PHP version different from the one you refactor to? [default: your PHP version], uses PHP_VERSION_ID format
$rectorConfig->phpVersion(PhpVersion::PHP_74);

// Path to PHPStan with extensions, that PHPStan in Rector uses to determine types
$rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};