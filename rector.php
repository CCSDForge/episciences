<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    // Répertoires analysés
    $rectorConfig->paths([
        __DIR__ . '/application',
        __DIR__ . '/library',
        __DIR__ . '/public',
        __DIR__ . '/scripts',
        __DIR__ . '/tests',
    ]);

    // Activation des ensembles de règles
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,  // Migration du code jusqu’à PHP 8.1
        SetList::CODE_QUALITY,       // Améliorations générales du code
        SetList::DEAD_CODE,          // Suppression du code inutilisé
        SetList::TYPE_DECLARATION,   // Ajout de types et retours manquants
    ]);

    // Configuration PHPStan (optionnelle mais recommandée)
    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');

    // Cache de Rector
    $rectorConfig->cacheDirectory(__DIR__ . '/cache/rector');

    // Optionnel : ignorer certains chemins
    // $rectorConfig->skip([
    //     __DIR__ . '/vendor',
    //     __DIR__ . '/cache',
    // ]);
};
