<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratepaperController;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Review;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use unit\modules\ControllerTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Invitation page (git #320): the papers of a reviewer's workload are only named to who may manage them, with the
 * rule of the paper management page; the other papers are only counted.
 */
final class AdministratepaperWorkloadPapersTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratepaperController');
    }

    /**
     * @param array<string, mixed> $settings
     * @return MockObject&Episciences_Review
     */
    private function journal(array $settings = []): Episciences_Review
    {
        $review = $this->createMock(Episciences_Review::class);
        $review->method('getSetting')->willReturnCallback(static fn(string $name) => $settings[$name] ?? null);

        return $review;
    }

    /**
     * @param array<string, mixed> $state
     * @return MockObject&Episciences_Paper
     */
    private function paper(int $docId, array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn($docId);
        $paper->method('getRvid')->willReturn($state['rvid'] ?? RVID);
        $paper->method('isDeleted')->willReturn(false);
        $paper->method('isRemoved')->willReturn(false);
        $paper->method('isOwner')->willReturn($state['owner'] ?? false);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturn($state['assigned'] ?? false);
        $paper->method('checkConflictResponse')->willReturn($state['conflict'] ?? Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']);

        return $paper;
    }

    /**
     * @param Episciences_Paper[] $papers
     * @param array<string, mixed> $settings
     * @return list<int>
     */
    private function manageable(array $papers, array $settings = []): array
    {
        $method = new ReflectionMethod(AdministratepaperController::class, 'manageableDocIds');
        $controller = new AdministratepaperController($this->getRequest(), new Zend_Controller_Response_HttpTestCase());

        /** @var list<int> */
        return $method->invoke($controller, $papers, $this->journal($settings));
    }

    public function testASecretarySeesThePapersOfTheJournal(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertSame([1, 2], $this->manageable([$this->paper(1), $this->paper(2)]));
    }

    public function testAPaperWithADeclaredConflictOfInterestIsNotNamed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $manageable = $this->manageable(
            [$this->paper(1), $this->paper(2, ['conflict' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']])],
            [Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']
        );

        self::assertSame([1], $manageable);
    }

    public function testAnEditorOnlyManagingTheirPapersOnlySeesThem(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        $manageable = $this->manageable(
            [$this->paper(1, ['assigned' => true]), $this->paper(2)],
            [Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1']
        );

        self::assertSame([1], $manageable);
    }

    public function testAGuestEditorOnlySeesTheirPapers(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_GUEST_EDITOR]);

        self::assertSame([3], $this->manageable([$this->paper(3, ['assigned' => true]), $this->paper(4)]));
    }

    public function testOwnPapersAndPapersOfAnotherJournalAreNotNamed(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertSame([], $this->manageable([$this->paper(1, ['owner' => true]), $this->paper(2, ['rvid' => RVID + 1])]));
    }
}
