<?php

namespace unit\library\Episciences\Form\Validate;

use Episciences_Form_Validate_MimeType;
use PHPUnit\Framework\TestCase;

/**
 * @group Form_Validate_MimeType
 */
class MimeTypeTest extends TestCase
{

    protected Episciences_Form_Validate_MimeType $validator;

    // Temporary directory for test files
    protected string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        //Initialize the validator with default allowed Mime types
        $this->validator = new Episciences_Form_Validate_MimeType();

        // Create a clean temporary folder
        $this->tempDir = sys_get_temp_dir() . '/mime_test_' . uniqid('', true);
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    public function tearDown(): void
    {
        // File cleanup
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    /**
     * Helper: Creates a temporary file with specific content
     */
    private function createTempFile($filename, $content): string
    {
        $path = $this->tempDir . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    // Helper: Simulates Zend upload data
    private function mockFileData($tmpPath, $originalName, $mimeType = null): array
    {
        return [
                'name' => $originalName,
                'type' => $mimeType, // It may be null; the validator will detect this
                'tmp_name' => $tmpPath,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpPath)
        ];
    }

    // Test 1: Valid PDF file
    public function testValidPdfFile(): void
    {
        // A simple way to digitally sign a PDF
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $path = $this->createTempFile('test.pdf', $pdfContent);

        $fileData = $this->mockFileData($path, 'document.pdf');

        $this->assertTrue($this->validator->isValid($path, $fileData));
        $this->assertEmpty($this->validator->getMessages(), "A valid PDF should not generate any errors.");
    }

    // Test 2: Valid text file
    public function testValidTextFile(): void
    {
        $content = "This is a plain text file.\nLigne 2.";
        $path = $this->createTempFile('test.txt', $content);
        $fileData = $this->mockFileData($path, 'notes.txt');
        $this->assertTrue($this->validator->isValid($path, $fileData));
    }

    // Test 3: Valid JPEG file
    public function testValidJpegFile(): void
    {
        $jpegHeader = pack('H*', 'ffd8ffe000104a46494600010100000100010000');
        $path = $this->createTempFile('image.jpg', $jpegHeader . str_repeat("\x00", 100));

        $fileData = $this->mockFileData($path, 'photo.jpg');

        $this->assertTrue($this->validator->isValid($path, $fileData));
    }

    public function testValidDocxWithCorrectMimeType(): void
    {
        // valid content: ZIP signature ("PK") + a few bytes of padding
        // A real .docx file is a ZIP archive containing XML.
        // Here, we're just simulating the header.
        $zipSignature = pack('H*', '504b0304'); // "PK\x03\x04"
        $validContent = $zipSignature . str_repeat("\x00", 200);

        $path = $this->createTempFile('document.docx', $validContent);

        $fileData = $this->mockFileData(
                $path,
                'document.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );


        $this->assertTrue(
                $this->validator->isValid($path, $fileData),
                "A valid DOCX file with the correct MIME type must be accepted"
        );
    }

    public function testValidDocxFileWithoutMimeType(): void
    {
        // A .docx file is actually a ZIP file, so it starts with "PK"
        // ZIP signature: 0x50 0x4B 0x03 0x04
        $docxHeader = pack('H*', '504b0304');

        // added a valid minimal ZIP file
        $path = $this->createTempFile('document.docx',
                $docxHeader . str_repeat("\x00", 100)
        );

        $fileData = $this->mockFileData($path, 'document.docx');

        $this->assertTrue($this->validator->isValid($path, $fileData));
    }

    public function testInvalidOctetStreamFile(): void
    {

        $binaryContent = pack('H*', '89504e470d0a1a0a');

        $path = $this->createTempFile('data.bin',
                $binaryContent . str_repeat("\xFF", 100)
        );

        $fileData = $this->mockFileData($path, 'data.bin', 'application/octet-stream');

        $this->assertFalse($this->validator->isValid($path, $fileData));
    }
    
}
