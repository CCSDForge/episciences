<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Reviewer\LightAnswer;
use Episciences\Reviewer\LightAnswerReport;
use Episciences\Reviewer\RequestType;
use Episciences_Paper;
use Episciences_Rating_Criterion;
use Episciences_Rating_Grid;
use Episciences_Rating_Report;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use unit\library\Episciences\Reviewer\RequestTypeTestDatabase;
use unit\library\Episciences\Security\SecurityLogCaptureTrait;
use unit\modules\support\ResponseSent;
use unit\modules\ControllerTestCase;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Table_Abstract;
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
        $_FILES = ['file_item_0' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]];
        $this->dir = sys_get_temp_dir() . '/light-answer-test-' . bin2hex(random_bytes(4)) . '/';
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        @unlink($this->dir . 'grid_t11.xml');
        @rmdir($this->dir . 'answer');
        @rmdir($this->dir);
    }

    private string $dir = '';

    /**
     * The grid of the request, with one criterion (option, comment, file), ready to be filled in
     */
    private function report(): Episciences_Rating_Report
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setType(Episciences_Rating_Criterion::TYPE_CRITERION);
        $criterion->setId('item_0');
        $criterion->setLabels(['fr' => 'Pertinence']);
        $criterion->setOptions([['value' => 0, 'label' => ['fr' => 'Faible']], ['value' => 1, 'label' => ['fr' => 'Forte']]]);
        $criterion->setComment_setting(true);
        $criterion->setAttachment_setting(true);
        $criterion->setVisibility(Episciences_Rating_Criterion::VISIBILITY_EDITORS);
        $grid = new Episciences_Rating_Grid();
        $grid->setFilename('grid_t11.xml');
        $grid->setCriteria([$criterion]);
        self::assertTrue($grid->save($this->dir));

        $report = new Episciences_Rating_Report();
        $report->setPath($this->dir . 'answer/');
        self::assertTrue($report->loadXML($this->dir . 'grid_t11.xml'));

        return $report;
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
        return new LightAnswer(1, self::AID, RVID, self::DOCID, 11, 42, true, new \DateTimeImmutable('2026-01-01'));
    }

    /**
     * The request type found by the answer page for a request sent with the given mode, once the option of its type
     * has been set to the other value
     */
    private function lightAnswerTypeAfterTheOptionChanged(bool $sentWithoutAccount): ?RequestType
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db = RequestTypeTestDatabase::create();
        Zend_Db_Table_Abstract::setDefaultAdapter($db);

        try {
            $db->insert('review_request_type', [
                'rvid' => RVID,
                'labels' => '{"en": "Quick opinion"}',
                'light_answer' => (int)!$sentWithoutAccount,
            ]);
            $assignment = new Episciences_User_Assignment([
                'rvid' => RVID,
                'roleid' => Episciences_User_Assignment::ROLE_REVIEWER,
                'request_type_id' => (int)$db->lastInsertId(),
                'light_answer' => $sentWithoutAccount,
            ]);

            return $this->call('lightAnswerType', $assignment);
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }

    public function testTheModeOfAnswerIsTheOneOfTheRequestWhenItWasSent(): void
    {
        // sent without an account, the option of the type unchecked since: still answered without an account
        $type = $this->lightAnswerTypeAfterTheOptionChanged(true);
        self::assertInstanceOf(RequestType::class, $type);
        self::assertFalse($type->isLightAnswer());

        // sent with an account, the option of the type checked since: still answered with an account
        self::assertNull($this->lightAnswerTypeAfterTheOptionChanged(false));
    }

    // ------------------------------------------------------------------
    // Answer associated with an account (#319, lot E)
    // ------------------------------------------------------------------

    private const TMP_UID = 500;
    private const INVITED_EMAIL = 'invited@example.org';

    /**
     * An answer given by a person without an account (temporary user TMP_UID), saved
     */
    private function tmpAnswer(?int $linkedUid = null): LightAnswer
    {
        return new LightAnswer(1, self::AID, RVID, self::DOCID, 11, self::TMP_UID, true, new \DateTimeImmutable('2026-01-01'), null, $linkedUid);
    }

    private function tmpAssignment(): Episciences_User_Assignment
    {
        return new Episciences_User_Assignment(['id' => self::AID, 'uid' => self::TMP_UID, 'tmp_user' => 1, 'rvid' => RVID]);
    }

    /**
     * The signed-in account (self::UID), with this address; the temporary invited person has INVITED_EMAIL
     */
    private function signedInWith(string $email): void
    {
        $this->logInAs([\Episciences_Acl::ROLE_MEMBER])->setEmail($email);
        $this->db->respondTo('/FROM `USER_TMP`/', [['ID' => self::TMP_UID, 'EMAIL' => self::INVITED_EMAIL, 'LASTNAME' => 'Invited']]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linkWrites(): array
    {
        return array_values(array_filter($this->db->updates(), static fn(array $update): bool => $update['table'] === 'review_light_answer'));
    }

    /**
     * @param array<string, mixed> $post
     */
    private function link(LightAnswer $answer, ?array $post = null, bool $withRequestToken = true): ?LightAnswer
    {
        $request = $post === null ? $this->getRequest() : $this->postRequest($post, $withRequestToken);

        return $this->call('linkLightAnswer', $request, $answer, $this->invitation(), $this->tmpAssignment(), $this->paper());
    }

    public function testAnAccountHoldingTheInvitedAddressIsAssociatedWithTheAnswerAtOnce(): void
    {
        $this->signedInWith('Invited@Example.org');

        $linked = $this->link($this->tmpAnswer());

        self::assertInstanceOf(LightAnswer::class, $linked);
        self::assertTrue($linked->isOfAccount(self::UID));
        self::assertCount(1, $this->linkWrites());
        self::assertSame(self::UID, $this->linkWrites()[0]['values']['linked_uid']);
        self::assertTrue($this->controller->view->answerLinkedNow);
    }

    public function testAnotherAccountIsOfferedToAssociateTheAnswer(): void
    {
        $this->signedInWith('someone.else@example.org');

        $answer = $this->link($this->tmpAnswer());

        self::assertInstanceOf(LightAnswer::class, $answer);
        self::assertFalse($answer->isOfAccount(self::UID));
        self::assertSame([], $this->linkWrites());
        self::assertTrue($this->controller->view->canLinkAnswer);
    }

    public function testAnotherAccountAssociatesTheAnswerOnRequest(): void
    {
        $this->signedInWith('someone.else@example.org');
        // the page of the invitation is loaded again: its URL is built by the router
        \Zend_Controller_Front::getInstance()->getRouter()->addDefaultRoutes();

        try {
            $this->link($this->tmpAnswer(), ['link_light_answer' => '1']);
            self::fail('the page is loaded again once the answer is associated');
        } catch (ResponseSent) {
        }

        self::assertCount(1, $this->linkWrites());
        self::assertSame(self::UID, $this->linkWrites()[0]['values']['linked_uid']);
    }

    public function testARequestWithoutTheRequestTokenAssociatesNothing(): void
    {
        $this->signedInWith('someone.else@example.org');

        $this->link($this->tmpAnswer(), ['link_light_answer' => '1'], false);

        self::assertSame([], $this->linkWrites());
        self::assertTrue($this->controller->view->canLinkAnswer);
    }

    public function testAnAnswerAlreadyOfTheAccountIsLeftAsItIs(): void
    {
        $this->signedInWith('someone.else@example.org');

        $answer = $this->link($this->tmpAnswer(self::UID), ['link_light_answer' => '1']);

        self::assertInstanceOf(LightAnswer::class, $answer);
        self::assertSame([], $this->linkWrites());
        self::assertTrue($this->controller->view->answerOfAccount);
    }

    public function testTheAccountOfAnAnswerOpensItWithoutTheLink(): void
    {
        $this->db->respondTo('/FROM `review_light_answer`/', [[
            'id' => 1, 'aid' => self::AID, 'rvid' => RVID, 'docid' => self::DOCID, 'request_type_id' => 11,
            'uid' => self::TMP_UID, 'tmp_user' => 1, 'linked_uid' => self::UID, 'created_at' => '2026-01-01 00:00:00',
        ]]);
        self::assertFalse($this->call('isAccountOfLightAnswer', self::AID), 'signed out');

        $this->logInAs([\Episciences_Acl::ROLE_MEMBER]);
        self::assertTrue($this->call('isAccountOfLightAnswer', self::AID));

        $this->logInAs([\Episciences_Acl::ROLE_MEMBER], self::UID + 1);
        self::assertFalse($this->call('isAccountOfLightAnswer', self::AID));
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

    public function testAnInvalidGridIsNotSaved(): void
    {
        $assignment = $this->createMock(Episciences_User_Assignment::class);
        $type = new RequestType(11, RVID, null, ['en' => 'Quick opinion'], true);
        $report = $this->report();

        $saved = $this->call(
            'saveLightAnswer',
            LightAnswerReport::form($report),
            $report,
            // not an option of the criterion
            ['note_item_0' => '9', LightAnswerReport::SUBMIT => '1'],
            null,
            $this->invitation(),
            $assignment,
            $this->paper(),
            $type
        );

        self::assertFalse($saved);
        self::assertTrue($this->controller->view->lightAnswerErrors);
        self::assertSame([], $this->db->inserts());
        self::assertFileDoesNotExist($this->dir . 'answer/' . LightAnswerReport::REPORT_FILE);
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
        $request = $this->postRequest(['note_item_0' => '1', LightAnswerReport::SUBMIT => '1'], true);
        $this->controller->setRequest($request);
        $type = new RequestType(11, RVID, null, ['en' => 'Quick opinion'], true);

        $this->call('lightAnswerProcess', $request, $this->invitation(token: null), $this->assignment(self::UID + 1, true), $type);

        self::assertNotEmpty($this->controller->view->errors);
        self::assertSame([], $this->db->inserts());
        self::assertSame(['token_invalid'], array_map(static fn($record): string => (string)$record->message, $this->securityLogRecords()));
    }

    public function testAPostWithAFileOutsideTheGridStoresNoFile(): void
    {
        $report = $this->report();
        $file = ['name' => 'opinion.pdf', 'type' => 'application/pdf', 'tmp_name' => '/nonexistent', 'error' => UPLOAD_ERR_OK, 'size' => 10];
        $_FILES = ['file_item_0' => $file, 'other' => $file];

        self::assertNull($this->call('uploadLightAnswerFiles', $report));
        self::assertDirectoryDoesNotExist($this->dir . 'answer/');
    }

    public function testWithoutFileTheGridIsSavedWithoutUpload(): void
    {
        self::assertSame([], $this->call('uploadLightAnswerFiles', $this->report()));
    }
}
