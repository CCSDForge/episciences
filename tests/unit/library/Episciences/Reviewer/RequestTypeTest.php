<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\RequestType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Reviewer\RequestType
 */
final class RequestTypeTest extends TestCase
{
    private const RVID = 3;

    public function testDefaultsAreTheReviewReportThenTheQuickOpinion(): void
    {
        $types = RequestType::defaults(self::RVID);

        self::assertSame(
            [RequestType::CODE_FULL_REPORT, RequestType::CODE_QUICK_OPINION],
            array_map(static fn(RequestType $type): string => $type->getCode(), $types)
        );
        self::assertSame([1, 2], array_map(static fn(RequestType $type): int => $type->getPosition(), $types));

        foreach ($types as $type) {
            self::assertSame(self::RVID, $type->getRvid());
            self::assertNull($type->getId());
            self::assertTrue($type->isEnabled());
            self::assertTrue($type->isDefault());
            self::assertNotSame('', $type->getLabel('fr'));
            self::assertNotSame('', $type->getLabel('en'));
        }

        self::assertTrue($types[0]->isFullReport());
        self::assertNull($types[0]->getDefaultDeadline());
        // the instructions are shown on the page of the answer without an account
        self::assertSame('', $types[0]->getInstructions('fr'));
        self::assertNotSame('', $types[1]->getInstructions('fr'));
        self::assertNotSame('', $types[1]->getInstructions('en'));
        self::assertSame('2 week', $types[1]->getDefaultDeadline());
    }

    public function testLabelFallsBackToTheFallbackLanguageThenToTheFirstLabel(): void
    {
        $type = new RequestType(null, self::RVID, 'phase_one', ['fr' => 'Phase 1', 'en' => 'Phase one']);
        self::assertSame('Phase 1', $type->getLabel('fr'));
        self::assertSame('Phase one', $type->getLabel('de'));

        $frenchOnly = new RequestType(null, self::RVID, 'phase_one', ['fr' => 'Phase 1']);
        self::assertSame('Phase 1', $frenchOnly->getLabel('es'));
    }

    public function testLabelsAreTrimmedAndEmptyOrInvalidOnesDropped(): void
    {
        $type = new RequestType(null, self::RVID, 'phase_one', ['fr' => '  Phase 1 ', 'en' => '   ', 'english' => 'x', 3 => 'y']);

        self::assertSame(['fr' => 'Phase 1'], $type->getLabels());
    }

    public function testAtLeastOneLabelIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, 'phase_one', ['fr' => ' ']);
    }

    public function testTooLongLabelIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, 'phase_one', ['en' => str_repeat('a', RequestType::LABEL_MAX_LENGTH + 1)]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCodes(): array
    {
        return [
            'empty' => [''],
            'upper case' => ['Quick'],
            'starts with a digit' => ['1st_phase'],
            'space' => ['quick opinion'],
            'dash' => ['quick-opinion'],
            'too long' => [str_repeat('a', 31)],
            'path' => ['../x'],
            'reserved' => ['generic'],
        ];
    }

    #[DataProvider('invalidCodes')]
    public function testInvalidCodeIsRefused(string $code): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, $code, ['en' => 'Label']);
    }

    public function testInvalidDeadlineIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, 'phase_one', ['en' => 'Label'], false, '2 fortnight');
    }

    public function testReviewReportTypeCannotBeDisabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, RequestType::CODE_FULL_REPORT, ['en' => 'Review report'], false, null, 1, false);
    }

    public function testReviewReportTypeCannotBeAnsweredWithoutAnAccount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, RequestType::CODE_FULL_REPORT, ['en' => 'Review report'], true);
    }

    public function testOnlyTheReviewReportCountsByDefault(): void
    {
        self::assertTrue(RequestType::defaults(self::RVID)[0]->countsAsReport());
        self::assertFalse(RequestType::defaults(self::RVID)[1]->countsAsReport());
        // the review report always counts, whatever is given
        self::assertTrue((new RequestType(null, self::RVID, RequestType::CODE_FULL_REPORT, ['en' => 'Review report'], false, null, 1, true, [], false))->countsAsReport());
    }

    public function testATypeOfTheJournalCanCountAsReviewReportAndKeepsItInItsRow(): void
    {
        $phase = new RequestType(8, self::RVID, 'phase_two', ['en' => 'Phase two'], false, null, 3, true, [], true);

        self::assertTrue($phase->countsAsReport());
        self::assertSame(1, $phase->toRow()['counts_as_report']);
        self::assertTrue(RequestType::fromRow($phase->toRow() + ['id' => 8])->countsAsReport());
        self::assertTrue($phase->withId(9)->countsAsReport());
        self::assertFalse($phase->withSettings(['en' => 'Phase 2'], null, 3, true, null, null, false)->countsAsReport());
        self::assertTrue($phase->withSettings(['en' => 'Phase 2'], null, 3, true)->countsAsReport(), 'unchanged when not given');
    }

    public function testATypeAnsweredWithoutAnAccountCannotCountAsReviewReport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, 'phase_two', ['en' => 'Phase two'], true, null, 0, true, [], true);
    }

    public function testRowRoundTrip(): void
    {
        $type = new RequestType(7, self::RVID, 'phase_two', ['fr' => 'Phase 2 « détaillée »', 'en' => 'Phase two'], true, '1 month', 4, false, ['fr' => ' Consigne ', 'en' => '']);

        $row = $type->toRow() + ['id' => 7];
        $copy = RequestType::fromRow($row);

        self::assertSame('{"fr":"Phase 2 « détaillée »","en":"Phase two"}', $row['labels']);
        self::assertEquals($type, $copy);
    }

    public function testRowWithUnreadableLabelsFallsBackToTheCode(): void
    {
        $type = RequestType::fromRow(['id' => 1, 'rvid' => self::RVID, 'code' => 'phase_one', 'labels' => '{broken']);

        self::assertSame('phase_one', $type->getLabel('fr'));
    }

    public function testWithSettingsKeepsIdentity(): void
    {
        $type = new RequestType(5, self::RVID, 'phase_one', ['en' => 'Phase one']);
        $changed = $type->withSettings(['en' => 'Phase 1'], '2 week', 9, false);

        self::assertSame(5, $changed->getId());
        self::assertSame('phase_one', $changed->getCode());
        self::assertSame('Phase 1', $changed->getLabel('en'));
        self::assertSame('2 week', $changed->getDefaultDeadline());
        self::assertSame(9, $changed->getPosition());
        self::assertFalse($changed->isEnabled());
        self::assertFalse($changed->isDefault());
    }

    public function testInstructionsAreTrimmedFallBackAndAreLimited(): void
    {
        $type = new RequestType(null, self::RVID, 'phase_one', ['en' => 'Phase one'], true, null, 0, true, ['fr' => '  Consigne  ', 'en' => ' ']);

        self::assertSame(['fr' => 'Consigne'], $type->getAllInstructions());
        self::assertSame('Consigne', $type->getInstructions('de'));
        self::assertSame('{"fr":"Consigne"}', $type->toRow()['instructions']);
        self::assertNull((new RequestType(null, self::RVID, 'phase_one', ['en' => 'Phase one']))->toRow()['instructions']);

        $this->expectException(InvalidArgumentException::class);
        new RequestType(null, self::RVID, 'phase_one', ['en' => 'Phase one'], true, null, 0, true, ['en' => str_repeat('a', RequestType::INSTRUCTIONS_MAX_LENGTH + 1)]);
    }
}
