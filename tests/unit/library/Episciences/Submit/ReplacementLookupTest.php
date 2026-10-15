<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

// Episciences_Paper::manageNewVersionErrors() refers to the COAR Notify script for its context key
require_once __DIR__ . '/../../../../../scripts/InboxNotifications.php';

use Episciences_Paper;
use Episciences_PapersManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Replacement offered by the search of a document already in the journal, and lookup of the paper to replace.
 * Rows are inserted in a transaction that is rolled back after each test.
 */
final class ReplacementLookupTest extends TestCase
{
    private const OWNER_UID = 4242;

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    /**
     * @param array<string, mixed> $columns columns overriding the defaults
     */
    private function insertPaper(int $rvid, int $uid, int $status, array $columns = []): int
    {
        $this->db->insert(T_PAPERS, $columns + [
            'PAPERID' => 1,
            'RVID' => $rvid,
            'UID' => $uid,
            'STATUS' => $status,
            'IDENTIFIER' => 'replacement-lookup-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * @return array<string, array{int, array<string, mixed>, bool}>
     */
    public static function replacementOfferProvider(): array
    {
        $submissionForm = ['version' => 2, 'isNewVersionOf' => false];

        return [
            'submitted paper, submission form' => [Episciences_Paper::STATUS_SUBMITTED, $submissionForm, true],
            'paper ok for reviewing, submission form' => [Episciences_Paper::STATUS_OK_FOR_REVIEWING, $submissionForm, true],
            'paper ready to publish, submission form' => [Episciences_Paper::STATUS_CE_READY_TO_PUBLISH, $submissionForm, false],
            'paper approved by the author, submission form' => [Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION, $submissionForm, false],
            'paper ready to publish, new version page' => [Episciences_Paper::STATUS_CE_READY_TO_PUBLISH, ['version' => 2, 'isNewVersionOf' => true], true],
            'paper ready to publish, COAR Notify' => [Episciences_Paper::STATUS_CE_READY_TO_PUBLISH, $submissionForm + ['isEpiNotify' => true], true],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('replacementOfferProvider')]
    public function testReplacementIsOfferedOnlyWhereTheNewVersionCanBeRecorded(int $status, array $options, bool $expected): void
    {
        $docId = $this->insertPaper(RVID, self::OWNER_UID, $status);
        $paper = Episciences_PapersManager::partialGet($docId, RVID);
        self::assertNotNull($paper);

        $result = $paper->manageNewVersionErrors($options + ['rvId' => RVID]);

        self::assertIsArray($result);
        self::assertSame($expected, $result['canBeReplaced']);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function notReplacedInPlaceProvider(): array
    {
        return [
            'paper ready to publish' => [Episciences_Paper::STATUS_CE_READY_TO_PUBLISH],
            'paper approved by the author' => [Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notReplacedInPlaceProvider')]
    public function testSubmissionFormReportsThePublicationInProgressForAPaperNotReplacedInPlace(int $status): void
    {
        $docId = $this->insertPaper(RVID, self::OWNER_UID, $status);
        $paper = Episciences_PapersManager::partialGet($docId, RVID);
        self::assertNotNull($paper);
        $translator = \Zend_Registry::get('Zend_Translate');

        $newer = $paper->manageNewVersionErrors(['version' => 2, 'isNewVersionOf' => false, 'rvId' => RVID]);
        $same = $paper->manageNewVersionErrors(['version' => 1, 'isNewVersionOf' => false, 'rvId' => RVID]);

        // No new version has been requested by the journal: the author is not pointed to a new version page
        self::assertIsArray($newer);
        self::assertStringContainsString(
            $translator->translate(\Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE),
            $newer['message']
        );
        self::assertStringNotContainsString(
            $translator->translate('pour répondre à la demande de modification.'),
            $newer['message']
        );
        // A version that is not newer is still reported as already existing
        self::assertIsArray($same);
        self::assertStringContainsString($translator->translate('du document existe déjà dans la revue.'), $same['message']);
    }

    public function testNewVersionOfARefusedPaperIsFoundOnTheSharedConceptIdentifier(): void
    {
        $refusedDocId = $this->insertPaper(RVID, self::OWNER_UID, Episciences_Paper::STATUS_REFUSED, [
            'IDENTIFIER' => 'zenodo-version-1-REFUSED',
            'REPOID' => (int)\Episciences_Repositories::ZENODO_REPO_ID,
            'CONCEPT_IDENTIFIER' => 'replacement-lookup-concept',
        ]);
        // Saved at the same second as the refused version: only the docid tells them apart
        $newDocId = $this->insertPaper(RVID, self::OWNER_UID, Episciences_Paper::STATUS_SUBMITTED, [
            'IDENTIFIER' => 'zenodo-version-2',
            'REPOID' => (int)\Episciences_Repositories::ZENODO_REPO_ID,
            'CONCEPT_IDENTIFIER' => 'replacement-lookup-concept',
        ]);

        self::assertGreaterThan($refusedDocId, $newDocId);

        $lookup = new Episciences_Paper([
            'rvid' => RVID,
            'identifier' => 'zenodo-version-3',
            'version' => 1,
            'repoId' => (int)\Episciences_Repositories::ZENODO_REPO_ID,
        ]);
        $concept = new \ReflectionProperty(Episciences_Paper::class, '_concept_identifier');
        $concept->setAccessible(true);
        $concept->setValue($lookup, 'replacement-lookup-concept');

        // All the versions of a paper share its concept identifier: the latest one is the paper
        self::assertSame($newDocId, $lookup->findExistingDocId());
    }
}
