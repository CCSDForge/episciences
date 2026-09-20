<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlternativePipelinePresentationTest extends TestCase
{
    protected function tearDown(): void
    {
        Episciences_Auth::getInstance()->clearIdentity();
    }

    public function testModalRecipientsHaveTagContainersAndJsonValues(): void
    {
        $user = $this->createMock(Episciences_User::class);
        $user->method('getFullName')->willReturn('Editor');
        $user->method('getEmail')->willReturn('editor@example.org');
        Episciences_Auth::getInstance()->getStorage()->write($user);
        $form = Episciences_PapersManager::getAltStartLayoutEditingForm([
            'id' => 123, 'subject' => 'Proof', 'body' => 'Please check', 'to' => 'author@example.org',
        ]);
        foreach (['cc', 'bcc'] as $field) {
            $hidden = $form->getElement('hidden_' . $field);
            self::assertNotNull($hidden);
            self::assertSame([], json_decode($hidden->getValue(), true));
            self::assertSame('', $form->getElement($field)->getValue());
            self::assertSame('alt-start-layout-editing-form-' . $field . '-tags',
                $form->getElement($field)->getDecorator('openDiv')->getOption('id'));
        }
    }

    public function testAuthorApprovedStatusHasColorAndEnglishLabel(): void
    {
        $colors = Episciences_View_Helper_GetAvatar::getPaperStatusColors();
        self::assertSame('#175732', $colors[Episciences_Paper::STATUS_ALT_AUTHOR_PROOF_APPROVED]);
        $translations = require APPLICATION_PATH . '/languages/en/tags.php';
        self::assertSame('proof approved by author', $translations[
            Episciences_Paper::$_statusLabel[Episciences_Paper::STATUS_ALT_AUTHOR_PROOF_APPROVED]
        ]);
    }
}
