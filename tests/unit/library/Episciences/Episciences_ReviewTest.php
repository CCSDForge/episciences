<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Review.
 *
 * Focuses on pure-logic methods (constants, setters/getters, settings management,
 * static state). DB-dependent methods (find, save, loadSettings, getPapers, etc.)
 * are excluded — they belong to integration tests.
 *
 * @covers Episciences_Review
 */
class Episciences_ReviewTest extends TestCase
{
    private Episciences_Review $review;

    protected function setUp(): void
    {
        $this->review = new Episciences_Review();
    }

    // =========================================================================
    // Status constants
    // =========================================================================

    public function testStatusConstants(): void
    {
        self::assertSame(0, Episciences_Review::STATUS_NOTVALID);
        self::assertSame(1, Episciences_Review::STATUS_VALID);
        self::assertSame(2, Episciences_Review::STATUS_REFUSED);
        self::assertSame('1', Episciences_Review::ENABLED);
        self::assertSame('0', Episciences_Review::DISABLED);
    }

    // =========================================================================
    // Deadline constants
    // =========================================================================

    public function testDeadlineConstants(): void
    {
        self::assertSame('1 month', Episciences_Review::DEFAULT_INVITATION_DEADLINE);
        self::assertSame('2 month', Episciences_Review::DEFAULT_RATING_DEADLINE);
        self::assertSame('2 month', Episciences_Review::DEFAULT_RATING_DEADLINE_MIN);
        self::assertSame('6 month', Episciences_Review::DEFAULT_RATING_DEADLINE_MAX);
    }

    // =========================================================================
    // Setting key constants (sample)
    // =========================================================================

    public function testSettingKeyConstants(): void
    {
        self::assertSame('invitation_deadline', Episciences_Review::SETTING_INVITATION_DEADLINE);
        self::assertSame('rating_deadline', Episciences_Review::SETTING_RATING_DEADLINE);
        self::assertSame('ISSN', Episciences_Review::SETTING_ISSN);
        self::assertSame('doiAssignMode', Episciences_Review_DoiSettings::SETTING_DOI_ASSIGN_MODE);
    }

    // =========================================================================
    // Default language
    // =========================================================================

    public function testDefaultLang(): void
    {
        self::assertSame('en', Episciences_Review::DEFAULT_LANG);
    }

    // =========================================================================
    // RVID setter/getter
    // =========================================================================

    public function testSetAndGetRvid(): void
    {
        $this->review->setRvid(42);
        self::assertSame(42, $this->review->getRvid());
    }

    public function testSetRvidCastsToInt(): void
    {
        $this->review->setRvid('7');
        self::assertSame(7, $this->review->getRvid());
        self::assertIsInt($this->review->getRvid());
    }

    public function testDefaultRvidIsZero(): void
    {
        self::assertSame(0, $this->review->getRvid());
    }

