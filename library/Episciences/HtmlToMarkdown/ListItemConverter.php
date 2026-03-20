<?php

declare(strict_types=1);

namespace Episciences\HtmlToMarkdown;

use League\HTMLToMarkdown\Coerce;
use League\HTMLToMarkdown\Configuration;
use League\HTMLToMarkdown\ConfigurationAwareInterface;
use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;
use League\HTMLToMarkdown\PreConverterInterface;

/**
 * Custom ListItemConverter that fixes numbered list position calculation.
 *
 * The default league/html-to-markdown ListItemConverter uses getSiblingPosition()
 * which can return incorrect values when nested lists are present because it counts
 * all non-whitespace nodes including converted markdown text nodes.
 *
 * This converter uses PreConverterInterface to capture the correct <li> positions
 * BEFORE the DOM is modified during conversion.
 */
class ListItemConverter implements ConverterInterface, ConfigurationAwareInterface, PreConverterInterface
{
    protected Configuration $config;
    protected ?string $listItemStyle = null;

    /**
     * Store positions of <li> elements keyed by their content hash.
     * @var array<string, int>
     */
    private static array $liPositions = [];

    /**
     * Track which <ol> elements we've already processed.
     * @var array<string, bool>
     */
    private static array $processedLists = [];

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    /**
     * Called before conversion starts - capture positions of all <li> in ordered lists.
     */
    public function preConvert(ElementInterface $element): void
    {
        $tagName = $element->getTagName();

        // Process <ol> elements to capture their <li> positions
        if ($tagName === 'ol') {
            $listId = $this->getElementId($element);

            if (!isset(self::$processedLists[$listId])) {
                self::$processedLists[$listId] = true;
                $this->captureListPositions($element);
            }
        }
    }

    /**
     * Capture positions of all direct <li> children of an <ol>.
     */
    private function captureListPositions(ElementInterface $olElement): void
    {
        $position = 0;
        $start = (int) $olElement->getAttribute('start');
        if ($start < 1) {
            $start = 1;
        }

        foreach ($olElement->getChildren() as $child) {
            if ($child->getTagName() === 'li') {
                $position++;
                $liId = $this->getElementId($child);
                self::$liPositions[$liId] = $start + $position - 1;
            }
        }
    }

    /**
     * Generate a unique ID for an element based on its content and structure.
     */
    private function getElementId(ElementInterface $element): string
    {
        // Use the raw HTML content as identifier - this should be unique enough
        return md5($element->getChildrenAsString() . $element->getTagName());
    }

    public function convert(ElementInterface $element): string
    {
        $listType = ($parent = $element->getParent()) ? $parent->getTagName() : 'ul';
        $level = $element->getListItemLevel();

        $value = \trim(\implode("\n" . '    ', \explode("\n", \trim($element->getValue()))));

        // Get the actual position for this <li>
        $liId = $this->getElementId($element);
        $actualPosition = self::$liPositions[$liId] ?? $element->getSiblingPosition();

        $prefix = '';
        if ($level > 0 && $actualPosition === 1) {
            $prefix = "\n";
        }

        if ($listType === 'ul') {
            $listItemStyle = Coerce::toString($this->config->getOption('list_item_style', '-'));
            $listItemStyleAlternate = Coerce::toString($this->config->getOption('list_item_style_alternate', ''));

            if ($this->listItemStyle === null) {
                $this->listItemStyle = $listItemStyleAlternate ?: $listItemStyle;
            }

            if ($listItemStyleAlternate && $level === 0 && $actualPosition === 1) {
                $this->listItemStyle = $this->listItemStyle === $listItemStyle ? $listItemStyleAlternate : $listItemStyle;
            }

            return $prefix . $this->listItemStyle . ' ' . $value . "\n";
        }

        // For ordered lists, use the pre-captured position
        return $prefix . $actualPosition . '. ' . $value . "\n";
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['li'];
    }

    /**
     * Reset the static caches (useful for testing).
     */
    public static function resetCache(): void
    {
        self::$liPositions = [];
        self::$processedLists = [];
    }
}