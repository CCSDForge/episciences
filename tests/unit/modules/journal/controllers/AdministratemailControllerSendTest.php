<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use AdministratemailController;
use Episciences\Mail\RecipientListValidator;
use Episciences_Acl;
use unit\modules\ControllerTestCase;

/**
 * Mail sent from the modal of the paper page (AdministratemailController::sendAction(), in_modal):
 * a refused mail is not answered as a success, so that the modal keeps the form as typed.
 */
final class AdministratemailControllerSendTest extends ControllerTestCase
{
    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'AdministratemailController');
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private static function modalPost(array $fields): array
    {
        return array_merge([
            'in_modal' => '1',
            'submit' => '1',
            'subject' => 'Hello',
            'content' => '<p>Body</p>',
            'hidden_to' => '[]',
            'hidden_cc' => '[]',
            'hidden_bcc' => '[]',
        ], $fields);
    }

    public function testMailWithoutRecipientIsRefusedWithAnErrorStatus(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);

        $result = $this->dispatch(
            AdministratemailController::class,
            'send',
            $this->xhrPostRequest(self::modalPost([]), true)
        );

        self::assertSame(422, $result->statusCode());
        self::assertStringContainsString("Votre message n'a pas pu être envoyé :", $result->body());
        self::assertStringContainsString('Veuillez saisir au moins un destinataire', $result->body());
    }

    public function testTooManyRecipientsAreRefusedWithAnErrorStatus(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_SECRETARY]);
        $bcc = [];
        for ($i = 0; $i <= RecipientListValidator::MAX_TOTAL; $i++) {
            $bcc[] = ['key' => 'k' . $i, 'value' => 'user' . $i . '@example.org', 'uid' => null];
        }

        $result = $this->dispatch(
            AdministratemailController::class,
            'send',
            $this->xhrPostRequest(self::modalPost(['hidden_bcc' => (string)json_encode($bcc)]), true)
        );

        self::assertSame(422, $result->statusCode());
        self::assertStringContainsString('Trop de destinataires (100 au maximum).', $result->body());
    }
}
