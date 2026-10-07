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
    private const OWNER_UID = 42;

    private function storedPaper(int $status = Episciences_Paper::STATUS_SUBMITTED, int $uid = self::OWNER_UID): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => 10,
            'paperid' => 20,
            'uid' => $uid,
            'identifier' => 'stored-identifier',
            'version' => 1,
            'repoId' => 1,
            'status' => $status,
            'vid' => 3,
            'sid' => 4,
            'submission_date' => '2020-01-02 03:04:05',
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
            'old_submissiondate' => '1999-01-01 00:00:00',
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
        self::assertSame('2020-01-02 03:04:05', $values['old_submissiondate']);
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

    public function testResubmittedRefusedPaperKeepsNoSubmissionDate(): void
    {
        $values = Episciences_Submit::applyStoredPaperToReplacement(
            ['old_submissiondate' => '1999-01-01 00:00:00'],
            $this->storedPaper(Episciences_Paper::STATUS_REFUSED)
        );

        self::assertArrayNotHasKey('old_submissiondate', $values);
        self::assertSame(Episciences_Paper::STATUS_REFUSED, $values['old_paper_status']);
        self::assertSame(20, $values['old_paperid']);
    }

    public function testMissingPaperIsReported(): void
    {
        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_FOUND,
            Episciences_Submit::getReplacementError(null, self::OWNER_UID, false)
        );
    }

    public function testPaperOfSomeoneElseIsReported(): void
    {
        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_OWNER,
            Episciences_Submit::getReplacementError($this->storedPaper(), self::OWNER_UID + 1, false)
        );
    }

    public function testPaperInProgressCannotBeReplaced(): void
    {
        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE,
            Episciences_Submit::getReplacementError(
                $this->storedPaper(Episciences_Paper::STATUS_BEING_REVIEWED),
                self::OWNER_UID,
                true
            )
        );
    }

    public function testRefusedPaperDependsOnTheJournalSetting(): void
    {
        $refused = $this->storedPaper(Episciences_Paper::STATUS_REFUSED);

        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE,
            Episciences_Submit::getReplacementError($refused, self::OWNER_UID, false)
        );
        self::assertNull(Episciences_Submit::getReplacementError($refused, self::OWNER_UID, true));
    }

    public function testOwnerCanReplaceASubmittedPaper(): void
    {
        self::assertNull(Episciences_Submit::getReplacementError($this->storedPaper(), self::OWNER_UID, false));
    }

    public function testRefusedPaperWithConceptIdentifierIsComparedOnItsConcept(): void
    {
        $previousTranslator = \Zend_Registry::isRegistered('Zend_Translate') ? \Zend_Registry::get('Zend_Translate') : null;
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['k' => 'v'], 'locale' => 'en']));

        try {
            $refused = new Episciences_Paper([
                'docid' => 10,
                'identifier' => 'zenodo-version-1',
                'version' => 1,
                'repoId' => (int)\Episciences_Repositories::ZENODO_REPO_ID,
                'status' => Episciences_Paper::STATUS_REFUSED,
                'concept_identifier' => 'zenodo-concept',
            ]);

            // Another version of the same concept: the identifier of the version itself differs
            $result = $refused->updatePaper([
                'search_doc' => [
                    'docId' => 'zenodo-version-2',
                    'version' => 1,
                    'repoId' => (int)\Episciences_Repositories::ZENODO_REPO_ID,
                ],
                'concept_identifier' => 'zenodo-concept',
            ]);

            self::assertStringNotContainsString("l'identifiant de l'article a changé", $result['message']);
        } finally {
            if ($previousTranslator !== null) {
                \Zend_Registry::set('Zend_Translate', $previousTranslator);
            }
        }
    }

    public function testPaperReadyToPublishIsReplacedInPlace(): void
    {
        $values = Episciences_Submit::applyStoredPaperToReplacement(
            ['can_replace' => 1],
            $this->storedPaper(Episciences_Paper::STATUS_CE_READY_TO_PUBLISH)
        );

        self::assertSame(Episciences_Paper::STATUS_CE_READY_TO_PUBLISH, $values['old_paper_status']);
        self::assertNull(Episciences_Submit::getReplacementError($this->storedPaper(Episciences_Paper::STATUS_CE_READY_TO_PUBLISH), self::OWNER_UID, false));
    }
}
