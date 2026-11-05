<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_CommentsManager;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Review;
use Episciences_User_AssignmentsManager;
use PaperController;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Front;
use Zend_Registry;
use Zend_View_Interface;

/**
 * PaperController::isAllowedToAnswerRevisionRequest(): the "Answer this request" button is offered to the paper's
 * owner and to the secretaries (with the administrators and chief editors), for a paper of the current journal; not
 * to the editors, guest editors, copy editors and co-authors. The endpoints of the button's menu (forms and saves)
 * apply the same rule: hiding the button is not enough.
 */
final class PaperControllerRevisionRequestAnswerAccessTest extends ControllerTestCase
{
    private const DOCID = 1234;
    private const PCID = 5678;
    private const OWNER_UID = 1111;

    protected function setUp(): void
    {
        self::loadController('journal', 'PaperController');
        // The comment lists and the assignments are cached for the process: drop those of the previous tests
        Episciences_CommentsManager::getCachePool()->clear();
        Episciences_User_AssignmentsManager::getCachePool()->clear();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedStaffRoles(): iterable
    {
        yield 'secretary' => [Episciences_Acl::ROLE_SECRETARY];
        yield 'administrator' => [Episciences_Acl::ROLE_ADMIN];
        yield 'chief editor' => [Episciences_Acl::ROLE_CHIEF_EDITOR];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedStaffRoles(): iterable
    {
        yield 'editor' => [Episciences_Acl::ROLE_EDITOR];
        yield 'guest editor' => [Episciences_Acl::ROLE_GUEST_EDITOR];
        yield 'copy editor' => [Episciences_Acl::ROLE_COPY_EDITOR];
    }

    /**
     * Endpoints of the "Answer this request" menu, with the request they receive.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function answerEndpoints(): iterable
    {
        yield 'answer form' => ['answerrequest', 'GET'];
        yield 'contact form' => ['contactrequest', 'GET'];
        yield 'new version form' => ['newversion', 'GET'];
        yield 'temporary version form' => ['tmpversion', 'GET'];
        yield 'answer save' => ['saveanswer', 'POST'];
        yield 'new version save' => ['savenewversion', 'POST'];
        yield 'temporary version save' => ['savetmpversion', 'POST'];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedStaffOnEndpoints(): iterable
    {
        foreach (self::answerEndpoints() as $endpoint => [$action, $method]) {
            foreach (self::refusedStaffRoles() as $role => [$roleId]) {
                yield "$role, $endpoint" => [$action, $method, $roleId];
            }
        }
    }

    /**
     * An editor, guest editor or copy editor calling the endpoint directly, without the button, is refused
     * before anything is shown or recorded.
     *
     * @dataProvider refusedStaffOnEndpoints
     */
    public function testOtherEditorialStaffIsRefusedByTheEndpoint(string $action, string $method, string $role): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, $role]);

