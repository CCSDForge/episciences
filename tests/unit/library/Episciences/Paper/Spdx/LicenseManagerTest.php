<?php

namespace unit\library\Episciences\Paper\Spdx;

use Episciences\Paper\Spdx\License;
use Episciences\Paper\Spdx\LicenseManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * @coversDefaultClass \Episciences\Paper\Spdx\LicenseManager
 */
class LicenseManagerTest extends TestCase
{
    private ?Zend_Db_Adapter_Abstract $previousAdapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        LicenseManager::clearCache();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        LicenseManager::clearCache();
    }

    /**
     * Stub adapter: fetchAssoc() is expected to run $expectedQueries times
     */
    private function useAdapter(int $expectedQueries): void
    {
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAssoc'])
            ->getMock();
        $adapter->expects(self::exactly($expectedQueries))->method('fetchAssoc')->willReturn([
            'MIT' => ['code' => 'MIT', 'name' => 'MIT License'],
            'CC-BY-4.0' => ['code' => 'CC-BY-4.0', 'name' => 'Creative Commons Attribution 4.0 International'],
        ]);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
    }

    /**
     * @covers ::fetchRecommended
     * @covers ::fetchRecommendedOptions
     */
    public function testRecommendedLicensesAreQueriedOnce(): void
    {
        $this->useAdapter(1);

        $licenses = LicenseManager::fetchRecommended();

        self::assertContainsOnlyInstancesOf(License::class, $licenses);
        self::assertSame($licenses, LicenseManager::fetchRecommended());
        self::assertSame(
            ['MIT' => 'MIT License', 'CC-BY-4.0' => 'Creative Commons Attribution 4.0 International'],
            LicenseManager::fetchRecommendedOptions()
        );
    }

    /**
     * @covers ::fetchRecommended
     */
    public function testCacheDependsOnFetchMode(): void
    {
        $this->useAdapter(2);

        self::assertContainsOnlyInstancesOf(License::class, LicenseManager::fetchRecommended());
        self::assertSame(['code' => 'MIT', 'name' => 'MIT License'], LicenseManager::fetchRecommended(['code', 'name'], 'array')['MIT']);
    }

    /**
     * @covers ::normalizeCodes
     */
    public function testNormalizeCodes(): void
    {
        self::assertSame(['MIT', 'Apache-2.0'], LicenseManager::normalizeCodes(['MIT', '', ' MIT ', ' Apache-2.0 ', null]));
        self::assertSame(['MIT'], LicenseManager::normalizeCodes(' MIT '));
        self::assertSame([], LicenseManager::normalizeCodes(''));
        self::assertSame([], LicenseManager::normalizeCodes(null));
    }

    /**
     * @covers ::containsCode
     */
    public function testContainsCodeIsCaseInsensitive(): void
    {
        self::assertTrue(LicenseManager::containsCode('cc-by-4.0', ['MIT', 'CC-BY-4.0']));
        self::assertTrue(LicenseManager::containsCode('CC-BY-4.0', ['cc-by-4.0']));
        self::assertFalse(LicenseManager::containsCode('CC-BY-4.0', ['CC-BY-SA-4.0']));
        self::assertFalse(LicenseManager::containsCode('CC-BY-4.0', []));
    }

    /**
     * @covers ::loadSpdxCode
     * @covers ::clearCache
     */
    public function testSpdxCodesAreQueriedOnceUntilCacheIsCleared(): void
    {
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchCol'])
            ->getMock();
        $adapter->expects(self::exactly(2))->method('fetchCol')->willReturn(['MIT', 'CC-BY-4.0']);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        self::assertSame(['MIT', 'CC-BY-4.0'], LicenseManager::loadSpdxCode());
        self::assertSame(['MIT', 'CC-BY-4.0'], LicenseManager::loadSpdxCode());
        LicenseManager::clearCache();
        LicenseManager::loadSpdxCode();
    }

    /**
     * @covers ::getNameByIdentifier
     */
    public function testGetNameByIdentifierReturnsNullForAnUnknownCode(): void
    {
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchOne'])
            ->getMock();
        // Zend_Db returns false when no row matches (e.g. an empty license table)
        $adapter->method('fetchOne')->willReturnOnConsecutiveCalls('MIT License', false);
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);

        self::assertSame('MIT License', LicenseManager::getNameByIdentifier('MIT'));
        self::assertNull(LicenseManager::getNameByIdentifier('UNKNOWN-1.0'));
    }

    /**
     * @covers ::clearCache
     */
    public function testClearCacheQueriesAgain(): void
    {
        $this->useAdapter(2);

        LicenseManager::fetchRecommended();
        LicenseManager::clearCache();
        LicenseManager::fetchRecommended();
    }
}
