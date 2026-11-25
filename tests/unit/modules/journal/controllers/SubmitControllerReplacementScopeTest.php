<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper;
use Episciences_PapersManager;
use Episciences_Review;
use Episciences_Submit;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * The paper replaced by a new version is read from the database, never described by the client.
 * The database adapter is a partial mock: queries are built for real, nothing is executed.
 *
 * The refusal cases use a refused paper and a higher version of the same document: without the
 * check under test, the replacement would go on and rename the identifier of the paper (update).
 */
final class SubmitControllerReplacementScopeTest extends TestCase
{
    private const OWNER_UID = 424242;
    private const SUBMISSION_DATE = '2020-01-02 03:04:05';

    private Zend_Db_Adapter_Abstract $previousAdapter;

    private mixed $previousSettings = null;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();

        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['query', 'update'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->previousSettings = Zend_Registry::isRegistered('reviewSettings') ? Zend_Registry::get('reviewSettings') : null;
        $this->allowRefusedPaperResubmission(true);

        require_once APPLICATION_PATH . '/modules/journal/controllers/SubmitController.php';
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Registry::set('reviewSettings', $this->previousSettings);
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    public function testRenameIdentifierIsRestrictedToThePaperInItsJournal(): void
    {
        $this->adapter->expects(self::once())
            ->method('update')
            ->with(
                T_PAPERS,
                ['IDENTIFIER' => 'abc-REFUSED'],
                ['IDENTIFIER = ?' => 'abc', 'PAPERID = ?' => 7, 'RVID = ?' => 3]
            )
            ->willReturn(1);

        self::assertSame(1, Episciences_PapersManager::renameIdentifier('abc', 'abc-REFUSED', 7, 3));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function missingPaperOrJournalProvider(): array
    {
        return [
            'no paper' => [0, 3],
            'no journal' => [7, 0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingPaperOrJournalProvider')]
    public function testRenameIdentifierWithoutPaperOrJournalRenamesNothing(int $paperId, int $rvId): void
    {
        $this->adapter->expects(self::never())->method('update');

        self::assertFalse(Episciences_PapersManager::renameIdentifier('abc', 'abc-REFUSED', $paperId, $rvId));
    }

    public function testReplacementOfAnUnknownPaperIsRefusedWithoutAnyWrite(): void
    {
        $this->logInAs(self::OWNER_UID);

        $statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $statement->method('fetch')->willReturn(false);

        $selects = [];
        $this->adapter->method('query')->willReturnCallback(
            function ($sql) use ($statement, &$selects) {
                $selects[] = (string)$sql;

                return $statement;
            }
        );
        $this->adapter->expects(self::never())->method('update');

        // Everything below is attacker-controlled
        $formValues = [
            'old_docid' => '12',
            'old_identifier' => 'victim-identifier',
            'old_paper_status' => '5',
            'old_paperid' => '99',
            'old_version' => '0',
            'old_repoid' => '1',
            'search_doc' => ['docId' => 'victim-identifier', 'version' => '2', 'repoId' => '1'],
        ];

        [$result] = $this->replace($formValues);

        self::assertSame(0, $result['code']);
        self::assertNotEmpty($selects);
        self::assertMatchesRegularExpression('/DOCID\W*\s*=\s*\'?12\b/i', $selects[0]);
    }

    public function testReplacementOfAPaperOfAnotherJournalIsRefusedWithoutAnyWrite(): void
    {
        $this->logInAs(self::OWNER_UID);
        $this->returnPaper(['RVID' => RVID + 1]);
        $this->adapter->expects(self::never())->method('update');

        $formValues = $this->forgedFormValues();
        [$result] = $this->replace($formValues);

        self::assertSame(0, $result['code']);
    }

    public function testReplacementOfAPaperOwnedBySomeoneElseInTheSameJournalIsRefusedWithoutAnyWrite(): void
    {
        $this->logInAs(self::OWNER_UID + 1);
        $this->returnPaper();
        $this->adapter->expects(self::never())->method('update');

        $formValues = $this->forgedFormValues();
        [$result] = $this->replace($formValues);

        self::assertSame(0, $result['code']);
    }

    public function testReplacementOfAPaperOwnedBySomeoneElseTellsWhy(): void
    {
        $this->logInAs(self::OWNER_UID + 1);
        $this->returnPaper();

        $formValues = $this->forgedFormValues();
        [$result] = $this->replace($formValues);

        self::assertSame(
            Zend_Registry::get('Zend_Translate')->translate(Episciences_Submit::REPLACEMENT_ERROR_NOT_OWNER),
            $result['message']
        );
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

    /**
     * Replacing such a paper from the submission form would add a second active row for the paper
     * and remove the files of the stored one: its new version goes through the new version page.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notReplacedInPlaceProvider')]
    public function testPaperFurtherInThePublicationProcessIsNotReplacedFromTheSubmissionForm(int $status): void
    {
        $this->logInAs(self::OWNER_UID);
        $this->returnPaper(['STATUS' => $status]);
        $this->adapter->expects(self::never())->method('update');

        $formValues = $this->forgedFormValues();
        [$result] = $this->replace($formValues);

        self::assertSame(0, $result['code']);
        self::assertSame(
            Zend_Registry::get('Zend_Translate')->translate(Episciences_Submit::REPLACEMENT_ERROR_NOT_REPLACEABLE),
            $result['message']
        );
    }

    public function testResubmissionOfARefusedPaperIsRefusedWhenTheJournalDoesNotAllowIt(): void
    {
        $this->allowRefusedPaperResubmission(false);
        $this->logInAs(self::OWNER_UID);
        $this->returnPaper();
        $this->adapter->expects(self::never())->method('update');

        $formValues = $this->forgedFormValues();
        [$result] = $this->replace($formValues);

        self::assertSame(0, $result['code']);
    }

    /**
     * Control case: the same setup reaches the write once every check passes.
     * The renaming is reported as failed, so that the submission stops there.
     */
    public function testResubmissionOfAnOwnRefusedPaperRenamesTheIdentifierOfThePaperInItsJournalOnly(): void
    {
        $this->logInAs(self::OWNER_UID);
        $this->returnPaper();
        $this->adapter->expects(self::once())
            ->method('update')
            ->with(
                T_PAPERS,
                ['IDENTIFIER' => 'paper-identifier-REFUSED'],
                ['IDENTIFIER = ?' => 'paper-identifier', 'PAPERID = ?' => 12, 'RVID = ?' => RVID]
            )
            ->willReturn(0);

        $formValues = $this->forgedFormValues();
        $this->replace($formValues);

        self::assertSame(Episciences_Paper::STATUS_REFUSED, $formValues['old_paper_status']);
        self::assertArrayNotHasKey('old_submissiondate', $formValues);
    }

    public function testReplacementTakesTheStatusAndSubmissionDateOfTheStoredPaper(): void
    {
        $this->logInAs(self::OWNER_UID);
        $this->returnPaper(['STATUS' => Episciences_Paper::STATUS_SUBMITTED]);
        $this->adapter->expects(self::never())->method('update');

        $formValues = $this->forgedFormValues();
        // Same version as the stored paper: the replacement stops before any write
        $formValues['search_doc']['version'] = '1';

        [$result] = $this->replace($formValues);

        self::assertSame(0, $result['code']);
        self::assertSame(12, $formValues['old_docid']);
        self::assertSame(Episciences_Paper::STATUS_SUBMITTED, $formValues['old_paper_status']);
        self::assertSame(self::SUBMISSION_DATE, $formValues['old_submissiondate']);
    }

    public function testNewSubmissionIgnoresAPostedSubmissionDate(): void
    {
        $submit = $this->createMock(Episciences_Submit::class);
        $submit->expects(self::once())
            ->method('saveDoc')
            ->with(self::callback(static fn(array $values): bool => !array_key_exists('old_submissiondate', $values)))
            ->willReturn(['code' => 1, 'message' => '']);

        $controller = new \SubmitController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $method = new ReflectionMethod($controller, 'handleNewSubmission');
        $method->setAccessible(true);

        $method->invoke($controller, $submit, ['old_submissiondate' => '1999-01-01 00:00:00']);
    }

    private function logInAs(int $uid): void
    {
        Zend_Auth::getInstance()->getStorage()->write(new Episciences_User(['UID' => $uid]));
    }

    private function allowRefusedPaperResubmission(bool $allowed): void
    {
        Zend_Registry::set('reviewSettings', [Episciences_Review::SETTING_CAN_RESUBMIT_REFUSED_PAPER => $allowed ? '1' : '0']);
    }

    /**
     * By default: a refused paper of the current journal, owned by OWNER_UID.
     *
     * @param array<string, mixed> $overrides
     */
    private function returnPaper(array $overrides = []): void
    {
        $rows = [array_merge([
            'DOCID' => 12,
            'PAPERID' => 12,
            'RVID' => RVID,
            'UID' => self::OWNER_UID,
            'STATUS' => Episciences_Paper::STATUS_REFUSED,
            'IDENTIFIER' => 'paper-identifier',
            'VERSION' => 1,
            'REPOID' => 1,
            'SUBMISSION_DATE' => self::SUBMISSION_DATE,
        ], $overrides)];

        $statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $statement->method('fetch')->willReturnCallback(static function () use (&$rows) {
            return array_shift($rows) ?? false;
        });
        $statement->method('fetchAll')->willReturn([]);
        $statement->method('fetchColumn')->willReturn(false);
        $this->adapter->method('query')->willReturn($statement);
    }

    /**
     * A new version of the stored paper, described with forged values.
     *
     * @return array<string, mixed>
     */
    private function forgedFormValues(): array
    {
        return [
            'old_docid' => '12',
            'old_paper_status' => '0',
            'old_submissiondate' => '1999-01-01 00:00:00',
            'search_doc' => ['docId' => 'paper-identifier', 'version' => '2', 'repoId' => '1'],
        ];
    }

    /**
     * @param array<string, mixed> $formValues
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function replace(array &$formValues): array
    {
        $controller = new \SubmitController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $method = new ReflectionMethod($controller, 'handlePaperReplacement');
        $method->setAccessible(true);

        return $method->invokeArgs($controller, [&$formValues]);
    }
}
