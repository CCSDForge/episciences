<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlternativePipelineEmailDeliveryTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 5);
    }

    public function testFinalVersionSubmissionNotifiesAuthorAndLayoutEditor(): void
    {
        $source = file_get_contents(
            $this->projectRoot . '/application/modules/journal/controllers/PaperController.php'
        );

        self::assertStringContainsString('notifyAltFinalVersionSubmitted($paper, $post)', $source);
        self::assertStringContainsString('TYPE_PAPER_ALT_FINAL_VERSION_DEPOSIT_AUTHOR_COPY', $source);
        self::assertStringContainsString('TYPE_PAPER_ALT_FINAL_VERSION_DEPOSIT_EDITOR_COPY', $source);
    }

    public function testStartLayoutEditingTargetsTheAuthor(): void
    {
        $source = file_get_contents(
            $this->projectRoot . '/application/modules/journal/controllers/AdministratepaperController.php'
        );
        $start = strpos($source, 'public function altstartlayouteditingAction');
        $request = strpos($source, 'public function altrequestfinalversionAction');
        $method = substr($source, $start, $request - $start);

        self::assertStringContainsString("'author'", $method);
        self::assertStringNotContainsString("'copyEditors'", $method);
    }

    public function testProofResponsesUseTheSharedManagerRecipientResolver(): void
    {
        $source = file_get_contents(
            $this->projectRoot . '/application/modules/journal/controllers/PaperController.php'
        );

        self::assertStringContainsString(
            'Episciences_PapersManager::getAlternativePipelineManagerRecipients($paper)',
            $source
        );
    }

    public function testFailedMailIsNotLoggedAsSent(): void
    {
        $source = file_get_contents(
            $this->projectRoot . '/application/modules/common/controllers/PaperDefaultController.php'
        );
        $write = strpos($source, 'if (!$mail->writeMail())');
        $log = strpos($source, 'Episciences_Paper_Logger::CODE_MAIL_SENT', $write);

        self::assertNotFalse($write);
        self::assertNotFalse($log);
        self::assertStringContainsString('return false;', substr($source, $write, $log - $write));
    }
}
