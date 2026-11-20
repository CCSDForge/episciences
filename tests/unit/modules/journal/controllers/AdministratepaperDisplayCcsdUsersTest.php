<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Episciences_Acl;
use unit\modules\ControllerTestCase;

/**
 * Invitation page, new reviewer: the accounts found with the address or the name typed (displayccsdusers) show what
 * each of them is already asked in the journal, as the known reviewers table does (git #320).
 */
final class AdministratepaperDisplayCcsdUsersTest extends ControllerTestCase
{
    private const DOCID = 12;
    private const AUTHOR_UID = 99;
    private const ACCOUNT_UID = 501;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    protected function setUp(): void
    {
        $this->givenJournal();
        $this->db
            ->respondTo('/FROM `USER_INVITATION`/', [[
                'UID' => self::ACCOUNT_UID, 'TMP_USER' => 0, 'TMP_EMAIL' => null, 'ITEMID' => 30, 'PAPER_STATUS' => 2,
                'STATUS' => 'pending', 'SENDING_DATE' => '2026-11-10 09:00:00', 'EXPIRATION_DATE' => '2099-01-01 00:00:00',
            ]])
            ->respondTo('/FROM `USER_ASSIGNMENT`/', [])
            ->respondTo('/FROM `PAPERS`/', [[
                'DOCID' => self::DOCID, 'PAPERID' => self::DOCID, 'RVID' => RVID, 'VID' => 0, 'SID' => 0,
                'UID' => self::AUTHOR_UID, 'STATUS' => 0, 'VERSION' => 1, 'IDENTIFIER' => '1234', 'REPOID' => 1,
                'FLAG' => 'submitted', 'WHEN' => '2026-01-01 00:00:00', 'SUBMISSION_DATE' => '2026-01-01 00:00:00',
                'MODIFICATION_DATE' => '2026-01-01 00:00:00',
            ]]);
    }

    private function display(): string
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $request = $this->xhrPostRequest([
            'paper_id' => (string)self::DOCID,
            'is_search_with_mail' => '1',
            'user_lang' => 'en',
            'ignore_list' => '[]',
            'post' => json_encode([
                ['UID' => self::ACCOUNT_UID, 'USERNAME' => 'jdoe', 'EMAIL' => 'jane@example.org', 'FIRSTNAME' => '<b>Jane</b>', 'LASTNAME' => 'Doe'],
                ['UID' => 502, 'USERNAME' => 'other', 'EMAIL' => 'jane@example.org', 'FIRSTNAME' => 'Other', 'LASTNAME' => 'Account'],
            ], JSON_THROW_ON_ERROR),
        ]);

        return $this->dispatch(AdministratepaperController::class, 'displayccsdusers', $request)->output;
    }

    public function testEachAccountShowsWhatItIsAlreadyAskedInTheJournal(): void
    {
        $html = $this->display();

        self::assertMatchesRegularExpression('/<tr\s+id="ccsduser_501".*?Invitations en attente : 1.*?<\/tr>/s', $html);
        self::assertMatchesRegularExpression('/<tr\s+id="ccsduser_502".*?Jamais invité.*?<\/tr>/s', $html);
    }

    public function testTheFormerCountsAreReplaced(): void
    {
        $html = $this->display();

        self::assertStringContainsString('Sollicitations dans la revue', $html);
        self::assertStringNotContainsString('Invitation(s)', $html);
        self::assertStringNotContainsString('Relecture(s)', $html);
        // no count of reviews made for any journal of the platform
        self::assertSame([], array_filter($this->db->selects(), static fn(string $sql): bool => str_contains($sql, 'COUNT(*)') && str_contains($sql, 'REVIEWER_REPORT')));
    }

    public function testTheCellsAreEscaped(): void
    {
        $html = $this->display();

        self::assertStringContainsString('<td>&lt;b&gt;Jane&lt;/b&gt;</td>', $html);
        self::assertStringNotContainsString('<td><b>Jane</b></td>', $html);
    }
}
