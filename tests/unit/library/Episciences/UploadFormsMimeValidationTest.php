<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences\Upload\MimeTypePolicy;
use Episciences_Form_Validate_MimeType;
use Episciences_Website_Navigation_Page_File;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Zend_Form;

/**
 * The upload forms check the real content type of the file, not only its extension.
 */
final class UploadFormsMimeValidationTest extends TestCase
{
    private const CONFIGURATION = '/configs/journal.configurable.constants.json';

    /**
     * Content types that run script in the browser must never be accepted
     */
    private const ACTIVE_CONTENT_TYPES = ['image/svg+xml', 'text/xml', 'application/xml', 'application/xhtml+xml', 'text/javascript', 'application/javascript'];

    /**
     * Every form element that checks the extension of an uploaded file also checks its content
     */
    public function testEveryExtensionCheckGoesWithAContentCheck(): void
    {
        $checked = 0;

        foreach ($this->phpSources() as $path => $source) {
            $extensionChecks = preg_match_all("/'Extension'\s*=>\s*(array\(|\[)\s*false/", $source);

            if ($extensionChecks === 0) {
                continue;
            }

            $checked++;
            self::assertGreaterThanOrEqual(
                $extensionChecks,
                preg_match_all('/new\s+Episciences_Form_Validate_MimeType\s*\(/', $source),
                $path . ': every extension check goes with a content type check'
            );
        }

        self::assertGreaterThan(0, $checked, 'the scan found the upload forms');
    }

    public function testFileElementKeepsBothTheExtensionAndTheMimeTypeValidators(): void
    {
        $form = new Zend_Form();
        $form->addElement('file', 'attachment', [
            'validators' => [
                'Count' => [false, 1],
                'Extension' => [false, 'pdf,png'],
                new Episciences_Form_Validate_MimeType(),
            ],
        ]);

        $element = $form->getElement('attachment');

        self::assertNotNull($element->getValidator('Extension'));
        self::assertInstanceOf(Episciences_Form_Validate_MimeType::class, $element->getValidator(Episciences_Form_Validate_MimeType::class));
    }

    public function testEveryAllowedExtensionHasContentTypesAndNothingElse(): void
    {
        $config = $this->loadConfiguration();
        $policy = MimeTypePolicy::fromJsonFile(APPLICATION_PATH . self::CONFIGURATION);

        self::assertEqualsCanonicalizing($config['allowed_extensions'], $policy->extensions(), 'allowed_extensions and allowed_mimes_by_extension list the same extensions');

        foreach ($config['allowed_extensions'] as $extension) {
            self::assertTrue($policy->isExtensionAllowed($extension), $extension);
        }
    }

    public function testConfiguredTypesDoNotAllowActiveContent(): void
    {
        $policy = MimeTypePolicy::fromJsonFile(APPLICATION_PATH . self::CONFIGURATION);

        foreach (self::ACTIVE_CONTENT_TYPES as $type) {
            self::assertNotContains($type, $policy->mimeTypes(), $type);
        }
    }

    /**
     * A generic container type is accepted only for the extensions that really use it
     */
    public function testContainerTypesAreNotSharedWithUnrelatedExtensions(): void
    {
        $policy = MimeTypePolicy::fromJsonFile(APPLICATION_PATH . self::CONFIGURATION);

        foreach (['png', 'pdf', 'txt', 'tex'] as $extension) {
            self::assertFalse($policy->accepts($extension, 'application/CDFV2'), $extension);
            self::assertFalse($policy->accepts($extension, 'application/zip'), $extension);
        }

        self::assertTrue($policy->accepts('doc', 'application/CDFV2'));
        self::assertTrue($policy->accepts('docx', 'application/zip'));
    }

    public function testRuntimeConstantsComeFromTheConfiguration(): void
    {
        if (!defined('ALLOWED_MIMES_BY_EXTENSION') || ALLOWED_MIMES_BY_EXTENSION === []) {
            self::markTestSkipped('The journal constants are not defined in this environment');
        }

        $policy = MimeTypePolicy::fromJsonFile(APPLICATION_PATH . self::CONFIGURATION);

        self::assertSame($policy->toArray(), ALLOWED_MIMES_BY_EXTENSION);
        self::assertEqualsCanonicalizing($policy->mimeTypes(), ALLOWED_MIMES_TYPES);
        self::assertEqualsCanonicalizing($policy->extensions(), ALLOWED_EXTENSIONS);
    }

    public function testPageFileUploadIsOptionalWhenNothingWasSent(): void
    {
        self::assertNull(Episciences_Website_Navigation_Page_File::validateUpload(null));
        self::assertNull(Episciences_Website_Navigation_Page_File::validateUpload(['error' => ['src' => UPLOAD_ERR_NO_FILE]]));
    }

    public function testPageFileUploadRefusesAnEmptyFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'page');

        try {
            $message = Episciences_Website_Navigation_Page_File::validateUpload([
                'error' => ['src' => UPLOAD_ERR_OK],
                'tmp_name' => ['src' => $path],
                'name' => ['src' => 'document.pdf'],
            ]);

            self::assertNotNull($message);
            self::assertStringContainsString('document.pdf', $message);
        } finally {
            unlink($path);
        }
    }

    public function testPageFileUploadRefusesAFailedTransfer(): void
    {
        $message = Episciences_Website_Navigation_Page_File::validateUpload([
            'error' => ['src' => UPLOAD_ERR_INI_SIZE],
            'tmp_name' => ['src' => ''],
            'name' => ['src' => 'document.pdf'],
        ]);

        self::assertNotNull($message);
    }

    /**
     * @return \Generator<string, string> path => source
     */
    private function phpSources(): \Generator
    {
        foreach (['/../library/Episciences', '/modules'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APPLICATION_PATH . $directory, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    yield $file->getPathname() => (string)file_get_contents($file->getPathname());
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfiguration(): array
    {
        return json_decode((string)file_get_contents(APPLICATION_PATH . self::CONFIGURATION), true, 512, JSON_THROW_ON_ERROR);
    }
}
