<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\QueueMessage;
use Episciences\QueueMessageManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Sending a message to the queue (test database); the rows are removed in tearDown().
 *
 * @covers \Episciences\QueueMessageManager::add
 */
final class Episciences_QueueMessageManagerAddTest extends TestCase
{
    private const RVCODE = 'queue-message-test';

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
    }

    protected function tearDown(): void
    {
        $this->db->delete(QueueMessageManager::TABLE, ['rvcode = ?' => self::RVCODE]);
    }

    /**
     * The status change message of Episciences_Paper::postPaperStatus() sets no timeout: the default one is stored
     */
    public function testMessageWithoutTimeoutIsStoredWithTheDefaultTimeout(): void
    {
        $id = (new QueueMessage([
            'rvcode' => self::RVCODE,
            'message' => ['docid' => 1, 'status' => 0],
            'type' => QueueMessageManager::TYPE_STATUS_CHANGED,
        ]))->send();

        $row = $this->db->fetchRow(
            $this->db->select()->from(QueueMessageManager::TABLE)->where('id = ?', $id)
        );

        self::assertIsArray($row);
        self::assertSame((string)QueueMessageManager::TYPE_DEFAULT_TIMEOUT, (string)$row['timeout']);
        self::assertSame(QueueMessageManager::TYPE_STATUS_CHANGED, $row['type']);
        self::assertSame(['docid' => 1, 'status' => 0], json_decode((string)$row['message'], true));
    }

    public function testGivenTimeoutIsStored(): void
    {
        $message = new QueueMessage([
            'rvcode' => self::RVCODE,
            'message' => ['docid' => 2],
            'type' => QueueMessageManager::TYPE_STATUS_CHANGED,
        ]);
        $message->setTimeout(30);

        $id = $message->send();

        self::assertSame('30', (string)$this->db->fetchOne(
            $this->db->select()->from(QueueMessageManager::TABLE, ['timeout'])->where('id = ?', $id)
        ));
    }

    public function testMessageWithoutTypeIsRefusedBeforeTheDatabase(): void
    {
        $message = new QueueMessage(['rvcode' => self::RVCODE, 'message' => ['docid' => 3]]);

        try {
            $message->send();
            self::fail('A message without a type must be refused');
        } catch (InvalidArgumentException $e) {
            self::assertSame('type cannot be empty', $e->getMessage());
        }

        self::assertSame('0', (string)$this->db->fetchOne(
            $this->db->select()->from(QueueMessageManager::TABLE, ['COUNT(*)'])->where('rvcode = ?', self::RVCODE)
        ));
    }
}
