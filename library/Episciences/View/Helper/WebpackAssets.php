<?php

declare(strict_types=1);

/**
 * Renders <script> tags, or exposes raw URLs, for a webpack-encore entry by reading entrypoints.json.
 */
class Episciences_View_Helper_WebpackAssets extends Zend_View_Helper_Abstract
{
    /** @var array<string, mixed>|null */
    private static ?array $entrypoints = null;

    private static string $entrypointsPathOverride = '';

    public function webpackAssets(string $entryName): string
    {
        $html = '';
        foreach ($this->getEntryUrls($entryName) as $src) {
            $html .= '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . PHP_EOL;
        }

        return $html;
    }

    /**
     * Raw asset URLs for a webpack-encore entry, e.g. to feed them into another renderer
     * (such as the jQuery container, so they inherit its APPLICATION_VERSION cache-busting).
     *
     * @return list<string>
     */
    public function getEntryUrls(string $entryName, string $type = 'js'): array
    {
        $data = self::loadEntrypoints();

        $urls = $data['entrypoints'][$entryName][$type] ?? null;
        if (!is_array($urls)) {
            return [];
        }

        return array_values(array_filter($urls, 'is_string'));
    }

    /**
     * Queues a webpack-encore entry's JS files onto the jQuery container (addJavascriptFile),
     * so they render alongside — and are cache-busted the same way as — the rest of the page's scripts.
     */
    public function queueScript(string $entryName): void
    {
        foreach ($this->getEntryUrls($entryName, 'js') as $url) {
            $this->view->jQuery()->addJavascriptFile($url);
        }
    }

    /**
     * Queues a webpack-encore entry's CSS files onto the jQuery container (addStylesheet),
     * so they render alongside — and are cache-busted the same way as — the rest of the page's styles.
     */
    public function queueStylesheet(string $entryName): void
    {
        foreach ($this->getEntryUrls($entryName, 'css') as $url) {
            $this->view->jQuery()->addStylesheet($url);
        }
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
