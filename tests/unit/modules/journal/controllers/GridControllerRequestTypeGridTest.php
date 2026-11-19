<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Acl;
use Episciences_GridsManager;
use unit\modules\ControllerTestCase;

/**
 * Grid of a review request type (#319): only a type of the journal gets one.
 */
final class GridControllerRequestTypeGridTest extends ControllerTestCase
{
    private const OTHER_JOURNAL_TYPE_ID = 31;

    protected function setUp(): void
    {
        self::loadController('journal', 'GridController');

        // the action redirects to the grid list through the default route
        $router = \Zend_Controller_Front::getInstance()->getRouter();
        if (!$router->hasRoute('default')) {
            $router->addDefaultRoutes();
        }

        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $this->db->respondTo('/FROM `review_request_type` WHERE \(id = /', [[
            'id' => self::OTHER_JOURNAL_TYPE_ID, 'rvid' => RVID + 1, 'default_type' => 2, 'labels' => '{"en":"Quick opinion"}',
            'light_answer' => 1, 'position' => 2, 'enabled' => 1,
        ]]);
    }

    public function testNoGridIsCreatedForATypeOfAnotherJournal(): void
    {
        $gridFile = REVIEW_GRIDS_PATH . Episciences_GridsManager::filenameOf(Episciences_GridsManager::requestTypeGridKey(self::OTHER_JOURNAL_TYPE_ID));

        $result = $this->dispatch(\GridController::class, 'add', $this->postRequest(['request_type' => (string)self::OTHER_JOURNAL_TYPE_ID, 'submit' => '1'], true));

        self::assertNotNull($result->redirectUrl());
        self::assertFileDoesNotExist($gridFile);

        /** @var \Zend_Controller_Action_Helper_FlashMessenger $flash */
        $flash = \Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $messages = $flash->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->getCurrentMessages();
        $flash->clearCurrentMessages();
        self::assertNotEmpty($messages);
    }
}
