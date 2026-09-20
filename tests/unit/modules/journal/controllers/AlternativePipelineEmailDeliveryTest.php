<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

final class AlternativePipelineEmailDeliveryTest extends TestCase
{
    /** @dataProvider deliveryResults */
    public function testMailIsLoggedOnlyAfterSuccessfulDelivery(bool $sent): void
    {
        $mail = $this->createMock(Episciences_Mail::class);
        $mail->method('setTo')->willReturn(true);
        $mail->method('writeMail')->willReturn($sent);
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->expects($sent ? self::once() : self::never())->method('log');
        $controller = $this->getMockBuilder(PaperDefaultController::class)->disableOriginalConstructor()
            ->onlyMethods(['createModalMail'])->getMock();
        $controller->method('createModalMail')->willReturn($mail);
        $method = new ReflectionMethod(PaperDefaultController::class, 'sendMailFromModal');
        $method->setAccessible(true);
        self::assertSame($sent, $method->invoke($controller, $this->createMock(Episciences_User::class), $paper, 'Subject', 'Body', []));
    }

    public static function deliveryResults(): iterable
    {
        yield 'success' => [true];
        yield 'failure' => [false];
    }

    public function testMissingTemplateDoesNotSendOrLogAnEmptyMail(): void
    {
        $mail = $this->createMock(Episciences_Mail::class);
        $mail->method('setTo')->willReturn(true);
        $mail->expects(self::never())->method('writeMail');
        $template = $this->createMock(Episciences_Mail_Template::class);
        $template->method('findByKey')->with('missing-template')->willReturn(false);
        $template->expects(self::never())->method('loadTranslations');
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->expects(self::never())->method('log');
        $controller = $this->getMockBuilder(PaperDefaultController::class)->disableOriginalConstructor()
            ->onlyMethods(['createModalMail', 'createModalMailTemplate'])->getMock();
        $controller->method('createModalMail')->willReturn($mail);
        $controller->method('createModalMailTemplate')->willReturn($template);
        $method = new ReflectionMethod(PaperDefaultController::class, 'sendMailFromModal');
        $method->setAccessible(true);
        $warnings = [];
        set_error_handler(static function ($level, $message) use (&$warnings) {
            $warnings[] = $message;
            return true;
        }, E_USER_NOTICE);
        try {
            self::assertFalse($method->invoke($controller, $this->createMock(Episciences_User::class), $paper, '', '', [], [], 'missing-template'));
            self::assertCount(1, $warnings);
            self::assertStringContainsString('mail not sent', $warnings[0]);
        } finally {
            restore_error_handler();
        }
    }
}
