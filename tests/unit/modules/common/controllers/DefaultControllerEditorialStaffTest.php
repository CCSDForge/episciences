<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;

/**
 * DefaultController::isEditorialStaff(): who handles a paper for the journal, shared by the paper page,
 * its restricted access check and the document files. The logged user is a real identity with roles,
 * the paper assignment is stubbed.
 */
final class DefaultControllerEditorialStaffTest extends TestCase
{
    private const UID = 4242;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
    }

    /**
     * @param string[] $roles
     */
    private function logInAs(array $roles): void
    {
        $user = new Episciences_User(['UID' => self::UID]);
        $user->setRoles([RVID => $roles]);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @param array<string, mixed> $journalSettings
     */
    private function isEditorialStaff(array $journalSettings, bool $isAssigned = false): bool
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isAssignedEditorOrCopyEditor')->with(self::UID)->willReturn($isAssigned);

        $method = new ReflectionMethod(\DefaultController::class, 'isEditorialStaff');
        $method->setAccessible(true);

        return $method->invoke(null, $paper, $journalSettings);
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>, bool}>
     */
    public static function encapsulationProvider(): array
    {
        $editors = Episciences_Review::SETTING_ENCAPSULATE_EDITORS;
        $copyEditors = Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS;

        return [
            'editor, journal not restricting editors' => [Episciences_Acl::ROLE_EDITOR, $editors, [$editors => '0'], true],
            'editor, journal restricting editors' => [Episciences_Acl::ROLE_EDITOR, $editors, [$editors => '1'], false],
            'editor, setting never saved' => [Episciences_Acl::ROLE_EDITOR, $editors, [], false],
            'copy editor, journal not restricting copy editors' => [Episciences_Acl::ROLE_COPY_EDITOR, $copyEditors, [$copyEditors => '0'], true],
            'copy editor, journal restricting copy editors' => [Episciences_Acl::ROLE_COPY_EDITOR, $copyEditors, [$copyEditors => '1'], false],
            'copy editor, setting never saved' => [Episciences_Acl::ROLE_COPY_EDITOR, $copyEditors, [], false],
        ];
    }

    /**
     * @param array<string, mixed> $journalSettings
     */
    #[DataProvider('encapsulationProvider')]
    public function testUnassignedEditorsFollowTheJournalEncapsulation(string $role, string $setting, array $journalSettings, bool $expected): void
    {
        $this->logInAs([$role]);

        self::assertSame($expected, $this->isEditorialStaff($journalSettings));
    }

    public function testEditorsSettingDoesNotOpenAccessToCopyEditors(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_COPY_EDITOR]);

        self::assertFalse($this->isEditorialStaff([Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '0']));
    }

    public function testSecretaryHandlesEveryPaper(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertTrue($this->isEditorialStaff([]));
    }

    public function testAssignedEditorHandlesThePaperWhateverTheEncapsulation(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertTrue($this->isEditorialStaff([Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '1'], true));
    }

    public function testMemberIsNotEditorialStaff(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_MEMBER]);

        self::assertFalse($this->isEditorialStaff([Episciences_Review::SETTING_ENCAPSULATE_EDITORS => '0']));
    }
}
