<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Acl;
use unit\modules\ControllerTestCase;
use unit\modules\support\DispatchResult;
use Zend_Controller_Request_HttpTestCase;

/**
 * Review request types screen (#319): request token, types of the current journal only, deletion of unused types only.
 */
final class RequesttypeControllerTest extends ControllerTestCase
{
    private const TYPE_ID = 21;
    private const USED_TYPE_ID = 22;
    private const OTHER_JOURNAL_TYPE_ID = 23;

    private bool $localeRegistered = false;

    private mixed $previousLanguages = null;

    protected function setUp(): void
    {
        self::loadController('journal', 'RequesttypeController');

        if (!\Zend_Registry::isRegistered('Zend_Locale')) {
            \Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));
            $this->localeRegistered = true;
        }
        // languages of the journal: a label is required in each of them
        $this->previousLanguages = \Zend_Registry::isRegistered('languages') ? \Zend_Registry::get('languages') : null;
        \Zend_Registry::set('languages', ['fr', 'en']);

        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $this->db->respondTo('/FROM `review_request_type` WHERE \(id = /', function (string $sql, array $bind): array {
            $rows = [
                self::TYPE_ID => ['id' => self::TYPE_ID, 'rvid' => RVID, 'code' => 'phase_two', 'labels' => '{"fr":"Phase 2","en":"Phase two"}'],
                self::USED_TYPE_ID => ['id' => self::USED_TYPE_ID, 'rvid' => RVID, 'code' => 'phase_one', 'labels' => '{"en":"Phase one"}'],
                self::OTHER_JOURNAL_TYPE_ID => ['id' => self::OTHER_JOURNAL_TYPE_ID, 'rvid' => RVID + 1, 'code' => 'elsewhere', 'labels' => '{"en":"Elsewhere"}'],
            ];

            foreach ($rows as $id => $row) {
                if (preg_match('/id = ' . $id . '\b/', $sql) === 1 || in_array($id, $bind, false)) {
                    return [$row];
                }
            }

            return [];
        });
        $this->db->respondTo('/FROM `USER_ASSIGNMENT` WHERE \(REQUEST_TYPE_ID = /', function (string $sql, array $bind): array {
            $used = preg_match('/REQUEST_TYPE_ID = ' . self::USED_TYPE_ID . '\b/', $sql) === 1 || in_array(self::USED_TYPE_ID, $bind, false);
            return $used ? [['ID' => 1]] : [];
        });
    }

    protected function tearDown(): void
    {
        if ($this->localeRegistered) {
            \Zend_Registry::getInstance()->offsetUnset('Zend_Locale');
        }
        if ($this->previousLanguages === null) {
            \Zend_Registry::getInstance()->offsetUnset('languages');
        } else {
            \Zend_Registry::set('languages', $this->previousLanguages);
        }
    }

    private function call(string $action, Zend_Controller_Request_HttpTestCase $request): DispatchResult
    {
        return $this->dispatch(\RequesttypeController::class, $action, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPost(): array
    {
        return [
            'code' => 'phase_three',
            'label' => ['fr' => 'Phase 3', 'en' => 'Phase three'],
            'deadline' => '2',
            'deadline_unit' => 'week',
            'position' => '5',
            'enabled' => '1',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function typeWrites(): array
    {
        $writes = array_merge($this->db->inserts(), $this->db->updates(), $this->db->deletes());

        return array_values(array_filter($writes, static fn(array $write): bool => $write['table'] === 'review_request_type'));
    }

    public function testTheFormerListPageOpensTheTypesTabOfTheGridsPage(): void
    {
        $result = $this->call('list', $this->getRequest());

        self::assertSame('/grid/list?tab=types', $result->redirectUrl());
    }

    public function testAPostWithoutTheRequestTokenSavesNothing(): void
    {
        $result = $this->call('edit', $this->postRequest($this->validPost()));

        self::assertSame('/grid/list?tab=types', $result->redirectUrl());
        self::assertSame([], $this->typeWrites());
    }

    public function testANewTypeIsSavedForTheCurrentJournal(): void
    {
        $result = $this->call('edit', $this->postRequest($this->validPost(), true));

        self::assertSame('/grid/list?tab=types', $result->redirectUrl());
        $inserts = $this->typeWrites();
        self::assertCount(1, $inserts);
        self::assertSame(RVID, $inserts[0]['values']['rvid']);
        self::assertSame('phase_three', $inserts[0]['values']['code']);
        self::assertSame('2 week', $inserts[0]['values']['default_deadline']);
    }

    public function testAnInvalidTypeIsNotSaved(): void
    {
        $result = $this->call('edit', $this->postRequest(['code' => 'Bad code', 'label' => []] + $this->validPost(), true));

        self::assertNull($result->redirectUrl());
        self::assertSame([], $this->typeWrites());
    }

    public function testATypeOfAnotherJournalCannotBeEdited(): void
    {
        $result = $this->call('edit', $this->postRequest($this->validPost(), true, ['id' => (string)self::OTHER_JOURNAL_TYPE_ID]));

        self::assertSame(404, $result->statusCode());
        self::assertSame([], $this->typeWrites());
    }

    public function testAnUnusedTypeIsDeleted(): void
    {
        $result = $this->call('delete', $this->postRequest(['id' => (string)self::TYPE_ID], true));

        self::assertSame('/grid/list?tab=types', $result->redirectUrl());
        $deletes = $this->typeWrites();
        self::assertCount(1, $deletes);
        self::assertArrayHasKey('where', $deletes[0]);
    }

    public function testAUsedTypeOrATypeOfAnotherJournalIsNotDeleted(): void
    {
        $this->call('delete', $this->postRequest(['id' => (string)self::USED_TYPE_ID], true));
        $this->call('delete', $this->postRequest(['id' => (string)self::OTHER_JOURNAL_TYPE_ID], true));

        self::assertSame([], $this->typeWrites());
    }

    public function testADeletionWithoutTheRequestTokenDeletesNothing(): void
    {
        $this->call('delete', $this->postRequest(['id' => (string)self::TYPE_ID]));

        self::assertSame([], $this->typeWrites());
    }
}
