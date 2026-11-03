<?php

declare(strict_types=1);

namespace unit\modules;

use Episciences_Acl;
use Episciences_Auth;
use Episciences_Csrf_Helper;
use Episciences_Review;
use Episciences_ReviewsManager;
use ReflectionProperty;
use unit\modules\support\FixtureController;
use Zend_Auth;
use Zend_Controller_Front;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * The base class of the controller tests: database without connection, requests, identity,
 * dispatch and restoration of the global state.
 */
final class ControllerTestCaseTest extends ControllerTestCase
{
    // ------------------------------------------------------------------
    // Database
    // ------------------------------------------------------------------

    public function testQueriesAreBuiltWithoutConnectingAndAnsweredWithTheProgrammedRows(): void
    {
        $this->db->respondTo('/FROM `VOLUME`/', [['VID' => '7', 'POSITION' => '1'], ['VID' => '9', 'POSITION' => '2']]);
        $select = $this->db->select()->from(T_VOLUMES)->where('BIB_REFERENCE = ?', "it's");

        self::assertSame(['VID' => '7', 'POSITION' => '1'], $this->db->fetchRow($select));
        self::assertSame(['7', '9'], $this->db->fetchCol($select));
        self::assertSame('7', $this->db->fetchOne($select));
        self::assertSame(['7' => '1', '9' => '2'], $this->db->fetchPairs($select));
        self::assertCount(2, $this->db->fetchAll($select));

        self::assertFalse($this->db->isConnected());
        self::assertCount(5, $this->db->selects());
        self::assertStringContainsString("BIB_REFERENCE = 'it\\'s'", $this->db->selects()[0]);
    }

    public function testQueryNoRuleMatchesHasNoRow(): void
    {
        $this->db->respondTo('/FROM `SECTION`/', [['SID' => '1']]);
        $select = $this->db->select()->from(T_VOLUMES);

        self::assertFalse($this->db->fetchRow($select));
        self::assertFalse($this->db->fetchOne($select));
        self::assertSame([], $this->db->fetchCol($select));
    }

    public function testAnswerCanBeComputedFromTheBoundValues(): void
    {
        $this->db->respondTo('/FROM `VOLUME`/', static fn(string $sql, array $bind): array => [['VID' => $bind[0]]]);

        self::assertSame('12', $this->db->fetchOne('SELECT VID FROM `VOLUME` WHERE VID = ?', ['12']));
    }

    public function testNullColumnIsReadAsNull(): void
    {
        $this->db->respondTo('/FROM `VOLUME`/', [['BIB_REFERENCE' => null]]);

        self::assertNull($this->db->fetchOne('SELECT BIB_REFERENCE FROM `VOLUME`'));
    }

    public function testWritesAndTransactionsAreRecordedNotExecuted(): void
    {
        $this->db->beginTransaction();
        $this->db->insert(T_VOLUMES, ['RVID' => 3]);
        $this->db->update(T_VOLUMES, ['POSITION' => 2], ['VID = ?' => 7]);
        $this->db->delete(T_VOLUMES, ['VID = ?' => 8]);
        $this->db->rollBack();

        self::assertSame([['table' => T_VOLUMES, 'values' => ['RVID' => 3]]], $this->db->inserts());
        self::assertSame('1', $this->db->lastInsertId());
        self::assertSame([['table' => T_VOLUMES, 'values' => ['POSITION' => 2], 'where' => ['VID = ?' => 7]]], $this->db->updates());
        self::assertSame([['table' => T_VOLUMES, 'where' => ['VID = ?' => 8]]], $this->db->deletes());
        self::assertSame(['begin', 'rollback'], $this->db->transactions());
        self::assertSame([], $this->db->queries());
    }

    public function testTheRecordingAdapterIsTheDefaultOne(): void
    {
        self::assertSame($this->db, Zend_Db_Table_Abstract::getDefaultAdapter());
    }

    // ------------------------------------------------------------------
    // Requests
    // ------------------------------------------------------------------

    public function testPostRequestCarriesTheValidRequestTokenOnlyWhenAsked(): void
    {
        self::assertTrue(Episciences_Csrf_Helper::validateRequestToken($this->postRequest(['a' => '1'], true)));
        self::assertFalse(Episciences_Csrf_Helper::validateRequestToken($this->postRequest(['a' => '1'])));
    }

    public function testScriptRequestSendsTheTokenAsAHeader(): void
    {
        $request = $this->xhrPostRequest(['a' => '1'], true);

        self::assertTrue($request->isXmlHttpRequest());
        self::assertTrue($request->isPost());
        self::assertNull($request->getPost('csrf_token'));
        self::assertTrue(Episciences_Csrf_Helper::validateRequestToken($request));
    }

    public function testARequestDoesNotKeepWhatAnEarlierOnePosted(): void
    {
        $this->postRequest(['docid' => '12']);
        $request = $this->getRequest(['id' => '3'], ['vid' => '4']);

        self::assertNull($request->getPost('docid'));
        self::assertSame('3', $request->getQuery('id'));
        self::assertSame('4', $request->getParam('vid'));
        self::assertTrue($request->isGet());
    }

    public function testFormTokenIsAcceptedOnce(): void
    {
        $field = $this->formToken('fixture_form');

        self::assertTrue(Episciences_Csrf_Helper::validateToken('fixture_form', (string)reset($field)));
    }

    // ------------------------------------------------------------------
    // Identity
    // ------------------------------------------------------------------

    public function testNobodyIsLoggedByDefault(): void
    {
        self::assertFalse(Episciences_Auth::isLogged());
    }

