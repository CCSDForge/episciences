<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences_Paper;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;

/**
 * The paper replaced by a new version is described by the stored paper, never by the posted form.
 */
final class ReplacementValuesTest extends TestCase
{
    private function storedPaper(): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => 10,
            'paperid' => 20,
            'identifier' => 'stored-identifier',
            'version' => 1,
            'repoId' => 1,
            'status' => Episciences_Paper::STATUS_SUBMITTED,
            'vid' => 3,
            'sid' => 4,
        ]);
    }

    public function testPostedOldValuesAreReplacedByTheStoredOnes(): void
    {
        $posted = [
            'old_docid' => '999',
            'old_paper_status' => '0',
            'old_version' => '0',
            'old_paperid' => '888',
            'old_paper_vid' => '777',
            'old_paper_sid' => '666',
            'old_identifier' => 'forged',
            'old_repoid' => '2',
            'old_conceptIdentifier' => 'forged-concept',
            'can_replace' => '1',
        ];

        $values = Episciences_Submit::applyStoredPaperToReplacement($posted, $this->storedPaper());

        self::assertSame(10, $values['old_docid']);
        self::assertSame(Episciences_Paper::STATUS_SUBMITTED, $values['old_paper_status']);
        self::assertSame(1.0, $values['old_version']);
        self::assertSame(20, $values['old_paperid']);
        self::assertSame(3, $values['old_paper_vid']);
        self::assertSame(4, $values['old_paper_sid']);
        self::assertArrayNotHasKey('old_identifier', $values);
        self::assertArrayNotHasKey('old_repoid', $values);
        self::assertArrayNotHasKey('old_conceptIdentifier', $values);
        self::assertSame('1', $values['can_replace']);
    }

    public function testMissingPostedValuesAreFilledFromTheStoredPaper(): void
    {
        $values = Episciences_Submit::applyStoredPaperToReplacement([], $this->storedPaper());

        self::assertSame(10, $values['old_docid']);
        self::assertSame(20, $values['old_paperid']);
    }
}
