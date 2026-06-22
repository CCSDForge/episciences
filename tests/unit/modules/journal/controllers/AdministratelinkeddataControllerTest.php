<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AdministratelinkeddataController.
 * Ensures PHP 8.1 deprecations are fixed and security checks are in place.
 *
 * @covers AdministratelinkeddataController
 */
class AdministratelinkeddataControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/AdministratelinkeddataController.php'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName);
        $this->assertNotFalse($start, "Method $methodName not found in AdministratelinkeddataController");

        $end = strpos($this->source, 'function ', $start + strlen('function ' . $methodName));

        return $end === false
            ? substr($this->source, $start)
            : substr($this->source, $start, $end - $start);
    }

    /**
     * @covers AdministratelinkeddataController::removeldAction
     */
    public function testRemoveldActionFixesDeprecations(): void
    {
        $method = $this->extractMethod('removeldAction');

        $this->assertStringContainsString(
            "trim(\$request->getPost('paperId') ?? '')",
            $method,
            'removeldAction must use null coalescing operator with trim() to avoid PHP 8.1 deprecation'
        );

        $this->assertStringContainsString(
            "if (!\$datasetInDb instanceof Episciences_Paper_Dataset)",
            $method,
            'removeldAction must check if $datasetInDb is a valid instance'
        );

        $this->assertStringContainsString(
            "htmlspecialchars(\$datasetInDb->getName() ?? '', ENT_QUOTES, 'UTF-8')",
            $method,
            'removeldAction must use null coalescing operator with htmlspecialchars() to avoid PHP 8.1 deprecation'
        );
    }

    /**
     * @covers AdministratelinkeddataController::addldAction
     */
    public function testAddldActionFixesDeprecations(): void
    {
        $method = $this->extractMethod('addldAction');

        $this->assertStringContainsString(
            "trim(\$this->getRequest()->getPost('typeld') ?? '')",
            $method,
            'addldAction must use null coalescing operator with trim() to avoid PHP 8.1 deprecation'
        );
    }

    /**
     * @covers AdministratelinkeddataController::setnewinfoldAction
     */
    public function testSetnewinfoldActionFixesDeprecations(): void
    {
        $method = $this->extractMethod('setnewinfoldAction');

        $this->assertStringContainsString(
            "trim(\$request->getPost('relationship') ?? '')",
            $method,
            'setnewinfoldAction must use null coalescing operator with trim() to avoid PHP 8.1 deprecation'
        );
    }
}
