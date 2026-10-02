<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;

class RemindersTest extends TestCase
{
    public function testDeadlineInterval(): void
    {

        $deadlineDateTime = date_create('2024-09-14 00:00:00');
        $current1 = date_create('2024-09-04 01:23:03');
        $current2 = date_create('2024-09-04 01:23:03');

        $current1->setTime(0, 0);

        $interval1 = $current1->diff($deadlineDateTime, true)->format('%a'); // in days
        $interval2 = $current2->diff($deadlineDateTime, true)->format('%a'); // in days

        self::assertEquals(10, $interval1);
        self::assertEquals(9, $interval2);

    }

    public function testRemindersScriptUsesMailDisplayCodeForReviewCodeTag(): void
    {
        $scriptPath = realpath(__DIR__ . '/../../../scripts/reminders.php');
        self::assertFileExists($scriptPath);

        $source = file_get_contents($scriptPath);
        self::assertStringContainsString(
            'addTag(Episciences_Mail_Tags::TAG_REVIEW_CODE, $review->getMailDisplayCode())',
            $source,
            'reminders.php must use $review->getMailDisplayCode() for TAG_REVIEW_CODE so custom mail display code is respected'
        );
        self::assertStringNotContainsString(
            'addTag(Episciences_Mail_Tags::TAG_REVIEW_CODE, $rvCode)',
            $source,
            'reminders.php must not overwrite TAG_REVIEW_CODE with raw $rvCode'
        );
    }

    // -------------------------------------------------------------------------
    // %%PERMANENT_ARTICLE_ID%% — must not regress to a per-recipient DB lookup
    // -------------------------------------------------------------------------

    /**
     * $paper is already loaded (it's how $paper->getDocid() is obtained on the
     * same line); passing $paper->getPaperid() to setDocid() lets it resolve
     * %%PERMANENT_ARTICLE_ID%% without a DB lookup. This guards against a
     * regression back to setDocid($paper->getDocid()) alone, which would
     * silently reintroduce a per-recipient query in this cron's hottest loop
     * (reminder types x reviews x recipients).
     */
    public function testRemindersScriptPassesPaperIdToSetDocid(): void
    {
        $scriptPath = realpath(__DIR__ . '/../../../scripts/reminders.php');
        $source = file_get_contents($scriptPath);

        self::assertMatchesRegularExpression(
            '/setDocid\(\s*\$paper->getDocid\(\)\s*,\s*\$paper->getPaperid\(\)\s*\)/',
            $source,
            'BUG: setDocid() must be given $paper->getPaperid() to avoid a per-recipient DB lookup '
            . 'for %%PERMANENT_ARTICLE_ID%% in the reminders cron'
        );
    }

    /**
     * The per-recipient $tags array (built by Episciences_Mail_Reminder::loadRecipients())
     * is merged into $mail *after* setDocid(), and unconditionally overwrites any tag
     * it also defines — including %%PERMANENT_ARTICLE_ID%% when present. Losing this
     * ordering would make setDocid()'s resolution the final word instead of the
     * per-recipient value, which is what every reminder type is actually built to supply.
     */
    public function testRemindersScriptMergesRecipientTagsAfterSetDocid(): void
    {
        $scriptPath = realpath(__DIR__ . '/../../../scripts/reminders.php');
        $source = file_get_contents($scriptPath);

        $setDocidPos = strpos($source, 'setDocid(');
        $tagsMergePos = strpos($source, 'foreach ($tags as $name => $value)');

        self::assertNotFalse($setDocidPos, 'setDocid( call not found in reminders.php');
        self::assertNotFalse($tagsMergePos, 'Recipient $tags merge loop not found in reminders.php');
        self::assertGreaterThan(
            $setDocidPos,
            $tagsMergePos,
            'BUG: the per-recipient $tags merge loop must run after setDocid(), '
            . 'so recipient-supplied tags (including %%PERMANENT_ARTICLE_ID%%) take precedence'
        );
    }
}