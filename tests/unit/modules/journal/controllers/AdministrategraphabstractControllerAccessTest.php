<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use Episciences_Paper;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Authorization of the graphical abstract actions (AdministrategraphabstractController::canManagePaper())
 * and type of the files they accept.
 *
 * The paper is looked up in the current journal; secretaries and assigned editors may manage it,
 * its owner or a co-author only while it is neither published nor removed. The database adapter
 * is a partial mock that captures the queries. Uploaded files are accepted on their actual content.
 */
final class AdministrategraphabstractControllerAccessTest extends TestCase
{
    private const UID = 12345;

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> */
    private array $queries = [];

    /** @var list<string> */
    private array $tmpFiles = [];

    private mixed $previousAuthStorage;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministrategraphabstractController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['query'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);

        foreach ($this->tmpFiles as $tmpFile) {
            if (is_file($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function logIn(string $role = Episciences_Acl::ROLE_MEMBER): void
    {
        $user = new Episciences_User(['UID' => self::UID]);
        $user->setRoles([RVID => [$role]]);
        Zend_Auth::getInstance()->getStorage()->write($user);
    }

    /**
     * @param array<string, mixed>|false $paperRow
     */
    private function stubPaper(array|false $paperRow): void
    {
        $this->adapter->method('query')->willReturnCallback(
            function ($sql) use ($paperRow) {
                $this->queries[] = $sql instanceof Zend_Db_Select ? $sql->assemble() : (string)$sql;

                // One row per query, so that fetch() loops end
                $statement = $this->createMock(Zend_Db_Statement_Interface::class);
                $statement->method('fetch')->willReturnOnConsecutiveCalls($paperRow, false);

                return $statement;
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paperOwnedByTheLoggedInUser(int $status): array
    {
        return ['DOCID' => 77, 'PAPERID' => 77, 'RVID' => RVID, 'UID' => self::UID, 'STATUS' => $status];
    }

    private function canManagePaper(int $docId): bool
    {
        $controller = new \AdministrategraphabstractController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $method = new ReflectionMethod($controller, 'canManagePaper');
        $method->setAccessible(true);

        return $method->invoke($controller, $docId);
    }

    public function testAnonymousUserIsDeniedWithoutQueryingTheDatabase(): void
    {
        $this->stubPaper($this->paperOwnedByTheLoggedInUser(Episciences_Paper::STATUS_SUBMITTED));

        self::assertFalse($this->canManagePaper(77));
        self::assertSame([], $this->queries);
    }

    public function testInvalidDocIdIsDenied(): void
    {
        $this->logIn(Episciences_Acl::ROLE_SECRETARY);
        $this->stubPaper(false);

        self::assertFalse($this->canManagePaper(0));
        self::assertSame([], $this->queries);
    }

    public function testPaperIsLookedUpInTheCurrentJournal(): void
    {
        $this->logIn(Episciences_Acl::ROLE_SECRETARY);
        $this->stubPaper(false);

        self::assertFalse($this->canManagePaper(77), 'a paper missing from the current journal must be refused');

        $paperQueries = array_values(array_filter(
            $this->queries,
            static fn(string $sql): bool => str_contains($sql, 'DOCID')
        ));
        self::assertNotEmpty($paperQueries, 'the paper must be looked up');
        foreach ($paperQueries as $sql) {
            self::assertMatchesRegularExpression('/DOCID = 77\b/', $sql);
            self::assertMatchesRegularExpression('/RVID = ' . RVID . '\b/', $sql);
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function editableStatusProvider(): array
    {
        return [
            'submitted' => [Episciences_Paper::STATUS_SUBMITTED],
            'accepted' => [Episciences_Paper::STATUS_ACCEPTED],
        ];
    }

    /**
     * @dataProvider editableStatusProvider
     */
    public function testOwnerCanManageAPaperThatIsNeitherPublishedNorRemoved(int $status): void
    {
        $this->logIn();
        $this->stubPaper($this->paperOwnedByTheLoggedInUser($status));

        self::assertTrue($this->canManagePaper(77));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function closedStatusProvider(): array
    {
        return [
            'published' => [Episciences_Paper::STATUS_PUBLISHED],
            'removed' => [Episciences_Paper::STATUS_REMOVED],
        ];
    }

    /**
     * @dataProvider closedStatusProvider
     */
    public function testOwnerCannotManageAPublishedOrRemovedPaper(int $status): void
    {
        $this->logIn();
        $this->stubPaper($this->paperOwnedByTheLoggedInUser($status));

        self::assertFalse($this->canManagePaper(77));
    }

    public function testSecretaryCanManageAPublishedPaper(): void
    {
        $this->logIn(Episciences_Acl::ROLE_SECRETARY);
        $this->stubPaper(['DOCID' => 77, 'PAPERID' => 77, 'RVID' => RVID, 'UID' => 1, 'STATUS' => Episciences_Paper::STATUS_PUBLISHED]);

        self::assertTrue($this->canManagePaper(77));
    }

    private function createFile(string $name, string $content): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('graphabs_', true) . '_' . $name;
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;

        return $path;
    }

    private function invokeStatic(string $methodName, mixed ...$args): mixed
    {
        $method = new ReflectionMethod(\AdministrategraphabstractController::class, $methodName);
        $method->setAccessible(true);

        return $method->invoke(null, ...$args);
    }

    public function testRasterImageIsStoredWithTheExtensionOfItsContent(): void
    {
        // 1x1 PNG, named as a JPEG
        $png = (string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        self::assertSame('png', $this->invokeStatic('detectImageExtension', $this->createFile('image.jpg', $png)));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedContentProvider(): array
    {
        return [
            'svg' => ['image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'svg named as png' => ['image.png', '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'html named as gif' => ['image.gif', '<html><body><script>alert(1)</script></body></html>'],
        ];
    }

    /**
     * @dataProvider refusedContentProvider
     */
    public function testContentThatIsNotAnAcceptedImageIsRefused(string $name, string $content): void
    {
        self::assertNull($this->invokeStatic('detectImageExtension', $this->createFile($name, $content)));
    }

    public function testFileThatWasNotUploadedIsRefused(): void
    {
        $png = (string)base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        self::assertNull($this->invokeStatic('getAcceptedImageExtension', ['tmp_name' => $this->createFile('image.png', $png), 'error' => UPLOAD_ERR_OK]));
        self::assertNull($this->invokeStatic('getAcceptedImageExtension', ['error' => UPLOAD_ERR_OK]));
    }
}
