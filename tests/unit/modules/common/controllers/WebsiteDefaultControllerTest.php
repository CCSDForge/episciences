<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests for WebsiteDefaultController and associated views.
 *
 * @covers WebsiteDefaultController
 */
class WebsiteDefaultControllerTest extends TestCase
{
    private string $controllerSource;
    private string $viewSource;
    private string $partialSource;

    protected function setUp(): void
    {
        $this->controllerSource = file_get_contents(
            APPLICATION_PATH . '/modules/common/controllers/WebsiteDefaultController.php'
        );
        $this->viewSource = file_get_contents(
            APPLICATION_PATH . '/modules/common/views/scripts/website/header.phtml'
        );
        $this->partialSource = file_get_contents(
            APPLICATION_PATH . '/modules/common/views/scripts/website/header-logo-form.phtml'
        );
    }

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->controllerSource, 'function ' . $methodName);
        $this->assertNotFalse($start, "Method $methodName not found in WebsiteDefaultController");

        $end = strpos($this->controllerSource, 'function ', $start + strlen('function ' . $methodName));
        if ($end === false) {
            return substr($this->controllerSource, $start);
        }

        return substr($this->controllerSource, $start, $end - $start);
    }

    /**
     * @covers WebsiteDefaultController::ajaxheaderAction
     */
    public function testAjaxheaderActionRendersCorrectPartial(): void
    {
        $method = $this->extractMethod('ajaxheaderAction');

        $this->assertStringContainsString(
            'disableLayout',
            $method,
            'ajaxheaderAction must call disableLayout()'
        );

        $this->assertStringContainsString(
            "render('header-logo-form')",
            $method,
            "ajaxheaderAction must render the 'header-logo-form' partial"
        );

        $this->assertStringNotContainsString(
            'setNoRender',
            $method,
            'ajaxheaderAction should not call setNoRender() since it renders a partial'
        );
    }

    /**
     * Verifies that header.phtml uses the new partial for logo forms.
     */
    public function testHeaderViewUsesPartialForLogoForms(): void
    {
        $this->assertStringContainsString(
            "partial('website/header-logo-form.phtml'",
            $this->viewSource,
            "header.phtml must use the 'website/header-logo-form.phtml' partial"
        );
    }

    /**
     * Verifies that the partial renders multi-language text inputs correctly.
     */
    public function testPartialRendersMultiLangInputs(): void
    {
        $this->assertStringContainsString(
            'foreach ($languages as $lang)',
            $this->partialSource,
            'The partial must iterate over available languages'
        );

        $this->assertStringContainsString(
            '<input type="text"',
            $this->partialSource,
            'The partial must contain text inputs'
        );

        $this->assertStringContainsString(
            'required="required"',
            $this->partialSource,
            'The text inputs must be required in the HTML (but will be toggled by JS if hidden)'
        );

        $this->assertStringContainsString(
            '<span class="text-danger">*</span>',
            $this->partialSource,
            'The text inputs should show a mandatory asterisk since they are required for text logos'
        );
        
        $this->assertStringContainsString(
            'elem-link="<?php echo $this->escape($elemLink) ?>"',
            $this->partialSource,
            'The partial must include the elem-link attribute for dynamic display'
        );

        $this->assertStringContainsString(
            'elem-value="<?php echo $this->escape($elemValue) ?>"',
            $this->partialSource,
            'The partial must include the elem-value attribute for dynamic display'
        );
        
        $this->assertStringContainsString(
            'name="<?php echo $this->escape($belongsTo) ?>[text][<?php echo $this->escape($lang) ?>]"',
            $this->partialSource,
            'The partial must use the correct naming convention for multi-language text inputs'
        );
    }
    
    /**
     * Verifies that header.phtml uses translated error messages.
     */
    public function testHeaderViewUsesTranslatedErrorMessages(): void
    {
        $this->assertStringContainsString(
            "translate('Le champ Libellé est obligatoire dans toutes les langues')",
            $this->viewSource,
            "header.phtml should use a translated key for missing languages error"
        );
    }
}
