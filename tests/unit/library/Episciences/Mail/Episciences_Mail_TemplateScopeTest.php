<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Template;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Journal scoping of the template lookup and deletion by id.
 *
 * Default templates are shared by all journals and custom templates belong to one journal:
 * an id coming from a request must only reach a custom template of the current journal.
 * The database adapter is a partial mock: queries are built for real and captured.
 */
final class Episciences_Mail_TemplateScopeTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    /** @var list<string> */
    private array $selects = [];

    protected function setUp(): void
    {
        // The constructor sets a locale, which reads the registry
        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
        }
        if (!\Zend_Registry::isRegistered('languages')) {
            \Zend_Registry::set('languages', ['en', 'fr']);
        }

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'delete'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * @param array<string, mixed>|false $row
     */
    private function stubFetchRow(array|false $row): void
    {
        $this->adapter->method('fetchRow')->willReturnCallback(
            function ($select) use ($row) {
                $this->selects[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

                return $row;
            }
        );
    }

    public function testFindCustomIsScopedToTheJournalAndToCustomTemplates(): void
    {
        $this->stubFetchRow(false);

        self::assertFalse((new Episciences_Mail_Template())->findCustom(7, 3));

        self::assertCount(1, $this->selects);
        self::assertMatchesRegularExpression('/ID\s*=\s*7/', $this->selects[0]);
        self::assertMatchesRegularExpression('/RVID\s*=\s*3/', $this->selects[0]);
        self::assertStringContainsString('PARENTID IS NOT NULL', $this->selects[0]);
    }

    public function testFindCustomPopulatesTheTemplateWhenFound(): void
    {
        $this->stubFetchRow([
            'ID' => '7',
            'PARENTID' => '2',
            'RVID' => '3',
            'RVCODE' => 'jtest',
            'KEY' => 'custom_paper_submitted_editor_copy',
            'TYPE' => 'paper',
        ]);

        $template = new Episciences_Mail_Template();

        self::assertTrue($template->findCustom(7, 3));
        self::assertEquals(7, $template->getId());
        self::assertTrue($template->isCustom());
    }

    public function testDeleteBindsTheIdentifier(): void
    {
        $captured = null;
        $this->adapter->method('delete')->willReturnCallback(
            function ($table, $where) use (&$captured) {
                $captured = $where;

                return 0;
            }
        );

        $template = new Episciences_Mail_Template(['id' => 7, 'key' => 'custom_x']);

        self::assertFalse($template->delete());
        self::assertSame(['ID = ?' => 7], $captured);
    }
}
