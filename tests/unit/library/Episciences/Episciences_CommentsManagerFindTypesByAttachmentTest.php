<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_CommentsManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * @covers Episciences_CommentsManager::findTypesByAttachment
 */
final class Episciences_CommentsManagerFindTypesByAttachmentTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * @param list<array{TYPE: int|string, FILE: string}> $rows
     */
    private function givenComments(array $rows): void
    {
        $this->adapter->method('fetchAll')->willReturnCallback(
            function (Zend_Db_Select $select) use ($rows): array {
                self::assertStringContainsString('(DOCID = 12)', $select->assemble(), 'only the comments of the requested version');
                return $rows;
            }
        );
    }

    public function testTypeOfTheCommentCarryingASingleFile(): void
    {
        $this->givenComments([
            ['TYPE' => '0', 'FILE' => 'question.pdf'],
            ['TYPE' => '12', 'FILE' => 'other.pdf'],
        ]);

        self::assertSame([0], Episciences_CommentsManager::findTypesByAttachment(12, 'question.pdf'));
    }

    public function testFileListedAmongSeveralFiles(): void
    {
        $this->givenComments([
            ['TYPE' => '22', 'FILE' => json_encode(['a.pdf', 'résumé (v2).pdf'])],
        ]);

        self::assertSame([22], Episciences_CommentsManager::findTypesByAttachment(12, 'résumé (v2).pdf'));
    }

    public function testEveryCommentCarryingTheSameFileName(): void
    {
        $this->givenComments([
            ['TYPE' => '12', 'FILE' => 'same.pdf'],
            ['TYPE' => '1', 'FILE' => 'same.pdf'],
            ['TYPE' => '1', 'FILE' => 'same.pdf'],
        ]);

        self::assertSame([12, 1], Episciences_CommentsManager::findTypesByAttachment(12, 'same.pdf'));
    }

    public function testPartialNameDoesNotMatch(): void
    {
        $this->givenComments([
            ['TYPE' => '0', 'FILE' => 'question.pdf.bak'],
            ['TYPE' => '1', 'FILE' => json_encode(['my-question.pdf'])],
        ]);

        self::assertSame([], Episciences_CommentsManager::findTypesByAttachment(12, 'question.pdf'));
    }
}
