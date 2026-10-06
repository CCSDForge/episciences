<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Review;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Form_Element_Text;

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
        $element->setValidators($method->invoke(null));

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
        ];
    }

    /**
     * @dataProvider deadlineValueProvider
     */
    public function testDeadlineValue(string $value, bool $expected): void
    {
        self::assertSame($expected, $this->buildElement()->isValid($value));
    }

    public function testUnitsMustBeKnown(): void
    {
        $valid = [
            'rating_deadline_unit' => 'week',
            'rating_deadline_min_unit' => 'day',
            'rating_deadline_max_unit' => 'month',
            'invitation_deadline_unit' => 'month',
        ];

        self::assertTrue(Episciences_Review::hasValidDeadlineUnits($valid));
        self::assertFalse(Episciences_Review::hasValidDeadlineUnits(array_merge($valid, ['rating_deadline_unit' => 'weeks'])));
        self::assertFalse(Episciences_Review::hasValidDeadlineUnits(array_merge($valid, ['invitation_deadline_unit' => "day'); DROP TABLE x;--"])));
        self::assertFalse(Episciences_Review::hasValidDeadlineUnits(array_diff_key($valid, ['rating_deadline_max_unit' => 1])));
    }
}
