<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences_Mail;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * An entry of the mail log can only be read through the history visible to the current user:
 * same journal, same documents, same conflict-of-interest restrictions as the history list.
 * The adapter is a partial mock: the query is built for real and captured.
 */
final class MailHistoryScopeTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void
    {
        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    private function mailOfJournal(int $rvid): Episciences_Mail
    {
        // The constructor needs the database
        $mail = $this->getMockBuilder(Episciences_Mail::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $mail->setRvid($rvid);

        return $mail;
    }

    private function stubCount(int|false $count): void
    {
        $adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchOne'])
            ->getMock();
        $adapter->method('fetchOne')->willReturnCallback(function ($select) use ($count) {
            $this->queries[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return $count;
        });
        Zend_Db_Table_Abstract::setDefaultAdapter($adapter);
    }

    public function testEntryOfTheHistoryIsFound(): void
    {
        $this->stubCount(1);

        self::assertTrue($this->mailOfJournal(3)->isInHistory(42, [10, 11]));
    }

    public function testEntryOutsideTheHistoryIsNotFound(): void
    {
        $this->stubCount(0);

        self::assertFalse($this->mailOfJournal(3)->isInHistory(42, [10, 11]));
    }

    public function testNoQueryIsSentForAnInvalidId(): void
    {
        $this->stubCount(1);

        self::assertFalse($this->mailOfJournal(3)->isInHistory(0));
        self::assertFalse($this->mailOfJournal(3)->isInHistory(-5));
        self::assertSame([], $this->queries);
    }

    public function testQueryIsRestrictedToTheJournalTheEntryAndTheVisibleDocuments(): void
    {
        $this->stubCount(1);

        $this->mailOfJournal(3)->isInHistory(42, [10, 11]);

        self::assertCount(1, $this->queries);
        self::assertMatchesRegularExpression('/\bRVID = 3\b/', $this->queries[0], 'the journal of the mail');
        self::assertMatchesRegularExpression('/\bID = 42\b/', $this->queries[0], 'the entry');
        self::assertMatchesRegularExpression('/DOCID IN \(10,11\)/', $this->queries[0], 'the visible documents');
    }

    public function testStrictModeDoesNotShowEntriesWithoutDocument(): void
    {
        $this->stubCount(1);

        $this->mailOfJournal(3)->isInHistory(42, [10], ['strict' => true]);

        self::assertStringNotContainsString('DOCID IS NULL', $this->queries[0]);
    }

    public function testWithoutVisibleDocumentsOnlyMailsWithoutDocumentOrOfTheUserAreVisible(): void
    {
        $this->stubCount(1);

        $this->mailOfJournal(3)->isInHistory(42, []);

        self::assertStringContainsString('DOCID IS NULL OR UID', $this->queries[0]);
        self::assertStringNotContainsString('DOCID IN', $this->queries[0]);
    }

    public function testNonNumericDocumentIdsNeverReachTheQuery(): void
    {
        $this->stubCount(1);

        $this->mailOfJournal(3)->isInHistory(42, ['10', "1) OR 1=1 --", 'abc']);

        self::assertMatchesRegularExpression('/DOCID IN \(10\)/', $this->queries[0]);
        self::assertStringNotContainsString('1=1', $this->queries[0]);
    }
}
