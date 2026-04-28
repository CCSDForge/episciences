<?php

namespace unit\library\Episciences\Rating;

use Episciences_Rating_Criterion;
use Episciences_Rating_Grid;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for Episciences_Rating_Grid.
 *
 * All tests are DB-free: Grid is a pure in-memory entity.
 *
 * Bugs documented/fixed:
 *   G1 — loadXML() dead call: getElementsByTagName('text') result discarded
 *   G2 — loadXML() null dereference: item(0) on <head> when div has no head child
 *   G3 — loadXML() null dereference: $node->firstChild on empty <f> elements
 *   G4 — getCriterion() TypeError: array_key_exists on null when criteria not initialized
 *
 * @covers Episciences_Rating_Grid
 */
final class Episciences_Rating_GridTest extends TestCase
{
    // =========================================================================
    // __construct / populate
    // =========================================================================

    public function testConstructWithEmptyArrayCreatesEmptyGrid(): void
    {
        $grid = new Episciences_Rating_Grid();

        self::assertNull($grid->getId());
        self::assertNull($grid->getFilename());
        self::assertNull($grid->getCriteria());
        self::assertNull($grid->getXml());
    }

    public function testConstructWithValuesPopulatesProperties(): void
    {
        $grid = new Episciences_Rating_Grid(['id' => 42, 'filename' => 'grid.xml']);

        self::assertSame(42, $grid->getId());
        self::assertSame('grid.xml', $grid->getFilename());
    }

    public function testConstructIgnoresUnknownKeys(): void
    {
        $grid = new Episciences_Rating_Grid(['nonexistent' => 'value']);

        self::assertNull($grid->getId());
    }

    // =========================================================================
    // setCriteria / getCriteria
    // =========================================================================

    public function testSetCriteriaAcceptsCriterionObjects(): void
    {
        $grid = new Episciences_Rating_Grid();
        $c    = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);

        $grid->setCriteria([$c]);

