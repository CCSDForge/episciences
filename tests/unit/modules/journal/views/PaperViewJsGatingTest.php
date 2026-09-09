<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;

/**
 * Guards the anonymous-visitor JS payload reduction on paper/view.phtml: TinyMCE,
 * FilePond and the other editor-only scripts must stay behind an
 * Episciences_Auth::isLogged() gate, while biblioRef.js (public references/citations
 * panel) must stay unconditional.
 *
 * @coversNothing
 */
final class PaperViewJsGatingTest extends TestCase
{
    /**
     * Source snippets rather than resolved constant values (VENDOR_TINYMCE / TINYMCE_DIR aren't
     * defined by the test bootstrap, only by public/const.php's defineVendorJsLibraries(), which
     * isn't invoked there) — matches the structural-source-check style already used for this kind
     * of gating assertion (see RequestTokenRenderingTest).
     *
     * @return list<string>
     */
    private function editorOnlyScripts(): array
    {
        return [
            'addJavascriptFile(VENDOR_TINYMCE)',
            'TINYMCE_DIR . "tinymce_patch.js"',
            "addJavascriptFile('/js/common/visualDeleteFile.js')",
            "addJavascriptFile('/js/paper/copy_editing_form.js')",
            "addJavascriptFile('/js/paper/updateOrcidAuthors.js')",
            "addJavascriptFile('/js/common/updateMetaData.js')",
            "addJavascriptFile('/js/user/affiliations.js')",
            "addJavascriptFile('/js/components/collapsible-message.js')",
        ];
    }

    private function fileContents(): string
    {
        $path = APPLICATION_PATH . '/modules/journal/views/scripts/paper/view.phtml';
        self::assertFileExists($path);
        return (string) file_get_contents($path);
    }

    public function testEditorOnlyScriptsAreGuardedByIsLoggedCheck(): void
    {
        $view = $this->fileContents();

        $guardPos = strpos($view, 'if (Episciences_Auth::isLogged())');
        self::assertNotFalse($guardPos, 'the editor-only scripts must be wrapped in an Episciences_Auth::isLogged() check');

        $closingBracePos = strpos($view, "\n}", $guardPos);
        self::assertNotFalse($closingBracePos, 'the isLogged() block must be closed');

        foreach ($this->editorOnlyScripts() as $script) {
            $scriptPos = strpos($view, $script);
            self::assertNotFalse($scriptPos, "expected to find '$script' queued in paper/view.phtml");
            self::assertGreaterThan($guardPos, $scriptPos, "'$script' must be queued after the isLogged() guard");
            self::assertLessThan($closingBracePos, $scriptPos, "'$script' must be queued inside the isLogged() block");
        }
    }

    public function testBiblioRefScriptIsNotGuarded(): void
    {
        $view = $this->fileContents();

        $guardPos = strpos($view, 'if (Episciences_Auth::isLogged())');
        self::assertNotFalse($guardPos);
        $closingBracePos = strpos($view, "\n}", $guardPos);
        self::assertNotFalse($closingBracePos);

        $biblioRefPos = strpos($view, "addJavascriptFile('/js/paper/biblioRef.js')");
        self::assertNotFalse($biblioRefPos, 'biblioRef.js must be queued in paper/view.phtml');
        self::assertGreaterThan(
            $closingBracePos,
            $biblioRefPos,
            'biblioRef.js must be queued outside the isLogged() block — it drives the public references panel, visible to anonymous visitors'
        );
    }
}