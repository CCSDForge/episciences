<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences\Paper\ManagementRefusal;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Paper_Conflict;
use Episciences_Review;
use Episciences_User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Session;

/**
 * Moving a paper to another section (savesection): request token, paper of the current journal,
 * and the same permissions as the paper management page.
 *
 * The logged user is a real identity with roles; papers and journal are stubbed.
 */
final class AdministratepaperSaveSectionTest extends TestCase
{
    private const UID = 4242;

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    private bool $previousSessionTestMode;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchAll', 'fetchCol', 'fetchOne', 'query', 'update', 'insert', 'beginTransaction'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        // isConflictDetected() reads the session
        $this->previousSessionTestMode = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::$_unitTestEnabled = $this->previousSessionTestMode;
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
    private function paper(array $state = []): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getRvid')->willReturn($state['rvid'] ?? RVID);
        $paper->method('isDeleted')->willReturn($state['deleted'] ?? false);
        $paper->method('isRemoved')->willReturn($state['removed'] ?? false);
        $paper->method('isOwner')->willReturn($state['owner'] ?? false);
        $paper->method('isAssignedEditorOrCopyEditor')->willReturn($state['assigned'] ?? false);
        $paper->method('checkConflictResponse')->willReturn($state['conflict'] ?? Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']);

        return $paper;
    }

    private function controller(?Zend_Controller_Request_HttpTestCase $request = null): \AdministratepaperController
    {
        return new \AdministratepaperController(
            $request ?? new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function refusal(Episciences_Paper $paper, array $settings = []): ?ManagementRefusal
    {
        $method = new ReflectionMethod(\AdministratepaperController::class, 'findManagementRefusal');

        /** @var ManagementRefusal|null */
        return $method->invoke($this->controller(), $this->journal($settings), $paper);
    }

    // ------------------------------------------------------------------
    // Request
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedRequestProvider(): array
    {
        return [
            'GET request' => ['GET'],
            'POST request without token' => ['POST'],
        ];
    }

    /**
     * Without the request token nothing is looked up nor written.
     */
    #[DataProvider('rejectedRequestProvider')]
    public function testRequestWithoutTokenIsRefusedBeforeLoadingThePaper(string $method): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        foreach (['fetchRow', 'fetchAll', 'fetchCol', 'fetchOne', 'query', 'update', 'insert', 'beginTransaction'] as $dbMethod) {
            $this->adapter->expects(self::never())->method($dbMethod);
        }

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod($method)->setPost(['docid' => '12', 'sid' => '4']);
        $controller = $this->controller($request);

        ob_start();
        try {
            $controller->savesectionAction();
        } finally {
            $output = (string)ob_get_clean();
        }

        self::assertSame('', $output);
        self::assertSame(403, $controller->getResponse()->getHttpResponseCode());
    }

    // ------------------------------------------------------------------
    // Paper permissions
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, ManagementRefusal}>
     */
    public static function unmanageablePaperProvider(): array
    {
        return [
            'paper of another journal' => [['rvid' => RVID + 1], ManagementRefusal::OTHER_JOURNAL],
            'paper deleted by its author' => [['deleted' => true], ManagementRefusal::DELETED_OR_REMOVED],
            'paper removed by the journal' => [['removed' => true], ManagementRefusal::DELETED_OR_REMOVED],
            'own submission' => [['owner' => true], ManagementRefusal::OWN_SUBMISSION_OR_CONFLICT],
            'conflict of interest declared' => [
                ['conflict' => Episciences_Paper_Conflict::AVAILABLE_ANSWER['yes']],
                ManagementRefusal::OWN_SUBMISSION_OR_CONFLICT,
            ],
        ];
    }

    /**
     * Refused even to a secretary, as on the paper management page.
     *
     * @param array<string, mixed> $state
     */
    #[DataProvider('unmanageablePaperProvider')]
    public function testPaperThatCannotBeManagedIsRefused(array $state, ManagementRefusal $expected): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertSame($expected, $this->refusal($this->paper($state), [Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']));
    }

    public function testSecretaryCanManageAPaperOfTheJournal(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertNull($this->refusal($this->paper()));
    }

    public function testAssignedEditorCanManageThePaper(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertNull($this->refusal($this->paper(['assigned' => true]), ['encapsulateEditors' => '1']));
    }

    public function testUnassignedEditorIsRefusedWhenEditorsOnlyManageTheirPapers(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertSame(ManagementRefusal::PERMISSIONS, $this->refusal($this->paper(), ['encapsulateEditors' => '1']));
    }

    public function testUnassignedGuestEditorIsRefused(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_GUEST_EDITOR]);

        self::assertSame(ManagementRefusal::PERMISSIONS, $this->refusal($this->paper()));
    }
}
