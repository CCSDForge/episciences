<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Messenger\Enqueue\BoundedRetryDispatcher;
use Episciences\Next\Enqueue\NextRevalidationQueuePort;
use Episciences\Next\RevalidationService;
use Episciences\Volume\ProceedingsValidator;
use Episciences_Acl;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use Episciences_Volume;
use Episciences\Volume\DoiQueue;
use unit\library\Episciences\Messenger\Enqueue\RestoresStaticQueuePort;
use unit\library\Episciences\Messenger\Enqueue\SpyEnqueueFailureStore;
use unit\library\Episciences\Messenger\Enqueue\SpyMessageBus;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use Zend_Registry;

/**
 * Proceedings DOI of a volume through the volume form (edit) and the Crossref export.
 *
 * The actions run for real on programmed rows: a DOI sent to Crossref is never changed by the
 * form, the suffix is checked on the server, the export is the deposit XML.
 */
final class VolumeControllerProceedingsTest extends ControllerTestCase
{
    use RestoresStaticQueuePort;

    private const VID = 5;
    private const PREFIX = '10.46298';
    private const STORED_SUFFIX = '51';

    private mixed $previousRevalidationPort;

    private int $previousReviewId;

    protected function setUp(): void
    {
        $this->previousReviewId = Episciences_Review::getCurrentReviewId();
        Episciences_Review::setCurrentReviewId(RVID);
        self::loadController('journal', 'VolumeController');

        $this->previousRevalidationPort = self::readStaticQueuePort(RevalidationService::class);
        RevalidationService::setPort(new NextRevalidationQueuePort(new BoundedRetryDispatcher(new SpyMessageBus(), new SpyEnqueueFailureStore())));

        $this->givenJournal([Episciences_Review_DoiSettings::SETTING_DOI_PREFIX => self::PREFIX]);
        Zend_Registry::set('reviewSettingsDoi', new Episciences_Review_DoiSettings([Episciences_Review_DoiSettings::SETTING_DOI_PREFIX => self::PREFIX]));
        Zend_Registry::set('languages', ['en']);
        Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
    }

    protected function tearDown(): void
    {
        Episciences_Review::setCurrentReviewId($this->previousReviewId);
        self::writeStaticQueuePort(RevalidationService::class, $this->previousRevalidationPort);
    }

    private static function storedDoi(): string
    {
        return ProceedingsValidator::buildDoi(self::PREFIX, (string)RVCODE, self::STORED_SUFFIX);
    }

    private function givenProceedingsVolume(string $doiStatus, bool $isProceeding = true): void
    {
        $this->db
            ->respondTo('/GET_LOCK/', [['lock' => '1']])
            ->respondTo("/FROM `VOLUME` WHERE \\(VID = '?" . self::VID . "'?\\)/", [[
                'VID' => self::VID, 'RVID' => RVID, 'POSITION' => 1, 'titles' => '{"en":"Proceedings of MFPS"}',
            ]])
            ->respondTo('/FROM `VOLUME_SETTING` WHERE \(VID = ' . self::VID . '\)/', [
                ['SETTING' => Episciences_Volume::VOLUME_IS_PROCEEDING, 'VALUE' => $isProceeding ? '1' : '0'],
                ['SETTING' => Episciences_Volume::VOLUME_CONFERENCE_NAME, 'VALUE' => 'MFPS'],
                ['SETTING' => Episciences_Volume::VOLUME_CONFERENCE_START_DATE, 'VALUE' => '2026-10-27'],
                ['SETTING' => Episciences_Volume::VOLUME_CONFERENCE_END_DATE, 'VALUE' => '2026-10-29'],
                ['SETTING' => Episciences_Volume::VOLUME_CONFERENCE_DOI, 'VALUE' => self::storedDoi()],
            ])
            ->respondTo('/FROM `doi_queue_volumes`/', $doiStatus === DoiQueue::STATUS_NOT_ASSIGNED ? [] : [[
                'id' => 1, 'vid' => self::VID, 'doi_status' => $doiStatus, 'date_init' => '2026-11-01 10:00:00', 'date_updated' => '2026-11-01 10:00:00',
            ]]);
    }

