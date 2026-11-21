<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_View_Helper_Message;
use Episciences_Acl;
use unit\modules\ControllerTestCase;
use Zend_Controller_Action_Helper_FlashMessenger;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Request_HttpTestCase;

/**
 * Sending an invitation again (#319, lot F): nothing is sent without the request token, nor for an unknown invitation.
 * The sending itself is covered by the end-to-end tests (Mailpit).
 */
final class AdministratepaperResendInvitationTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        self::loadController('journal', 'AdministratepaperController');
        $this->givenJournal();
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        self::messages(Ccsd_View_Helper_Message::MSG_ERROR);
        self::messages(Ccsd_View_Helper_Message::MSG_SUCCESS);
    }

    /**
     * @return list<string> messages of this namespace, which are then forgotten
     */
    private static function messages(string $namespace): array
    {
        /** @var Zend_Controller_Action_Helper_FlashMessenger $messenger */
        $messenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');
        $messages = array_values($messenger->setNamespace($namespace)->getCurrentMessages());
        $messenger->clearCurrentMessages();

        return $messages;
    }

    /**
     * @return list<string> tables written by the request
     */
    private function writtenTables(): array
    {
        return array_values(array_unique(array_column($this->db->inserts(), 'table')));
    }

    private function resend(Zend_Controller_Request_HttpTestCase $request): bool
    {
        $result = $this->dispatch(\AdministratepaperController::class, 'resendinvitation', $request);

        return (bool)($result->json()['result'] ?? true);
    }

    public function testNothingIsSentWithoutTheRequestToken(): void
    {
        self::assertFalse($this->resend($this->postRequest(['aid' => '12'])));

        self::assertSame([], $this->writtenTables());
        self::assertSame(['Votre session a expiré, veuillez réessayer.'], self::messages(Ccsd_View_Helper_Message::MSG_ERROR));
        self::assertSame([], self::messages(Ccsd_View_Helper_Message::MSG_SUCCESS));
    }

    public function testNothingIsSentByAGetRequest(): void
    {
        self::assertFalse($this->resend($this->getRequest(['aid' => '12'])));

        self::assertSame([], $this->writtenTables());
    }

    public function testNothingIsSentForAnUnknownInvitation(): void
    {
        self::assertFalse($this->resend($this->postRequest(['aid' => '12'], true)));

        self::assertSame([], $this->writtenTables());
        self::assertSame(["Impossible de retrouver l'invitation"], self::messages(Ccsd_View_Helper_Message::MSG_ERROR));
    }
}