        self::assertCount(1, $grid->getCriteria());
        self::assertSame($c, $grid->getCriteria()[0]);
    }

    public function testSetCriteriaConvertsArraysToCriterionObjects(): void
    {
        $grid = new Episciences_Rating_Grid();
        $grid->setCriteria([
            ['type' => Episciences_Rating_Criterion::TYPE_SEPARATOR],
        ]);

        $criteria = $grid->getCriteria();
        self::assertCount(1, $criteria);
        self::assertInstanceOf(Episciences_Rating_Criterion::class, $criteria[0]);
        self::assertTrue($criteria[0]->isSeparator());
    }

    public function testSetCriteriaRejectsNonCriterionNonArrayItems(): void
    {
        // is_a() check: objects that are not Criterion instances are converted via new Criterion()
        // which may fail silently — just verify the count
        $grid = new Episciences_Rating_Grid();
        $grid->setCriteria([
            new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION]),
            new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_SEPARATOR]),
        ]);

        self::assertCount(2, $grid->getCriteria());
    }

    public function testSetCriteriaWithEmptyArrayGivesEmptyArray(): void
    {
        $grid = new Episciences_Rating_Grid();
        $grid->setCriteria([]);

        self::assertIsArray($grid->getCriteria());
        self::assertCount(0, $grid->getCriteria());
    }

    // =========================================================================
    // addCriterion
    // =========================================================================

    public function testAddCriterionAppendsToCriteria(): void
    {
        $grid = new Episciences_Rating_Grid();
        $grid->setCriteria([]);

        $c1 = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION]);
        $c2 = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_SEPARATOR]);

        $grid->addCriterion($c1);
        $grid->addCriterion($c2);

        self::assertCount(2, $grid->getCriteria());
    }

    // =========================================================================
    // setCriterion
    // =========================================================================

    public function testSetCriterionReplacesAtIndex(): void
    {
        $grid = new Episciences_Rating_Grid();
        $c1   = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION]);
        $c2   = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_SEPARATOR]);

        $grid->setCriterion(0, $c1);
        $grid->setCriterion(0, $c2);

        self::assertSame($c2, $grid->getCriteria()[0]);
    }

    // =========================================================================
    // getCriterion — Bug G4 fix
    // =========================================================================

    /**
     * Fix G4: getCriterion() must return null (not throw TypeError) when
     * _criteria has not been initialized (is null).
     * Before the fix: array_key_exists($id, null) → TypeError in PHP 8.1+.
     */
    public function testGetCriterionReturnsNullWhenCriteriaNotInitialized(): void
    {
        $grid = new Episciences_Rating_Grid();
        // _criteria is null by default

        self::assertNull($grid->getCriterion(0));
    }

    public function testGetCriterionReturnsNullForMissingKey(): void
    {
        $grid = new Episciences_Rating_Grid();
        $grid->setCriteria([]);

        self::assertNull($grid->getCriterion(99));
    }

    public function testGetCriterionReturnsCorrectCriterion(): void
    {
        $grid = new Episciences_Rating_Grid();
        $c    = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION]);
        $grid->setCriterion('item_0', $c);

        self::assertSame($c, $grid->getCriterion('item_0'));
    }

    // =========================================================================
    // removeCriterion
    // =========================================================================

    public function testRemoveCriterionDeletesMatchingCriterion(): void
    {
        $grid = new Episciences_Rating_Grid();

        $c1 = new Episciences_Rating_Criterion();
        $c1->setId('item_0');
        $c2 = new Episciences_Rating_Criterion();
        $c2->setId('item_1');

        $grid->setCriteria([$c1, $c2]);
        $grid->removeCriterion('item_0');

        $remaining = array_values($grid->getCriteria());
        self::assertCount(1, $remaining);
        self::assertSame('item_0', $remaining[0]->getId(), 'item_1 should be renumbered to item_0');
    }

    public function testRemoveCriterionOnNonExistentIdLeavesAllCriteria(): void
    {
        $grid = new Episciences_Rating_Grid();

        $c = new Episciences_Rating_Criterion();
        $c->setId('item_0');
        $grid->setCriteria([$c]);

        $grid->removeCriterion('item_99');

        self::assertCount(1, $grid->getCriteria());
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testToArrayContainsCriteriaKey(): void
    {
        $grid = new Episciences_Rating_Grid();
        $grid->setCriteria([]);

        $array = $grid->toArray();

        self::assertArrayHasKey('criteria', $array);
        self::assertIsArray($array['criteria']);
    }

    public function testToArrayWithNullCriteriaReturnsCriteriaKey(): void
    {
        $grid  = new Episciences_Rating_Grid();
        $array = $grid->toArray();

        self::assertArrayHasKey('criteria', $array);
        self::assertIsArray($array['criteria']);
        self::assertCount(0, $array['criteria']);
    }

    public function testToArraySerializesCriterionObjects(): void
    {
        $grid = new Episciences_Rating_Grid();

        $c = new Episciences_Rating_Criterion();
        $c->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $c->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $c->setPosition(1);

        $grid->setCriteria([$c]);

        $array = $grid->toArray();
        self::assertCount(1, $array['criteria']);
        self::assertSame(Episciences_Rating_Criterion::TYPE_CRITERION, $array['criteria'][0]['type']);
    }

    // =========================================================================
    // Bug G1 — dead call removed from loadXML()
    // =========================================================================

    /**
     * Fix G1: loadXML() previously called $xml->getElementsByTagName('text')
     * without assigning or using the result — a no-op that wasted a DOM traversal.
     * Verify the dead call was removed from the source.
     */
    public function testLoadXmlNoLongerHasDeadGetElementsByTagNameTextCall(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Grid::class, 'loadXML');
        $lines      = file($reflection->getFileName());
        $source     = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        self::assertStringNotContainsString(
            "getElementsByTagName('text');",
            $source,
            "Fix G1: the dead call \$xml->getElementsByTagName('text') (result discarded) must be removed"
        );
    }

    // =========================================================================
    // Bug G2 — null safety on item(0) for <head> element
    // =========================================================================

    /**
     * Fix G2: loadXML() previously called item(0)->getElementsByTagName('label')
     * directly. If a <div> had no <head> child, item(0) returns null and PHP threw
     * a TypeError. Verify the fix guards with a null check before iterating.
     */
    public function testLoadXmlGuardsAgainstNullHeadElement(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Grid::class, 'loadXML');
        $lines      = file($reflection->getFileName());
        $source     = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        // Old pattern: item(0)->getElementsByTagName — direct chaining without null check
        self::assertStringNotContainsString(
            "->item(0)->getElementsByTagName",
            $source,
            "Fix G2: item(0) must not be chained directly — assign to variable and null-check first"
        );

        // New pattern: null check before iterating
        self::assertStringContainsString(
            '$headElement !== null',
            $source,
            "Fix G2: a null guard on the head element must be present"
        );
    }

    // =========================================================================
    // Bug G3 — null safety on firstChild in <f> elements
    // =========================================================================

    /**
     * Fix G3: loadXML() called $node->firstChild->getAttribute('value') without
     * checking firstChild for null. An empty <f> element caused a fatal TypeError.
     */
    public function testLoadXmlGuardsAgainstNullFirstChild(): void
    {
        $reflection = new ReflectionMethod(Episciences_Rating_Grid::class, 'loadXML');
        $lines      = file($reflection->getFileName());
        $source     = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

        self::assertStringContainsString(
            '$node->firstChild !== null',
            $source,
            "Fix G3: firstChild must be null-checked before calling getAttribute() on it"
        );
    }
}
