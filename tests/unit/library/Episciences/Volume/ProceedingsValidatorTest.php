<?php

declare(strict_types=1);

namespace unit\library\Episciences\Volume;

use Episciences\Volume\ProceedingsValidator;
use Episciences_Volume;
use PHPUnit\Framework\TestCase;
use unit\modules\support\RecordingDbAdapter;

/**
 * @covers \Episciences\Volume\ProceedingsValidator
 */
final class ProceedingsValidatorTest extends TestCase
{
    private const RVID = 42;

    private RecordingDbAdapter $db;

    protected function setUp(): void
    {
        $this->db = new RecordingDbAdapter();
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function validate(array $values, ?int $vid = 5, bool $doiLocked = false, string $prefix = '10.46298'): array
    {
        $values += [
            'is_proceeding' => '1',
            'conference_name' => 'MFPS',
            'conference_start' => '2026-10-27',
            'conference_end' => '2026-10-29',
            'conference_proceedings_doi' => '51',
        ];

        return (new ProceedingsValidator($this->db))->validate($values, $prefix, 'mbj', self::RVID, $vid, $doiLocked);
    }

    public function testValidProceedings(): void
    {
        self::assertSame([], $this->validate([]));

        $uniqueness = $this->db->selects()[0];
        self::assertStringContainsString('(V.RVID = 42)', $uniqueness);
        self::assertStringContainsString("(S.VALUE = '10.46298/mbj.proceedings.51')", $uniqueness);
        self::assertStringContainsString('(S.VID != 5)', $uniqueness);
    }

    public function testNotAProceedingsIsNotChecked(): void
    {
        self::assertSame([], $this->validate(['is_proceeding' => '0', 'conference_name' => '', 'conference_proceedings_doi' => 'é']));
        self::assertSame([], $this->db->queries());
    }

    public function testALockedDoiKeepsTheVolumeAProceedings(): void
    {
        self::assertSame(
            [Episciences_Volume::VOLUME_IS_PROCEEDING => ProceedingsValidator::MSG_DOI_LOCKED_PROCEEDINGS],
            $this->validate(['is_proceeding' => '0'], 5, true)
        );
    }

    public function testNameAndDatesAreRequired(): void
    {
        self::assertSame([
            'conference_name' => ProceedingsValidator::MSG_REQUIRED,
            'conference_start' => ProceedingsValidator::MSG_REQUIRED,
            'conference_end' => ProceedingsValidator::MSG_REQUIRED,
        ], $this->validate(['conference_name' => ' ', 'conference_start' => '', 'conference_end' => '']));
    }

    public function testEndDateBeforeStartDateIsRefused(): void
    {
        self::assertSame(
            ['conference_end' => ProceedingsValidator::MSG_END_BEFORE_START],
            $this->validate(['conference_end' => '2026-10-26'])
        );
        self::assertSame([], $this->validate(['conference_end' => '2026-10-27']), 'Same day');
    }

    public function testInvalidSuffixIsRefusedWithoutQuery(): void
    {
        self::assertSame(['conference_proceedings_doi' => ProceedingsValidator::MSG_DOI_SUFFIX_FORMAT], $this->validate(['conference_proceedings_doi' => 'a/b']));
        self::assertSame([], $this->db->queries());
    }

    public function testDoiUsedByAnotherVolumeIsRefused(): void
    {
        $this->db->respondTo('/FROM `VOLUME_SETTING` AS `S`/', [['COUNT(*)' => '1']]);

        self::assertSame(['conference_proceedings_doi' => ProceedingsValidator::MSG_DOI_ALREADY_USED], $this->validate([], null));
        self::assertStringNotContainsString('S.VID !=', $this->db->selects()[0], 'A new volume is compared with every volume');
    }

    public function testSuffixIsIgnoredWhenLockedOrWithoutPrefix(): void
    {
        self::assertSame([], $this->validate(['conference_proceedings_doi' => 'é'], 5, true));
        self::assertSame([], $this->validate(['conference_proceedings_doi' => 'é'], 5, false, ''));
    }

    public function testDoiHelpers(): void
    {
        self::assertSame('10.46298/mbj.proceedings.51', ProceedingsValidator::buildDoi('10.46298', 'mbj', '51'));
        self::assertSame('mfps.40', ProceedingsValidator::extractDoiSuffix('10.46298/mbj.proceedings.mfps.40'));
        self::assertSame('', ProceedingsValidator::extractDoiSuffix('10.46298/mbj.12'));
        self::assertTrue(ProceedingsValidator::isDoiSuffixValid('a.B-c_9'));
        self::assertFalse(ProceedingsValidator::isDoiSuffixValid("51\n"));
    }
}
