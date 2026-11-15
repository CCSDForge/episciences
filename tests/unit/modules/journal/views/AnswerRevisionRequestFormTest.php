<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_Paper;
use Episciences_Review;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Front;
use Zend_Controller_Request_HttpTestCase;
use Zend_View;

/**
 * Answers offered to a revision request (partials/answer_revision_request_form.phtml): they are only offered to the
 * users allowed to answer (see PaperController::isAllowedToAnswerRevisionRequest()), and the temporary version only to
 * the users allowed to record it (see PaperController::isAllowedToWriteTemporaryVersion()).
 */
final class AnswerRevisionRequestFormTest extends TestCase
{
    private const PCID = 33;

    private ?\Zend_Controller_Request_Abstract $previousRequest = null;

    protected function setUp(): void
    {
        $front = Zend_Controller_Front::getInstance();
        $this->previousRequest = $front->getRequest();

        // The partial only offers the answers on the paper page
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setControllerName('paper')->setActionName('view');
        $front->setRequest($request);
    }

    protected function tearDown(): void
    {
        $front = Zend_Controller_Front::getInstance();

        if ($this->previousRequest) {
            $front->setRequest($this->previousRequest);
        } else {
            $front->resetInstance();
        }
    }

    private function render(?bool $isAllowedToWriteTemporaryVersion, bool $isTmpVersionEnabled = true, ?bool $isAllowedToAnswer = true): string
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isRevisionRequested')->willReturn(true);
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION);
        $paper->method('isOwner')->willReturn(true);

        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnCallback(
            static fn(string $setting) => $setting === Episciences_Review::SETTING_CAN_ANSWER_WITH_TMP_VERSION
                ? ($isTmpVersionEnabled ? '1' : '0')
                : null
        );

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->paper = $paper;
        $view->review = $review;
        $view->current_demand = ['PCID' => self::PCID];

        if ($isAllowedToAnswer !== null) {
            $view->isAllowedToAnswerRevisionRequest = $isAllowedToAnswer;
        }

        if ($isAllowedToWriteTemporaryVersion !== null) {
            $view->isAllowedToWriteTemporaryVersion = $isAllowedToWriteTemporaryVersion;
        }

        return $view->render('partials/answer_revision_request_form.phtml');
    }

    public function testTemporaryVersionIsOfferedToTheUsersAllowedToRecordIt(): void
    {
        $html = $this->render(true);

        self::assertStringContainsString('href="/paper/tmpversion?id=' . self::PCID . '"', $html);
        self::assertStringContainsString('href="/paper/answerrequest?id=' . self::PCID . '"', $html);
    }

    public function testTemporaryVersionIsNotOfferedToTheOtherUsers(): void
    {
        $html = $this->render(false);

        self::assertStringNotContainsString('/paper/tmpversion', $html);
        // The other answers stay offered
        self::assertStringContainsString('href="/paper/answerrequest?id=' . self::PCID . '"', $html);
    }

    public function testTemporaryVersionIsNotOfferedWhenTheRuleIsNotGiven(): void
    {
        self::assertStringNotContainsString('/paper/tmpversion', $this->render(null));
    }

    public function testTemporaryVersionIsNotOfferedWhenTheJournalDisablesIt(): void
    {
        self::assertStringNotContainsString('/paper/tmpversion', $this->render(true, false));
    }

    /**
     * Rendered as paper/view.phtml does, through the partial helper: the partial only sees the variables of its model.
     */
    private function renderThroughPartialHelper(bool $doNotDisplayContactChoice): string
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isRevisionRequested')->willReturn(true);
        $paper->method('getStatus')->willReturn(Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION);
        $paper->method('isOwner')->willReturn(true);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');

        return $view->partial('partials/answer_revision_request_form.phtml', [
            'paper' => $paper,
            'review' => $this->createMock(Episciences_Review::class),
            'current_demand' => ['PCID' => self::PCID],
            'doNotDisplayContactChoice' => $doNotDisplayContactChoice,
            'isAllowedToAnswerRevisionRequest' => true,
        ]);
    }

    public function testContactWithoutNewVersionIsOfferedToTheOwner(): void
    {
        self::assertStringContainsString(
            'href="/paper/contactrequest?id=' . self::PCID . '"',
            $this->renderThroughPartialHelper(false)
        );
    }

    public function testContactWithoutNewVersionIsNotOfferedWhenTheFinalVersionIsAwaited(): void
    {
        $html = $this->renderThroughPartialHelper(true);

        self::assertStringNotContainsString('/paper/contactrequest', $html);
        // The other answers stay offered
        self::assertStringContainsString('href="/paper/answerrequest?id=' . self::PCID . '"', $html);
    }

    public function testAnswersAreNotOfferedToTheUsersNotAllowedToAnswer(): void
    {
        // e.g. an editor or a copy editor assigned to the paper
        $html = $this->render(true, true, false);

        self::assertStringNotContainsString('id="answer-request"', $html);
        self::assertStringNotContainsString('/paper/newversion', $html);
        self::assertStringNotContainsString('/paper/answerrequest', $html);
        self::assertStringNotContainsString('/paper/tmpversion', $html);
    }

    public function testAnswersAreNotOfferedWhenTheRuleIsNotGiven(): void
    {
        self::assertStringNotContainsString('id="answer-request"', $this->render(true, true, null));
    }

    public function testAnswersAreOfferedToTheUsersAllowedToAnswer(): void
    {
        $html = $this->render(false);

        self::assertStringContainsString('id="answer-request"', $html);
        self::assertStringContainsString('href="/paper/newversion?id=' . self::PCID . '"', $html);
    }
}
