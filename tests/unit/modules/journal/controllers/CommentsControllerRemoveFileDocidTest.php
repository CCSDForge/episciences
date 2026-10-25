<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_CommentsManager;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Behaviour of CommentsController::removefilecommentAction() when the request names a paper (docid)
 * that the comment (pcid) does not belong to: the file must not be looked for in that other paper's
 * directory. The action is dispatched for real against a mocked database.
 */
final class CommentsControllerRemoveFileDocidTest extends TestCase
{
    private const COMMENT_DOCID = 12;
    private const OTHER_DOCID = 99;
    private const PCID = 34;

    private Zend_Db_Adapter_Abstract $previousAdapter;

    /** @var MockObject&Zend_Db_Adapter_Abstract */
    private $adapter;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/journal/controllers/CommentsController.php';

        $this->previousAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->adapter = $this->getMockBuilder($this->previousAdapter::class)
            ->setConstructorArgs([$this->previousAdapter->getConfig()])
            ->onlyMethods(['fetchRow', 'fetchAll', 'fetchOne', 'fetchCol', 'fetchAssoc', 'query', 'insert', 'update', 'delete'])
            ->getMock();
        Zend_Db_Table_Abstract::setDefaultAdapter($this->adapter);

        (new Zend_Controller_Action_Helper_FlashMessenger())->clearCurrentMessages(Ccsd_View_Helper_Message::MSG_ERROR);
    }

    protected function tearDown(): void
    {
        Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
    }

    /**
     * @return list<string> error messages reported to the user
     */
    private function removeFile(int $requestedDocId): array
    {
        $token = Episciences_Csrf_Helper::generateToken('removefilecomment');

        $paperRow = ['DOCID' => $requestedDocId, 'PAPERID' => $requestedDocId, 'RVID' => RVID, 'UID' => 1, 'STATUS' => 0];
        $statement = $this->createMock(Zend_Db_Statement_Interface::class);
        $statement->method('fetch')->willReturnOnConsecutiveCalls($paperRow, false);
        $statement->method('fetchAll')->willReturn([]);
        $this->adapter->method('query')->willReturn($statement);
        $this->adapter->method('fetchAll')->willReturn([]);
        $this->adapter->method('fetchAssoc')->willReturn([]);
        $this->adapter->method('fetchCol')->willReturn([]);

        // The comment, owned by someone else than the (anonymous) current user
        $this->adapter->method('fetchRow')->willReturn([
            'PCID' => self::PCID,
            'PARENTID' => null,
            'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
            'DOCID' => self::COMMENT_DOCID,
            'UID' => 7,
            'MESSAGE' => 'm',
            'FILE' => null,
            'OPTIONS' => null,
            'WHEN' => '2026-01-01 00:00:00',
        ]);
        $this->adapter->expects(self::never())->method('delete');
        $this->adapter->expects(self::never())->method('update');

        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setMethod('POST')
            ->setPost(['csrf_token_name' => $token['name'], $token['name'] => $token['value']])
            ->setParams(['docid' => (string)$requestedDocId, 'pcid' => (string)self::PCID, 'file' => 'cover.pdf']);

        $controller = new \CommentsController($request, new Zend_Controller_Response_HttpTestCase());
        $controller->getHelper('redirector')->setExit(false);
        $controller->removefilecommentAction();

        return $controller->getHelper('FlashMessenger')->getCurrentMessages(Ccsd_View_Helper_Message::MSG_ERROR);
    }

    public function testCommentOfAnotherPaperIsReportedAsMissing(): void
    {
        $messages = $this->removeFile(self::OTHER_DOCID);

        self::assertSame(['Le commentaire demandé n’existe pas.'], $messages);
    }

    public function testCommentOfTheRequestedPaperGoesOnToTheOwnershipCheck(): void
    {
        $messages = $this->removeFile(self::COMMENT_DOCID);

        self::assertSame(["Vous n'avez pas les autorisations nécessaires pour supprimer ce fichier."], $messages);
    }
}
