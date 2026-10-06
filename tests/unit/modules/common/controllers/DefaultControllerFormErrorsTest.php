<?php

declare(strict_types=1);

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Validation messages can contain the submitted value and are placed in an HTML flash
 * message: renderFormErrors() must escape them (source-pattern analysis).
 */
final class DefaultControllerFormErrorsTest extends TestCase
{
    public function testValidationMessagesAreEscaped(): void
    {
        $source = (string) file_get_contents(APPLICATION_PATH . '/modules/common/controllers/DefaultController.php');

        self::assertStringContainsString(
            "'<code>' . \$this->view->escape(\$this->view->translate(\$v)) . '</code>'",
            $source
        );
    }
}
