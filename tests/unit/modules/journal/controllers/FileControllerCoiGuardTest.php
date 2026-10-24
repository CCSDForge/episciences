<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Security\AccessLog;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Rating_Report;
use Episciences_Review;
use PHPUnit\Framework\Attributes\DataProvider;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;
use unit\modules\ControllerTestCase;

/**
 * Conflict of interest check on rating report attachments (FileController::reportAction()).
 *
 * The action runs for real on programmed rows. A refusal is answered with a 404 that is logged as
 * a masked access refusal; an allowed access goes on to the file, which does not exist here, and
 * gets a plain 404.
 */
final class FileControllerCoiGuardTest extends ControllerTestCase
{
    use SecurityLogCaptureTrait;

    private const DOCID = 12;
    private const REVIEWER_UID = 77;
    private const AUTHOR_UID = 99;

    protected function setUp(): void
    {
        self::loadController('journal', 'FileController');

        $this->db
            ->respondTo('/FROM `REVIEWER_REPORT`/', [[
                'ID' => 5,
                'DOCID' => self::DOCID,
                'UID' => self::REVIEWER_UID,
                'STATUS' => Episciences_Rating_Report::STATUS_COMPLETED,
            ]])
            ->respondTo('/FROM `PAPERS`/', [[
                'DOCID' => self::DOCID,
                'PAPERID' => self::DOCID,
                'RVID' => RVID,
                'UID' => self::AUTHOR_UID,
                'STATUS' => Episciences_Paper::STATUS_BEING_REVIEWED,
            ]]);
    }

    /**
     * @param string|null $answer answer of the logged user to the conflict check, null when not answered
     */
    private function givenConflictAnswer(?string $answer, int $uid = self::UID): void
    {
        $rows = [];
        if ($answer !== null) {
            $rows[] = ['cid' => 1, 'paper_id' => self::DOCID, 'by' => $uid, 'answer' => $answer, 'date' => '2026-01-01 00:00:00', 'SCREEN_NAME' => 'user'];
        }

        $this->db->respondTo('/FROM `paper_conflicts`/', $rows);
    }

    private function requestAttachment(): void
    {
        $request = $this->getRequest([], ['id' => '5', 'filename' => 'report', 'extension' => 'pdf']);

        $result = $this->dispatch(\FileController::class, 'report', $request);

        // Allowed or not, the answer does not tell whether the file exists
        self::assertSame(404, $result->statusCode());
    }

    private function isRefused(): bool
    {
        return in_array([AccessLog::NOT_FOUND_MASKED, 'rating_report_access'], $this->loggedEvents(), true);
    }

    /**
     * @return array<string, array{list<string>, string|null}>
     */
    public static function conflictProvider(): array
    {
        return [
            'editor who declared a conflict' => [[Episciences_Acl::ROLE_EDITOR], Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']],
            'editor who answered later' => [[Episciences_Acl::ROLE_EDITOR], Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']],
            'editor who did not answer' => [[Episciences_Acl::ROLE_EDITOR], null],
            'secretary who declared a conflict' => [[Episciences_Acl::ROLE_SECRETARY], Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']],
        ];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('conflictProvider')]
    public function testUserWithAnUnresolvedOrDeclaredConflictIsRefused(array $roles, ?string $answer): void
    {
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);
        $this->givenConflictAnswer($answer);
        $this->logInAs($roles);

        $this->requestAttachment();

        self::assertTrue($this->isRefused());
    }

    /**
     * @return array<string, array{list<string>, string|null}>
     */
    public static function noConflictProvider(): array
    {
        return [
            'editor without conflict' => [[Episciences_Acl::ROLE_EDITOR], Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']],
            'root who declared a conflict' => [[Episciences_Acl::ROLE_ROOT], Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']],
            'administrator only, who did not answer' => [[Episciences_Acl::ROLE_ADMIN], null],
            'administrator only, who answered later' => [[Episciences_Acl::ROLE_ADMIN], Episciences_Paper_Conflict::AVAILABLE_ANSWER['later']],
        ];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('noConflictProvider')]
    public function testUserWithoutConflictOrExemptFromTheCheckIsAllowed(array $roles, ?string $answer): void
    {
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);
        $this->givenConflictAnswer($answer);
        $this->logInAs($roles);

        $this->requestAttachment();

        self::assertFalse($this->isRefused());
    }

    public function testDeclaredConflictIsIgnoredWhenTheJournalDoesNotUseTheCheck(): void
    {
        $this->givenJournal();
        $this->givenConflictAnswer(Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']);
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $this->requestAttachment();

        self::assertFalse($this->isRefused());
    }

    public function testAnonymousVisitorIsRefused(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);
        $this->givenConflictAnswer(null);

        $this->requestAttachment();

        self::assertTrue($this->isRefused());
    }

}
