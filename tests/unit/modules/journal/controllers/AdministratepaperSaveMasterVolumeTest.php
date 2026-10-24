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
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use unit\modules\ControllerTestCase;
use Zend_Controller_Request_HttpTestCase;

/**
 * Moving a paper to another master volume (savemastervolume).
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

    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');

        $this->captureStaticQueuePort(SolrIndexing::class);
        $this->previousRevalidationPort = self::readStaticQueuePort(RevalidationService::class);
        $this->solrQueue = new SpyMessageBus();
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher($this->solrQueue, new SpyEnqueueFailureStore())));
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));

        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
    }

    protected function tearDown(): void
    {
        $this->restoreStaticQueuePort(SolrIndexing::class);
        self::writeStaticQueuePort(RevalidationService::class, $this->previousRevalidationPort);
    }

    private function givenPaper(int $status, int $vid = self::CURRENT_VID): void
    {
        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => $vid,
            'SID' => 0,
            'UID' => 99,
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
        $request ??= $this->postRequest(['docid' => (string)self::DOCID, 'vid' => (string)self::TARGET_VID], true);

        return $this->dispatch(\AdministratepaperController::class, 'savemastervolume', $request)->output;
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
}
