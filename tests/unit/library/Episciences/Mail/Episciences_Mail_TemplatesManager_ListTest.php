<?php

declare(strict_types=1);

namespace unit\library\Episciences\Mail;

use Episciences_Mail_TemplatesManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Mail templates list of a journal: a custom template replaces its default template, but the template of a review
 * request type created by the journal (#319), derived from the generic template, does not hide it.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_Mail_TemplatesManager::getList
 */
final class Episciences_Mail_TemplatesManager_ListTest extends TestCase
{
    private const RVID = 999501;
    private const RVCODE = 'list-test';

    private Zend_Db_Adapter_Abstract $db;

    private mixed $previousSettings = null;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();

        // settings of the journal, read by getList()
        $this->previousSettings = \Zend_Registry::isRegistered('reviewSettings') ? \Zend_Registry::get('reviewSettings') : null;
        \Zend_Registry::set('reviewSettings', []);
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();

        if ($this->previousSettings === null) {
            \Zend_Registry::getInstance()->offsetUnset('reviewSettings');
        } else {
            \Zend_Registry::set('reviewSettings', $this->previousSettings);
        }
    }

    private function template(string $key, ?int $parentId = null, ?int $rvid = null): int
    {
        $this->db->insert(T_MAIL_TEMPLATES, [
            'PARENTID' => $parentId,
            'RVID' => $rvid,
            'RVCODE' => $rvid === null ? null : self::RVCODE,
            'KEY' => $key,
            'TYPE' => 'paper_review',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * @return list<string>
     */
    private function listedKeys(): array
    {
        return array_column(Episciences_Mail_TemplatesManager::getList([], self::RVID), 'KEY');
    }

    public function testTheTemplateOfAJournalTypeDoesNotHideTheGenericTemplate(): void
    {
        $generic = $this->template('list_test_generic');
        $this->template('custom_list_test_phase_two', $generic, self::RVID);

        $keys = $this->listedKeys();

        self::assertContains('list_test_generic', $keys);
        self::assertContains('custom_list_test_phase_two', $keys);
    }

    public function testACustomTemplateStillReplacesItsDefaultTemplate(): void
    {
        $default = $this->template('list_test_accepted');
        $this->template('custom_list_test_accepted', $default, self::RVID);

        $keys = $this->listedKeys();

        self::assertNotContains('list_test_accepted', $keys);
        self::assertContains('custom_list_test_accepted', $keys);
    }
}
