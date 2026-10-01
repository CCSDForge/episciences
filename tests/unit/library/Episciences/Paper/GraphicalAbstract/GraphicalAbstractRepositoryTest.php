<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\GraphicalAbstract;

use Episciences\Paper\GraphicalAbstract\GraphicalAbstract;
use Episciences\Paper\GraphicalAbstract\GraphicalAbstractRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * DB-free tests: the SQL is captured by a stub adapter, the files are written in a temporary journal directory.
 *
 * @covers \Episciences\Paper\GraphicalAbstract\GraphicalAbstractRepository
 */
final class GraphicalAbstractRepositoryTest extends TestCase
{
    private mixed $previousAdapter;
    private GraphicalAbstractRecordingAdapter $adapter;
    private string $reviewPath = '';

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = new GraphicalAbstractRecordingAdapter();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);

        if ($this->reviewPath !== '') {
            $this->removeDir($this->reviewPath);
        }
    }

    public function testFindReadsTheThreeKeysInASingleQuery(): void
    {
        $this->adapter->rows = [['graphical_abstract.png', 'A chart', 'CC BY 4.0']];

        $graphicalAbstract = GraphicalAbstractRepository::find(42);

        self::assertNotNull($graphicalAbstract);
        self::assertSame('graphical_abstract.png', $graphicalAbstract->file);
        self::assertSame('A chart', $graphicalAbstract->alt);
        self::assertSame('CC BY 4.0', $graphicalAbstract->license);

        self::assertCount(1, $this->adapter->queries);
        [$sql, $bind] = $this->adapter->queries[0];
        self::assertStringContainsString("'$.database.current.graphical_abstract_file'", $sql);
        self::assertStringContainsString("'$.database.current.graphical_abstract_alt'", $sql);
        self::assertStringContainsString("'$.database.current.graphical_abstract_license'", $sql);
        self::assertStringContainsString('WHERE DOCID = ?', $sql);
        self::assertSame([42], $bind);
    }

    public function testFindReturnsNullWithoutFile(): void
    {
        // JSON_UNQUOTE(JSON_EXTRACT()) returns the string "null" for a JSON null
        $this->adapter->rows = [['null', 'A chart', null]];

        self::assertNull(GraphicalAbstractRepository::find(42));
    }

    public function testFindReturnsNullForAnUnknownPaper(): void
    {
        $this->adapter->rows = [false];

        self::assertNull(GraphicalAbstractRepository::find(42));
    }

    public function testFindNormalizesBlankAndNullTextsToNull(): void
    {
        $this->adapter->rows = [[' graphical_abstract.gif ', '  ', 'null']];

        $graphicalAbstract = GraphicalAbstractRepository::find(42);

        self::assertNotNull($graphicalAbstract);
        self::assertSame('graphical_abstract.gif', $graphicalAbstract->file);
        self::assertNull($graphicalAbstract->alt);
        self::assertNull($graphicalAbstract->license);
    }

    public function testSaveWritesTheThreeKeysInASingleMergePatch(): void
    {
        GraphicalAbstractRepository::save(42, new GraphicalAbstract('graphical_abstract.png', 'A chart', 'CC BY 4.0'));

        self::assertCount(1, $this->adapter->queries);
        [$sql, $bind] = $this->adapter->queries[0];
        self::assertStringStartsWith('UPDATE ' . T_PAPERS . ' SET DOCUMENT = JSON_MERGE_PATCH(COALESCE(DOCUMENT, JSON_OBJECT())', $sql);
        self::assertStringContainsString(
            "JSON_OBJECT('database', JSON_OBJECT('current', JSON_OBJECT('graphical_abstract_file', ?, 'graphical_abstract_alt', ?, 'graphical_abstract_license', ?))))",
            $sql
        );
        self::assertStringEndsWith('WHERE DOCID = ?', $sql);
        self::assertSame(['graphical_abstract.png', 'A chart', 'CC BY 4.0', 42], $bind);
    }

    public function testSaveRemovesAnEmptyLicenseAndKeepsOnlyTheFileBasename(): void
    {
        GraphicalAbstractRepository::save(42, new GraphicalAbstract('../../graphical_abstract.png', 'A chart', ' '));

        // a null value removes the key in JSON_MERGE_PATCH
        self::assertSame(['graphical_abstract.png', 'A chart', null, 42], $this->adapter->queries[0][1]);
    }

    public function testDeleteRemovesTheKeysAndTheFile(): void
    {
        $file = $this->createDocument(42, 'graphical_abstract.png');
        $this->adapter->rows = [['graphical_abstract.png', 'A chart', null]];

        GraphicalAbstractRepository::delete(42, $this->reviewPath);

        self::assertFileDoesNotExist($file);
        self::assertCount(2, $this->adapter->queries);
        self::assertSame([null, null, null, 42], $this->adapter->queries[1][1]);
    }

    public function testCopyToVersionCopiesTheFileAndTheKeys(): void
    {
        $this->createDocument(10, 'graphical_abstract.webp', 'image-bytes');
        $this->adapter->rows = [
            ['graphical_abstract.webp', 'A chart', 'CC0 1.0'], // source
            [null, null, null], // target
        ];

        self::assertTrue(GraphicalAbstractRepository::copyToVersion(10, 11, $this->reviewPath));

        $copy = $this->reviewPath . 'public/documents/11/graphical_abstract.webp';
        self::assertFileExists($copy);
        self::assertSame('image-bytes', file_get_contents($copy));
        self::assertSame('0644', substr(sprintf('%o', fileperms($copy)), -4));

        $lastQuery = end($this->adapter->queries);
        self::assertSame(['graphical_abstract.webp', 'A chart', 'CC0 1.0', 11], $lastQuery[1]);
    }

    public function testCopyToVersionCopiesTheFileWhenTheTargetOnlyInheritedTheKeys(): void
    {
        // a new version cloned from the previous Episciences_Paper inherits the stored keys, not the file
        $this->createDocument(10, 'graphical_abstract.png');
        $this->adapter->rows = [
            ['graphical_abstract.png', 'A chart', null],
            ['graphical_abstract.png', 'A chart', null],
        ];

        self::assertTrue(GraphicalAbstractRepository::copyToVersion(10, 11, $this->reviewPath));
        self::assertFileExists($this->reviewPath . 'public/documents/11/graphical_abstract.png');
    }

    public function testCopyToVersionKeepsTheIllustrationOfTheTarget(): void
    {
        $this->createDocument(10, 'graphical_abstract.png');
        $this->createDocument(11, 'graphical_abstract.jpg', 'own');
        $this->adapter->rows = [
            ['graphical_abstract.png', 'Old', null],
            ['graphical_abstract.jpg', 'Own', null],
        ];

        self::assertFalse(GraphicalAbstractRepository::copyToVersion(10, 11, $this->reviewPath));
        self::assertFileDoesNotExist($this->reviewPath . 'public/documents/11/graphical_abstract.png');
        self::assertCount(2, $this->adapter->queries, 'no write expected');
    }

    public function testCopyToVersionDoesNothingWhenTheSourceFileIsMissing(): void
    {
        $this->reviewPath = $this->makeReviewPath();
        $this->adapter->rows = [['graphical_abstract.png', 'A chart', null]];

        self::assertFalse(GraphicalAbstractRepository::copyToVersion(10, 11, $this->reviewPath));
        self::assertCount(1, $this->adapter->queries);
    }

    public function testCopyToVersionDoesNothingWithoutSourceIllustration(): void
    {
        $this->adapter->rows = [[null, null, null]];

        self::assertFalse(GraphicalAbstractRepository::copyToVersion(10, 11, '/nonexistent/'));
    }

    public function testCopyToVersionIgnoresTheSameDocument(): void
    {
        self::assertFalse(GraphicalAbstractRepository::copyToVersion(10, 10, '/nonexistent/'));
        self::assertSame([], $this->adapter->queries);
    }

    public function testPathsAndPublicUrl(): void
    {
        self::assertSame('/data/rvcode/public/documents/42/', GraphicalAbstractRepository::documentsDir(42, '/data/rvcode'));
        self::assertSame('/data/rvcode/public/documents/42/x.png', GraphicalAbstractRepository::filePath(42, '../x.png', '/data/rvcode/'));
        self::assertSame('/public/documents/42/a%20b.png', GraphicalAbstractRepository::publicUrl(42, 'dir/a b.png'));
    }

    public function testDocumentsDirRequiresAJournalDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        GraphicalAbstractRepository::documentsDir(42, '');
    }

    private function makeReviewPath(): string
    {
        $path = sys_get_temp_dir() . '/ep_graphical_abstract_' . bin2hex(random_bytes(6)) . '/';
        mkdir($path, 0775, true);

        return $path;
    }

    private function createDocument(int $docId, string $file, string $contents = 'image'): string
    {
        if ($this->reviewPath === '') {
            $this->reviewPath = $this->makeReviewPath();
        }

        $dir = GraphicalAbstractRepository::ensureDocumentsDir($docId, $this->reviewPath);
        file_put_contents($dir . $file, $contents);

        return $dir . $file;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
        }
        rmdir($dir);
    }
}

