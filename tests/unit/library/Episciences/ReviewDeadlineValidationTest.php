<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\Form\Validate\DeadlineUnit;
use Episciences_Form_Validate_CheckDefaultRatingDeadline;
use Episciences_Form_Validate_CheckMaximumDeadlineDelay;
use Episciences_Form_Validate_CheckMinimumDeadlineDelay;
use Episciences_Review;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Form_Element_Text;
use Zend_View;

/**
 * Deadline settings of the journal: a positive integer and one of the known units.
 */
final class ReviewDeadlineValidationTest extends TestCase
{
    private function buildElement(): Zend_Form_Element_Text
    {
        $method = new ReflectionMethod(Episciences_Review::class, 'getDeadlineValueValidators');
        $method->setAccessible(true);

        $element = new Zend_Form_Element_Text('deadline');
        $element->setRequired(true);
        $element->setValidators($method->invoke(null, 'deadline_unit'));

        return $element;
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function deadlineValueProvider(): array
    {
        return [
            'integer' => ['7', true],
            'two digits' => ['12', true],
            'zero' => ['0', false],
            'negative' => ['-1', false],
            'decimal' => ['1.5', false],
            'exponent' => ['1e3', false],
            'text' => ['abc', false],
            'markup' => ['"><img src=x onerror=alert(1)>', false],
            'with unit' => ['2 month', false],
            'maximum' => ['999', true],
            'above maximum' => ['1000', false],
            'overflowing integer' => ['99999999999999999999', false],
            'empty' => ['', false],
            'surrounding spaces' => [' 7 ', false],
        ];
    }

    /**
     * @dataProvider deadlineValueProvider
     */
    public function testDeadlineValue(string $value, bool $expected): void
    {
        self::assertSame($expected, $this->buildElement()->isValid($value, ['deadline_unit' => 'month']));
    }

    private const UNIT_KEYS = [
        Episciences_Review::SETTING_RATING_DEADLINE_UNIT,
        Episciences_Review::SETTING_RATING_DEADLINE_MIN_UNIT,
        Episciences_Review::SETTING_RATING_DEADLINE_MAX_UNIT,
        Episciences_Review::SETTING_INVITATION_DEADLINE_UNIT,
    ];

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function unitProvider(): array
    {
        return [
            'day' => ['day', true],
            'week' => ['week', true],
            'month' => ['month', true],
            'plural' => ['weeks', false],
            'uppercase' => ['DAY', false],
            'empty' => ['', false],
            'null' => [null, false],
            'array' => [['day'], false],
            'markup' => ['"><script>alert(1)</script>', false],
            'sql' => ["day'); DROP TABLE x;--", false],
        ];
    }

    /**
     * @dataProvider unitProvider
     */
    public function testUnitValidator(mixed $unit, bool $expected): void
    {
        $validator = new DeadlineUnit('rating_deadline_unit');

        self::assertSame($expected, $validator->isValid('2', ['rating_deadline_unit' => $unit]));
    }

    public function testMissingUnitIsRejected(): void
    {
        self::assertFalse((new DeadlineUnit('rating_deadline_unit'))->isValid('2', []));
        self::assertFalse((new DeadlineUnit('rating_deadline_unit'))->isValid('2'));
    }

    /**
     * The unit validator is wired to the value element through the form context, one unit per element.
     *
     * @dataProvider unitKeyProvider
     */
    public function testEachValueElementChecksItsOwnUnit(string $unitKey): void
    {
        $valueKey = substr($unitKey, 0, -5);
        $context = array_fill_keys(self::UNIT_KEYS, 'day');

        $validators = (new ReflectionMethod(Episciences_Review::class, 'getDeadlineValueValidators'))->invoke(null, $unitKey);
        $element = new Zend_Form_Element_Text($valueKey);
        $element->setRequired(true)->setValidators($validators);

        self::assertTrue($element->isValid('2', $context));

        $context[$unitKey] = 'weeks';
        self::assertFalse($element->isValid('2', $context));
        self::assertContains(DeadlineUnit::INVALID_UNIT, $element->getErrors());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unitKeyProvider(): array
    {
        return array_combine(self::UNIT_KEYS, array_map(static fn(string $k): array => [$k], self::UNIT_KEYS));
    }

    public function testBuildIntervalRejectsUnusableInput(): void
    {
        self::assertSame('3 week', DeadlineUnit::buildInterval(['d' => ' 3 ', 'd_unit' => 'week'], 'd'));
        self::assertNull(DeadlineUnit::buildInterval(['d' => '3', 'd_unit' => 'weeks'], 'd'));
        self::assertNull(DeadlineUnit::buildInterval(['d' => 'abc', 'd_unit' => 'week'], 'd'));
        self::assertNull(DeadlineUnit::buildInterval(['d_unit' => 'week'], 'd'));
        self::assertNull(DeadlineUnit::buildInterval(['d' => '3'], 'd'));
    }

    /**
     * @return array<string, array{mixed, array{int, string}|null}>
     */
    public static function storedIntervalProvider(): array
    {
        return [
            'default' => ['1 month', [1, 'month']],
            'legacy plural' => ['2 weeks', [2, 'week']],
            'surrounding spaces' => [' 10 day ', [10, 'day']],
            'legacy upper case' => ['2 WEEK', [2, 'week']],
            'legacy mixed case plural' => ['3 Months', [3, 'month']],
            'upper bound' => [Episciences_Review::DEADLINE_VALUE_MAX . ' day', [Episciences_Review::DEADLINE_VALUE_MAX, 'day']],
            'above upper bound' => [(Episciences_Review::DEADLINE_VALUE_MAX + 1) . ' day', null],
            'sql injected in the unit' => ['1 DAY) , (SELECT SLEEP(5)) -- ', null],
            'sql injected in the value' => ['1) OR 1=1 -- day', null],
            'unknown unit' => ['3 hour', null],
            'unknown upper-case unit' => ['3 HOUR', null],
            'zero' => ['0 day', null],
            'negative' => ['-1 day', null],
            'unit only' => [' week', null],
            'empty' => ['', null],
            'null' => [null, null],
            'array' => [['1 day'], null],
        ];
    }

    /**
     * @param array{int, string}|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('storedIntervalProvider')]
    public function testParseStoredInterval(mixed $stored, ?array $expected): void
    {
        self::assertSame($expected, DeadlineUnit::parseStoredInterval($stored));
    }

    /**
     * @return array<string, array{class-string, array<string, string>, bool}>
     */
    public static function crossFieldProvider(): array
    {
        $ok = [
            'rating_deadline' => '2', 'rating_deadline_unit' => 'month',
            'rating_deadline_min' => '1', 'rating_deadline_min_unit' => 'month',
            'rating_deadline_max' => '6', 'rating_deadline_max_unit' => 'month',
        ];

        return [
            'default within bounds' => [Episciences_Form_Validate_CheckDefaultRatingDeadline::class, $ok, true],
            'default below min' => [Episciences_Form_Validate_CheckDefaultRatingDeadline::class, array_merge($ok, ['rating_deadline' => '1', 'rating_deadline_unit' => 'week']), false],
            'default above max' => [Episciences_Form_Validate_CheckDefaultRatingDeadline::class, array_merge($ok, ['rating_deadline' => '7']), false],
            'min above max' => [Episciences_Form_Validate_CheckMinimumDeadlineDelay::class, array_merge($ok, ['rating_deadline_min' => '7']), false],
            'min within bounds' => [Episciences_Form_Validate_CheckMinimumDeadlineDelay::class, $ok, true],
            'max below min' => [Episciences_Form_Validate_CheckMaximumDeadlineDelay::class, array_merge($ok, ['rating_deadline_max' => '1', 'rating_deadline_max_unit' => 'week']), false],
            'max within bounds' => [Episciences_Form_Validate_CheckMaximumDeadlineDelay::class, $ok, true],
            // an unusable unit elsewhere is reported by its own element, never by a misleading comparison error
            'default, bogus min unit' => [Episciences_Form_Validate_CheckDefaultRatingDeadline::class, array_merge($ok, ['rating_deadline_min_unit' => 'bogus']), true],
            'min, bogus max unit' => [Episciences_Form_Validate_CheckMinimumDeadlineDelay::class, array_merge($ok, ['rating_deadline_max_unit' => 'bogus']), true],
            'max, missing min unit' => [Episciences_Form_Validate_CheckMaximumDeadlineDelay::class, array_diff_key($ok, ['rating_deadline_min_unit' => 1]), true],
        ];
    }

    /**
     * @dataProvider crossFieldProvider
     * @param class-string<Episciences_Form_Validate_CheckDefaultRatingDeadline|Episciences_Form_Validate_CheckMinimumDeadlineDelay|Episciences_Form_Validate_CheckMaximumDeadlineDelay> $class
     * @param array<string, string> $context
     */
    public function testCrossFieldValidatorsUseTheFormContext(string $class, array $context, bool $expected): void
    {
        $field = match ($class) {
            Episciences_Form_Validate_CheckDefaultRatingDeadline::class => 'rating_deadline',
            Episciences_Form_Validate_CheckMinimumDeadlineDelay::class => 'rating_deadline_min',
            default => 'rating_deadline_max',
        };

        $validator = new $class();

        self::assertSame($expected, $validator->isValid($context[$field], $context));
    }

    private function renderDeadlineElement(string $value): string
    {
        $element = new Zend_Form_Element_Text('rating_deadline');
        $element->setValue($value);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->element = $element;

        return $view->render('review/deadline_element.phtml');
    }

    public function testViewEscapesTheRedisplayedValue(): void
    {
        $html = $this->renderDeadlineElement('"><img src=x onerror=alert(1)> week');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&quot;&gt;&lt;img', $html);
    }

    public function testViewSelectsTheStoredUnit(): void
    {
        $html = $this->renderDeadlineElement('3 week');

        self::assertMatchesRegularExpression('/<option value="week" selected="selected"/', $html);
        self::assertSame(1, substr_count($html, 'selected="selected"'));
    }

    public function testViewToleratesAMissingUnit(): void
    {
        $html = $this->renderDeadlineElement('3');

        self::assertStringContainsString('value="3"', $html);
        self::assertStringNotContainsString('selected="selected"', $html);
    }
}
