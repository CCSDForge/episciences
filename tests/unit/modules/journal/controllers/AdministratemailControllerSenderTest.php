<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Auth;
use Zend_Auth_Storage_Interface;
use Zend_Auth_Storage_NonPersistent;
use Zend_Controller_Request_HttpTestCase;
use Zend_Controller_Response_HttpTestCase;

/**
 * Sender of the mails of the mailing form (AdministratemailController::buildMail()).
 *
 * The From, Reply-To and sender UID are those of the connected user: the values posted
 * by the client are ignored.
 */
final class AdministratemailControllerSenderTest extends TestCase
{
    private Zend_Auth_Storage_Interface $previousAuthStorage;

    protected function setUp(): void
    {
        $this->previousAuthStorage = Zend_Auth::getInstance()->getStorage();
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        $sender = $this->createMock(\Episciences_User::class);
        $sender->method('getUid')->willReturn(1);
        $sender->method('getScreenName')->willReturn('Sender');
        $sender->method('getEmail')->willReturn('sender@example.org');
        $sender->method('getFullName')->willReturn('Sender Name');
        Zend_Auth::getInstance()->getStorage()->write($sender);

        require_once APPLICATION_PATH . '/modules/journal/controllers/AdministratemailController.php';
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->setStorage($this->previousAuthStorage);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function buildMail(array $post): \Episciences_Mail
    {
        $controller = new \AdministratemailController(
            new Zend_Controller_Request_HttpTestCase(),
            new Zend_Controller_Response_HttpTestCase()
        );
        $recipients = [
            'to' => [['value' => 'Recipient <recipient@example.org>']],
            'cc' => [],
            'bcc' => [],
        ];

        $method = new ReflectionMethod($controller, 'buildMail');

        return $method->invoke($controller, $post, $recipients, null);
    }

    public function testTheMailIsSentFromTheJournalOnBehalfOfTheConnectedUser(): void
    {
        $mail = $this->buildMail(['subject' => 'Subject', 'content' => 'Content']);

        self::assertSame(RVCODE . '@' . DOMAIN, $mail->getFrom());
        self::assertSame('sender@example.org', $mail->getReplyTo());
        self::assertSame(1, $mail->getUid());
        self::assertStringContainsString('Sender Name', implode(' ', $mail->getHeaders()['From']));
    }

    public function testPostedSenderValuesAreIgnored(): void
    {
        $mail = $this->buildMail([
            'subject' => 'Subject',
            'content' => 'Content',
            'from' => 'Someone <ceo@other.org>',
            'replyto' => 'Someone <ceo@other.org>',
            'reply-to' => 'Someone <ceo@other.org>',
            'sender' => '999',
        ]);

        self::assertSame(RVCODE . '@' . DOMAIN, $mail->getFrom());
        self::assertSame('sender@example.org', $mail->getReplyTo());
        self::assertSame(1, $mail->getUid());
    }
}