    public function testLoggedUserHasItsRolesInTheJournal(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        self::assertSame(self::UID, Episciences_Auth::getUid());
        self::assertTrue(Episciences_Auth::isSecretary());
        self::assertFalse(Episciences_Auth::isImpersonating());
    }

    public function testImpersonatedUserKeepsTheOriginalIdentity(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_ROOT]);
        $this->impersonate([Episciences_Acl::ROLE_EDITOR], 51);

        self::assertSame(51, Episciences_Auth::getUid());
        self::assertTrue(Episciences_Auth::isImpersonating());
        self::assertSame(self::UID, Episciences_Auth::getOriginalIdentity()?->getUid());
    }

    // ------------------------------------------------------------------
    // Journal
    // ------------------------------------------------------------------

    public function testJournalIsLoadedFromTheProgrammedSettings(): void
    {
        $this->givenJournal([Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED => '1']);

        $journal = Episciences_ReviewsManager::find(RVID);
        self::assertInstanceOf(Episciences_Review::class, $journal);
        $journal->loadSettings();

        self::assertSame('1', $journal->getSetting(Episciences_Review::SETTING_SYSTEM_IS_COI_ENABLED));
        self::assertMatchesRegularExpression('/FROM `REVIEW` WHERE \(RVID = ' . RVID . '\)/', $this->db->selects()[0]);
    }

    public function testJournalIsNotTakenFromAnEarlierTest(): void
    {
        self::assertFalse(Episciences_ReviewsManager::find(RVID));
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    public function testActionAnswerIsReturned(): void
    {
        $result = $this->dispatch(FixtureController::class, 'echo', $this->getRequest());

        self::assertFalse($result->stopped);
        self::assertSame(202, $result->statusCode());
        self::assertSame('echoed echo', $result->output);
        self::assertSame('echoed echobody', $result->body());
        self::assertNull($result->redirectUrl());
    }

    public function testRedirectionStopsTheActionAsInProduction(): void
    {
        $result = $this->dispatch(FixtureController::class, 'redirect', $this->getRequest());

        self::assertTrue($result->stopped);
        self::assertSame(302, $result->statusCode());
        self::assertStringEndsWith('/volume/list', (string)$result->redirectUrl());
        self::assertSame('', $result->output);
    }

    public function testSentJsonStopsTheActionAsInProduction(): void
    {
        $result = $this->dispatch(FixtureController::class, 'json', $this->postRequest());

        self::assertTrue($result->stopped);
        self::assertSame(['status' => 1], $result->json());
        self::assertSame('application/json', $result->header('Content-Type'));
    }

    public function testApplicationControllerRunsOnTheRecordingAdapter(): void
    {
        self::loadController('journal', 'VolumeController');
        $this->db->respondTo('/FROM `VOLUME`/', [['VID' => '1'], ['VID' => '2']]);

        $refused = $this->dispatch(\VolumeController::class, 'sort', $this->xhrPostRequest(['sorted' => ['volume_2', 'volume_1']]));
        self::assertSame(403, $refused->statusCode());
        self::assertSame([], $this->db->queries());

        $sorted = $this->dispatch(\VolumeController::class, 'sort', $this->xhrPostRequest(['sorted' => ['volume_2', 'volume_1']], true));
        self::assertSame(200, $sorted->statusCode());
        self::assertCount(1, $this->db->selects());
        self::assertMatchesRegularExpression('/\bRVID = ' . RVID . '\b/', $this->db->selects()[0]);
        self::assertSame(
            [[T_VOLUMES, ['POSITION' => 1], '2'], [T_VOLUMES, ['POSITION' => 2], '1']],
            array_map(static fn(array $update): array => [$update['table'], $update['values'], $update['where']['VID = ?'] ?? null], $this->db->updates())
        );
    }

    // ------------------------------------------------------------------
    // Restoration
    // ------------------------------------------------------------------

    public function testGlobalStateIsRestoredAfterTheTest(): void
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->restoreControllerEnvironment();
        $previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $previousPost = $_POST;
        $previousSession = $_SESSION;
        $previousFrontResponse = Zend_Controller_Front::getInstance()->getResponse();
        $journalCache = new ReflectionProperty(Episciences_ReviewsManager::class, '_cache');
        $previousJournalCache = $journalCache->getValue();
        $this->setUpControllerEnvironment();

        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $this->postRequest(['docid' => '12'], true);
        Zend_Registry::set('controller_test_case_entry', true);
        $this->dispatch(FixtureController::class, 'echo', $this->getRequest());
        $this->givenJournal();
        Episciences_ReviewsManager::find(RVID);
        self::assertNotSame($previousJournalCache, $journalCache->getValue());
        self::assertNotSame($previousFrontResponse, Zend_Controller_Front::getInstance()->getResponse());

        $this->restoreControllerEnvironment();

        self::assertNotSame($adapter, $previousAdapter);
        self::assertSame($previousAdapter, Zend_Db_Table_Abstract::getDefaultAdapter());
        self::assertSame($previousPost, $_POST);
        self::assertSame($previousSession, $_SESSION);
        self::assertFalse(Zend_Auth::getInstance()->hasIdentity());
        self::assertFalse(Zend_Registry::isRegistered('controller_test_case_entry'));
        self::assertSame($previousFrontResponse, Zend_Controller_Front::getInstance()->getResponse());
        self::assertSame($previousJournalCache, $journalCache->getValue());

        // The test ends in the state the after-test restoration expects
        $this->setUpControllerEnvironment();
    }
}
