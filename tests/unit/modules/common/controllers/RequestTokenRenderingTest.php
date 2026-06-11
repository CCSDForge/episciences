<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Rendering / wiring checks for the per-session request token.
 *
 * The token is exposed as a meta tag by the main layout (for authenticated
 * users only, so that anonymous page views do not create a session) and read
 * back by the JavaScript callers of the mutating endpoints.
 *
 * @coversNothing
 */
final class RequestTokenRenderingTest extends TestCase
{
    private function fileContents(string $relativePath): string
    {
        $path = dirname(APPLICATION_PATH) . '/' . ltrim($relativePath, '/');
        self::assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The layout must render the csrf-token meta tag, escaped, and only for
     * authenticated users.
     */
    public function testLayoutRendersTheTokenMetaForAuthenticatedUsersOnly(): void
    {
        $layout = $this->fileContents('application/modules/common/views/layout/layout.phtml');

        $metaPos = strpos($layout, '<meta name="csrf-token"');
        self::assertNotFalse($metaPos, 'the layout must render the csrf-token meta tag');

        $guardPos = strpos($layout, 'Episciences_Auth::isLogged()');
        self::assertNotFalse($guardPos, 'the meta tag must be wrapped in an authentication check');
        self::assertLessThan($metaPos, $guardPos,
            'the authentication check must wrap the meta tag');

        self::assertStringContainsString(
            'escape(Episciences_Csrf_Helper::getSessionToken())',
            $layout,
            'the token must be escaped and come from the session token helper'
        );
    }

    /**
     * The upload widget must send the token with every upload/delete call.
     */
    public function testFileUploadWidgetSendsTheToken(): void
    {
        $js = $this->fileContents('public/js/library/es.fileupload.js');

        self::assertStringContainsString('meta[name="csrf-token"]', $js,
            'the widget must read the token from the meta tag');
        self::assertStringContainsString('formData.csrf_token', $js,
            'the token must be part of the form data sent to /file/upload and /file/delete');
    }

    /**
     * The graphical-abstract calls must send the token with both mutating calls.
     */
    public function testGraphicalAbstractCallsSendTheToken(): void
    {
        $js = $this->fileContents('public/js/paper/graphicalAbstract.js');

        self::assertStringContainsString('meta[name="csrf-token"]', $js,
            'the script must read the token from the meta tag');

        self::assertSame(2, substr_count($js, 'appendRequestToken(form_data)'),
            'both the add and the delete call must append the token');
        self::assertStringContainsString("formData.append('csrf_token'", $js,
            'the token must be appended as the csrf_token field');
    }
}
