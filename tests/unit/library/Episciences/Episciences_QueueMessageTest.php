<?php

namespace unit\library\Episciences;

use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences\QueueMessage.
 *
 * Pure-logic: setters/getters, setType validation, toArray shape.
 * DB-dependent methods (send, delete) excluded.
 *
 * @covers \Episciences\QueueMessage
 */
class Episciences_QueueMessageTest extends TestCase
{
    // =========================================================================
    // Constructor
    // =========================================================================

    public function testConstructorWithNullDoesNotThrow(): void
    {
        $msg = new QueueMessage(null);
        self::assertInstanceOf(QueueMessage::class, $msg);
    }

    public function testConstructorWithEmptyArrayDoesNotThrow(): void
    {
        $msg = new QueueMessage([]);
        self::assertInstanceOf(QueueMessage::class, $msg);
    }

    // =========================================================================
    // setRvcode / getRvcode
    // =========================================================================

    public function testSetAndGetRvcode(): void
    {
        $msg = new QueueMessage();
        $msg->setRvcode('epijinfo');
        self::assertSame('epijinfo', $msg->getRvcode());
    }

    public function testSetRvcodeWithNull(): void
    {
        $msg = new QueueMessage();
        $msg->setRvcode(null);
        self::assertNull($msg->getRvcode());
    }

    // =========================================================================
    // setMessage / getMessage
    // =========================================================================

    public function testSetAndGetMessage(): void
    {
        $msg = new QueueMessage();
        $msg->setMessage(['key' => 'value']);
        self::assertSame(['key' => 'value'], $msg->getMessage());
    }

    public function testSetMessageWithNull(): void
    {
        $msg = new QueueMessage();
        $msg->setMessage(null);
        self::assertNull($msg->getMessage());
    }

    // =========================================================================
    // setTimeout / getTimeout
    // =========================================================================

    public function testSetAndGetTimeout(): void
    {
        $msg = new QueueMessage();
        $msg->setTimeout(120);
        self::assertSame(120, $msg->getTimeout());
    }

    public function testSetTimeoutWithNull(): void
    {
        $msg = new QueueMessage();
        $msg->setTimeout(null);
        self::assertNull($msg->getTimeout());
    }

    // =========================================================================
    // setProcessed / getProcessed
    // =========================================================================

    public function testSetAndGetProcessed(): void
    {
        $msg = new QueueMessage();
        $msg->setProcessed(QueueMessageManager::PROCESSED);
        self::assertSame(QueueMessageManager::PROCESSED, $msg->getProcessed());
    }

    public function testSetProcessedWithNull(): void
    {
        $msg = new QueueMessage();
        $msg->setProcessed(null);
        self::assertNull($msg->getProcessed());
    }

    // =========================================================================
    // setType / getType — validation
    // =========================================================================

    public function testSetTypeWithValidType(): void
    {
        $msg = new QueueMessage();
        $msg->setType(QueueMessageManager::TYPE_STATUS_CHANGED);
        self::assertSame(QueueMessageManager::TYPE_STATUS_CHANGED, $msg->getType());
    }

    public function testSetTypeReturnsFluent(): void
    {
        $msg = new QueueMessage();
        $result = $msg->setType(QueueMessageManager::TYPE_STATUS_CHANGED);
        self::assertInstanceOf(QueueMessage::class, $result);
    }

    public function testSetTypeWithInvalidTypeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $msg = new QueueMessage();
        $msg->setType('not_a_valid_type');
    }

    public function testSetTypeWithEmptyStringThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $msg = new QueueMessage();
        $msg->setType('');
    }

    // =========================================================================
    // QueueMessageManager constants
    // =========================================================================

    public function testQueueManagerConstants(): void
    {
        self::assertSame('status_changed', QueueMessageManager::TYPE_STATUS_CHANGED);
        self::assertContains('status_changed', QueueMessageManager::VALID_TYPES);
        self::assertSame(0, QueueMessageManager::UNPROCESSED);
        self::assertSame(1, QueueMessageManager::PROCESSED);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testToArrayContainsSevenKeys(): void
    {
        $array = $this->buildInitializedMessage()->toArray();
        self::assertCount(7, $array);
    }

    public function testToArrayHasExpectedKeys(): void
    {
        $array = $this->buildInitializedMessage()->toArray();
        self::assertArrayHasKey('id', $array);
        self::assertArrayHasKey('rvcode', $array);
        self::assertArrayHasKey('message', $array);
        self::assertArrayHasKey('timeout', $array);
        self::assertArrayHasKey('created_at', $array);
        self::assertArrayHasKey('processed', $array);
        self::assertArrayHasKey('updated_at', $array);
    }

    public function testToArrayReflectsSetValues(): void
    {
        $msg = $this->buildInitializedMessage();
        $msg->setRvcode('testjournal');
        $msg->setMessage(['docid' => 42, 'status' => 5]);
        $msg->setTimeout(60);
        $msg->setProcessed(QueueMessageManager::UNPROCESSED);

        $array = $msg->toArray();

        self::assertSame('testjournal', $array['rvcode']);
        self::assertSame(['docid' => 42, 'status' => 5], $array['message']);
        self::assertSame(60, $array['timeout']);
        self::assertSame(QueueMessageManager::UNPROCESSED, $array['processed']);
    }

    public function testToArrayTypeKeyIsAbsent(): void
    {
        // type is not included in toArray() — by design
        $array = $this->buildInitializedMessage()->toArray();
        self::assertArrayNotHasKey('type', $array);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a QueueMessage with private uninitialized properties (id, created_at,
     * updated_at) set to null via reflection so toArray() can be called safely.
     */
    private function buildInitializedMessage(): QueueMessage
    {
        $msg = new QueueMessage();
        // Public setters for nullable properties
        $msg->setRvcode(null);
        $msg->setMessage(null);
        $msg->setTimeout(null);
        $msg->setProcessed(null);
        // id, created_at, updated_at have private setters — initialize via reflection
        foreach (['id', 'created_at', 'updated_at'] as $prop) {
            $rp = new \ReflectionProperty(QueueMessage::class, $prop);
            $rp->setAccessible(true);
            $rp->setValue($msg, null);
        }
        return $msg;
    }
}
