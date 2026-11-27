<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_Error;
use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Paper_Authors_Repository;
use Episciences_PapersManager;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use Zend_Db_Statement_Exception;

/**
 * Update of the metadata of a paper from its repository (paper/updaterecorddata):
 * the record is updated even when a step fails, and the contributor is told that some data could not be stored.
 *
 * The action runs for real on programmed rows, with a fake repository ("New Version Spy" hooks).
 */
final class PaperControllerUpdateRecordDataTest extends ControllerTestCase
{
    use RestoresStaticQueuePort;

    private const DOCID = 12;
    private const SPY_REPO_ID = 9998;

    /** @var array<mixed> */
    private array $previousRepositories;

    private CacheItemPoolInterface $previousAuthorsCache;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/support/NewVersionSpyHooks.php';
    }

    protected function setUp(): void
    {
        self::loadController('journal', 'PaperController');

        $this->captureStaticQueuePort(SolrIndexing::class);
        SolrIndexing::setPort(new SolrIndexQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));

        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $this->previousRepositories = $repositories->getValue();
        $repositories->setValue(null, [
            self::SPY_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'New Version Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
            ],
        ]);

        Episciences_Repositories_NewVersionSpy_Hooks::reset();
        Episciences_Repositories_NewVersionSpy_Hooks::$record = ['record' => '<record/>', 'enrichment' => []];

        // The authors of the paper are looked up through a static cache: an earlier test must not answer for them
        $this->previousAuthorsCache = Episciences_Paper_Authors_Repository::getCachePool();
        Episciences_Paper_Authors_Repository::setCachePool(new ArrayAdapter());

        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER]);
        $this->givenPaper();
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::reset();
        Episciences_Paper_Authors_Repository::setCachePool($this->previousAuthorsCache);
        $this->restoreStaticQueuePort(SolrIndexing::class);
    }

    public function testFailedStepIsToldAndTheRecordIsStillUpdated(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new Ccsd_Error('files unavailable');

        [$result, $json] = $this->updateRecordData();

        self::assertTrue($json['warning'] ?? false);
        self::assertSame(Episciences_PapersManager::RECORD_UPDATE_FAILURE_MESSAGE, $json['message']);
        self::assertArrayNotHasKey('error', $json);
        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the next steps run');
        self::assertNotEmpty($this->recordUpdates(), 'the record is still updated');
        self::assertSame(200, $result->statusCode());
    }

    /**
     * The authors taken from the paper metadata, when the paper has none: a failed insert is a failed step
     */
    public function testAuthorsThatCannotBeStoredAreTold(): void
    {
        $this->db->respondTo('/^INSERT INTO `' . preg_quote(T_PAPER_AUTHORS, '/') . '`/i', static function (): never {
            throw new Zend_Db_Statement_Exception('authors insert failed');
        });

        [, $json] = $this->updateRecordData();

        self::assertTrue($json['warning'] ?? false);
        self::assertSame(Episciences_PapersManager::RECORD_UPDATE_FAILURE_MESSAGE, $json['message']);
        self::assertNotEmpty($this->recordUpdates(), 'the record is still updated');
    }

    public function testNoWarningWhenEveryStepSucceeds(): void
    {
        [, $json] = $this->updateRecordData();

        self::assertArrayNotHasKey('warning', $json);
        self::assertNotEmpty(
            preg_grep('/^INSERT INTO `' . preg_quote(T_PAPER_AUTHORS, '/') . '`/i', array_column($this->db->queries(), 'sql')),
            'the authors of the paper are stored'
        );
        self::assertSame('Les métadonnées de cet article ont bien été mises à jour.', $json['message']);
    }

    /**
     * Refreshing the record metadata is reserved to the paper's contributors and to the users allowed to manage
     * papers: any other member is refused before the repository is queried or anything is recorded.
     */
    public function testOtherMemberIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_MEMBER], self::UID + 1);

        $result = $this->dispatch(\PaperController::class, 'updaterecorddata', $this->xhrPostRequest(['docid' => (string)self::DOCID]));

        self::assertSame(403, $result->statusCode());
        self::assertSame([], Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the repository is not queried');
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
        self::assertSame([], $this->db->updates(), 'nothing is recorded');
    }

    /**
     * @return array{0: DispatchResult, 1: array<string, mixed>}
     */
    private function updateRecordData(): array
    {
        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;
            return true;
        });

        try {
            $result = $this->dispatch(\PaperController::class, 'updaterecorddata', $this->xhrPostRequest(['docid' => (string)self::DOCID]));
        } finally {
            restore_error_handler();
        }

        $json = json_decode($result->output, true);
        self::assertIsArray($json, 'a JSON answer: ' . $result->output . ' ' . implode(' | ', $errors));

        return [$result, $json];
    }

    /**
     * @return list<array<string, mixed>> updates of the RECORD column
     */
    private function recordUpdates(): array
    {
        return array_values(array_filter(
            $this->db->updates(),
            static fn(array $update): bool => $update['table'] === T_PAPERS && array_key_exists('RECORD', $update['values'])
        ));
    }

    private function givenPaper(): void
    {
        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => 0,
            'SID' => 0,
            'UID' => self::UID,
            'STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED,
            'VERSION' => 1,
            'IDENTIFIER' => '42',
            'REPOID' => self::SPY_REPO_ID,
            'DOI' => '',
            'FLAG' => 'submitted',
            'WHEN' => '2026-01-01 00:00:00',
            'SUBMISSION_DATE' => '2026-01-01 00:00:00',
            'MODIFICATION_DATE' => '2026-01-01 00:00:00',
        ]]);
    }
}
