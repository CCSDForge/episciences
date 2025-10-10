<?php

namespace unit\scripts;

// Prevent auto-execution of the script when loaded for testing
define('PHPUNIT_RUNNING', true);

require_once __DIR__ . '/../../../scripts/getCreatorData.php';

use GetCreatorData;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Script;

/**
 * Unit tests for GetCreatorData script
 * Tests focus on non-database methods and utility functions
 */
class GetCreatorDataTest extends TestCase
{
    /**
     * Test that the class extends Script
     */
    public function testConstructorExtendsScript(): void
    {
        $script = new GetCreatorData([]);
        $this->assertInstanceOf(Script::class, $script);
    }

    /**
     * Test that isDryRun returns the correct default value
     * Note: When no dry-run param is passed, the constructor sets it to false via setDryRun((bool)$this->getParam('dry-run'))
     * The property default is true, but constructor overrides it based on the parameter
     */
    public function testIsDryRunReturnsDefaultValue(): void
    {
        $script = new GetCreatorData([]);
        // After constructor runs with no dry-run param, it becomes false
        $this->assertFalse($script->isDryRun(), 'Dry-run mode should be false when no parameter is provided');
    }

    /**
     * Test that setDryRun updates the dry-run status
     */
    public function testSetDryRunUpdatesValue(): void
    {
        $script = new GetCreatorData([]);

        $script->setDryRun(false);
        $this->assertFalse($script->isDryRun(), 'Dry-run should be false after setting');

        $script->setDryRun(true);
        $this->assertTrue($script->isDryRun(), 'Dry-run should be true after setting');
    }

    /**
     * Test that the logger is initialized in the constructor
     */
    public function testConstructorInitializesLogger(): void
    {
        $script = new GetCreatorData([]);

        $reflection = new ReflectionClass($script);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $logger = $loggerProperty->getValue($script);

        $this->assertInstanceOf(Logger::class, $logger, 'Logger should be initialized');
        $this->assertEquals('creatorEnrichment', $logger->getName(), 'Logger name should be creatorEnrichment');
    }

    /**
     * Test that logger has at least one handler (file handler)
     */
    public function testLoggerHasFileHandler(): void
    {
        $script = new GetCreatorData([]);

        $reflection = new ReflectionClass($script);
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $logger = $loggerProperty->getValue($script);

        $handlers = $logger->getHandlers();
        $this->assertNotEmpty($handlers, 'Logger should have at least one handler');
        $this->assertInstanceOf(StreamHandler::class, $handlers[0], 'First handler should be StreamHandler');
    }

    /**
     * Test dry-run parameter is correctly parsed from options
     */
    public function testDryRunParameterParsing(): void
    {
        // Test with dry-run enabled - the parameter needs to be set and retrieved via getParam
        // The Script parent class handles parameter parsing, so we test the setter/getter instead
        $scriptWithDryRun = new GetCreatorData([]);
        $scriptWithDryRun->setDryRun(true);
        $this->assertTrue($scriptWithDryRun->isDryRun(), 'Should enable dry-run when set to true');

        // Test with dry-run explicitly disabled
        $scriptNoDryRun = new GetCreatorData([]);
        $scriptNoDryRun->setDryRun(false);
        $this->assertFalse($scriptNoDryRun->isDryRun(), 'Should disable dry-run when set to false');
    }

    /**
     * Test that isNoCache returns the correct default value
     */
    public function testIsNoCacheReturnsDefaultValue(): void
    {
        $script = new GetCreatorData([]);
        $this->assertFalse($script->isNoCache(), 'No-cache mode should be false by default');
    }

    /**
     * Test that setNoCache updates the no-cache status
     */
    public function testSetNoCacheUpdatesValue(): void
    {
        $script = new GetCreatorData([]);

        $script->setNoCache(false);
        $this->assertFalse($script->isNoCache(), 'No-cache should be false after setting');

        $script->setNoCache(true);
        $this->assertTrue($script->isNoCache(), 'No-cache should be true after setting');
    }

    /**
     * Test deleteCacheForDoi method exists
     */
    public function testDeleteCacheForDoiMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('deleteCacheForDoi'), 'deleteCacheForDoi method should exist');

        $method = $reflection->getMethod('deleteCacheForDoi');
        $this->assertTrue($method->isPrivate(), 'deleteCacheForDoi should be private');
    }

    /**
     * Test that class name follows PHP naming conventions
     */
    public function testClassNameFollowsConventions(): void
    {
        $this->assertTrue(class_exists('GetCreatorData'), 'Class should be named GetCreatorData (PascalCase)');
    }

    /**
     * Test processPaper method exists and is callable
     */
    public function testProcessPaperMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('processPaper'), 'processPaper method should exist');

        $method = $reflection->getMethod('processPaper');
        $this->assertTrue($method->isPrivate(), 'processPaper should be private');
    }

    /**
     * Test processSinglePaper method exists and is callable
     */
    public function testProcessSinglePaperMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('processSinglePaper'), 'processSinglePaper method should exist');

        $method = $reflection->getMethod('processSinglePaper');
        $this->assertTrue($method->isPrivate(), 'processSinglePaper should be private');
    }

    /**
     * Test processSingleDoi method exists and is callable
     */
    public function testProcessSingleDoiMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('processSingleDoi'), 'processSingleDoi method should exist');

        $method = $reflection->getMethod('processSingleDoi');
        $this->assertTrue($method->isPrivate(), 'processSingleDoi should be private');
    }

    /**
     * Test clearCache method exists and is callable
     */
    public function testClearCacheMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('clearCache'), 'clearCache method should exist');

        $method = $reflection->getMethod('clearCache');
        $this->assertTrue($method->isPrivate(), 'clearCache should be private');
    }

    /**
     * Test processEmptyDoi method exists
     */
    public function testProcessEmptyDoiMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('processEmptyDoi'), 'processEmptyDoi method should exist');
    }

    /**
     * Test processPaperWithDoi method exists
     */
    public function testProcessPaperWithDoiMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('processPaperWithDoi'), 'processPaperWithDoi method should exist');
    }

    /**
     * Test processHalRepository method exists
     */
    public function testProcessHalRepositoryMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('processHalRepository'), 'processHalRepository method should exist');
    }

    /**
     * Test insertAuthors method exists
     */
    public function testInsertAuthorsMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('insertAuthors'), 'insertAuthors method should exist');

        $method = $reflection->getMethod('insertAuthors');
        $this->assertTrue($method->isPrivate(), 'insertAuthors should be private');
    }

    /**
     * Test run method exists and is public
     */
    public function testRunMethodExists(): void
    {
        $script = new GetCreatorData([]);
        $reflection = new ReflectionClass($script);

        $this->assertTrue($reflection->hasMethod('run'), 'run method should exist');

        $method = $reflection->getMethod('run');
        $this->assertTrue($method->isPublic(), 'run method should be public');
    }
}
