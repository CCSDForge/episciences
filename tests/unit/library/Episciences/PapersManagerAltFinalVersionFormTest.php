<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PapersManagerAltFinalVersionFormTest extends TestCase
{
    /** @dataProvider passwords */
    public function testDepositFormUsesVersionAndPasswordState(?string $password, bool $required): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(123);
        $paper->method('getVersion')->willReturn(3.0);
        $paper->method('getPassword')->willReturn($password);
        $form = Episciences_PapersManager::getAltFinalVersionDepositForm($paper);
        self::assertSame('3', $form->getElement('version')->getValue());
        self::assertSame($required, $form->getElement('paperPassword')->isRequired());
        self::assertSame('/paper/savefinalversiondeposit/id/123', $form->getAction());
        self::assertNotNull($form->getElement('csrf_finalversiondeposit_123'));
    }

    public static function passwords(): iterable
    {
        yield 'missing' => [null, true];
        yield 'stored' => ['encrypted-password', false];
    }

    public function testDepositFormRestoresVersionAndAttachments(): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getDocid')->willReturn(123);
        $paper->method('getVersion')->willReturn(2.0);
        $form = Episciences_PapersManager::getAltFinalVersionDepositForm($paper, [
            'version' => '4', Episciences_Mail_Send::ATTACHMENTS => ['references.bib'],
        ]);
        self::assertSame('4', $form->getElement('version')->getValue());
        self::assertSame(['references.bib'], $form->getAttrib('preservedAttachments'));
    }
}
