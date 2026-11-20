<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\DefaultRequestType;
use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeInput;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Reviewer\RequestTypeInput
 */
final class RequestTypeInputTest extends TestCase
{
    private const RVID = 3;
    private const LANGUAGES = ['fr', 'en'];

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function post(array $changes = []): array
    {
        return $changes + [
            'label' => ['fr' => 'Phase 2', 'en' => 'Phase two'],
            'deadline' => '3',
            'deadline_unit' => 'week',
            'position' => '4',
            'enabled' => '1',
        ];
    }

    public function testANewTypeIsBuiltFromThePostedValues(): void
    {
        $input = new RequestTypeInput($this->post(), self::RVID, self::LANGUAGES);

        self::assertTrue($input->isValid());
        $type = $input->getType();
        self::assertNotNull($type);
        self::assertNull($type->getId());
        self::assertSame(self::RVID, $type->getRvid());
        self::assertNull($type->getDefaultType(), 'a type created by the journal');
        self::assertSame(['fr' => 'Phase 2', 'en' => 'Phase two'], $type->getLabels());
        self::assertSame('3 week', $type->getDefaultDeadline());
        self::assertSame(4, $type->getPosition());
        self::assertTrue($type->isEnabled());
    }

    public function testEmptyDeadlineMeansTheJournalSettingAndAnUncheckedBoxDisablesTheType(): void
    {
        $post = $this->post(['deadline' => '']);
        unset($post['enabled']);

        $type = (new RequestTypeInput($post, self::RVID, self::LANGUAGES))->getType();

        self::assertNotNull($type);
        self::assertNull($type->getDefaultDeadline());
        self::assertFalse($type->isEnabled());
    }

    public function testEachFieldErrorIsReported(): void
    {
        $input = new RequestTypeInput($this->post([
            'label' => ['fr' => ' ', 'en' => str_repeat('a', RequestType::LABEL_MAX_LENGTH + 1)],
            'deadline' => '2',
            'deadline_unit' => 'year',
            'position' => '-1',
        ]), self::RVID, self::LANGUAGES);

        self::assertFalse($input->isValid());
        self::assertNull($input->getType());
        self::assertEqualsCanonicalizing(['label_fr', 'label_en', 'deadline', 'position'], array_keys($input->getErrors()));
        self::assertStringContainsString((string)RequestType::LABEL_MAX_LENGTH, $input->getErrors()['label_en']);
    }

    public function testAPostedCodeIsIgnored(): void
    {
        $type = (new RequestTypeInput($this->post(['code' => 'full_report']), self::RVID, self::LANGUAGES))->getType();

        self::assertNotNull($type);
        self::assertNull($type->getDefaultType(), 'a journal cannot create a default type');
    }

    public function testTheDefaultTypeAndTheJournalOfAnExistingTypeNeverChange(): void
    {
        $existing = new RequestType(8, self::RVID, DefaultRequestType::QuickOpinion, ['fr' => 'Avis', 'en' => 'Opinion']);

        $type = (new RequestTypeInput($this->post(), 99, self::LANGUAGES, $existing))->getType();

        self::assertNotNull($type);
        self::assertSame(8, $type->getId());
        self::assertSame(DefaultRequestType::QuickOpinion, $type->getDefaultType());
        self::assertSame(self::RVID, $type->getRvid());
        self::assertSame('Phase 2', $type->getLabel('fr'));
    }

    public function testTheReviewReportStaysEnabled(): void
    {
        $fullReport = new RequestType(1, self::RVID, DefaultRequestType::FullReport, ['fr' => 'Rapport', 'en' => 'Report']);
        $post = $this->post();
        unset($post['enabled']);

        self::assertTrue((new RequestTypeInput($post, self::RVID, self::LANGUAGES, $fullReport))->getType()?->isEnabled());
    }

    public function testNonStringValuesAreIgnored(): void
    {
        $input = new RequestTypeInput($this->post(['label' => 'not an array']), self::RVID, self::LANGUAGES);

        self::assertEqualsCanonicalizing(['label_fr', 'label_en'], array_keys($input->getErrors()));
    }

    public function testATypeCanBeAnsweredWithoutAnAccountButNeverTheReviewReport(): void
    {
        self::assertTrue((new RequestTypeInput($this->post(['light_answer' => '1']), self::RVID, self::LANGUAGES))->getType()?->isLightAnswer());
        self::assertFalse((new RequestTypeInput($this->post(), self::RVID, self::LANGUAGES))->getType()?->isLightAnswer());

        $fullReport = new RequestType(1, self::RVID, DefaultRequestType::FullReport, ['fr' => 'Rapport', 'en' => 'Report']);
        self::assertFalse((new RequestTypeInput($this->post(['light_answer' => '1']), self::RVID, self::LANGUAGES, $fullReport))->getType()?->isLightAnswer());
    }

    public function testTheCountAsReviewReportBoxIsIgnoredWithAnAnswerWithoutAnAccount(): void
    {
        self::assertTrue((new RequestTypeInput($this->post(['counts_as_report' => '1']), self::RVID, self::LANGUAGES))->getType()?->countsAsReport());
        self::assertFalse((new RequestTypeInput($this->post(), self::RVID, self::LANGUAGES))->getType()?->countsAsReport());

        $type = (new RequestTypeInput($this->post(['counts_as_report' => '1', 'light_answer' => '1']), self::RVID, self::LANGUAGES))->getType();
        self::assertNotNull($type);
        self::assertTrue($type->isLightAnswer());
        self::assertFalse($type->countsAsReport());

        $fullReport = new RequestType(1, self::RVID, DefaultRequestType::FullReport, ['en' => 'Review report']);
        self::assertTrue((new RequestTypeInput($this->post(), self::RVID, self::LANGUAGES, $fullReport))->getType()?->countsAsReport());
    }

    public function testThePassedOnToTheAuthorBoxSetsTheTypeButNeverTheReviewReport(): void
    {
        self::assertTrue((new RequestTypeInput($this->post(['visible_to_author' => '1']), self::RVID, self::LANGUAGES))->getType()?->isVisibleToAuthor());
        self::assertFalse((new RequestTypeInput($this->post(), self::RVID, self::LANGUAGES))->getType()?->isVisibleToAuthor());

        // the review reports are always passed on, the box being unchecked or absent
        $fullReport = new RequestType(1, self::RVID, DefaultRequestType::FullReport, ['en' => 'Review report']);
        self::assertTrue((new RequestTypeInput($this->post(), self::RVID, self::LANGUAGES, $fullReport))->getType()?->isVisibleToAuthor());
    }

    public function testInstructionsAreOptionalAndLimited(): void
    {
        $type = (new RequestTypeInput($this->post(['instructions' => ['fr' => 'Indiquez votre avis.', 'en' => '']]), self::RVID, self::LANGUAGES))->getType();
        self::assertSame(['fr' => 'Indiquez votre avis.'], $type?->getAllInstructions());

        $input = new RequestTypeInput($this->post(['instructions' => ['en' => str_repeat('a', RequestType::INSTRUCTIONS_MAX_LENGTH + 1)]]), self::RVID, self::LANGUAGES);
        self::assertArrayHasKey('instructions_en', $input->getErrors());
    }
}