        $result = $this->dispatch(PaperController::class, $action, $this->answerRequest($method));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
        self::assertSame([], $this->db->updates(), 'nothing is recorded');
    }

    /**
     * @dataProvider allowedStaffRoles
     */
    public function testSecretariesReachTheAnswerForm(string $role): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, $role]);

        $result = $this->dispatch(PaperController::class, 'answerrequest', $this->answerRequest('GET'));

        self::assertSame(200, $result->response->getHttpResponseCode());
    }

    public function testOwnerReachesTheAnswerForm(): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_AUTHOR], self::OWNER_UID);

        $result = $this->dispatch(PaperController::class, 'answerrequest', $this->answerRequest('GET'));

        self::assertSame(200, $result->response->getHttpResponseCode());
    }

    /**
     * @dataProvider answerEndpoints
     */
    public function testCoAuthorIsRefusedByTheEndpoint(string $action, string $method): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest();
        $this->givenAssignment(Episciences_Acl::ROLE_CO_AUTHOR);
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_AUTHOR]);

        $result = $this->dispatch(PaperController::class, $action, $this->answerRequest($method));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
        self::assertSame([], $this->db->updates(), 'nothing is recorded');
    }

    /**
     * A secretary of another journal: the paper is not one of the current journal.
     *
     * @dataProvider answerEndpoints
     */
    public function testPaperOfAnotherJournalIsRefused(string $action, string $method): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest(Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION, Episciences_CommentsManager::TYPE_REVISION_REQUEST, RVID + 1);
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(PaperController::class, $action, $this->answerRequest($method));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
    }

    /**
     * The new version save picks its rule from the type of the request: a request of another paper cannot be used
     * to get the rule of the copy-editing requests (open to the editors) on a paper waiting for a revision.
     */
    public function testCopyEditingRequestOfAnotherPaperDoesNotOpenTheNewVersionSave(): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest(
            Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION,
            Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,
            RVID,
            self::DOCID + 1
        );
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_EDITOR]);

        $result = $this->dispatch(PaperController::class, 'savenewversion', $this->answerRequest('POST'));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function saveEndpoints(): iterable
    {
        yield 'answer save' => ['saveanswer'];
        yield 'new version save' => ['savenewversion'];
    }

    /**
     * Even for a user allowed to answer the paper's requests, the answer is not attached to a request of another
     * paper.
     *
     * @dataProvider saveEndpoints
     */
    public function testRequestOfAnotherPaperIsRefused(string $action): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest(
            Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION,
            Episciences_CommentsManager::TYPE_REVISION_REQUEST,
            RVID,
            self::DOCID + 1
        );
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_AUTHOR], self::OWNER_UID);

        $result = $this->dispatch(PaperController::class, $action, $this->answerRequest('POST'));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
        self::assertSame([], $this->db->updates(), 'nothing is recorded');
    }

    /**
     * An unknown paper is refused, not an error.
     */
    public function testNewVersionSaveOfAnUnknownPaperIsRefused(): void
    {
        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(PaperController::class, 'savenewversion', $this->answerRequest('POST'));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
    }

    /**
     * @return iterable<string, array{string, string|null}> role, answer to the conflict of interest
     */
    public static function staffInConflictOfInterest(): iterable
    {
        $yes = Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes'];

        yield 'secretary, conflict declared' => [Episciences_Acl::ROLE_SECRETARY, $yes];
        // Until the secretary answers, the conflict of interest is presumed
        yield 'secretary, not answered yet' => [Episciences_Acl::ROLE_SECRETARY, null];
        yield 'chief editor, conflict declared' => [Episciences_Acl::ROLE_CHIEF_EDITOR, $yes];
        yield 'administrator, conflict declared' => [Episciences_Acl::ROLE_ADMIN, $yes];
    }

    /**
     * @return iterable<string, array{string, string, string, string|null}>
     */
    public static function staffInConflictOfInterestOnEndpoints(): iterable
    {
        foreach (self::answerEndpoints() as $endpoint => [$action, $method]) {
            foreach (self::staffInConflictOfInterest() as $case => [$role, $answer]) {
                yield "$case, $endpoint" => [$action, $method, $role, $answer];
            }
        }
    }

    /**
     * With the conflicts of interest enabled, a secretary in conflict on the paper does not see the button: the
     * endpoints refuse them too.
     *
     * @dataProvider staffInConflictOfInterestOnEndpoints
     */
    public function testSecretaryInConflictOfInterestIsRefused(string $action, string $method, string $role, ?string $answer): void
    {
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);
        $this->givenConflictAnswer($answer);
        $this->givenRevisionRequest();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, $role]);

        $result = $this->dispatch(PaperController::class, $action, $this->answerRequest($method));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
        self::assertSame([], $this->db->updates(), 'nothing is recorded');
    }

    /**
     * Once they answered there is no conflict, the secretary answers the request.
     */
    public function testSecretaryWithoutConflictOfInterestIsAllowed(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);
        $this->givenConflictAnswer(Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']);
        $this->givenRevisionRequest();
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(PaperController::class, 'answerrequest', $this->answerRequest('GET'));

        self::assertSame(200, $result->response->getHttpResponseCode());
    }

    /**
     * The rule of the copy-editing requests is limited to the papers of the current journal too.
     */
    public function testCopyEditingFinalVersionOfAPaperOfAnotherJournalIsRefused(): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest(
            Episciences_Paper::STATUS_CE_REVIEW_FORMATTING_DEPOSED,
            Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,
            RVID + 1
        );
        $this->logInAs([Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_AUTHOR], self::OWNER_UID);

        $result = $this->dispatch(PaperController::class, 'savenewversion', $this->answerRequest('POST'));

        self::assertSame(403, $result->response->getHttpResponseCode());
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
    }

    /**
     * The answer UID gave to the conflict of interest on the paper (none when $answer is null).
     */
    private function givenConflictAnswer(?string $answer): void
    {
        $this->db->respondTo('/FROM `' . preg_quote(T_PAPER_CONFLICTS, '/') . '`/', $answer === null ? [] : [[
            'cid' => 1,
            'paper_id' => self::DOCID,
            'by' => self::UID,
            'answer' => $answer,
            'message' => null,
            'date' => '2026-01-01 00:00:00',
            'SCREEN_NAME' => 'Staff',
        ]]);
    }

    /**
     * @return iterable<string, array{list<string>, int, string|null, bool}> roles, uid, role assigned on the paper, allowed
     */
    public static function copyEditingFinalVersionAuthors(): iterable
    {
        $member = Episciences_Acl::ROLE_MEMBER;

        yield 'owner' => [[$member, Episciences_Acl::ROLE_AUTHOR], self::OWNER_UID, null, true];
        yield 'co-author' => [[$member, Episciences_Acl::ROLE_AUTHOR], self::UID, Episciences_Acl::ROLE_CO_AUTHOR, true];
        yield 'secretary' => [[$member, Episciences_Acl::ROLE_SECRETARY], self::UID, null, true];
        yield 'editor' => [[$member, Episciences_Acl::ROLE_EDITOR], self::UID, null, true];
        yield 'guest editor' => [[$member, Episciences_Acl::ROLE_GUEST_EDITOR], self::UID, null, true];
        yield 'any other member' => [[$member], self::UID, null, false];
    }

    /**
     * The new version save also answers the copy-editing requests for the final version, which are not part of the
     * "Answer this request" menu: they keep the rule of the new version save (the paper's contributors and the users
     * allowed to manage papers).
     *
     * @dataProvider copyEditingFinalVersionAuthors
     * @param list<string> $roles
     */
    public function testCopyEditingFinalVersionKeepsTheRuleOfTheNewVersionSave(array $roles, int $uid, ?string $assignedRole, bool $allowed): void
    {
        $this->givenJournal();
        $this->givenRevisionRequest(Episciences_Paper::STATUS_CE_REVIEW_FORMATTING_DEPOSED, Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST);
        if ($assignedRole !== null) {
            $this->givenAssignment($assignedRole);
        }
        $this->logInAs($roles, $uid);

        // Without the request token: an allowed user is sent back to the paper, before anything is recorded
        $result = $this->dispatch(PaperController::class, 'savenewversion', $this->answerRequest('POST'));

        self::assertSame($allowed, $result->response->getHttpResponseCode() !== 403);
        self::assertSame([], $this->db->inserts(), 'nothing is recorded');
    }

    /**
     * The flag viewAction() hands to the "Answer this request" button, for each kind of visitor of a paper waiting
     * for a revision. The editorial staff is assigned to the paper: they see its revision requests.
     *
     * @return iterable<string, array{list<string>, int, string|null, bool}> roles, uid, role assigned on the paper, expected
     */
    public static function paperPageVisitors(): iterable
    {
        $member = Episciences_Acl::ROLE_MEMBER;

        yield 'owner' => [[$member, Episciences_Acl::ROLE_AUTHOR], self::OWNER_UID, null, true];
        yield 'co-author' => [[$member, Episciences_Acl::ROLE_AUTHOR], self::UID, Episciences_Acl::ROLE_CO_AUTHOR, false];
        yield 'secretary' => [[$member, Episciences_Acl::ROLE_SECRETARY], self::UID, null, true];
        yield 'administrator' => [[$member, Episciences_Acl::ROLE_ADMIN], self::UID, null, true];
        yield 'chief editor' => [[$member, Episciences_Acl::ROLE_CHIEF_EDITOR], self::UID, null, true];
        yield 'editor' => [[$member, Episciences_Acl::ROLE_EDITOR], self::UID, Episciences_Acl::ROLE_EDITOR, false];
        yield 'guest editor' => [[$member, Episciences_Acl::ROLE_GUEST_EDITOR], self::UID, Episciences_Acl::ROLE_EDITOR, false];
        yield 'copy editor' => [[$member, Episciences_Acl::ROLE_COPY_EDITOR], self::UID, Episciences_Acl::ROLE_COPY_EDITOR, false];
    }

    /**
     * @dataProvider paperPageVisitors
     * @param list<string> $roles
     */
    public function testPaperPageOffersTheAnswerButtonUnderTheSameRule(array $roles, int $uid, ?string $assignedRole, bool $expected): void
    {
        $this->givenPaperPage();
        $this->givenRevisionRequest();
        if ($assignedRole !== null) {
            $this->givenAssignment($assignedRole);
        }
        $this->logInAs($roles, $uid);

        $result = $this->dispatch(PaperController::class, 'view', $this->getRequest([], ['id' => self::DOCID]));

        self::assertNull($result->redirectUrl(), 'the paper page is shown');
        self::assertSame($expected, $this->paperPageView()->isAllowedToAnswerRevisionRequest);
    }

    /**
     * The journal and the route the paper page needs to build its links.
     */
    private function givenPaperPage(): void
    {
        $settings = [Episciences_Review::SETTING_REPOSITORIES => '[1]'];
        $this->givenJournal($settings);
        // The settings of the current journal, registered by the bootstrap (ControllerTestCase restores the registry)
        Zend_Registry::set('reviewSettings', $settings);

        $router = Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }
    }

    /**
     * UID is assigned to the paper in $role (the queries on the other roles find nobody).
     */
    private function givenAssignment(string $role): void
    {
        $this->db
            ->respondTo(
                '/FROM `' . preg_quote(T_ASSIGNMENTS, '/') . '`/',
                // UID first: the editors are read by fetchAssoc(), keyed by the first column
                static fn(string $sql): array => str_contains($sql, "ROLEID = '" . $role . "'") ? [[
                    'UID' => self::UID,
                    'ID' => 1,
                    'RVID' => RVID,
                    'ITEMID' => self::DOCID,
                    'ITEM' => 'paper',
                    'TMP_USER' => 0,
                    'ROLEID' => $role,
                    'STATUS' => 'active',
                    'WHEN' => '2026-01-01 00:00:00',
                    'DEADLINE' => null,
                ]] : []
            )
            ->respondTo('/FROM `' . preg_quote(T_USERS, '/') . '`/', [[
                'UID' => self::UID,
                'uuid' => '00000000-0000-4000-8000-000000004242',
                'LANGUEID' => 'en',
                'SCREEN_NAME' => 'Staff',
            ]]);
    }

    /**
     * The view the dispatched action filled (the view renderer holds it).
     */
    private function paperPageView(): Zend_View_Interface
    {
        /** @var \Zend_Controller_Action_Helper_ViewRenderer $viewRenderer */
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');

        return $viewRenderer->view;
    }

    private function answerRequest(string $method): \Zend_Controller_Request_HttpTestCase
    {
        // The forms receive the request id, the saves the paper and the request in the query string
        return $method === 'GET'
            ? $this->getRequest([], ['id' => self::PCID])
            : $this->postRequest(['comment' => 'answer'])->setQuery(['docid' => self::DOCID, 'pcid' => self::PCID]);
    }

    /**
     * A paper of $rvid owned by OWNER_UID, in $status, with the request PCID of type $type (on the paper
     * $requestDocid: DOCID by default).
     */
    private function givenRevisionRequest(
        int $status = Episciences_Paper::STATUS_WAITING_FOR_MINOR_REVISION,
        int $type = Episciences_CommentsManager::TYPE_REVISION_REQUEST,
        int $rvid = RVID,
        int $requestDocid = self::DOCID
    ): void
    {
        $this->db->respondTo('/FROM `' . preg_quote(T_PAPER_COMMENTS, '/') . '`/', [[
            'PCID' => self::PCID,
            'PARENTID' => null,
            'TYPE' => $type,
            'DOCID' => $requestDocid,
            'UID' => 2222,
            'SCREEN_NAME' => 'Requester',
            'MESSAGE' => 'Please revise',
            'FILE' => null,
            'OPTIONS' => null,
            'DEADLINE' => null,
            'WHEN' => '2026-01-01 00:00:00',
        ]]);

        $this->db->respondTo('/FROM `PAPERS`/', [[
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => $rvid,
            'VID' => 0,
            'SID' => 0,
            'UID' => self::OWNER_UID,
            'STATUS' => $status,
            'VERSION' => 1,
            'IDENTIFIER' => '42',
            'REPOID' => 1,
            'DOI' => '',
            'FLAG' => 'submitted',
            'WHEN' => '2026-01-01 00:00:00',
            'SUBMISSION_DATE' => '2026-01-01 00:00:00',
            'MODIFICATION_DATE' => '2026-01-01 00:00:00',
        ]]);
    }
}
