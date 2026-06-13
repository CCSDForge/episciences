<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Source-level checks that the user management views emit the request tokens
 * consumed by saverolesAction (roles form) and deleteAction (user lists).
 *
 * @coversNothing
 */
final class UserViewTokenRenderingTest extends TestCase
{
    private function read(string $relativePath): string
    {
        return (string) file_get_contents(APPLICATION_PATH . $relativePath);
    }

    public function testRolesFormRendersTheToken(): void
    {
        $view = $this->read('/modules/common/views/scripts/user/roles_form.phtml');
        self::assertStringContainsString('type="hidden"', $view,
            'roles_form.phtml must emit a hidden input');
        self::assertStringContainsString("\$this->csrfToken['name']", $view,
            'the hidden input name must come from the token');
        self::assertStringContainsString("\$this->csrfToken['value']", $view,
            'the hidden input value must come from the token');
    }

    public function testCommonUserListRendersPerRowDeleteToken(): void
    {
        $view = $this->read('/modules/common/views/scripts/user/list.phtml');
        self::assertStringContainsString("Episciences_Csrf_Helper::generateToken('user_delete_'", $view,
            'list.phtml must generate a per-user delete token');
        self::assertStringContainsString('data-csrf-name', $view,
            'list.phtml must expose the token name on the delete control');
        self::assertStringContainsString('data-csrf-value', $view,
            'list.phtml must expose the token value on the delete control');
    }

    public function testPortalUserListRendersPerRowDeleteToken(): void
    {
        $view = $this->read('/modules/portal/views/scripts/user/list.phtml');
        self::assertStringContainsString("Episciences_Csrf_Helper::generateToken('user_delete_'", $view,
            'portal list.phtml must generate a per-user delete token');
        self::assertStringContainsString('data-csrf-name', $view,
            'portal list.phtml must expose the token name on the delete control');
    }
}
