<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use Ccsd_Db_Adapter_Cas;
use Episciences_User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * User lookups feeding the autocomplete endpoints must never expose credential columns.
 *
 * T_CAS_USERS holds the account credentials next to the identity data. Both databases are
 * partial mocks: queries are built for real and captured, rows are canned.
 */
final class UserLookupColumnScopeTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $previousAdapter;
    private mixed $previousCasAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $localDb;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $casDb;

    /** @var list<string> SQL sent to the CAS database */
    private array $casQueries = [];

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/UserDefaultController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->localDb = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAssoc'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->localDb);

        $this->casDb = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchAll'])
            ->getMock();
        $property = new ReflectionProperty(Ccsd_Db_Adapter_Cas::class, 'cas_adapter');
        $property->setAccessible(true);
        $this->previousCasAdapter = $property->getValue();
        $property->setValue(null, $this->casDb);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        (new ReflectionProperty(Ccsd_Db_Adapter_Cas::class, 'cas_adapter'))->setValue(null, $this->previousCasAdapter);
    }

    /**
     * @param array<int, array<string, mixed>> $localUsers
     * @param list<array<string, mixed>> $casRows
     */
    private function stubDatabases(array $localUsers, array $casRows): void
    {
        $this->localDb->method('fetchAssoc')->willReturn($localUsers);
        $this->casDb->method('fetchAll')->willReturnCallback(function ($select) use ($casRows) {
            $this->casQueries[] = $select instanceof Zend_Db_Select ? $select->assemble() : (string)$select;

            return $casRows;
        });
    }

    public function testFilterUsersSelectsIdentityColumnsOnly(): void
    {
        $this->stubDatabases([5 => ['UID' => 5, 'SCREEN_NAME' => 'Jane']], []);

        Episciences_User::filterUsers('jane', false);

        self::assertCount(1, $this->casQueries);
        $sql = strtoupper($this->casQueries[0]);
        self::assertDoesNotMatchRegularExpression('/SELECT\s+`?[A-Z_]*`?\.?\*/', $sql, 'no wildcard selection');
        self::assertStringNotContainsString('PASSWORD', $sql);
        foreach (['UID', 'USERNAME', 'EMAIL', 'LASTNAME', 'FIRSTNAME'] as $column) {
            self::assertStringContainsString($column, $sql);
        }
    }

    public function testFilterUsersTakesTheScreenNameFromTheLocalTable(): void
    {
        $this->stubDatabases(
            [5 => ['UID' => 5, 'SCREEN_NAME' => 'Jane S'], 6 => ['UID' => 6, 'SCREEN_NAME' => 'Other']],
            [
                ['UID' => 5, 'EMAIL' => 'j@example.org', 'FIRSTNAME' => 'Jane', 'LASTNAME' => 'Smith', 'USERNAME' => 'js'],
                ['UID' => 9, 'EMAIL' => 'x@example.org', 'FIRSTNAME' => 'X', 'LASTNAME' => 'Y', 'USERNAME' => 'xy'],
            ]
        );

        $rows = Episciences_User::filterUsers('jane', false);

        self::assertSame('Jane S', $rows[0]['SCREEN_NAME']);
        self::assertSame('', $rows[1]['SCREEN_NAME'], 'unknown locally: empty, not an error');
    }

    public function testFilterUsersWithoutLocalUsersDoesNotQueryTheCasDatabase(): void
    {
        $this->stubDatabases([], []);

        self::assertNull(Episciences_User::filterUsers('jane', false));
        self::assertSame([], $this->casQueries);
    }

    /**
     * @return string the body of the answer
     */
    private function getmails(bool $ajax, string $term): string
    {
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setQuery('term', $term);
        if ($ajax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }
        $controller = new \UserDefaultController($request, new Zend_Controller_Response_HttpTestCase());

        ob_start();
        try {
            $controller->getmailsAction();
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    public function testGetmailsReturnsOnlyAutocompleteFields(): void
    {
        $this->stubDatabases(
            [5 => ['UID' => 5, 'SCREEN_NAME' => 'Jane S'], 6 => ['UID' => 6, 'SCREEN_NAME' => '']],
            [
                // Extra columns must never be echoed back, even if a query returned them
                ['UID' => 5, 'EMAIL' => 'j@example.org', 'FIRSTNAME' => 'Jane', 'LASTNAME' => 'Smith', 'USERNAME' => 'js', 'PASSWORD' => 'hash'],
                ['UID' => 6, 'EMAIL' => 'b@example.org', 'FIRSTNAME' => 'Bob', 'LASTNAME' => 'O\'Neil', 'USERNAME' => 'bo', 'PASSWORD' => 'hash'],
            ]
        );

        $output = $this->getmails(true, 'j');
        $users = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('hash', $output);
        self::assertSame(
            ['uid' => 5, 'name' => 'Jane S', 'mail' => 'j@example.org', 'label' => 'Jane S &lt;j@example.org&gt;'],
            $users[0]
        );
        self::assertSame('Bob O\'Neil', $users[1]['name'], 'falls back on first and last name');
        foreach ($users as $user) {
            self::assertSame(['uid', 'name', 'mail', 'label'], array_keys($user));
        }
    }

    public function testGetmailsLabelIsHtmlEncoded(): void
    {
        $this->stubDatabases(
            [5 => ['UID' => 5, 'SCREEN_NAME' => '<img src=x onerror=alert(1)>']],
            [['UID' => 5, 'EMAIL' => 'j@example.org', 'FIRSTNAME' => 'J', 'LASTNAME' => 'S', 'USERNAME' => 'js']]
        );

        $users = json_decode($this->getmails(true, 'j'), true, 512, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('<img', $users[0]['label']);
    }

    public function testGetmailsIgnoresARequestThatIsNotAjax(): void
    {
        $this->localDb->expects(self::never())->method('fetchAssoc');
        $this->casDb->expects(self::never())->method('fetchAll');

        self::assertSame('', $this->getmails(false, 'j'));
    }
}
