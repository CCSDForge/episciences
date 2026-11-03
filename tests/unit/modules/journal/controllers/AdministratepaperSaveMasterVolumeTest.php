<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Next\Enqueue\NextRevalidationQueuePort;
use Episciences\Next\RevalidationService;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Review;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use Zend_Controller_Request_HttpTestCase;

/**
 * Moving a paper to another master volume (savemastervolume): request token, paper the user may
 * manage, volume of the current journal.
 *
 * The action runs for real on programmed rows; the Solr and Next.js queues are spies.
 */
final class AdministratepaperSaveMasterVolumeTest extends ControllerTestCase
{
    use RestoresStaticQueuePort;

    private const DOCID = 12;
    private const CURRENT_VID = 2;
    private const TARGET_VID = 3;

    private SpyMessageBus $solrQueue;

    private mixed $previousRevalidationPort;

    private string $conflictAnswer = Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'];

    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');

        $this->captureStaticQueuePort(SolrIndexing::class);
        $this->previousRevalidationPort = self::readStaticQueuePort(RevalidationService::class);
        $this->solrQueue = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher($this->solrQueue, new SpyEnqueueFailureStore())));
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));

        // the conflict of interest check is on: the logged user answered it ($conflictAnswer)
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);
        $this->db->respondTo('/FROM `paper_conflicts`/', fn(): array => [[
            'cid' => 1,
            'paper_id' => self::DOCID,
            'by' => self::UID,
            'answer' => $this->conflictAnswer,
            'date' => '2026-01-01 00:00:00',
            'SCREEN_NAME' => 'user',
        ]]);
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
    }

    protected function tearDown(): void
    {
        $this->restoreStaticQueuePort(SolrIndexing::class);
        self::writeStaticQueuePort(RevalidationService::class, $this->previousRevalidationPort);
    }

    private function givenPaper(int $status, int $vid = self::CURRENT_VID, int $uid = 99): void
    {
        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => $vid,
            'SID' => 0,
            'UID' => $uid,
            'STATUS' => $status,
            'VERSION' => 1,
            'IDENTIFIER' => '1234',
            'REPOID' => 1,
            'FLAG' => 'submitted',
            'WHEN' => '2026-01-01 00:00:00',
            'SUBMISSION_DATE' => '2026-01-01 00:00:00',
            'MODIFICATION_DATE' => '2026-01-01 00:00:00',
            'PUBLICATION_DATE' => '2026-01-02 00:00:00',
        ]]);
    }

    private function givenTargetVolumeInTheJournal(): void
    {
        $this->db->respondTo('/FROM `VOLUME` WHERE \(VID = ' . self::TARGET_VID . '\)/', [['VID' => self::TARGET_VID, 'RVID' => RVID, 'POSITION' => 1]]);
    }

    private function move(?Zend_Controller_Request_HttpTestCase $request = null): string
    {
        return $this->dispatchMove($request)->output;
    }

    private function dispatchMove(?Zend_Controller_Request_HttpTestCase $request = null): DispatchResult
    {
        $request ??= $this->postRequest(['docid' => (string)self::DOCID, 'vid' => (string)self::TARGET_VID], true);

        return $this->dispatch(\AdministratepaperController::class, 'savemastervolume', $request);
    }

    private function assertNothingWritten(): void
    {
        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->db->deletes());
        self::assertSame([], $this->reindexedPapers());
    }

    /**
     * @return list<array<string, mixed>> values written to the papers table
     */
    private function paperUpdates(): array
    {
        $updates = array_filter($this->db->updates(), static fn(array $update): bool => $update['table'] === T_PAPERS);

        return array_column($updates, 'values');
    }

    /**
     * @return list<int> ids of the papers sent to the Solr index
     */
    private function reindexedPapers(): array
    {
        $messages = array_filter($this->solrQueue->dispatched, static fn(object $message): bool => $message instanceof IndexPaperMessage);

        return array_values(array_map(static fn(IndexPaperMessage $message): int => $message->docId, $messages));
    }

    public function testPublishedPaperMovedToAnotherVolumeIsSavedAndReindexed(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED);
        $this->givenTargetVolumeInTheJournal();

        self::assertSame('1', $this->move());

        self::assertCount(1, $this->paperUpdates());
        self::assertSame(self::TARGET_VID, $this->paperUpdates()[0]['VID']);
        self::assertSame([self::DOCID], $this->reindexedPapers());
    }

    public function testUnpublishedPaperMovedToAnotherVolumeIsNotReindexed(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_BEING_REVIEWED);
        $this->givenTargetVolumeInTheJournal();

        self::assertSame('1', $this->move());

        self::assertCount(1, $this->paperUpdates());
        self::assertSame([], $this->reindexedPapers());
    }

    public function testPostingTheCurrentVolumeChangesNothing(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED, self::TARGET_VID);
        $this->givenTargetVolumeInTheJournal();

        self::assertSame('1', $this->move());

        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->db->deletes());
        self::assertSame([], $this->reindexedPapers());
    }

    public function testVolumeOfAnotherJournalIsRefused(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED);

        self::assertSame('', $this->move());

        self::assertMatchesRegularExpression('/FROM `VOLUME` WHERE \(VID = ' . self::TARGET_VID . '\) AND \(RVID = ' . RVID . '\)/', implode("\n", $this->db->selects()));
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->db->deletes());
        self::assertSame([], $this->reindexedPapers());
    }

    public function testGetRequestChangesNothing(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED);
        $this->givenTargetVolumeInTheJournal();

        $this->move($this->getRequest(['docid' => (string)self::DOCID, 'vid' => (string)self::TARGET_VID]));

        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->db->deletes());
        self::assertSame([], $this->reindexedPapers());
    }

    public function testPostWithoutTokenIsRefusedBeforeLoadingThePaper(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED);
        $this->givenTargetVolumeInTheJournal();

        $result = $this->dispatchMove($this->postRequest(['docid' => (string)self::DOCID, 'vid' => (string)self::TARGET_VID]));

        self::assertSame('', $result->output);
        self::assertSame(403, $result->statusCode());
        self::assertSame([], $this->db->queries());
        $this->assertNothingWritten();
    }

    public function testUnknownPaperIsRefused(): void
    {
        $this->givenTargetVolumeInTheJournal();

        $result = $this->dispatchMove();

        self::assertSame('', $result->output);
        self::assertSame(403, $result->statusCode());
        $this->assertNothingWritten();
    }

    public function testOwnSubmissionIsRefused(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED, self::CURRENT_VID, self::UID);
        $this->givenTargetVolumeInTheJournal();

        $result = $this->dispatchMove();

        self::assertSame('', $result->output);
        self::assertSame(403, $result->statusCode());
        $this->assertNothingWritten();
    }

    public function testPaperWithADeclaredConflictIsRefused(): void
    {
        $this->givenPaper(Episciences_Paper::STATUS_PUBLISHED);
        $this->givenTargetVolumeInTheJournal();
        $this->conflictAnswer = Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'];

        $result = $this->dispatchMove();

        self::assertSame('', $result->output);
        self::assertSame(403, $result->statusCode());
        $this->assertNothingWritten();
    }
}
