<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
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
 * Setting the secondary volumes of a paper (saveothervolumes): request token, paper the user may
 * manage, volumes of the current journal.
 *
 * The action runs for real on programmed rows; the Solr queue is a spy.
 */
final class AdministratepaperSaveOtherVolumesTest extends ControllerTestCase
{
    use RestoresStaticQueuePort;

    private const DOCID = 12;
    private const MASTER_VID = 2;
    private const OTHER_VID = 3;

    private string $conflictAnswer = Episciences_Paper_Conflict::AVAILABLE_ANSWER['no'];

    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');

        $this->captureStaticQueuePort(SolrIndexing::class);
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));

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
    }

    private function givenPaper(int $uid = 99): void
    {
        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => self::MASTER_VID,
            'SID' => 0,
            'UID' => $uid,
            'STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED,
            'VERSION' => 1,
            'IDENTIFIER' => '1234',
            'REPOID' => 1,
            'FLAG' => 'submitted',
            'WHEN' => '2026-01-01 00:00:00',
            'SUBMISSION_DATE' => '2026-01-01 00:00:00',
            'MODIFICATION_DATE' => '2026-01-01 00:00:00',
        ]]);
    }

    private function givenOtherVolumeInTheJournal(): void
    {
        $this->db->respondTo('/FROM `VOLUME` WHERE \(VID = ' . self::OTHER_VID . '\)/', [['VID' => self::OTHER_VID, 'RVID' => RVID, 'POSITION' => 1]]);
    }

    private function save(?Zend_Controller_Request_HttpTestCase $request = null): DispatchResult
    {
        $request ??= $this->postRequest(['docid' => (string)self::DOCID, 'vids' => [(string)self::OTHER_VID]], true);

        return $this->dispatch(\AdministratepaperController::class, 'saveothervolumes', $request);
    }

    /**
     * @return list<string> conditions of the deletions from the secondary volumes table
     */
    private function otherVolumeDeletions(): array
    {
        $deletes = array_filter($this->db->deletes(), static fn(array $delete): bool => $delete['table'] === T_VOLUME_PAPER);

        return array_values(array_map(static fn(array $delete): string => (string)$delete['where'], $deletes));
    }

    private function assertRefused(DispatchResult $result): void
    {
        self::assertSame('', $result->output);
        self::assertSame(403, $result->statusCode());
        self::assertSame([], $this->db->inserts());
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->db->deletes());
    }

    /**
     * Saving no volume removes the secondary volumes of the paper (adding one goes through a
     * prepared statement, which the recording adapter does not run).
     */
    public function testSecretaryCanRemoveTheSecondaryVolumes(): void
    {
        $this->givenPaper();

        $result = $this->save($this->postRequest(['docid' => (string)self::DOCID], true));

        self::assertSame(200, $result->statusCode());
        self::assertSame(['DOCID = ' . self::DOCID], $this->otherVolumeDeletions());
    }

    public function testPostWithoutTokenIsRefusedBeforeLoadingThePaper(): void
    {
        $this->givenPaper();
        $this->givenOtherVolumeInTheJournal();

        $result = $this->save($this->postRequest(['docid' => (string)self::DOCID, 'vids' => [(string)self::OTHER_VID]]));

        $this->assertRefused($result);
        self::assertSame([], $this->db->queries());
    }

    public function testGetRequestIsRefused(): void
    {
        $this->givenPaper();
        $this->givenOtherVolumeInTheJournal();

        $this->assertRefused($this->save($this->getRequest(['docid' => (string)self::DOCID, 'vids' => [(string)self::OTHER_VID]])));
    }

    public function testUnknownPaperIsRefused(): void
    {
        $this->givenOtherVolumeInTheJournal();

        $this->assertRefused($this->save());
    }

    public function testOwnSubmissionIsRefused(): void
    {
        $this->givenPaper(self::UID);
        $this->givenOtherVolumeInTheJournal();

        $this->assertRefused($this->save());
    }

    public function testPaperWithADeclaredConflictIsRefused(): void
    {
        $this->givenPaper();
        $this->givenOtherVolumeInTheJournal();
        $this->conflictAnswer = Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'];

        $this->assertRefused($this->save());
    }
}