/**
 * Records every query; fetch() returns the queued rows, one per query.
 */
final class GraphicalAbstractRecordingAdapter extends Zend_Db_Adapter_Abstract
{
    /** @var list<array{0: string, 1: array<int, mixed>}> */
    public array $queries = [];

    /** @var list<array<int, mixed>|false> */
    public array $rows = [];

    public function __construct()
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
    }

    /**
     * @param string $sql
     * @param array<int, mixed> $bind
     */
    public function query($sql, $bind = []): Zend_Db_Statement_Interface
    {
        $this->queries[] = [(string)$sql, $bind];

        return new GraphicalAbstractRecordingStatement($this->rows === [] ? false : array_shift($this->rows));
    }

    /** @return array<int, string> */
    public function listTables(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function describeTable($tableName, $schemaName = null): array
    {
        return [];
    }

    protected function _connect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function closeConnection(): void {}

    /**
     * @return Zend_Db_Statement_Interface
     */
    public function prepare($sql)
    {
        return new GraphicalAbstractRecordingStatement(false);
    }

    public function lastInsertId($tableName = null, $primaryKey = null): string
    {
        return '0';
    }

    protected function _beginTransaction(): void {}

    protected function _commit(): void {}

    protected function _rollBack(): void {}

    public function setFetchMode($mode): void
    {
        $this->_fetchMode = $mode;
    }

    public function limit($sql, $count, $offset = 0): string
    {
        return $sql;
    }

    public function supportsParameters($type): bool
    {
        return true;
    }

    public function getServerVersion(): string
    {
        return 'test';
    }
}

