<?php

declare(strict_types=1);

namespace unit\library\Episciences\Rating;

use Episciences_Rating_Criterion;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Rating_Criterion.
 *
 * Pure entity: language fallback for labels/descriptions, options handling,
 * note/comment/attachment predicates, separator/criterion typing and toArray().
 */
class Episciences_Rating_CriterionTest extends TestCase
{
    // ---------------------------------------------------------------------
    // populate() / constructor
    // ---------------------------------------------------------------------

    public function testConstructorPopulatesViaSetters(): void
    {
        $criterion = new Episciences_Rating_Criterion(['type' => Episciences_Rating_Criterion::TYPE_CRITERION, 'position' => 3]);

        self::assertSame(Episciences_Rating_Criterion::TYPE_CRITERION, $criterion->getType());
        self::assertSame(3, $criterion->getPosition());
    }

    public function testPopulateIsCaseInsensitiveAndIgnoresUnknownKeys(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->populate(['TYPE' => Episciences_Rating_Criterion::TYPE_SEPARATOR, 'unknownKey' => 'ignored']);

        self::assertSame(Episciences_Rating_Criterion::TYPE_SEPARATOR, $criterion->getType());
    }

    public function testConstructorWithNonArrayDoesNotThrow(): void
    {
        $criterion = new Episciences_Rating_Criterion('not-an-array');

        self::assertNull($criterion->getId());
    }

    // ---------------------------------------------------------------------
    // getLabel() language fallback
    // ---------------------------------------------------------------------

    public function testGetLabelReturnsRequestedLanguage(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setLabel('fr', 'Originalité');
        $criterion->setLabel('en', 'Originality');

        self::assertSame('Originalité', $criterion->getLabel('fr'));
        self::assertSame('Originality', $criterion->getLabel('en'));
    }

    public function testGetLabelFallsBackToEnglishWhenRequestedMissing(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setLabel('en', 'Originality');

        self::assertSame('Originality', $criterion->getLabel('de'));
    }

    public function testGetLabelFallsBackToFirstWhenNoEnglish(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setLabel('es', 'Originalidad');

        self::assertSame('Originalidad', $criterion->getLabel('de'));
    }

    public function testGetLabelReturnsNullWhenNoLabels(): void
    {
        $criterion = new Episciences_Rating_Criterion();

        self::assertNull($criterion->getLabel('en'));
    }

    // ---------------------------------------------------------------------
    // getDescription() language fallback
    // ---------------------------------------------------------------------

    public function testGetDescriptionFallsBackToEnglish(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setDescription('en', 'A description');

        self::assertSame('A description', $criterion->getDescription('it'));
    }

    public function testGetDescriptionReturnsNullWhenEmpty(): void
    {
        $criterion = new Episciences_Rating_Criterion();

        self::assertNull($criterion->getDescription('en'));
    }

    // ---------------------------------------------------------------------
    // options + getOptionLabel + getMaxNote
    // ---------------------------------------------------------------------

    public function testGetOptionReturnsFalseForUnknownId(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([0 => ['label' => ['en' => 'Poor']]]);

        self::assertFalse($criterion->getOption(5));
        self::assertIsArray($criterion->getOption(0));
    }

    public function testGetOptionLabelReturnsLocalizedLabel(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([
            1 => ['label' => ['fr' => 'Moyen', 'en' => 'Average']],
        ]);

        self::assertSame('Moyen', $criterion->getOptionLabel(1, 'fr'));
        self::assertSame('Average', $criterion->getOptionLabel(1, 'de')); // english fallback
    }

    public function testGetOptionLabelReturnsNullWhenOptionMissing(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([1 => ['label' => ['en' => 'Average']]]);

        self::assertNull($criterion->getOptionLabel(99, 'en'));
    }

    public function testGetOptionLabelReturnsNullWhenLabelNotArray(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([1 => ['label' => 'plain-string']]);

        self::assertNull($criterion->getOptionLabel(1, 'en'));
    }

    public function testGetMaxNoteReturnsOneWhenNoOptions(): void
    {
        $criterion = new Episciences_Rating_Criterion();

        self::assertSame(1, $criterion->getMaxNote());
    }

    public function testGetMaxNoteReturnsHighestOptionKey(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([0 => [], 1 => [], 2 => [], 3 => []]);

        self::assertSame(3, $criterion->getMaxNote());
    }

    public function testGetMaxNoteReturnsOneWhenOnlyZeroOption(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([0 => []]);

        // max key is 0, which is not > 0, so fallback to 1
        self::assertSame(1, $criterion->getMaxNote());
    }

    // ---------------------------------------------------------------------
    // coefficient / allows* / hasOptions
    // ---------------------------------------------------------------------

    public function testHasCoefficient(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        self::assertFalse($criterion->hasCoefficient());

        $criterion->setCoefficient(2);
        self::assertTrue($criterion->hasCoefficient());

        $criterion->setCoefficient('not-numeric');
        self::assertFalse($criterion->hasCoefficient());
    }

