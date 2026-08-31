<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AlternativePipelineConfigurationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 5);
    }

    public function testPipelineRequiresArxivAsTheOnlyRepository(): void
    {
        $reviewSource = file_get_contents($this->projectRoot . '/library/Episciences/Review.php');

        self::assertStringContainsString('public function isAlternativePipelineAvailable(): bool', $reviewSource);
        self::assertStringContainsString('count($repositories) === 1', $reviewSource);
        self::assertStringContainsString('Episciences_Repositories::ARXIV_REPO_ID', $reviewSource);
        self::assertStringNotContainsString("'disabled' => !\$this->isAlternativePipelineAvailable()", $reviewSource);
    }

    public function testPostedSettingsCannotEnablePipelineForOtherRepositorySelections(): void
    {
        $controllerSource = file_get_contents(
            $this->projectRoot . '/application/modules/journal/controllers/ReviewController.php'
        );

        self::assertStringContainsString('count($selectedRepositories) !== 1', $controllerSource);
        self::assertStringContainsString(
            '$reviewSettingsToSave[Episciences_Review::SETTING_ALTERNATIVE_PIPELINE] = \'0\';',
            $controllerSource
        );
    }

    public function testRuntimeEntryPointsUseTheEligibilityAwareGuard(): void
    {
        foreach ([
            '/application/modules/journal/controllers/AdministratepaperController.php',
            '/application/modules/journal/controllers/PaperController.php',
            '/application/modules/journal/views/scripts/paper/view.phtml',
            '/application/modules/journal/views/scripts/partials/paper_status_button.phtml',
        ] as $relativePath) {
            $source = file_get_contents($this->projectRoot . $relativePath);
            self::assertStringContainsString('isAlternativePipelineEnabled()', $source, $relativePath);
        }
    }

    public function testProofInstructionsPointAuthorsToTheApprovalRequestEmail(): void
    {
        $viewSource = file_get_contents(
            $this->projectRoot . '/application/modules/journal/views/scripts/paper/view.phtml'
        );

        self::assertStringContainsString("lien figurant dans le courriel de demande d'approbation", $viewSource);
        self::assertStringContainsString("consulter l'épreuve", $viewSource);
    }
}