final class GraphicalAbstractRecordingStatement implements Zend_Db_Statement_Interface
{
    /**
     * @param array<int, mixed>|false $row
     */
    public function __construct(private readonly array|false $row) {}

    public function bindColumn($column, &$param, $type = null)
    {
        return true;
    }

    public function bindParam($parameter, &$variable, $type = null, $length = null, $options = null)
    {
        return true;
    }

    public function bindValue($parameter, $value, $type = null)
    {
        return true;
    }

    public function closeCursor()
    {
        return true;
    }

    public function columnCount()
    {
        return 3;
    }

    public function errorCode()
    {
        return '';
    }

    /** @return array<int, mixed> */
    public function errorInfo()
    {
        return [];
    }

    /** @param array<int|string, mixed> $params */
    public function execute(array $params = [])
    {
        return true;
    }

    /** @return array<int, mixed>|false */
    public function fetch($style = null, $cursor = null, $offset = null)
    {
        return $this->row;
    }

    /** @return array<int, mixed> */
    public function fetchAll($style = null, $col = null)
    {
        return [];
    }

    public function fetchColumn($col = 0)
    {
        return false;
    }

    /** @param array<string, mixed> $config */
    public function fetchObject($class = 'stdClass', array $config = [])
    {
        return false;
    }

    public function getAttribute($key)
    {
        return null;
    }

    public function nextRowset()
    {
        return true;
    }

    public function rowCount()
    {
        return 1;
    }

    public function setAttribute($key, $val)
    {
        return true;
    }

    public function setFetchMode($mode)
    {
        return true;
    }
}
