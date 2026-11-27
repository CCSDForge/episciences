<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_Error;
use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Enqueue\SolrIndexQueuePort;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use Episciences_Submit;
use ReflectionProperty;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use unit\modules\ControllerTestCase;

/**
 * New version posted by the journal staff (administratepaper/savenewpostedversion): the version is saved first,
 * then the record of the paper is updated from the repository. A failure of that update, whole or partial,
 * is told to the staff instead of ending the request with an error.
 *
 * The action runs for real on programmed rows, with a fake repository ("New Version Spy" hooks).
 */
final class AdministratepaperNewPostedVersionRecordTest extends ControllerTestCase
{
    use RestoresStaticQueuePort;

    private const DOCID = 12;
    private const SPY_REPO_ID = 9998;

    /** @var array<mixed> */
    private array $previousRepositories;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/support/NewVersionSpyHooks.php';
    }

    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');

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

        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->givenPaper();
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::reset();
        $this->restoreStaticQueuePort(SolrIndexing::class);
    }

    public function testRecordThatCannotBeLoadedIsToldNotAnError(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookApiRecords'] = new Ccsd_Error('record unavailable');

        $json = $this->postNewVersion();

        self::assertSame(2, (int)$json['version'], 'the new version is saved');
        self::assertFalse($json['isDataRecordUpdated']);
        self::assertSame(Episciences_Submit::POST_SAVE_FAILURE_MESSAGE_STAFF, $json['warning']);
    }

    public function testFailedStepIsTold(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookLinkedDataProcessing'] = new Ccsd_Error('linked data unavailable');

        $json = $this->postNewVersion();

        self::assertSame(2, (int)$json['version']);
        self::assertTrue($json['isDataRecordUpdated'], 'the record is updated');
        self::assertSame(Episciences_Submit::POST_SAVE_FAILURE_MESSAGE_STAFF, $json['warning']);
    }

    public function testNoWarningWhenTheRecordIsUpdated(): void
    {
        $json = $this->postNewVersion();

        self::assertTrue($json['isDataRecordUpdated']);
        self::assertArrayNotHasKey('warning', $json);
    }

    /**
     * @return array<string, mixed>
     */
    private function postNewVersion(): array
    {
        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;
            return true;
        });

        try {
            $result = $this->dispatch(
                \AdministratepaperController::class,
                'savenewpostedversion',
                $this->xhrPostRequest(['docid' => (string)self::DOCID, 'latest-repository-version' => '2'], true)
            );
        } finally {
            restore_error_handler();
        }

        $json = json_decode($result->output, true);
        self::assertIsArray($json, 'a JSON answer: ' . $result->output . ' ' . implode(' | ', $errors));

        return $json;
    }

    private function givenPaper(): void
    {
        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => RVID,
            'VID' => 0,
            'SID' => 0,
            'UID' => 99,
            'STATUS' => Episciences_Paper::STATUS_ACCEPTED,
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