    public function testAllowsNoteMirrorsHasOptions(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        self::assertFalse($criterion->hasOptions());
        self::assertFalse($criterion->allowsNote());

        $criterion->setOptions([0 => [], 1 => []]);
        self::assertTrue($criterion->hasOptions());
        self::assertTrue($criterion->allowsNote());
    }

    public function testAllowsCommentAndAttachmentReflectSettings(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setComment_setting(true);
        $criterion->setAttachment_setting(false);

        self::assertTrue($criterion->allowsComment());
        self::assertFalse($criterion->allowsAttachment());
    }

    // ---------------------------------------------------------------------
    // isCustom()
    // ---------------------------------------------------------------------

    public function testIsCustomReturnsTrueWhenAnyOptionHasLabel(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([
            0 => ['value' => 0],
            1 => ['label' => ['en' => 'Good']],
        ]);

        self::assertTrue($criterion->isCustom());
    }

    public function testIsCustomReturnsFalseWhenNoLabels(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([0 => ['value' => 0], 1 => ['value' => 1]]);

        self::assertFalse($criterion->isCustom());
    }

    public function testIsCustomReturnsFalseWhenLabelEmpty(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setOptions([0 => ['label' => []]]);

        self::assertFalse($criterion->isCustom());
    }

    // ---------------------------------------------------------------------
    // note / comment / attachment predicates
    // ---------------------------------------------------------------------

    public function testHasNoteIsFalseUntilNoteSet(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        self::assertFalse($criterion->hasNote());
    }

    public function testSetNoteCastsToIntAndHasNoteBecomesTrue(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setNote('3');

        self::assertSame(3, $criterion->getNote());
        self::assertTrue($criterion->hasNote());
    }

    public function testHasComment(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        self::assertFalse($criterion->hasComment());

        $criterion->setComment('Needs more detail');
        self::assertTrue($criterion->hasComment());
    }

    public function testHasAttachment(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        self::assertFalse($criterion->hasAttachment());

        $criterion->setAttachment('report.pdf');
        self::assertTrue($criterion->hasAttachment());
    }

    // ---------------------------------------------------------------------
    // isEmpty / hasValue
    // ---------------------------------------------------------------------

    public function testIsEmptyAndHasValue(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        self::assertTrue($criterion->isEmpty());
        self::assertFalse($criterion->hasValue());

        $criterion->setComment('A comment');
        self::assertFalse($criterion->isEmpty());
        self::assertTrue($criterion->hasValue());
    }

    public function testHasValueWithNoteOnly(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setNote(2);

        self::assertTrue($criterion->hasValue());
        self::assertFalse($criterion->isEmpty());
    }

    // ---------------------------------------------------------------------
    // type predicates
    // ---------------------------------------------------------------------

    public function testIsSeparatorAndIsCriterion(): void
    {
        $separator = new Episciences_Rating_Criterion();
        $separator->setType(Episciences_Rating_Criterion::TYPE_SEPARATOR);
        self::assertTrue($separator->isSeparator());
        self::assertFalse($separator->isCriterion());

        $criterion = new Episciences_Rating_Criterion();
        $criterion->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        self::assertTrue($criterion->isCriterion());
        self::assertFalse($criterion->isSeparator());
    }

    // ---------------------------------------------------------------------
    // toArray()
    // ---------------------------------------------------------------------

    public function testToArrayForSeparatorOmitsCriterionFields(): void
    {
        $separator = new Episciences_Rating_Criterion();
        $separator->setType(Episciences_Rating_Criterion::TYPE_SEPARATOR);
        $separator->setVisibility(Episciences_Rating_Criterion::VISIBILITY_PUBLIC);
        $separator->setPosition(1);

        $array = $separator->toArray();

        self::assertSame(Episciences_Rating_Criterion::TYPE_SEPARATOR, $array['type']);
        self::assertArrayHasKey('labels', $array);
        self::assertArrayNotHasKey('options', $array);
        self::assertArrayNotHasKey('note', $array);
    }

    public function testToArrayForCriterionIncludesCriterionFields(): void
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $criterion->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $criterion->setPosition(2);
        $criterion->setNote(4);
        $criterion->setCoefficient(2);
        $criterion->setComment('ok');
        $criterion->setOptions([0 => [], 1 => []]);

        $array = $criterion->toArray();

        self::assertSame(Episciences_Rating_Criterion::TYPE_CRITERION, $array['type']);
        self::assertArrayHasKey('options', $array);
        self::assertSame(4, $array['note']);
        self::assertSame(2, $array['coefficient']);
        self::assertSame('ok', $array['comment']);
    }

    // ---------------------------------------------------------------------
    // visibility constants / emojis
    // ---------------------------------------------------------------------

    public function testVisibilityEmojisCoverAllVisibilities(): void
    {
        $emojis = Episciences_Rating_Criterion::$visibilityEmojis;

        self::assertArrayHasKey(Episciences_Rating_Criterion::VISIBILITY_PUBLIC, $emojis);
        self::assertArrayHasKey(Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR, $emojis);
        self::assertArrayHasKey(Episciences_Rating_Criterion::VISIBILITY_EDITORS, $emojis);
    }
}
