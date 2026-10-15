<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences_Paper;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;

/**
 * Values of a replacement: checked against the stored paper and the document resolved on the server,
 * never taken from the posted form.
 */
final class ReplacementValuesTest extends TestCase
{
    private function storedPaper(int $status = Episciences_Paper::STATUS_SUBMITTED): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => 10,
            'paperid' => 20,
            'uid' => 42,
            'identifier' => 'stored-identifier',
            'version' => 1,
            'repoId' => 1,
            'status' => $status,
            'vid' => 3,
            'sid' => 4,
            'submission_date' => '2020-01-02 03:04:05',
        ]);
    }

    public function testResubmittedRefusedPaperGetsANewSubmissionDate(): void
    {
        $values = $this->buildValuesToPopulatePaper([
            'can_replace' => '1',
            'old_docid' => 10,
            'old_paper_status' => Episciences_Paper::STATUS_REFUSED,
            'old_paperid' => 20,
            'old_submissiondate' => '2020-01-02 03:04:05',
        ]);

        // A new row with the same paper id, but a new submission: the stored date is not kept
        self::assertNotSame('2020-01-02 03:04:05', $values['SUBMISSION_DATE']);
        self::assertArrayNotHasKey('DOCID', $values);
        self::assertSame(Episciences_Paper::STATUS_SUBMITTED, $values['STATUS']);
        self::assertSame(20, $values['PAPERID']);
    }

    public function testPostedSubmissionDateIsIgnoredOnANewSubmission(): void
    {
        $values = $this->buildValuesToPopulatePaper([
            'old_submissiondate' => '1999-01-01 00:00:00',
        ]);

        self::assertNotSame('1999-01-01 00:00:00', $values['SUBMISSION_DATE']);
    }

    public function testStoredSubmissionDateIsKeptOnAReplacementInPlace(): void
    {
        $values = $this->buildValuesToPopulatePaper([
            'can_replace' => '1',
            'old_docid' => 10,
            'old_paper_status' => Episciences_Paper::STATUS_SUBMITTED,
            'old_submissiondate' => '2020-01-02 03:04:05',
        ]);

        self::assertSame('2020-01-02 03:04:05', $values['SUBMISSION_DATE']);
    }

    public function testUnreviewedPaperIsReplacedInPlace(): void
    {
        foreach ([Episciences_Paper::STATUS_SUBMITTED, Episciences_Paper::STATUS_OK_FOR_REVIEWING] as $status) {
            $values = $this->buildValuesToPopulatePaper([
                'can_replace' => '1',
                'old_docid' => 10,
                'old_paper_status' => $status,
                'old_paperid' => 20,
            ]);

            self::assertSame(10, $values['DOCID'], 'status ' . $status);
            self::assertSame($status, $values['STATUS'], 'status ' . $status);
            self::assertSame(20, $values['PAPERID'], 'status ' . $status);
        }
    }

    public function testPaperFurtherInThePublicationProcessCannotBeReplacedFromTheSubmissionForm(): void
    {
        $statuses = [
            Episciences_Paper::STATUS_CE_READY_TO_PUBLISH,
            Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
        ];

        foreach ($statuses as $status) {
            // Still replaceable by a new version (new version page, COAR Notify), but not in place
            self::assertTrue($this->storedPaper($status)->canBeReplaced(), 'status ' . $status);
            self::assertFalse($this->storedPaper($status)->canBeReplacedInPlace(), 'status ' . $status);
            self::assertSame(
                Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE,
                Episciences_Submit::getReplacementError($this->storedPaper($status), true, true),
                'status ' . $status
            );
        }
    }

    public function testPostedDocumentValuesAreReplacedByTheResolvedOnes(): void
    {
        $values = Episciences_Submit::applySubmittedDocument(
            [
                'xml' => '<record>forged</record>',
                'concept_identifier' => 'forged-concept',
                'update' => '19990101T000000',
                'h_version' => 9.0,
                'search_doc' => ['docId' => 'doc', 'version' => 9.0, 'h_version' => 9.0, 'repoId' => 4],
            ],
            [
                'record' => '<record>searched</record>',
                'conceptIdentifier' => '10.5281/zenodo.1234',
                'hookVersion' => 2.0,
                'update' => '20240102T030405',
            ]
        );

        self::assertSame('<record>searched</record>', $values['xml']);
        self::assertSame('10.5281/zenodo.1234', $values['concept_identifier']);
        self::assertSame('20240102T030405', $values['update']);
        self::assertSame(2.0, $values['h_version']);
        self::assertSame(
            ['version' => 2.0, 'h_version' => 2.0, 'docId' => 'doc', 'repoId' => 4],
            $values['search_doc']
        );
    }

    public function testPostedDocumentValuesAreDroppedWhenTheDocumentHasNone(): void
    {
        $posted = [
            'concept_identifier' => 'forged-concept',
            'update' => '19990101T000000',
            'h_version' => 9.0,
            'search_doc' => ['version' => 9.0],
        ];

        $values = Episciences_Submit::applySubmittedDocument(
            $posted,
            ['record' => '<record>searched</record>', 'conceptIdentifier' => null, 'hookVersion' => null, 'update' => null]
        );

        self::assertArrayNotHasKey('concept_identifier', $values);
        self::assertArrayNotHasKey('update', $values);
        // Without a version given by the repository, the searched version (the key of the document) is kept
        self::assertSame(9.0, $values['h_version']);
        self::assertSame(['version' => 9.0], $values['search_doc']);
    }

    public function testForgedUpdateDatetimeIsNotAppendedToTheIdentifier(): void
    {
        $values = Episciences_Submit::applySubmittedDocument(
            ['update' => '19990101T000000', 'search_doc' => ['docId' => '2024/123', 'repoId' => 1, 'version' => 1]],
            ['record' => '<record/>', 'conceptIdentifier' => null, 'hookVersion' => null, 'update' => null]
        );

        self::assertSame('2024/123', $this->buildValuesToPopulatePaper($values)['IDENTIFIER']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildValuesToPopulatePaper(array $data): array
    {
        $data += [
            'search_doc' => ['docId' => 'identifier', 'repoId' => 1, 'version' => 1],
            'xml' => '',
        ];

        $method = new \ReflectionMethod(Episciences_Submit::class, 'buildValuesToPopulatePaper');
        $method->setAccessible(true);

        return $method->invoke(new Episciences_Submit(), $data);
    }

    public function testMissingPaperIsReported(): void
    {
        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_FOUND,
            Episciences_Submit::getReplacementError(null, true, false)
        );
    }

    public function testPaperOfSomeoneElseIsReported(): void
    {
        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_OWNER,
            Episciences_Submit::getReplacementError($this->storedPaper(), false, false)
        );
    }

    public function testPaperInProgressCannotBeReplaced(): void
    {
        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE,
            Episciences_Submit::getReplacementError(
                $this->storedPaper(Episciences_Paper::STATUS_BEING_REVIEWED),
                true,
                true
            )
        );
    }

    public function testRefusedPaperDependsOnTheJournalSetting(): void
    {
        $refused = $this->storedPaper(Episciences_Paper::STATUS_REFUSED);

        self::assertSame(
            Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE,
            Episciences_Submit::getReplacementError($refused, true, false)
        );
        self::assertNull(Episciences_Submit::getReplacementError($refused, true, true));
    }

    public function testOwnerCanReplaceASubmittedPaper(): void
    {
        self::assertNull(Episciences_Submit::getReplacementError($this->storedPaper(), true, false));
    }

    public function testRefusedPaperWithConceptIdentifierIsComparedOnItsConcept(): void
    {
        $registry = \Zend_Registry::getInstance();
        $hadTranslator = $registry->offsetExists('Zend_Translate');
        $previousTranslator = $hadTranslator ? $registry->offsetGet('Zend_Translate') : null;
        $hadSources = $registry->offsetExists('metadataSources');
        $previousSources = $hadSources ? $registry->offsetGet('metadataSources') : null;

        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['k' => 'v'], 'locale' => 'en']));
        // Zenodo must be known so that its hook (which exposes concept identifiers) is resolved without the database
        \Zend_Registry::set('metadataSources', [
            (int)\Episciences_Repositories::ZENODO_REPO_ID => [
                \Episciences_Repositories::REPO_LABEL => 'Zenodo',
                \Episciences_Repositories::REPO_TYPE => \Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                'identifier' => null,
                'base_url' => 'https://zenodo.org/',
                'api_url' => 'https://zenodo.org/api/',
            ],
        ]);
        $this->resetRepositoriesCache();

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
            if ($hadTranslator) {
                \Zend_Registry::set('Zend_Translate', $previousTranslator);
            } else {
                $registry->offsetUnset('Zend_Translate');
            }
            if ($hadSources) {
                \Zend_Registry::set('metadataSources', $previousSources);
            } else {
                $registry->offsetUnset('metadataSources');
            }
            $this->resetRepositoriesCache();
        }
    }

    private function resetRepositoriesCache(): void
    {
        $prop = new \ReflectionProperty(\Episciences_Repositories::class, '_repositories');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }
}