    public function testSetRvidReturnsFluent(): void
    {
        $result = $this->review->setRvid(1);
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // Code setter/getter
    // =========================================================================

    public function testSetAndGetCode(): void
    {
        $this->review->setCode('epijinfo');
        self::assertSame('epijinfo', $this->review->getCode());
    }

    public function testSetCodeReturnsFluent(): void
    {
        $result = $this->review->setCode('test');
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    public function testDefaultCodeIsEmptyString(): void
    {
        self::assertSame('', $this->review->getCode());
    }

    // =========================================================================
    // Name setter/getter
    // =========================================================================

    public function testSetAndGetName(): void
    {
        $this->review->setName('Journal of Tests');
        self::assertSame('Journal of Tests', $this->review->getName());
    }

    public function testSetNameReturnsFluent(): void
    {
        $result = $this->review->setName('test');
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // Status setter/getter
    // =========================================================================

    public function testSetAndGetStatus(): void
    {
        $this->review->setStatus('1');
        self::assertSame('1', $this->review->getStatus());
    }

    // =========================================================================
    // Piwikid setter/getter
    // =========================================================================

    public function testSetAndGetPiwikid(): void
    {
        $this->review->setPiwikid(99);
        self::assertSame(99, $this->review->getPiwikid());
    }

    public function testSetPiwikidReturnsFluent(): void
    {
        $result = $this->review->setPiwikid(1);
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // setSetting / getSettings
    // =========================================================================

    public function testSetSettingAndGetSettings(): void
    {
        $this->review->setSetting('ISSN', '1234-5678');
        $settings = $this->review->getSettings();

        self::assertIsArray($settings);
        self::assertArrayHasKey('ISSN', $settings);
        self::assertSame('1234-5678', $settings['ISSN']);
    }

    public function testSetMultipleSettings(): void
    {
        $this->review->setSetting(Episciences_Review::SETTING_ISSN, '1234-5678');
        $this->review->setSetting(Episciences_Review::SETTING_INVITATION_DEADLINE, '2');

        $settings = $this->review->getSettings();

        self::assertCount(2, $settings);
        self::assertSame('1234-5678', $settings[Episciences_Review::SETTING_ISSN]);
        self::assertSame('2', $settings[Episciences_Review::SETTING_INVITATION_DEADLINE]);
    }

    public function testSetSettingReturnsFluent(): void
    {
        $result = $this->review->setSetting('key', 'value');
        self::assertInstanceOf(Episciences_Review::class, $result);
    }

    // =========================================================================
    // getRepositories (reads from $_settings)
    // =========================================================================

    public function testGetRepositoriesReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->review->getRepositories());
    }

    public function testGetRepositoriesReturnsSetValue(): void
    {
        $repos = [1, 2, 3];
        $this->review->setSetting('repositories', $repos);
        self::assertSame($repos, $this->review->getRepositories());
    }

    // =========================================================================
    // Static currentReviewId
    // =========================================================================

    public function testSetAndGetCurrentReviewId(): void
    {
        Episciences_Review::setCurrentReviewId(5);
        self::assertSame(5, Episciences_Review::getCurrentReviewId());
    }

    public function testSetCurrentReviewIdWithZero(): void
    {
        Episciences_Review::setCurrentReviewId(0);
        self::assertSame(0, Episciences_Review::getCurrentReviewId());
    }

    // =========================================================================
    // ASSIGNMENT_EDITORS_MODE constant
    // =========================================================================

    public function testAssignmentEditorsModeKeys(): void
    {
        $mode = Episciences_Review::ASSIGNMENT_EDITORS_MODE;
        self::assertArrayHasKey('predefined', $mode);
        self::assertArrayHasKey('default', $mode);
        self::assertArrayHasKey('advanced', $mode);
        self::assertSame('0', $mode['predefined']);
        self::assertSame('1', $mode['default']);
        self::assertSame('2', $mode['advanced']);
    }

    // =========================================================================
    // forYourInformation() — pure wrapper, catches all exceptions
    // =========================================================================

    public function testForYourInformationReturnsStringWithNullDocId(): void
    {
        $result = Episciences_Review::forYourInformation(null, null, false);
        self::assertIsString($result);
    }

    // =========================================================================
    // setOptions()
    // =========================================================================

    public function testSetOptionsAppliesRvidAndCode(): void
    {
        $this->review->setOptions(['rvid' => 3, 'code' => 'myjournal']);
        self::assertSame(3, $this->review->getRvid());
        self::assertSame('myjournal', $this->review->getCode());
    }

    public function testConstructorWithOptions(): void
    {
        $review = new Episciences_Review(['rvid' => 10, 'code' => 'test-journal', 'name' => 'Test Journal']);
        self::assertSame(10, $review->getRvid());
        self::assertSame('test-journal', $review->getCode());
        self::assertSame('Test Journal', $review->getName());
    }

    // =========================================================================
    // loadSettings() cache
    // =========================================================================

    public function testLoadSettingsUsesInMemoryCache(): void
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_Review_LoadSettingsCacheTestAdapter([
            [
                'SETTING' => Episciences_Review::SETTING_ISSN,
                'VALUE' => '1234-5678',
            ],
            [
                'SETTING' => Episciences_Review::SETTING_REPOSITORIES,
                'VALUE' => '[1,2]',
            ],
        ]);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
            $review = new Episciences_Review(['rvid' => 8]);

            $review->loadSettings();
            $review->loadSettings();

            self::assertSame(1, $adapter->fetchAllCount);
            self::assertSame('1234-5678', $review->getSetting(Episciences_Review::SETTING_ISSN));
            self::assertSame([1, 2], $review->getSetting(Episciences_Review::SETTING_REPOSITORIES));
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }

    public function testLoadSettingsCanForceReload(): void
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_Review_LoadSettingsCacheTestAdapter([
            [
                'SETTING' => Episciences_Review::SETTING_ISSN,
                'VALUE' => '1234-5678',
            ],
            [
                'SETTING' => Episciences_Review::SETTING_CONTACT_JOURNAL,
                'VALUE' => '1',
            ],
        ]);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
            $review = new Episciences_Review(['rvid' => 8]);

            $review->loadSettings();
            $adapter->setRows([
                [
                    'SETTING' => Episciences_Review::SETTING_ISSN,
                    'VALUE' => '8765-4321',
                ],
            ]);

            $review->loadSettings(true);

            self::assertSame(2, $adapter->fetchAllCount);
            self::assertSame('8765-4321', $review->getSetting(Episciences_Review::SETTING_ISSN));
            self::assertArrayNotHasKey(Episciences_Review::SETTING_CONTACT_JOURNAL, $review->getSettings());
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }

