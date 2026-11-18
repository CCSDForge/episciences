<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Acl;
use Episciences_GridsManager;
use unit\modules\ControllerTestCase;

/**
 * Grid of a review request type (#319): a type answered without an account uses no grid, none is created for it.
 */
final class GridControllerRequestTypeGridTest extends ControllerTestCase
{
    private const LIGHT_TYPE_ID = 31;

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
            'id' => self::LIGHT_TYPE_ID, 'rvid' => RVID, 'default_type' => 2, 'labels' => '{"en":"Quick opinion"}',
            'light_answer' => 1, 'position' => 2, 'enabled' => 1,
        ]]);
    }

    public function testNoGridIsCreatedForATypeAnsweredWithoutAnAccount(): void
    {
        $gridFile = REVIEW_GRIDS_PATH . Episciences_GridsManager::filenameOf(Episciences_GridsManager::requestTypeGridKey(self::LIGHT_TYPE_ID));

        $result = $this->dispatch(\GridController::class, 'add', $this->postRequest(['request_type' => (string)self::LIGHT_TYPE_ID, 'submit' => '1'], true));

        self::assertNotNull($result->redirectUrl());
        self::assertFileDoesNotExist($gridFile);

        /** @var \Zend_Controller_Action_Helper_FlashMessenger $flash */
        $flash = \Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $messages = $flash->setNamespace(Ccsd_View_Helper_Message::MSG_ERROR)->getCurrentMessages();
        $flash->clearCurrentMessages();
        self::assertNotEmpty($messages);
    }
}