    /**
     * @param array<string, string> $override
     */
    private function postEdit(array $override = []): DispatchResult
    {
        $post = $override + [
            'title_en' => 'Proceedings of MFPS',
            'year' => '',
            'num' => '',
            'status' => '1',
            'current_issue' => '0',
            'special_issue' => '0',
            'is_proceeding' => '1',
            'conference_name' => 'MFPS',
            'conference_start' => '2026-10-27',
            'conference_end' => '2026-10-29',
            'conference_proceedings_doi' => self::STORED_SUFFIX,
            'submit' => 'Valider',
        ];

        return $this->dispatch(\VolumeController::class, 'edit', $this->postRequest($post, true, ['id' => (string)self::VID]));
    }

    /**
     * @return list<string> SQL of the settings written for the volume
     */
    private function writtenSettings(string $table): array
    {
        $sql = array_column($this->db->queries(), 'sql');

        return array_values(array_filter($sql, static fn(string $query): bool => str_starts_with($query, 'INSERT INTO ' . $table)));
    }

    private function writtenDoi(string $table): ?string
    {
        foreach ($this->writtenSettings($table) as $query) {
            if (preg_match("/VALUES \('conference_proceedings_doi', '([^']*)'/", $query, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @return list<string> SQL of the queue inserts
     */
    private function queuedStatuses(): array
    {
        $sql = array_column($this->db->queries(), 'sql');
        $inserts = array_filter($sql, static fn(string $query): bool => str_contains($query, 'INSERT INTO `doi_queue_volumes`'));

        return array_values($inserts);
    }

    /**
     * @return array<string, mixed>
     */
    private function doiRowData(): array
    {
        $form = $this->lastView()->form;

        return $form->getDecorator('ViewScript')->getOption(\Episciences_VolumesManager::PROCEEDINGS_DOI_VIEW_OPTION);
    }

    private function lastView(): \Zend_View_Interface
    {
        return \Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer')->view;
    }

    public function testRequestedDoiIsKeptWhateverIsPosted(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_REQUESTED);

        $result = $this->postEdit(['conference_proceedings_doi' => 'changed']);

        self::assertNotNull($result->redirectUrl(), 'Saved and redirected');
        self::assertSame(self::storedDoi(), $this->writtenDoi(T_VOLUME_SETTINGS));
        self::assertSame(self::storedDoi(), $this->writtenDoi(T_VOLUME_PROCEEDING));
        self::assertSame([], $this->queuedStatuses(), 'The requested status is not set back to assigned');
        self::assertSame([], $this->db->deletes());
    }

    public function testRequestedDoiIsKeptWhenTheSuffixIsNotPosted(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_PUBLIC);
        $post = ['conference_proceedings_doi' => ''];

        $this->postEdit($post);

        self::assertSame(self::storedDoi(), $this->writtenDoi(T_VOLUME_SETTINGS));
    }

    public function testRegisteredDoiForbidsUncheckingProceedings(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_PUBLIC);

        $result = $this->postEdit(['is_proceeding' => '0']);

        self::assertNull($result->redirectUrl());
        self::assertSame([], $this->writtenSettings(T_VOLUME_SETTINGS));
        self::assertSame([], $this->db->updates());
    }

    public function testAssignedDoiFollowsTheNewSuffix(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $result = $this->postEdit(['conference_proceedings_doi' => 'mfps.40']);

        self::assertNotNull($result->redirectUrl());
        $expected = ProceedingsValidator::buildDoi(self::PREFIX, (string)RVCODE, 'mfps.40');
        self::assertSame($expected, $this->writtenDoi(T_VOLUME_SETTINGS));
        self::assertSame($expected, $this->writtenDoi(T_VOLUME_PROCEEDING));
        self::assertCount(1, $this->queuedStatuses());
        self::assertStringContainsString("'assigned'", $this->queuedStatuses()[0]);
    }

    public function testSuffixIsIgnoredWhenTheVolumeIsNotAProceedings(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $result = $this->postEdit(['is_proceeding' => '0', 'conference_proceedings_doi' => 'a/b?x']);

        self::assertNotNull($result->redirectUrl());
        self::assertSame('', $this->writtenDoi(T_VOLUME_SETTINGS), 'An unvalidated suffix is never stored');
        self::assertSame([], $this->queuedStatuses());
    }

    public function testFormIsRefusedWhileTheDoiLockIsHeld(): void
    {
        $this->db->respondTo('/GET_LOCK/', [['lock' => '0']]);
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $result = $this->postEdit(['conference_proceedings_doi' => 'mfps.40']);

        self::assertNull($result->redirectUrl());
        self::assertSame([], $this->writtenSettings(T_VOLUME_SETTINGS));
        self::assertSame([], $this->queuedStatuses());
    }

    public function testTheDoiLockIsReleasedAfterTheSave(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $this->postEdit(['conference_proceedings_doi' => 'mfps.40']);

        $sql = implode("\n", array_column($this->db->queries(), 'sql'));
        self::assertStringContainsString('SELECT RELEASE_LOCK(?)', $sql);
        self::assertGreaterThan(strpos($sql, 'INSERT INTO `doi_queue_volumes`'), strrpos($sql, 'RELEASE_LOCK'), 'Released once the DOI is queued');
    }

    public function testRemovingTheSuffixForgetsTheAssignedDoi(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $this->postEdit(['conference_proceedings_doi' => '']);

        self::assertSame('', $this->writtenDoi(T_VOLUME_SETTINGS));
        self::assertSame([], $this->queuedStatuses());
        self::assertSame([['table' => T_DOI_QUEUE_VOLUMES, 'where' => ['vid = ?' => self::VID, 'doi_status = ?' => 'assigned']]], $this->db->deletes());
    }

    /**
     * @dataProvider invalidSuffixProvider
     */
    public function testInvalidSuffixIsRefused(string $suffix): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $result = $this->postEdit(['conference_proceedings_doi' => $suffix]);

        self::assertNull($result->redirectUrl(), 'The form is displayed again');
        self::assertSame([], $this->writtenSettings(T_VOLUME_SETTINGS));
        self::assertSame([], $this->db->updates());
        self::assertSame([], $this->queuedStatuses());
        $doiRow = $this->doiRowData();
        self::assertSame($suffix, $doiRow['suffix'], 'The posted suffix is shown again');
        self::assertNotEmpty($doiRow['error']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSuffixProvider(): array
    {
        return [
            'accent' => ['é51'],
            'slash' => ['51/2'],
            'too long' => [str_repeat('a', 21)],
        ];
    }

    public function testSuffixUsedByAnotherVolumeIsRefused(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);
        $this->db->respondTo('/FROM `VOLUME_SETTING` AS `S`/', [['COUNT(*)' => '1']]);

        $result = $this->postEdit(['conference_proceedings_doi' => '52']);

        self::assertNull($result->redirectUrl());
        self::assertSame([], $this->db->updates());
        self::assertMatchesRegularExpression('/\(S\.VID != ' . self::VID . '\)/', implode("\n", $this->db->selects()));
    }

    public function testMissingConferenceFieldsAreRefused(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $result = $this->postEdit(['conference_name' => '', 'conference_start' => '2026-10-29', 'conference_end' => '2026-10-27']);

        self::assertNull($result->redirectUrl());
        self::assertSame([], $this->db->updates());
        $form = $this->lastView()->form;
        self::assertNotEmpty($form->getElement('conference_name')->getMessages());
        self::assertNotEmpty($form->getElement('conference_end')->getMessages());
    }

    public function testExportReturnsTheCrossrefXmlOfTheVolume(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_ASSIGNED);

        $result = $this->dispatch(\VolumeController::class, 'export', $this->getRequest([], ['id' => (string)self::VID]));

        self::assertStringStartsWith('text/xml', (string)$result->header('Content-Type'));
        self::assertStringContainsString('<doi>' . self::storedDoi() . '</doi>', $result->body());
        self::assertStringContainsString('<email_address>noreply@' . DOMAIN . '</email_address>', $result->body());
    }

    public function testExportRefusesAVolumeThatIsNotAProceedings(): void
    {
        $this->givenProceedingsVolume(DoiQueue::STATUS_NOT_ASSIGNED, false);

        $result = $this->dispatch(\VolumeController::class, 'export', $this->getRequest([], ['id' => (string)self::VID]));

        self::assertSame('/browse/volumes', $result->redirectUrl());
        self::assertStringNotContainsString('<doi_batch', $result->body());
    }
}