    public function testGetSettingLoadsDbSettingsEvenIfManuallyPreinitialized(): void
    {
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $adapter = new Episciences_Review_LoadSettingsCacheTestAdapter([
            [
                'SETTING' => Episciences_Review::SETTING_ISSN,
                'VALUE' => '1234-5678',
            ],
        ]);

        try {
            Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
            $review = new Episciences_Review(['rvid' => 8]);

            // Set a setting manually before any load/get
            $review->setSetting('some_manual_setting', 'manual_val');

            // Trigger getSetting for a database setting
            $issn = $review->getSetting(Episciences_Review::SETTING_ISSN);

            self::assertSame(1, $adapter->fetchAllCount);
            self::assertSame('1234-5678', $issn);
            self::assertSame('manual_val', $review->getSetting('some_manual_setting'));
        } finally {
            Zend_Db_Table_Abstract::setDefaultAdapter($previousAdapter);
        }
    }
}

final class Episciences_Review_LoadSettingsCacheTestAdapter extends Zend_Db_Adapter_Abstract
{
    public int $fetchAllCount = 0;

    private array $rows;

    public function __construct(array $rows)
    {
        parent::__construct(['dbname' => 'test', 'password' => '', 'username' => 'test']);
        $this->rows = $rows;
    }

    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }

    public function fetchAll($sql, $bind = [], $fetchMode = null)
    {
        ++$this->fetchAllCount;
        return $this->rows;
    }

    public function listTables()
    {
        return [];
    }

    public function describeTable($tableName, $schemaName = null)
    {
        return [];
    }

    protected function _connect()
    {
    }

    public function isConnected()
    {
        return true;
    }

    public function closeConnection()
    {
    }

    public function prepare($sql)
    {
        return null;
    }

    public function lastInsertId($tableName = null, $primaryKey = null)
    {
        return null;
    }

    protected function _beginTransaction()
    {
    }

    protected function _commit()
    {
    }

    protected function _rollBack()
    {
    }

    public function setFetchMode($mode)
    {
        $this->_fetchMode = $mode;
    }

    public function limit($sql, $count, $offset = 0)
    {
        return $sql;
    }

    public function supportsParameters($type)
    {
        return false;
    }

    public function getServerVersion()
    {
        return 'test';
    }
}
