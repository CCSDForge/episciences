<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Tools;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PaperDefaultController (extractModalRecipientEmails() is covered by
 * PaperDefaultControllerRecipientEmailsTest).
 *
 * Strategy: source-code pattern analysis (same approach as other controller tests).
 * ZF1 module controllers are not Composer-autoloaded and require the full request stack
 * to instantiate, so we analyse the source and test the pure static helpers it calls.
 *
 * Bugs discovered during review are documented inline.
 * Intentionally failing tests document confirmed bugs (red → green once fixed).
 *
 */
final class PaperDefaultControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName);
        self::assertNotFalse($start, "Method $methodName not found in PaperDefaultController");

        $end = strpos($this->source, 'function ', (int) $start + strlen('function ' . $methodName));
        return $end === false
            ? substr($this->source, (int) $start)
            : substr($this->source, (int) $start, (int) $end - (int) $start);
    }

    // -----------------------------------------------------------------------
    // PR #1040 — answerRevisionNotifyManager()
    // -----------------------------------------------------------------------

    /**
     * Regression: $paper1 was removed from the signature (BUG 1 fix).
     * The parameter must not reappear.
     */
    public function testAnswerRevisionNotifyManagerHasNoPaper1Parameter(): void
    {
        $method = $this->extractMethod('answerRevisionNotifyManager');

        self::assertStringNotContainsString(
            '$paper1',
            $method,
            'answerRevisionNotifyManager() must not declare $paper1 — it was removed in PR #1040'
        );
    }

    /**
     * Regression: TAG_PAPER_SUBMISSION_DATE must use getSubmission_date() (the
     * initial submission date), not getWhen() of the old paper object (BUG 2 fix).
     */
    public function testAnswerRevisionNotifyManagerUsesSubmissionDate(): void
    {
        $method = $this->extractMethod('answerRevisionNotifyManager');

        self::assertStringContainsString(
            'getSubmission_date()',
            $method,
            'TAG_PAPER_SUBMISSION_DATE must use getSubmission_date() to return the initial submission date'
        );

        self::assertStringNotContainsString(
            '$paper1->getWhen()',
            $method,
            'TAG_PAPER_SUBMISSION_DATE must not use $paper1->getWhen() — $paper1 was removed'
        );
    }

    /**
     * PR #1040 adds handling for TYPE_REVISION_ANSWER_COMMENT to trigger the
     * paper_revision_answer template. The elseif branch must be present.
     */
    public function testAnswerRevisionNotifyManagerHandlesRevisionAnswerCommentType(): void
    {
        $method = $this->extractMethod('answerRevisionNotifyManager');

        self::assertStringContainsString(
            'TYPE_REVISION_ANSWER_COMMENT',
            $method,
            'answerRevisionNotifyManager() must handle TYPE_REVISION_ANSWER_COMMENT to select the correct template'
        );

        self::assertStringContainsString(
            'TYPE_PAPER_REVISION_ANSWER',
            $method,
            'answerRevisionNotifyManager() must assign TYPE_PAPER_REVISION_ANSWER for comment-only answers'
        );
    }

    /**
     * When no template is matched (unknown comment type), the method must log
     * and return false rather than sending a mail with an empty template key.
     */
    public function testAnswerRevisionNotifyManagerGuardsEmptyTemplateKey(): void
    {
        $method = $this->extractMethod('answerRevisionNotifyManager');

        self::assertStringContainsString(
            "\$templateKey === ''",
            $method,
            'answerRevisionNotifyManager() must guard against an empty template key'
        );

        // The guard must short-circuit the method — return false, not continue.
        $guardPos   = strpos($method, "\$templateKey === ''");
        $returnPos  = strpos($method, 'return false;', (int) $guardPos);

        self::assertNotFalse($returnPos,
            'answerRevisionNotifyManager() must return false when no template is found'
        );
    }

    // -----------------------------------------------------------------------
    // Episciences_Tools::postMailValidation() — behaviour contract
    // -----------------------------------------------------------------------

    /**
     * postMailValidation() is not a true RFC 5322 validator — it strips angle
     * brackets and returns the last whitespace-separated token. A bare string
     * with no angle brackets passes through unchanged, even if it is not a
     * valid email address.
     *
     * This is existing (pre-PR) behaviour; the test documents the contract so
     * callers know they must not rely on it for format validation.
     */
    public function testPostMailValidationStripsAngleBrackets(): void
    {
        $result = Episciences_Tools::postMailValidation('Alice Smith <alice@example.com>');
        self::assertSame('alice@example.com', $result['email']);
        self::assertSame('Alice Smith', $result['name']);
    }

    public function testPostMailValidationPassesBareStringUnchanged(): void
    {
        $result = Episciences_Tools::postMailValidation('alice@example.com');
        self::assertSame('alice@example.com', $result['email']);
        self::assertNull($result['name']);
    }

    public function testPostMailValidationDoesNotValidateEmailFormat(): void
    {
        // Not an email — but postMailValidation() still returns it as-is
        $result = Episciences_Tools::postMailValidation('not-an-email');
        self::assertSame('not-an-email', $result['email'],
            'postMailValidation() performs no format check; callers must not treat it as a validator'
        );
    }

    // -----------------------------------------------------------------------
    // Notification failures must not be hidden (editor -> author messages)
    // -----------------------------------------------------------------------

    /**
     * Like extractMethod(), but stops at the next method declaration instead of the
     * next "function " token, so closures inside the method body are kept.
     */
    private function extractMethodBody(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in PaperDefaultController");

        $matched = preg_match(
            '/\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s/',
            $this->source,
            $m,
            PREG_OFFSET_CAPTURE,
            (int) $start + 1
        );

        return $matched === 1
            ? substr($this->source, (int) $start, $m[0][1] - (int) $start)
            : substr($this->source, (int) $start);
    }

    /**
     * A plain Exception raised while notifying another editor used to escape the
     * narrow Zend-only catch and abort the method before the author block.
     */
    public function testEditorsLoopCatchesAnyException(): void
    {
        $method = $this->extractMethodBody('newCommentNotifyManager');

        self::assertStringNotContainsString(
            'catch (Zend_Db_Adapter_Exception|Zend_Mail_Exception|Zend_Exception|Zend_Session_Exception $e)',
            $method,
            'The editors loop must not let a plain Exception skip the author notification'
        );
    }

    /**
     * The author notification must count as sent only when sendMailFromReview() succeeds,
     * and the expected count must not depend on that outcome.
     */
    public function testAuthorNotificationUsesSendResult(): void
    {
        $method = $this->extractMethodBody('newCommentNotifyManager');

        self::assertStringContainsString('$authorNotificationSent = Episciences_Mail_Send::sendMailFromReview(', $method);
        self::assertStringNotContainsString('$authorNotificationSent = true;', $method);
        self::assertStringContainsString('$expectedNotifications = count($recipients) + 1;', $method);
    }

    /**
     * An unresolved author or an exception before the send must not be reported as a success:
     * the author expectation must not depend on the author being found.
     */
    public function testAuthorAlwaysExpectedForEditorToAuthorMessages(): void
    {
        $method = $this->extractMethodBody('newCommentNotifyManager');

        self::assertStringNotContainsString('$authorNotificationExpected', $method);
    }

    /**
     * Co-author notifications must count only successful sends, and a former co-author
     * skipped at send time must no longer be expected.
     */
    public function testCoAuthorNotificationsUseSendResultAndFilteredCount(): void
    {
        $method = $this->extractMethodBody('newCommentNotifyManager');

        self::assertStringContainsString('$coAuthorNotificationSent = Episciences_Mail_Send::sendMailFromReview(', $method);
        self::assertStringContainsString('if (!$coAuthorNotificationSent)', $method);

        self::assertMatchesRegularExpression(
            '/if \(!\$paper->isCoAuthorByUid\(\$coAuthorUid\)\) \{[^}]*--\$totalCoAuthorsToNotify;[^}]*continue;/s',
            $method,
            'A skipped former co-author must be removed from the expected count'
        );
    }

    /**
     * sendMailFromModal() must not record CODE_MAIL_SENT when writeMail() failed.
     */
    public function testSendMailFromModalChecksWriteMailResult(): void
    {
        $method = $this->extractMethodBody('sendMailFromModal');

        $guardPos = strpos($method, 'if (!$mail->writeMail())');
        $logPos = strpos($method, 'CODE_MAIL_SENT');

        self::assertNotFalse($guardPos, 'writeMail() result must be checked');
        self::assertNotFalse($logPos);
        self::assertLessThan($logPos, $guardPos, 'The writeMail() check must come before logging CODE_MAIL_SENT');
    }
}
