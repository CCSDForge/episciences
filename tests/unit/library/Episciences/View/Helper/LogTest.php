<?php

namespace unit\library\Episciences\View\Helper;

use Episciences_View_Helper_Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Zend_Registry;

/**
 * Unit tests for Episciences_View_Helper_Log
 *
 * @covers Episciences_View_Helper_Log
 */
class LogTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up registry entry after each test
        if (Zend_Registry::isRegistered('appLogger')) {
            $registry = Zend_Registry::getInstance();
            unset($registry['appLogger']);
        }
    }

    /**
     * Test that log() returns false when no appLogger is registered
     */
    public function testLogReturnsFalseWhenNoLoggerRegistered(): void
    {
        // Ensure appLogger is not in registry
        if (Zend_Registry::isRegistered('appLogger')) {
            $registry = Zend_Registry::getInstance();
            unset($registry['appLogger']);
        }

        $result = Episciences_View_Helper_Log::log('test message');

        $this->assertFalse($result);
    }

    /**
     * Test that log() returns true and calls the logger when appLogger is registered
     */
    public function testLogReturnsTrueAndCallsLogger(): void
    {
        // Create a mock PSR-3 compatible logger
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::NOTICE, 'test message', []);

        Zend_Registry::set('appLogger', $mockLogger);

        $result = Episciences_View_Helper_Log::log('test message');

        $this->assertTrue($result);
    }

    /**
     * Test that log() passes the correct level to the logger
     */
    public function testLogPassesCorrectLevel(): void
    {
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, 'an error occurred', []);

        Zend_Registry::set('appLogger', $mockLogger);

        $result = Episciences_View_Helper_Log::log('an error occurred', LogLevel::ERROR);

        $this->assertTrue($result);
    }

    /**
     * Test that log() passes context array to the logger
     */
    public function testLogPassesContextArray(): void
    {
        $context = ['user_id' => 42, 'action' => 'submit'];

        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::NOTICE, 'context test', $context);

        Zend_Registry::set('appLogger', $mockLogger);

        $result = Episciences_View_Helper_Log::log('context test', LogLevel::NOTICE, $context);

        $this->assertTrue($result);
    }

    /**
     * Test that log() uses NOTICE as the default log level
     */
    public function testLogDefaultLevelIsNotice(): void
    {
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('log')
            ->with(LogLevel::NOTICE, $this->anything(), $this->anything());

        Zend_Registry::set('appLogger', $mockLogger);

        Episciences_View_Helper_Log::log('default level test');
    }

    /**
     * Test that log() uses an empty context array by default
     */
    public function testLogDefaultContextIsEmptyArray(): void
    {
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('log')
            ->with($this->anything(), $this->anything(), []);

        Zend_Registry::set('appLogger', $mockLogger);

        Episciences_View_Helper_Log::log('default context test');
    }

    /**
     * Test that log() returns false when the logger itself throws an exception.
     * Covers the inner try/catch around $logger->log().
     */
    public function testLogReturnsFalseWhenLoggerThrows(): void
    {
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('log')
            ->willThrowException(new \RuntimeException('Logger internal error'));

        Zend_Registry::set('appLogger', $mockLogger);

        $result = Episciences_View_Helper_Log::log('test message');

        $this->assertFalse($result);
    }
}
