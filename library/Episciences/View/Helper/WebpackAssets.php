<?php

declare(strict_types=1);

/**
 * Renders <script> tags for a webpack-encore entry by reading entrypoints.json.
 */
class Episciences_View_Helper_WebpackAssets extends Zend_View_Helper_Abstract
{
    /** @var array<string, mixed>|null */
    private static ?array $entrypoints = null;

    private static string $entrypointsPathOverride = '';

    public function webpackAssets(string $entryName): string
    {
        $data = self::loadEntrypoints();

        $scripts = $data['entrypoints'][$entryName]['js'] ?? null;
        if (!is_array($scripts)) {
            return '';
        }

        $html = '';
        foreach ($scripts as $src) {
            if (!is_string($src)) {
                continue;
            }
            $html .= '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }

        return $html;
    }

    /** For testing only — overrides the path to entrypoints.json and resets the cache. */
    public static function setEntrypointsPath(string $path): void
    {
        self::$entrypointsPathOverride = $path;
        self::$entrypoints = null;
    }

    /** @return array<string, mixed> */
    private static function loadEntrypoints(): array
    {
        if (self::$entrypoints !== null) {
            return self::$entrypoints;
        }

        $path = self::$entrypointsPathOverride !== ''
            ? self::$entrypointsPathOverride
            : APPLICATION_PUBLIC_PATH . '/build/entrypoints.json';

        if (!is_file($path)) {
            self::$entrypoints = [];
            return self::$entrypoints;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            self::$entrypoints = [];
            return self::$entrypoints;
        }

        $decoded = json_decode($contents, true);
        self::$entrypoints = is_array($decoded) ? $decoded : [];
        return self::$entrypoints;
    }
}
