<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Reviewer\LightAnswer;
use Episciences\Reviewer\LightAnswerForm;
use Episciences\Reviewer\RequestType;
use Episciences_Paper;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;
use unit\modules\ControllerTestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_View;

/**
 * Answer without an account to a review request (#319): when the page can be used, and what is never saved.
 */
final class ReviewerControllerLightAnswerTest extends ControllerTestCase
{
    use SecurityLogCaptureTrait;

    private const AID = 70;
    private const DOCID = 80;

    private \ReviewerController $controller;

    protected function setUp(): void
    {
        self::loadController('journal', 'ReviewerController');

        // the registry is restored by ControllerTestCase after each test
        \Zend_Registry::set('Zend_Locale', new \Zend_Locale('fr'));
        \Zend_Registry::set('Zend_Translate', new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr']));

        $this->controller = new \ReviewerController(new Zend_Controller_Request_HttpTestCase(), new Zend_Controller_Response_HttpTestCase());
        $this->controller->view = new Zend_View();
        // a browser always posts the file field, empty when no file is chosen
        $_FILES = [LightAnswerForm::FILE => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]];
    }

    protected function tearDown(): void
    {
        $_FILES = [];
    }

    private function call(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionMethod(\ReviewerController::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->controller, ...$arguments);
    }

    /**
     * @return MockObject&Episciences_User_Invitation
     */
    private function invitation(bool $cancelled = false, bool $answered = false, bool $expired = false, ?string $token = 'row-secret')
    {
        $invitation = $this->createMock(Episciences_User_Invitation::class);
        $invitation->method('getToken')->willReturn($token);
        $invitation->method('getId')->willReturn(7);
        $invitation->method('getAid')->willReturn(self::AID);
        $invitation->method('isCancelled')->willReturn($cancelled);
        $invitation->method('isAnswered')->willReturn($answered);
        $invitation->method('hasExpired')->willReturn($expired);

        return $invitation;
    }

    /**
     * @return MockObject&Episciences_Paper
     */
    private function paper(bool $accepted = false)
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('getRvid')->willReturn(RVID);
        $paper->method('isAccepted')->willReturn($accepted);

        return $paper;
    }

    private function answer(): LightAnswer
    {
        return new LightAnswer(1, self::AID, RVID, self::DOCID, 11, 42, true, 'Interesting', null, new \DateTimeImmutable('2026-01-01'));
    }

    public function testTheFirstAnswerNeedsAnOpenInvitationOnAPaperStillToReview(): void
    {
        self::assertNull($this->call('lightAnswerError', $this->invitation(), null, $this->paper()));
        self::assertNotNull($this->call('lightAnswerError', $this->invitation(cancelled: true), null, $this->paper()));
        // declined
        self::assertNotNull($this->call('lightAnswerError', $this->invitation(answered: true), null, $this->paper()));
        self::assertNotNull($this->call('lightAnswerError', $this->invitation(expired: true), null, $this->paper()));
        self::assertNotNull($this->call('lightAnswerError', $this->invitation(), null, $this->paper(accepted: true)));
    }

    public function testAGivenAnswerStaysReachableUntilTheInvitationIsCancelled(): void
    {
        // accepted by the answer itself, and expired since: the answer can still be read or edited
        self::assertNull($this->call('lightAnswerError', $this->invitation(answered: true, expired: true), $this->answer(), $this->paper(accepted: true)));
        self::assertNotNull($this->call('lightAnswerError', $this->invitation(cancelled: true), $this->answer(), $this->paper()));
    }

    public function testAnAnswerWithoutMessageNorFileIsNotSaved(): void
    {
        $assignment = $this->createMock(Episciences_User_Assignment::class);
        $type = new RequestType(11, RVID, null, ['en' => 'Quick opinion'], true);

        $saved = $this->call(
            'saveLightAnswer',
            LightAnswerForm::create(),
            [LightAnswerForm::MESSAGE => '  <b></b> ', LightAnswerForm::SUBMIT => '1'],
            null,
            $this->invitation(),
            $assignment,
            $this->paper(),
            $type
        );

        self::assertFalse($saved);
        self::assertTrue($this->controller->view->lightAnswerMissing);
        self::assertSame([], $this->db->inserts());
    }

    /**
     * @return MockObject&Episciences_User_Assignment
     */
    private function assignment(int $uid, bool $tmpUser)
    {
        $assignment = $this->createMock(Episciences_User_Assignment::class);
        $assignment->method('getUid')->willReturn($uid);
        $assignment->method('isTmp_user')->willReturn($tmpUser);

        return $assignment;
    }

    public function testAnInvitationWithoutLinkTokenIsNotAnsweredWithoutAnAccount(): void
    {
        $request = $this->postRequest([LightAnswerForm::MESSAGE => 'Opinion', LightAnswerForm::SUBMIT => '1'], true);
        $this->controller->setRequest($request);
        $type = new RequestType(11, RVID, null, ['en' => 'Quick opinion'], true);

        $this->call('lightAnswerProcess', $request, $this->invitation(token: null), $this->assignment(self::UID + 1, true), $type);

        self::assertNotEmpty($this->controller->view->errors);
        self::assertSame([], $this->db->inserts());
        self::assertSame(['token_invalid'], array_map(static fn($record): string => (string)$record->message, $this->securityLogRecords()));
    }

    public function testAPostWithAnotherFileFieldStoresNoFile(): void
    {
        $file = ['name' => 'opinion.pdf', 'type' => 'application/pdf', 'tmp_name' => '/nonexistent', 'error' => UPLOAD_ERR_OK, 'size' => 10];
        $_FILES = [LightAnswerForm::FILE => $file, 'other' => $file];

        self::assertNull($this->call('uploadLightAnswerFile', sys_get_temp_dir() . '/light-answer-test/'));
        self::assertDirectoryDoesNotExist(sys_get_temp_dir() . '/light-answer-test/');
    }
}
