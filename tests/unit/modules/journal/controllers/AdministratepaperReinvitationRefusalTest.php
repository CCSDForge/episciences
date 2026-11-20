<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Episciences\Reviewer\InvitationEligibility;
use Episciences_Acl;
use Episciences_Paper;
use ReflectionMethod;
use unit\modules\ControllerTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Invitation page opened to invite again the reviewer of an expired invitation: whether they may be invited follows
 * the rules of the search (author, conflict of interest, a request in progress of every type, #319).
 */
final class AdministratepaperReinvitationRefusalTest extends ControllerTestCase
{
    private const DOCID = 31;
    private const AUTHOR_UID = 99;
    private const REVIEWER_UID = 4321;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        // a review report and a quick opinion
        $this->db->respondTo('/review_request_type/', [
            ['id' => 1, 'rvid' => RVID, 'default_type' => 1, 'labels' => '{"en":"Review report"}'],
            ['id' => 2, 'rvid' => RVID, 'default_type' => 2, 'labels' => '{"en":"Quick opinion"}'],
        ]);
    }

    /**
     * @param array<int|string, array<string, mixed>> $allJsReviewers
     */
    private function refusal(array $allJsReviewers, mixed $uid): ?string
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(self::DOCID);
        $paper->method('getPaperid')->willReturn(self::DOCID);
        $paper->method('getUid')->willReturn(self::AUTHOR_UID);

        $method = new ReflectionMethod(AdministratepaperController::class, 'reinvitationRefusal');
        $controller = new AdministratepaperController($this->getRequest(), new Zend_Controller_Response_HttpTestCase());

        /** @var string|null */
        return $method->invoke($controller, $paper, $allJsReviewers, $uid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function reviewer(int $type = 1): array
    {
        return [self::REVIEWER_UID => ['type' => $type, 'email' => 'reviewer@example.org']];
    }

    /**
     * @param list<int> $typeIds request types in progress of the reviewer on the paper
     */
    private function givenRequestsInProgress(array $typeIds): void
    {
        $this->db
            ->respondTo('/REQUEST_TYPE_ID.*ITEMID = ' . self::DOCID . '/s', array_map(
                static fn(int $typeId): array => ['UID' => self::REVIEWER_UID, 'REQUEST_TYPE_ID' => $typeId],
                $typeIds
            ))
            ->respondTo('/COALESCE\(u\.EMAIL, t\.EMAIL/', $typeIds === [] ? [] : [
                ['UID' => self::REVIEWER_UID, 'TMP_USER' => 0, 'EMAIL' => 'reviewer@example.org'],
            ]);
    }

    public function testAReviewerWhoMayStillBeAskedAnotherTypeIsInvitedAgain(): void
    {
        $this->givenRequestsInProgress([2]);

        self::assertNull($this->refusal(self::reviewer(), (string)self::REVIEWER_UID));
    }

    public function testAReviewerWithARequestOfEveryTypeIsAlreadyInvited(): void
    {
        $this->givenRequestsInProgress([1, 2]);

        self::assertSame(InvitationEligibility::REASON_ALREADY_INVITED, $this->refusal(self::reviewer(), (string)self::REVIEWER_UID));
    }

    public function testAReviewerWhoDeclaredAConflictOfInterestMayNotBeInvitedAgain(): void
    {
        $this->givenRequestsInProgress([]);
        $this->db->respondTo('/paper_conflicts/i', [['by' => self::REVIEWER_UID]]);

        self::assertSame(InvitationEligibility::REASON_CONFLICT, $this->refusal(self::reviewer(), (string)self::REVIEWER_UID));
    }

    public function testNothingToCheckWithoutAReviewerToInviteAgain(): void
    {
        self::assertNull($this->refusal(self::reviewer(), null));
        self::assertNull($this->refusal(self::reviewer(), '777'));
    }
}
