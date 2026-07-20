<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the "link invitation to account" logic in
 * ReviewerController (invitationAction / checkAndProcessLinkedInvitation /
 * processLinkDecision / linkToLoggedAccount), introduced/refactored in PR #1109.
 *
 * Strategy: source-code pattern analysis (same approach as PaperControllerTest
 * and PaperDefaultControllerTest). ZF1 module controllers require the full
 * request/response/view stack to instantiate and cannot be unit-tested by
 * direct invocation, so the private methods below are exercised through their
 * source text instead.
 *
 * @covers ReviewerController
 */
final class ReviewerControllerLinkedInvitationTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = (string) file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/ReviewerController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName . '(');
        self::assertNotFalse($start, "Method $methodName not found in ReviewerController");

        $end = strpos($this->source, "\n    public function ", (int) $start + 1);
        $end2 = strpos($this->source, "\n    protected function ", (int) $start + 1);
        $end3 = strpos($this->source, "\n    private function ", (int) $start + 1);
        $candidates = array_filter([$end, $end2, $end3], static fn($v) => $v !== false);
        $stop = $candidates ? min($candidates) : strlen($this->source);

        return substr($this->source, (int) $start, $stop - (int) $start);
    }

    // -------------------------------------------------------------------------
    // checkAndProcessLinkedInvitation() — $doRating must be false on every path
    // -------------------------------------------------------------------------

    /**
     * Regression test for a bug flagged in PR #1109 review and fixed by commit
     * "fix: condition logic.": checkAndProcessLinkedInvitation() is only called
     * when the logged-in user is NOT the assignment's uid. $doRating is `true`
     * on entry (set by invitationAction()) and must be forced to `false` before
     * ANY return from this method — otherwise a mismatched user hitting an
     * expired / already-answered / cancelled invitation URL would keep
     * $doRating = true, skip the "cette invitation ne vous est pas destinée"
     * gate in invitationAction(), and get the full paper detail view rendered
     * for an invitation that is not theirs.
     *
     * `$doRating = false;` now runs before the hasExpired()/isAnswered()/
     * isCancelled() guard, so this test passes; keep it to guard against a
     * regression.
     */
    public function testDoRatingIsSetFalseBeforeTheFirstReturn(): void
    {
        $method = $this->extractMethod('checkAndProcessLinkedInvitation');

        $firstReturnPos = strpos($method, 'return');
        self::assertNotFalse($firstReturnPos, 'checkAndProcessLinkedInvitation() must contain a return statement');

        $doRatingFalsePos = strpos($method, '$doRating = false;');
        self::assertNotFalse($doRatingFalsePos, 'checkAndProcessLinkedInvitation() must set $doRating = false');

        self::assertLessThan(
            $firstReturnPos,
            $doRatingFalsePos,
            'checkAndProcessLinkedInvitation() must set $doRating = false before its first return statement: '
            . 'the hasExpired()/isAnswered()/isCancelled() early return currently skips this assignment, '
            . 'leaving $doRating at the caller\'s initial value of true for a user the invitation was not sent to.'
        );
    }

    public function testExpiredAnsweredOrCancelledInvitationShortCircuits(): void
    {
        $method = $this->extractMethod('checkAndProcessLinkedInvitation');

        self::assertStringContainsString('hasExpired()', $method);
        self::assertStringContainsString('isAnswered()', $method);
        self::assertStringContainsString('isCancelled()', $method);
    }

    public function testResolveFromUserCallIsGuardedAgainstDbStatementException(): void
    {
        $method = $this->extractMethod('checkAndProcessLinkedInvitation');

        $tryPos = strpos($method, 'try {');
        $resolvePos = strpos($method, 'resolveFromUser()');
        $catchPos = strpos($method, 'catch (Zend_Db_Statement_Exception');

        self::assertNotFalse($tryPos, 'resolveFromUser() must be called inside a try block');
        self::assertNotFalse($resolvePos);
        self::assertNotFalse($catchPos, 'checkAndProcessLinkedInvitation() must catch Zend_Db_Statement_Exception from resolveFromUser()');
        self::assertTrue(
            $tryPos < $resolvePos && $resolvePos < $catchPos,
            'try { ... resolveFromUser() ... } catch (Zend_Db_Statement_Exception must appear in that order'
        );
    }

    public function testAutomaticLinkingRequiresExactEmailMatch(): void
    {
        $method = $this->extractMethod('checkAndProcessLinkedInvitation');

        self::assertStringContainsString('$fromUser->getEmail() === Episciences_Auth::getEmail()', $method,
            'Automatic linking must require an exact match between the invitation recipient email and the logged-in user email'
        );
    }

    // -------------------------------------------------------------------------
    // invitationAction() — loadAnswer() must run before isAnswered() is checked
    // -------------------------------------------------------------------------

    public function testInvitationActionLoadsAnswerBeforeFetchingAssignment(): void
    {
        $method = $this->extractMethod('invitationAction');

        $loadAnswerPos = strpos($method, '$invitation->loadAnswer();');
        $findAssignmentPos = strpos($method, 'Episciences_User_AssignmentsManager::findById');

        self::assertNotFalse($loadAnswerPos, 'invitationAction() must call $invitation->loadAnswer()');
        self::assertNotFalse($findAssignmentPos);
        self::assertLessThan(
            $findAssignmentPos,
            $loadAnswerPos,
            'loadAnswer() must run before checkAndProcessLinkedInvitation() is reachable, '
            . 'otherwise isAnswered() always evaluates against an unloaded answer'
        );
    }

    // -------------------------------------------------------------------------
    // processLinkDecision() — session cleanup must run for both decisions
    // -------------------------------------------------------------------------

    public function testProcessLinkDecisionAlwaysClearsTheSessionMarker(): void
    {
        $method = $this->extractMethod('processLinkDecision');

        self::assertSame(
            1,
            substr_count($method, 'unset($session->linkedInvitationIds[$invitationId]);'),
            'processLinkDecision() must clear the session "pre-linked" marker exactly once, '
            . 'regardless of whether the decision was acceptToLink or declineToLink'
        );
    }

    public function testProcessLinkDecisionSendsFlashMessageOnlyOnAccept(): void
    {
        $method = $this->extractMethod('processLinkDecision');

        $ifAcceptPos = strpos($method, "\$decision === 'acceptToLink'");
        $flashMessengerPos = strpos($method, 'FlashMessenger');

        self::assertNotFalse($ifAcceptPos);
        self::assertNotFalse($flashMessengerPos);
        self::assertLessThan(
            $flashMessengerPos,
            $ifAcceptPos,
            'The success flash message must only be added inside the acceptToLink branch'
        );
    }

    // -------------------------------------------------------------------------
    // linkToLoggedAccount() — save() failures must not bubble up as fatal errors
    // -------------------------------------------------------------------------

    public function testLinkToLoggedAccountCatchesDbAdapterException(): void
    {
        $method = $this->extractMethod('linkToLoggedAccount');

        self::assertStringContainsString('try {', $method);
        self::assertStringContainsString('catch (Zend_Db_Adapter_Exception', $method,
            'linkToLoggedAccount() must catch Zend_Db_Adapter_Exception from $assignment->save()'
        );
        self::assertStringContainsString('$assignment->setFrom_uid($assignment->getUid());', $method,
            'linkToLoggedAccount() must record the original recipient uid as FROM_UID before reassigning UID'
        );
    }

    // -------------------------------------------------------------------------
    // getSession() seam — used instead of instantiating Zend_Session_Namespace directly
    // -------------------------------------------------------------------------

    public function testCheckAndProcessLinkedInvitationUsesTheGetSessionSeam(): void
    {
        $method = $this->extractMethod('checkAndProcessLinkedInvitation');

        self::assertStringContainsString('$this->getSession()', $method,
            'checkAndProcessLinkedInvitation() must go through the getSession() seam instead of '
            . 'instantiating Zend_Session_Namespace directly'
        );
    }
}
