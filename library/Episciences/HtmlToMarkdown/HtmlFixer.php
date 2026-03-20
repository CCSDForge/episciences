<?php

declare(strict_types=1);

namespace Episciences\HtmlToMarkdown;

/**
 * Fixes malformed HTML before conversion to Markdown.
 *
 * Main issue: When <ul> or <ol> is placed as a sibling of <li> inside a parent <ol>/<ul>
 * instead of being nested inside the <li>, the DOM parser reorganizes the structure
 * and breaks list numbering.
 *
 * This class fixes the HTML by moving nested lists inside their preceding <li> element.
 */
class HtmlFixer
{
    /**
     * Fix malformed nested lists in HTML.
     *
     * Transforms:
     *   <ol><li>item</li><ul><li>sub</li></ul><li>next</li></ol>
     * Into:
     *   <ol><li>item<ul><li>sub</li></ul></li><li>next</li></ol>
     */
    public static function fixNestedLists(string $html): string
    {
        // Pattern to find <li>...</li> followed by <ul> or <ol> inside a list
        // We need to move the nested list inside the preceding <li>

        // Fix <li>...</li><ul> -> <li>...<ul>...</ul></li>
        $html = preg_replace_callback(
            '/<li>(.*?)<\/li>\s*(<ul>.*?<\/ul>)/is',
            function ($matches) {
                return '<li>' . $matches[1] . $matches[2] . '</li>';
            },
            $html
        );

        // Fix <li>...</li><ol> -> <li>...<ol>...</ol></li>
        $html = preg_replace_callback(
            '/<li>(.*?)<\/li>\s*(<ol>.*?<\/ol>)/is',
            function ($matches) {
                return '<li>' . $matches[1] . $matches[2] . '</li>';
            },
            $html
        );

        return $html;
    }
}