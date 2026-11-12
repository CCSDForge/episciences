<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

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
            'code' => 'phase_two',
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
        self::assertSame('phase_two', $type->getCode());
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
            'code' => 'Phase 2',
            'label' => ['fr' => ' ', 'en' => str_repeat('a', RequestType::LABEL_MAX_LENGTH + 1)],
            'deadline' => '2',
            'deadline_unit' => 'year',
            'position' => '-1',
        ]), self::RVID, self::LANGUAGES);

        self::assertFalse($input->isValid());
        self::assertNull($input->getType());
        self::assertEqualsCanonicalizing(['code', 'label_fr', 'label_en', 'deadline', 'position'], array_keys($input->getErrors()));
        self::assertStringContainsString((string)RequestType::LABEL_MAX_LENGTH, $input->getErrors()['label_en']);
    }

    public function testAReservedCodeIsRefused(): void
    {
        $input = new RequestTypeInput($this->post(['code' => 'generic']), self::RVID, self::LANGUAGES);

        self::assertArrayHasKey('code', $input->getErrors());
    }

    public function testTheCodeAndTheJournalOfAnExistingTypeNeverChange(): void
    {
        $existing = new RequestType(8, self::RVID, 'phase_one', ['fr' => 'Phase 1', 'en' => 'Phase one']);

        $type = (new RequestTypeInput($this->post(['code' => 'hijacked']), 99, self::LANGUAGES, $existing))->getType();

        self::assertNotNull($type);
        self::assertSame(8, $type->getId());
        self::assertSame('phase_one', $type->getCode());
        self::assertSame(self::RVID, $type->getRvid());
        self::assertSame('Phase 2', $type->getLabel('fr'));
    }

    public function testTheReviewReportStaysEnabled(): void
    {
        $fullReport = new RequestType(1, self::RVID, RequestType::CODE_FULL_REPORT, ['fr' => 'Rapport', 'en' => 'Report']);
        $post = $this->post();
        unset($post['enabled']);

        self::assertTrue((new RequestTypeInput($post, self::RVID, self::LANGUAGES, $fullReport))->getType()?->isEnabled());
    }

    public function testNonStringValuesAreIgnored(): void
    {
        $input = new RequestTypeInput($this->post(['code' => ['x'], 'label' => 'not an array']), self::RVID, self::LANGUAGES);

        self::assertEqualsCanonicalizing(['label_fr', 'label_en', 'code'], array_keys($input->getErrors()));
    }
}
