<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Ccsd_Form;
use Episciences_Review;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Reviewing settings of the journal: minimum number of reviews, number of rounds it applies to and
 * minimum of the later rounds (git #239).
 */
final class ReviewRequiredReviewersSettingsTest extends TestCase
{
    private function reviewingForm(): Ccsd_Form
    {
        $method = new ReflectionMethod(Episciences_Review::class, 'addRatingSettingsForm');

        /** @var Ccsd_Form */
        return $method->invoke(new Episciences_Review(), new Ccsd_Form());
    }

    public function testTheThreeSettingsAreInTheForm(): void
    {
        $form = $this->reviewingForm();

        self::assertNotNull($form->getElement(Episciences_Review::SETTING_REQUIRED_REVIEWERS));
        self::assertNotNull($form->getElement(Episciences_Review::SETTING_REQUIRED_REVIEWERS_ROUNDS));
        self::assertNotNull($form->getElement(Episciences_Review::SETTING_REQUIRED_REVIEWERS_LATER_ROUNDS));
    }

    public function testTheSettingsAreTakenFromTheSubmittedForm(): void
    {
        $review = new Episciences_Review();
        // as with the values of the submitted settings form
        $review->setOptions([
            Episciences_Review::SETTING_REQUIRED_REVIEWERS_ROUNDS => '2',
            Episciences_Review::SETTING_REQUIRED_REVIEWERS_LATER_ROUNDS => '0',
        ]);

        self::assertSame('2', $review->getSetting(Episciences_Review::SETTING_REQUIRED_REVIEWERS_ROUNDS));
        self::assertSame('0', $review->getSetting(Episciences_Review::SETTING_REQUIRED_REVIEWERS_LATER_ROUNDS));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function valueProvider(): array
    {
        $rounds = Episciences_Review::SETTING_REQUIRED_REVIEWERS_ROUNDS;
        $later = Episciences_Review::SETTING_REQUIRED_REVIEWERS_LATER_ROUNDS;
        $minimum = Episciences_Review::SETTING_REQUIRED_REVIEWERS;

        return [
            'minimum 0' => [$minimum, '0', true],
            'minimum 2' => [$minimum, '2', true],
            'minimum not a number' => [$minimum, 'two', false],
            'negative minimum' => [$minimum, '-1', false],
            'empty number of rounds (every round)' => [$rounds, '', true],
            'one round' => [$rounds, '1', true],
            'zero rounds' => [$rounds, '0', false],
            'decimal number of rounds' => [$rounds, '1.5', false],
            'later minimum 0' => [$later, '0', true],
            'later minimum 1' => [$later, '1', true],
            'later minimum not a number' => [$later, 'x', false],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testValuesAreChecked(string $setting, string $value, bool $valid): void
    {
        $element = $this->reviewingForm()->getElement($setting);
        self::assertNotNull($element);

        self::assertSame($valid, $element->isValid($value));
    }
}
