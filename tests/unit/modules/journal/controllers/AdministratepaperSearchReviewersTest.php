<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Episciences\Reviewer\InvitationEligibility;
use Episciences\Reviewer\ReviewerSearch;
use Episciences_Acl;
use Episciences_Review;
use unit\modules\ControllerTestCase;
use Zend_Controller_Request_HttpTestCase;

/**
 * Search of the people an editor may invite (git #900): refused to whoever may not manage the paper; each person comes
 * with the reason why they may not be invited, and what the invitation form needs.
 *
 * The terms used do not reach the accounts directory (words shorter than ReviewerSearch::MIN_DIRECTORY_WORD_LENGTH).
 */
final class AdministratepaperSearchReviewersTest extends ControllerTestCase
{
    private const DOCID = 12;
    private const AUTHOR_UID = 99;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function given(array $settings = []): void
    {
        $this->givenJournal($settings);
        $this->db
            ->respondTo('/FROM `PAPERS`/', [[
                'DOCID' => self::DOCID, 'PAPERID' => self::DOCID, 'RVID' => RVID, 'VID' => 0, 'SID' => 0,
                'UID' => self::AUTHOR_UID, 'STATUS' => 0, 'VERSION' => 1, 'IDENTIFIER' => '1234', 'REPOID' => 1,
                'FLAG' => 'submitted', 'WHEN' => '2026-01-01 00:00:00', 'SUBMISSION_DATE' => '2026-01-01 00:00:00',
                'MODIFICATION_DATE' => '2026-01-01 00:00:00',
            ]])
            // reviewers of the journal: the author of the paper is one of them
            ->respondTo('/FROM `USER` AS `u`/', [
                ['UID' => 7, 'FIRSTNAME' => 'Ada', 'LASTNAME' => 'Lovelace', 'EMAIL' => 'ada@example.org', 'USERNAME' => 'ada', 'SCREEN_NAME' => 'Ada L.', 'LANGUEID' => 'en'],
                ['UID' => self::AUTHOR_UID, 'FIRSTNAME' => 'Auth', 'LASTNAME' => 'Or', 'EMAIL' => 'author@example.org', 'USERNAME' => 'author', 'SCREEN_NAME' => '', 'LANGUEID' => ''],
            ]);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function xhr(array $query): Zend_Controller_Request_HttpTestCase
    {
        $request = $this->getRequest($query + ['docid' => (string)self::DOCID]);
        $request->setHeader('X-Requested-With', 'XMLHttpRequest');

        return $request;
    }

    public function testRefusedOutsideAScriptRequest(): void
    {
        $this->given();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(AdministratepaperController::class, 'searchreviewers', $this->getRequest(['docid' => (string)self::DOCID, 'term' => 'ad']));

        self::assertSame(403, $result->statusCode());
        self::assertSame('[]', $result->output);
    }

    public function testRefusedToAnEditorWhoMayNotManageThePaper(): void
    {
        $this->given([Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1']);
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $result = $this->dispatch(AdministratepaperController::class, 'searchreviewers', $this->xhr(['term' => 'ad']));

        self::assertSame(403, $result->statusCode());
    }

    public function testTheReviewersOfTheJournalAreBrowsedWithTheReasonWhenTheyMayNotBeInvited(): void
    {
        $this->given();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(AdministratepaperController::class, 'searchreviewers', $this->xhr(['browse' => '1', 'order' => 'unknown']));
        $json = json_decode($result->output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(2, $json['total']);
        self::assertSame(ReviewerSearch::ORDER_NAME, $json['order']);
        [$ada, $author] = $json['people'];
        self::assertNull($ada['refusal']);
        self::assertSame(
            ['id' => 7, 'email' => 'ada@example.org', 'full_name' => 'Ada Lovelace', 'firstname' => 'Ada', 'lastname' => 'Lovelace',
             'screen_name' => 'Ada L.', 'user_name' => 'ada', 'locale' => 'en', 'invitation_type' => 1],
            $ada['user']
        );
        self::assertSame(InvitationEligibility::REASON_AUTHOR, $author['refusal']);
        self::assertSame(Episciences_Review::DEFAULT_LANG, $author['user']['locale']);
    }

    public function testASearchAnswersByGroup(): void
    {
        $this->given();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(AdministratepaperController::class, 'searchreviewers', $this->xhr(['term' => 'ad lo']));
        $json = json_decode($result->output, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $result->statusCode());
        self::assertSame([ReviewerSearch::GROUP_JOURNAL, ReviewerSearch::GROUP_ACCOUNTS, ReviewerSearch::GROUP_WITHOUT_ACCOUNT], array_keys($json['groups']));
        self::assertSame('uid:7', $json['groups'][ReviewerSearch::GROUP_JOURNAL][0]['key']);
        self::assertSame([], $json['groups'][ReviewerSearch::GROUP_ACCOUNTS]);
    }
}
