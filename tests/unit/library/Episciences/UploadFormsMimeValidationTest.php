<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Form_Validate_MimeType;
use PHPUnit\Framework\TestCase;
use Zend_Form;

/**
 * The upload forms check the real content type of the file, not only its extension.
 */
final class UploadFormsMimeValidationTest extends TestCase
{
    private const SOURCES = [
        'Rating/Manager.php' => 1,
        'CommentsManager.php' => 4,
        'Submit.php' => 3,
        'ReviewersManager.php' => 1,
    ];

    public function testEveryUploadFormAddsTheMimeTypeValidator(): void
    {
        foreach (self::SOURCES as $file => $expected) {
            $source = (string) file_get_contents(APPLICATION_PATH . '/../library/Episciences/' . $file);

            self::assertSame(
                $expected,
                substr_count($source, 'new Episciences_Form_Validate_MimeType('),
                $file
            );
            self::assertSame(
                $expected,
                preg_match_all("/'Extension'\s*=>\s*(array\(|\[)false/", $source),
                $file . ': every extension check goes with a content type check'
            );
        }
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

    public function testSvgIsRejectedEvenWithAnAllowedExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script></svg>');

        try {
            $validator = new Episciences_Form_Validate_MimeType([
                Episciences_Form_Validate_MimeType::ALLOWED_MIME_TYPE_KEY => ['image/png', 'application/pdf'],
            ]);

            self::assertFalse($validator->isValid($path, ['name' => 'figure.png', 'tmp_name' => $path, 'type' => 'image/png']));
        } finally {
            unlink($path);
        }
    }
}
