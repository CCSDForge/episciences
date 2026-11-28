<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Closure;
use Episciences_Paper;
use Episciences_Paper_DocumentBackup;
use Episciences_ReviewsManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

// The test double below extends the controller
require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

/**
 * DefaultController::getMainDocumentContent() keeps a backup copy of the fetched PDF, keyed on the DOCID.
 * A new version saved on the same DOCID while the document is fetched must not leave the previous version
 * as the backup copy. The transport is mocked; the address literal avoids any DNS lookup.
 */
final class DefaultControllerDocumentBackupTest extends TestCase
{
    private const string URL = 'https://93.184.216.34/document.pdf';
    private const string PDF = '%PDF-1.4 fetched version';

    private ?Zend_Db_Adapter_Abstract $db = null;
    private ?Episciences_Paper_DocumentBackup $backup = null;
    private int $docId = 0;

    /** @var string[] directories created by the test */
    private array $createdDirs = [];

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;

        $journal = Episciences_ReviewsManager::findByRvid(RVID);
        if (!$journal) {
            self::markTestSkipped('The test journal does not exist.');
        }

        $this->db->insert(T_PAPERS, [
            'PAPERID' => 1,
            'RVID' => RVID,
            'UID' => 4242,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'document-backup-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);
        $this->docId = (int)$this->db->lastInsertId();

        $this->backup = new Episciences_Paper_DocumentBackup($this->docId, (string)$journal->getCode());

        // documents/, {docid}/, files/, {rvcode}/: the missing ones are removed in tearDown()
        $dir = rtrim($this->backup->getPath(), '/');
        for ($i = 0; $i < 4 && !is_dir($dir); $i++) {
            $this->createdDirs[] = $dir;
            $dir = dirname($dir);
        }
    }

    protected function tearDown(): void
    {
        $this->backup?->deleteDocumentBackupFile();

        foreach ($this->createdDirs as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }

        if ($this->docId > 0) {
            $this->db?->delete(T_PAPERS, ['DOCID = ?' => $this->docId]);
        }
    }

    /**
     * @param Closure(): void $whileFetching run while the document is fetched
     */
    private function fetch(Closure $whileFetching): ?string
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/pdf', 'Content-Length' => (string)strlen(self::PDF)], self::PDF),
        ]);

        $handler = static function (RequestInterface $request, array $options) use ($mock, $whileFetching) {
            $whileFetching();

            return $mock($request, $options);
        };

        $controller = (new ReflectionClass(DocumentBackupTestController::class))->newInstanceWithoutConstructor();
        $controller->documentHandler = $handler;

        $paper = new Episciences_Paper(['docid' => $this->docId, 'rvid' => RVID, 'version' => 1]);
        $method = new ReflectionMethod(\DefaultController::class, 'getMainDocumentContent');

        return $method->invoke($controller, $paper, self::URL);
    }

    public function testFetchedDocumentIsKeptAsTheBackupCopy(): void
    {
        self::assertSame(self::PDF, $this->fetch(static function (): void {
        }));

        self::assertSame(self::PDF, $this->backup?->getDocumentBackupFile());
    }

    public function testNoBackupCopyWhenANewVersionIsSavedWhileFetching(): void
    {
        $content = $this->fetch(function (): void {
            $this->db?->update(T_PAPERS, ['VERSION' => 2], ['DOCID = ?' => $this->docId]);
        });

        self::assertSame(self::PDF, $content);
        self::assertFalse($this->backup?->hasDocumentBackupFile());
    }

    public function testCopyOfANewerVersionSavedWhileFetchingIsNotOverwritten(): void
    {
        $this->fetch(function (): void {
            // The new version is saved and another request keeps its copy before this fetch ends
            $this->db?->update(T_PAPERS, ['VERSION' => 2], ['DOCID = ?' => $this->docId]);
            $this->backup?->saveDocumentBackupFile('%PDF-1.4 newer version');
        });

        self::assertSame('%PDF-1.4 newer version', $this->backup?->getDocumentBackupFile());
    }
}

/**
 * DefaultController fetching the document through the given transport handler.
 */
final class DocumentBackupTestController extends \DefaultController
{
    public ?Closure $documentHandler = null;

    protected function createDocumentHttpClient(?callable $handler = null): ClientInterface
    {
        return parent::createDocumentHttpClient($this->documentHandler);
    }
}
