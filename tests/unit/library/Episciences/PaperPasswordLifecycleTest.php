<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use PHPUnit\Framework\TestCase;

final class PaperPasswordLifecycleTest extends TestCase
{
    public function testSavingPublishedPaperAlwaysClearsPassword(): void
    {
        $source = (string)file_get_contents(LIBRARY_PATH . '/Episciences/Paper.php');
        $saveStart = strpos($source, 'public function save(): bool');
        self::assertNotFalse($saveStart);
        $saveSource = substr($source, $saveStart, 1200);

        self::assertStringContainsString('self::STATUS_PUBLISHED', $saveSource);
        self::assertStringContainsString('$this->setPassword()', $saveSource);
    }

    public function testManagementViewEscapesDecryptedPassword(): void
    {
        $source = (string)file_get_contents(
            APPLICATION_PATH . '/modules/journal/views/scripts/partials/paper_password_form.phtml'
        );

        self::assertStringContainsString(
            "htmlspecialchars((string)\$this->paperPassword, ENT_QUOTES, 'UTF-8')",
            $source
        );
        self::assertStringContainsString('paperPasswordDecryptionFailed', $source);
    }

    public function testManagementControllerDecryptsStoredPasswordForAuthorizedManagers(): void
    {
        $source = (string)file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php'
        );

        self::assertStringContainsString('$plainPaperPassword = $this->getPlainPaperPassword($paper)', $source);
        self::assertStringContainsString('$this->view->paperPassword = $plainPaperPassword', $source);
    }
}
