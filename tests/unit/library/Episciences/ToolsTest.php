<?php

namespace unit\library\Episciences;

use Episciences_Tools;
use PHPUnit\Framework\TestCase;

class ToolsTest extends TestCase
{
    private string $tempDir;
    private array $testFiles = [];

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/episciences_tools_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        foreach ($this->testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function createTestFile(string $filename, string $content = '', string $extension = ''): string
    {
        if ($extension) {
            $filename = $filename . '.' . $extension;
        }
        $filepath = $this->tempDir . '/' . $filename;
        file_put_contents($filepath, $content);
        $this->testFiles[] = $filepath;
        return $filepath;
    }

    public function testGetMimeTypeWithNonExistentFile(): void
    {
        $nonExistentFile = $this->tempDir . '/nonexistent.txt';
        
        // Capture the warning
        $warningTriggered = false;
        set_error_handler(function($severity, $message) use (&$warningTriggered) {
            $warningTriggered = true;
            $this->assertStringContainsString('Unable to read file:', $message);
        });
        
        $result = Episciences_Tools::getMimeType($nonExistentFile);
        
        restore_error_handler();
        $this->assertTrue($warningTriggered);
        
        $this->assertSame('', $result);
    }

    public function testGetMimeTypeWithUnreadableFile(): void
    {
        $file = $this->createTestFile('unreadable', 'test content', 'txt');
        
        // Make file unreadable
        chmod($file, 0000);
        
        // Capture the warning
        $warningTriggered = false;
        set_error_handler(function($severity, $message) use (&$warningTriggered) {
            $warningTriggered = true;
            $this->assertStringContainsString('Unable to read file:', $message);
        });
        
        $result = Episciences_Tools::getMimeType($file);
        
        restore_error_handler();
        $this->assertTrue($warningTriggered);
        
        // Restore permissions for cleanup
        chmod($file, 0644);
        
        $this->assertSame('', $result);
    }

    public function testGetMimeTypeWithTextFile(): void
    {
        $file = $this->createTestFile('test', 'Hello world!', 'txt');
        
        $result = Episciences_Tools::getMimeType($file);
        
        $this->assertStringContainsString('text', $result);
    }

    public function testGetMimeTypeWithHtmlFile(): void
    {
        $htmlContent = '<!DOCTYPE html><html><body><h1>Test</h1></body></html>';
        $file = $this->createTestFile('test', $htmlContent, 'html');
        
        $result = Episciences_Tools::getMimeType($file);
        
        // HTML files should be converted to application/octet-stream
        $this->assertSame('application/octet-stream', $result);
    }

    public function testGetMimeTypeWithZipFile(): void
    {
        // Create a simple ZIP file with content
        $file = $this->createTestFile('test', 'dummy', 'zip');
        
        // Create a simple zip file using PHP's ZipArchive
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFromString('test.txt', 'Test content');
            $zip->close();
        }
        
        $result = Episciences_Tools::getMimeType($file);
        
        // Should return application/zip for regular zip files
        $this->assertSame('application/zip', $result);
    }

    public function testGetMimeTypeWithDocxFile(): void
    {
        // For testing, we'll create a file with .docx extension and zip-like content
        // In real scenarios, this would be a proper DOCX file
        $file = $this->createTestFile('document', 'dummy', 'docx');
        
        // Create a simple zip file (DOCX is zip-based)
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?>');
            $zip->close();
        }
        
        $result = Episciences_Tools::getMimeType($file);
        
        // Should detect as MS Word document
        $this->assertSame('application/msword', $result);
    }

    public function testGetMimeTypeWithXlsxFile(): void
    {
        $file = $this->createTestFile('spreadsheet', 'dummy', 'xlsx');
        
        // Create a simple zip file (XLSX is zip-based)
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?>');
            $zip->close();
        }
        
        $result = Episciences_Tools::getMimeType($file);
        
        // Should detect as MS Excel document
        $this->assertSame('application/vnd.ms-excel', $result);
    }

    public function testGetMimeTypeWithPptxFile(): void
    {
        $file = $this->createTestFile('presentation', 'dummy', 'pptx');
        
        // Create a simple zip file (PPTX is zip-based)
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?>');
            $zip->close();
        }
        
        $result = Episciences_Tools::getMimeType($file);
        
        // Should detect as MS PowerPoint document
        $this->assertSame('application/vnd.ms-powerpoint', $result);
    }

    public function testGetMimeTypeWithOdtFile(): void
    {
        $file = $this->createTestFile('document', 'dummy', 'odt');
        
        // Create a simple zip file (ODT is zip-based)
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFromString('META-INF/manifest.xml', '<?xml version="1.0"?>');
            $zip->close();
        }
        
        $result = Episciences_Tools::getMimeType($file);
        
        // Should detect as OpenDocument
        $this->assertSame('application/opendocument', $result);
    }

    public function testGetMimeTypeWithPdfFile(): void
    {
        // Create a minimal PDF file
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n%%EOF";
        $file = $this->createTestFile('document', $pdfContent, 'pdf');
        
        $result = Episciences_Tools::getMimeType($file);
        
        $this->assertStringContainsString('application/pdf', $result);
    }

    public function testGetMimeTypeWithImageFile(): void
    {
        // Create a more complete PNG file with proper header
        $pngContent = pack('H*', '89504e470d0a1a0a0000000d494844520000000100000001080200000090773df40000000a4944415478da6300010000050001600a4d30000000049454e44ae426082');
        $file = $this->createTestFile('image', $pngContent, 'png');
        
        $result = Episciences_Tools::getMimeType($file);
        
        // Accept both image MIME type or application/octet-stream for simple test images
        $this->assertTrue(
            str_contains($result, 'image') || str_contains($result, 'application/octet-stream'),
            "Expected image MIME type or application/octet-stream, got: $result"
        );
    }

    /**
     * Test the extension method used by getMimeFileZip
     */
    public function testExtensionMethod(): void
    {
        // Test with file path
        $this->assertSame('txt', Episciences_Tools::extension('document.txt'));
        $this->assertSame('pdf', Episciences_Tools::extension('/path/to/file.pdf'));
        $this->assertSame('docx', Episciences_Tools::extension('file.DOCX')); // Should be lowercase
        // Test file without extension - the extension method will return the whole filename
        $result = Episciences_Tools::extension('noextension');
        $this->assertSame('oextension', $result); // Returns substring from position (false + 1) = 0
        
        // Test with actual file
        $file = $this->createTestFile('test', 'content', 'xyz');
        $this->assertSame('xyz', Episciences_Tools::extension($file));
    }

    /**
     * Test getMimeFileZip method directly
     */
    public function testGetMimeFileZipMethod(): void
    {
        // Test OpenDocument formats
        $this->assertSame('application/opendocument', Episciences_Tools::getMimeFileZip('file.odt'));
        $this->assertSame('application/opendocument', Episciences_Tools::getMimeFileZip('file.ods'));
        $this->assertSame('application/opendocument', Episciences_Tools::getMimeFileZip('file.odp'));
        
        // Test Microsoft Office formats
        $this->assertSame('application/msword', Episciences_Tools::getMimeFileZip('file.docx'));
        $this->assertSame('application/msword', Episciences_Tools::getMimeFileZip('file.dotx'));
        $this->assertSame('application/vnd.ms-excel', Episciences_Tools::getMimeFileZip('file.xlsx'));
        $this->assertSame('application/vnd.ms-powerpoint', Episciences_Tools::getMimeFileZip('file.pptx'));
        $this->assertSame('application/vnd.ms-powerpoint', Episciences_Tools::getMimeFileZip('file.ppsx'));
        
        // Test unknown zip file
        $this->assertSame('application/zip', Episciences_Tools::getMimeFileZip('file.zip'));
        $this->assertSame('application/zip', Episciences_Tools::getMimeFileZip('file.unknown'));
    }

    /**
     * Test convertToCamelCase with default parameters
     */
    public function testConvertToCamelCaseDefault(): void
    {
        // Test basic underscore to camelCase conversion
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test_string'));
        $this->assertSame('myVariableName', Episciences_Tools::convertToCamelCase('my_variable_name'));
        $this->assertSame('simpleTest', Episciences_Tools::convertToCamelCase('simple_test'));
        
        // Test real-world variable names
        $this->assertSame('dateCreation', Episciences_Tools::convertToCamelCase('date_creation'));
        $this->assertSame('dateCreation', Episciences_Tools::convertToCamelCase('date_Creation'));
        $this->assertSame('dateCreation', Episciences_Tools::convertToCamelCase('dateCreation')); // already camelCase
        $this->assertSame('datecreation', Episciences_Tools::convertToCamelCase('datecreation')); // all lowercase
        
        // Test common database field names
        $this->assertSame('userId', Episciences_Tools::convertToCamelCase('user_id'));
        $this->assertSame('createdAt', Episciences_Tools::convertToCamelCase('created_at'));
        $this->assertSame('updatedAt', Episciences_Tools::convertToCamelCase('updated_at'));
        $this->assertSame('firstName', Episciences_Tools::convertToCamelCase('first_name'));
        $this->assertSame('lastName', Episciences_Tools::convertToCamelCase('last_name'));
        $this->assertSame('emailAddress', Episciences_Tools::convertToCamelCase('email_address'));
        
        // Test single word
        $this->assertSame('test', Episciences_Tools::convertToCamelCase('test'));
        
        // Test empty string
        $this->assertSame('', Episciences_Tools::convertToCamelCase(''));
    }

    /**
     * Test convertToCamelCase with capitalizeFirstCharacter = true
     */
    public function testConvertToCamelCaseCapitalizeFirst(): void
    {
        // Test with first character capitalization
        $this->assertSame('TestString', Episciences_Tools::convertToCamelCase('test_string', '_', true));
        $this->assertSame('MyVariableName', Episciences_Tools::convertToCamelCase('my_variable_name', '_', true));
        
        // Test single word with capitalization
        $this->assertSame('Test', Episciences_Tools::convertToCamelCase('test', '_', true));
        
        // Test already capitalized
        $this->assertSame('TestString', Episciences_Tools::convertToCamelCase('TestString', '_', true));
    }

    /**
     * Test convertToCamelCase with different separators
     */
    public function testConvertToCamelCaseCustomSeparator(): void
    {
        // Test with dash separator
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test-string', '-'));
        $this->assertSame('myVariableName', Episciences_Tools::convertToCamelCase('my-variable-name', '-'));
        
        // Test with dot separator
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test.string', '.'));
        
        // Test with space separator
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test string', ' '));
        
        // Test with custom separator and capitalization
        $this->assertSame('TestString', Episciences_Tools::convertToCamelCase('test-string', '-', true));
    }

    /**
     * Test convertToCamelCase with stringToRemove parameter
     */
    public function testConvertToCamelCaseWithStringRemoval(): void
    {
        // Test basic string removal
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('prefix_test_string', '_', false, 'prefix_'));
        $this->assertSame('variableName', Episciences_Tools::convertToCamelCase('my_variable_name', '_', false, 'my_'));
        
        // Test string removal with capitalization
        $this->assertSame('TestString', Episciences_Tools::convertToCamelCase('prefix_test_string', '_', true, 'prefix_'));
        
        // Test removing multiple occurrences (str_replace removes ALL occurrences)
        $this->assertSame('string', Episciences_Tools::convertToCamelCase('test_test_string', '_', false, 'test_'));
        
        // Test removing non-existent string (should not affect result)
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test_string', '_', false, 'nonexistent'));
        
        // Test removing empty string (should not affect result)
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test_string', '_', false, ''));
    }

    /**
     * Test convertToCamelCase with uppercase strings
     */
    public function testConvertToCamelCaseWithUppercase(): void
    {
        // Test all uppercase strings (should be converted to lowercase first)
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('TEST_STRING'));
        $this->assertSame('myVariable', Episciences_Tools::convertToCamelCase('MY_VARIABLE'));
        
        // Test mixed case (should preserve mixed case if not all uppercase)
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('Test_String'));
        $this->assertSame('myVariable', Episciences_Tools::convertToCamelCase('My_Variable'));
        
        // Test uppercase with capitalization
        $this->assertSame('TestString', Episciences_Tools::convertToCamelCase('TEST_STRING', '_', true));
    }

    /**
     * Test convertToCamelCase edge cases
     */
    public function testConvertToCamelCaseEdgeCases(): void
    {
        // Test consecutive separators
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test__string'));
        $this->assertSame('myVariableName', Episciences_Tools::convertToCamelCase('my___variable___name'));
        
        // Test starting/ending with separator
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('_test_string'));
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('test_string_'));
        $this->assertSame('testString', Episciences_Tools::convertToCamelCase('_test_string_'));
        
        // Test only separators
        $this->assertSame('', Episciences_Tools::convertToCamelCase('___'));
        
        // Test single character parts
        $this->assertSame('aBC', Episciences_Tools::convertToCamelCase('a_b_c'));
        
        // Test numbers
        $this->assertSame('test123String', Episciences_Tools::convertToCamelCase('test_123_string'));
        $this->assertSame('var1Name2', Episciences_Tools::convertToCamelCase('var_1_name_2'));
    }

    /**
     * Test convertToCamelCase with real-world scenarios (most common usage without stringToRemove)
     */
    public function testConvertToCamelCaseRealWorldScenarios(): void
    {
        // Test typical database/model field names commonly found in this codebase
        $this->assertSame('dateCreation', Episciences_Tools::convertToCamelCase('date_creation'));
        $this->assertSame('dateModification', Episciences_Tools::convertToCamelCase('date_modification'));
        $this->assertSame('datePublication', Episciences_Tools::convertToCamelCase('date_publication'));
        $this->assertSame('dateAcceptation', Episciences_Tools::convertToCamelCase('date_acceptation'));
        $this->assertSame('dateSubmission', Episciences_Tools::convertToCamelCase('date_submission'));
        
        // Test volume/paper related fields
        $this->assertSame('volumeId', Episciences_Tools::convertToCamelCase('volume_id'));
        $this->assertSame('paperPosition', Episciences_Tools::convertToCamelCase('paper_position'));
        $this->assertSame('sectionId', Episciences_Tools::convertToCamelCase('section_id'));
        $this->assertSame('reviewId', Episciences_Tools::convertToCamelCase('review_id'));
        
        // Test user/author related fields
        $this->assertSame('authorId', Episciences_Tools::convertToCamelCase('author_id'));
        $this->assertSame('userRole', Episciences_Tools::convertToCamelCase('user_role'));
        $this->assertSame('isCorrespondingAuthor', Episciences_Tools::convertToCamelCase('is_corresponding_author'));
        
        // Test metadata fields
        $this->assertSame('metaDescription', Episciences_Tools::convertToCamelCase('meta_description'));
        $this->assertSame('metaKeywords', Episciences_Tools::convertToCamelCase('meta_keywords'));
        $this->assertSame('xmlMetadata', Episciences_Tools::convertToCamelCase('xml_metadata'));
        
        // Test configuration fields
        $this->assertSame('configValue', Episciences_Tools::convertToCamelCase('config_value'));
        $this->assertSame('settingName', Episciences_Tools::convertToCamelCase('setting_name'));
        $this->assertSame('defaultValue', Episciences_Tools::convertToCamelCase('default_value'));
        
        // Test mixed case input scenarios that might occur in the real codebase
        $this->assertSame('dateCreation', Episciences_Tools::convertToCamelCase('Date_Creation'));
        $this->assertSame('userRole', Episciences_Tools::convertToCamelCase('User_Role'));
        $this->assertSame('volumeId', Episciences_Tools::convertToCamelCase('Volume_Id'));
        
        // Test with PascalCase conversion (first letter capitalized)
        $this->assertSame('DateCreation', Episciences_Tools::convertToCamelCase('date_creation', '_', true));
        $this->assertSame('UserRole', Episciences_Tools::convertToCamelCase('user_role', '_', true));
        $this->assertSame('VolumeId', Episciences_Tools::convertToCamelCase('volume_id', '_', true));
    }

    /**
     * Test convertToCamelCase with complex combinations
     */
    public function testConvertToCamelCaseComplexCombinations(): void
    {
        // Test all parameters together
        $this->assertSame('TestString', Episciences_Tools::convertToCamelCase('prefix-test-string', '-', true, 'prefix-'));
        $this->assertSame('MyVariableName', Episciences_Tools::convertToCamelCase('OLD_MY_VARIABLE_NAME', '_', true, 'OLD_'));
        
        // Test with different separator and string removal
        $this->assertSame('configValue', Episciences_Tools::convertToCamelCase('app.config.value', '.', false, 'app.'));
        
        // Test uppercase with custom separator and string removal
        $this->assertSame('TestMethod', Episciences_Tools::convertToCamelCase('CLASS::TEST::METHOD', '::', true, 'CLASS::'));
    }

    /**
     * Test replaceAccents function (modern implementation using Unicode normalization)
     */
    public function testReplaceAccents(): void
    {
        // Test basic accent removal
        $this->assertSame('Cafe a la creme', Episciences_Tools::replaceAccents('Café à la crème'));
        $this->assertSame('resume', Episciences_Tools::replaceAccents('résumé'));
        $this->assertSame('naive', Episciences_Tools::replaceAccents('naïve'));
        
        // Test various accented characters from different languages
        $this->assertSame('Andre Muller', Episciences_Tools::replaceAccents('André Müller'));
        $this->assertSame('senor nino', Episciences_Tools::replaceAccents('señor niño'));
        $this->assertSame('Francois Ake', Episciences_Tools::replaceAccents('François Åke'));
        
        // Test comprehensive accent removal (modern Unicode normalization approach)
        $this->assertSame('eeeee', Episciences_Tools::replaceAccents('eèéêë'));
        $this->assertSame('aaaaaaa', Episciences_Tools::replaceAccents('aàáâãäå'));
        $this->assertSame('ooooooø', Episciences_Tools::replaceAccents('oòóôõöø')); // ø is preserved as it's a separate letter, not a diacritic
        $this->assertSame('uuuuu', Episciences_Tools::replaceAccents('uùúûü'));
        $this->assertSame('nnnn', Episciences_Tools::replaceAccents('nñńň')); // n, ñ, ń, ň -> n, n, n, n
        $this->assertSame('ccccc', Episciences_Tools::replaceAccents('çćčĉċ')); // Various c with diacritics
        
        // Test complex multilingual text
        $this->assertSame('Zelazny', Episciences_Tools::replaceAccents('Żelazny')); // Polish
        $this->assertSame('Dvorak', Episciences_Tools::replaceAccents('Dvořák')); // Czech
        $this->assertSame('Bjork', Episciences_Tools::replaceAccents('Björk')); // Swedish
        $this->assertSame('Pena', Episciences_Tools::replaceAccents('Peña')); // Spanish
        
        // Test edge cases
        $this->assertSame('', Episciences_Tools::replaceAccents(''));
        $this->assertSame('hello world', Episciences_Tools::replaceAccents('hello world'));
        $this->assertSame('123 test', Episciences_Tools::replaceAccents('123 test'));
        $this->assertSame('English text without accents', Episciences_Tools::replaceAccents('English text without accents'));
        
        // Test that the function handles whitespace correctly
        $this->assertSame('  spaces  preserved  ', Episciences_Tools::replaceAccents('  spacés  presèrved  '));
        
        // Test combining characters (Unicode normalization strength test)
        $this->assertSame('a', Episciences_Tools::replaceAccents('a' . "\u{0301}")); // a + combining acute accent
        $this->assertSame('e', Episciences_Tools::replaceAccents('e' . "\u{0302}")); // e + combining circumflex
    }

    /**
     * Test decodeLatex function without line break preservation (default behavior)
     */
    public function testDecodeLatexWithoutLineBreaks(): void
    {
        // Test basic LaTeX character replacement
        $input = "Test with \\c{c} and \\k{a} characters.";
        $expected = "Test with ç and ą characters.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input));

        // Test with line breaks - should remain unchanged (no nl2br conversion)
        $input = "Line 1\nLine 2\nLine 3";
        $expected = "Line 1\nLine 2\nLine 3";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input));

        // Test explicit false parameter
        $input = "Test \\l{} with\nnew lines";
        $expected = "Test ł with\nnew lines";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, false));

        // Test empty string
        $this->assertSame('', Episciences_Tools::decodeLatex(''));
        $this->assertSame('', Episciences_Tools::decodeLatex('', false));
    }

    /**
     * Test decodeLatex with all cedilla characters
     */
    public function testDecodeLatexCedilla(): void
    {
        // \c{c} and \c c -> ç
        $this->assertSame('ç', Episciences_Tools::decodeLatex("\\c{c}"));
        $this->assertSame('ç', Episciences_Tools::decodeLatex("\\c c"));
        $this->assertSame('François', Episciences_Tools::decodeLatex("Fran\\c{c}ois"));
    }

    /**
     * Test decodeLatex with ogonek characters
     */
    public function testDecodeLatexOgonek(): void
    {
        // \k{a} and \k a -> ą
        $this->assertSame('ą', Episciences_Tools::decodeLatex("\\k{a}"));
        $this->assertSame('ą', Episciences_Tools::decodeLatex("\\k a"));
    }

    /**
     * Test decodeLatex with barred l (Polish)
     */
    public function testDecodeLatexBarredL(): void
    {
        // \l{} and \l  -> ł
        $this->assertSame('ł', Episciences_Tools::decodeLatex("\\l{}"));
        $this->assertSame('ł', Episciences_Tools::decodeLatex("\\l "));
        $this->assertSame('łódź', Episciences_Tools::decodeLatex("\\l{}ódź"));
    }

    /**
     * Test decodeLatex with dot under letter
     */
    public function testDecodeLatexDotUnder(): void
    {
        // \d{u} and \d u -> ụ
        $this->assertSame('ụ', Episciences_Tools::decodeLatex("\\d{u}"));
        $this->assertSame('ụ', Episciences_Tools::decodeLatex("\\d u"));
    }

    /**
     * Test decodeLatex with ring over letter
     */
    public function testDecodeLatexRing(): void
    {
        // \r{a} and \r a -> å
        $this->assertSame('å', Episciences_Tools::decodeLatex("\\r{a}"));
        $this->assertSame('å', Episciences_Tools::decodeLatex("\\r a"));
        $this->assertSame('åke', Episciences_Tools::decodeLatex("\\r{a}ke"));
    }

    /**
     * Test decodeLatex with caron/háček
     */
    public function testDecodeLatexCaron(): void
    {
        // \v{s} -> š, \v{r} -> ř
        $this->assertSame('š', Episciences_Tools::decodeLatex("\\v{s}"));
        $this->assertSame('š', Episciences_Tools::decodeLatex("\\v s"));
        $this->assertSame('ř', Episciences_Tools::decodeLatex("\\v{r}"));
        $this->assertSame('ř', Episciences_Tools::decodeLatex("\\v r"));
        $this->assertSame('Dvořák', Episciences_Tools::decodeLatex("Dvo\\v{r}ák"));
    }

    /**
     * Test decodeLatex with circumflex
     */
    public function testDecodeLatexCircumflex(): void
    {
        // \^a -> â, \^{e} -> ê, \^{o} -> ô
        $this->assertSame('â', Episciences_Tools::decodeLatex("\\^a"));
        $this->assertSame('ê', Episciences_Tools::decodeLatex("\\^{e}"));
        $this->assertSame('ê', Episciences_Tools::decodeLatex("\\^e"));
        $this->assertSame('ô', Episciences_Tools::decodeLatex("\\^{o}"));
        $this->assertSame('ô', Episciences_Tools::decodeLatex("\\^o"));
    }

    /**
     * Test decodeLatex with acute accent
     */
    public function testDecodeLatexAcute(): void
    {
        // Various acute accents
        $this->assertSame('á', Episciences_Tools::decodeLatex("\\'{a}"));
        $this->assertSame('á', Episciences_Tools::decodeLatex("\\'a"));
        $this->assertSame('é', Episciences_Tools::decodeLatex("\\'{e}"));
        $this->assertSame('é', Episciences_Tools::decodeLatex("\\'e"));
        $this->assertSame('ó', Episciences_Tools::decodeLatex("\\'{o}"));
        $this->assertSame('ó', Episciences_Tools::decodeLatex("\\'o"));
        $this->assertSame('ć', Episciences_Tools::decodeLatex("\\'{c}"));
        $this->assertSame('ć', Episciences_Tools::decodeLatex("\\'c"));
        $this->assertSame('ń', Episciences_Tools::decodeLatex("\\'{n}"));
        $this->assertSame('ń', Episciences_Tools::decodeLatex("\\'n"));
        $this->assertSame('ý', Episciences_Tools::decodeLatex("\\'{y}"));
        $this->assertSame('ý', Episciences_Tools::decodeLatex("\\'y"));
    }

    /**
     * Test decodeLatex with grave accent
     */
    public function testDecodeLatexGrave(): void
    {
        // Various grave accents
        $this->assertSame('à', Episciences_Tools::decodeLatex("\\`{a}"));
        $this->assertSame('à', Episciences_Tools::decodeLatex("\\`a"));
        $this->assertSame('è', Episciences_Tools::decodeLatex("\\`{e}"));
        $this->assertSame('è', Episciences_Tools::decodeLatex("\\`e"));
        $this->assertSame('ì', Episciences_Tools::decodeLatex("\\`i"));
        $this->assertSame('ò', Episciences_Tools::decodeLatex("\\`{o}"));
        $this->assertSame('ò', Episciences_Tools::decodeLatex("\\`o"));
    }

    /**
     * Test decodeLatex with umlaut/dieresis
     */
    public function testDecodeLatexUmlaut(): void
    {
        // Various umlauts
        $this->assertSame('ä', Episciences_Tools::decodeLatex("\\\"{a}"));
        $this->assertSame('ä', Episciences_Tools::decodeLatex("\\\"a"));
        $this->assertSame('ë', Episciences_Tools::decodeLatex("\\\"{e}"));
        $this->assertSame('ë', Episciences_Tools::decodeLatex("\\\"e"));
        $this->assertSame('ö', Episciences_Tools::decodeLatex("\\\"{o}"));
        $this->assertSame('ö', Episciences_Tools::decodeLatex("\\\"o"));
        $this->assertSame('ü', Episciences_Tools::decodeLatex("\\\"{u}"));
        $this->assertSame('ü', Episciences_Tools::decodeLatex("\\\"u"));
        $this->assertSame('Müller', Episciences_Tools::decodeLatex("M\\\"uller"));
    }

    /**
     * Test decodeLatex with breve
     */
    public function testDecodeLatexBreve(): void
    {
        // \u{a} -> ă, \u{o} -> ŏ
        $this->assertSame('ă', Episciences_Tools::decodeLatex("\\u{a}"));
        $this->assertSame('ă', Episciences_Tools::decodeLatex("\\u a"));
        $this->assertSame('ŏ', Episciences_Tools::decodeLatex("\\u{o}"));
        $this->assertSame('ŏ', Episciences_Tools::decodeLatex("\\u o"));
    }

    /**
     * Test decodeLatex with Hungarian umlaut (double acute)
     */
    public function testDecodeLatexHungarianUmlaut(): void
    {
        // \H{o} -> ő, \H{u} -> ű
        $this->assertSame('ő', Episciences_Tools::decodeLatex("\\H{o}"));
        $this->assertSame('ő', Episciences_Tools::decodeLatex("\\H o"));
        $this->assertSame('ű', Episciences_Tools::decodeLatex("\\H{u}"));
        $this->assertSame('ű', Episciences_Tools::decodeLatex("\\H u"));
    }

    /**
     * Test decodeLatex with tilde
     */
    public function testDecodeLatexTilde(): void
    {
        // \~{o} -> õ
        $this->assertSame('õ', Episciences_Tools::decodeLatex("\\~{o}"));
        $this->assertSame('õ', Episciences_Tools::decodeLatex("\\~o"));
    }

    /**
     * Test decodeLatex with macron
     */
    public function testDecodeLatexMacron(): void
    {
        // \={o} -> ō
        $this->assertSame('ō', Episciences_Tools::decodeLatex("\\={o}"));
        $this->assertSame('ō', Episciences_Tools::decodeLatex("\\=o"));
    }

    /**
     * Test decodeLatex with dot over letter
     */
    public function testDecodeLatexDotOver(): void
    {
        // \.{o} -> ȯ
        $this->assertSame('ȯ', Episciences_Tools::decodeLatex("\\.{o}"));
        $this->assertSame('ȯ', Episciences_Tools::decodeLatex("\\.o"));
    }

    /**
     * Test decodeLatex with tie
     */
    public function testDecodeLatexTie(): void
    {
        // \t{oo} -> o͡o
        $this->assertSame('o͡o', Episciences_Tools::decodeLatex("\\t{oo}"));
        $this->assertSame('o͡o', Episciences_Tools::decodeLatex("\\t oo"));
    }

    /**
     * Test decodeLatex calls decodeAmpersand
     */
    public function testDecodeLatexDecodesAmpersand(): void
    {
        // Verify that &amp; is decoded to & in the result
        $this->assertSame('foo & bar', Episciences_Tools::decodeLatex('foo &amp; bar'));
        $this->assertSame('a & b & c', Episciences_Tools::decodeLatex('a &amp; b &amp; c'));

        // Combine LaTeX and ampersand
        $this->assertSame('François & Müller', Episciences_Tools::decodeLatex("Fran\\c{c}ois &amp; M\\\"uller"));
    }

    /**
     * Test decodeLatex with multiple LaTeX sequences in one string
     */
    public function testDecodeLatexMultipleSequences(): void
    {
        // Test real author names with multiple accents
        $this->assertSame(
            'José',
            Episciences_Tools::decodeLatex("Jos\\'{e}")
        );

        $this->assertSame(
            'łódz',
            Episciences_Tools::decodeLatex("\\l{}\\'{o}dz")
        );

        // Mixed accents from the supported list
        $this->assertSame(
            'çàéêëìóôöõōűü',
            Episciences_Tools::decodeLatex("\\c{c}\\`a\\'e\\^e\\\"e\\`i\\'o\\^o\\\"o\\~o\\=o\\H{u}\\\"u")
        );

        // Combined author name
        $this->assertSame(
            'François Müller',
            Episciences_Tools::decodeLatex("Fran\\c{c}ois M\\\"uller")
        );
    }

    /**
     * Test decodeLatex preserves non-LaTeX content
     */
    public function testDecodeLatexPreservesNonLatex(): void
    {
        // Regular text should pass through unchanged
        $this->assertSame('Hello World', Episciences_Tools::decodeLatex('Hello World'));
        $this->assertSame('Test 123 !@#$%', Episciences_Tools::decodeLatex('Test 123 !@#$%'));

        // Math mode should be preserved (not decoded)
        $this->assertSame('$x^2 + y^2 = z^2$', Episciences_Tools::decodeLatex('$x^2 + y^2 = z^2$'));
        $this->assertSame('\\begin{equation}', Episciences_Tools::decodeLatex('\\begin{equation}'));
    }

    /**
     * Test decodeLatex function with line break preservation
     */
    public function testDecodeLatexWithLineBreaks(): void
    {
        // Test text wrapping (line breaks after common words) - should be converted to spaces
        $input = "We incorporate strong negation in the theory of\ncomputable functionals and\ndefining simultaneously strong negation";
        $expected = "We incorporate strong negation in the theory of computable functionals and defining simultaneously strong negation";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test intentional line breaks (after sentence endings) - should be converted to <br>
        $input = "First sentence.\nSecond sentence.\nThird sentence.";
        $expected = "First sentence.<br />Second sentence.<br />Third sentence.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test double line breaks (paragraph breaks) - should be converted to <br><br>
        $input = "Paragraph 1\n\nParagraph 2";
        $expected = "Paragraph 1<br /><br />Paragraph 2";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test bullet points - should preserve line breaks before bullets, but merge text without punctuation
        $input = "Introduction.\n- First point.\n- Second point.\nConclusion.";
        $expected = "Introduction.<br />- First point.<br />- Second point.<br />Conclusion.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test mixed wrapping and intentional breaks
        $input = "Text with\nwrapping in middle.\n\nNew paragraph with\nmore wrapping.";
        $expected = "Text with wrapping in middle.<br /><br />New paragraph with more wrapping.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
    }

    /**
     * Test decodeLatex with paragraph-like structures (double line breaks)
     */
    public function testDecodeLatexWithParagraphs(): void
    {
        // Test abstract-like content with double line breaks
        $input = "We pose the fine-grained hardness hypothesis that the textbook algorithm for the NFA Acceptance problem is optimal up to subpolynomial factors.\n\nThis study underscores how the choice of workflow is never neutral: it profoundly shapes the quality.";
        $expected = "We pose the fine-grained hardness hypothesis that the textbook algorithm for the NFA Acceptance problem is optimal up to subpolynomial factors.<br /><br />This study underscores how the choice of workflow is never neutral: it profoundly shapes the quality.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test multiple paragraphs
        $input = "First paragraph.\n\nSecond paragraph with more content.\n\nThird paragraph.";
        $expected = "First paragraph.<br /><br />Second paragraph with more content.<br /><br />Third paragraph.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
    }

    /**
     * Test decodeLatex combining LaTeX decoding and line break preservation
     */
    public function testDecodeLatexCombined(): void
    {
        // Test realistic academic abstract with LaTeX and line breaks
        $input = "This article explores the Music Encoding Initiative (MEI) by examining \\c{c}ommonly used workflows.\n\nSince its creation to fill the absence of a standard for encoding musical works, MEI has evolved into a powerful framework capable of representing not only Western common music notation but also specialised systems such as mensural and neumatic notations.\n\nThe article emphasises that adopting MEI is not simply a technical choice but one that entails significant human considerations.";
        
        $expected = "This article explores the Music Encoding Initiative (MEI) by examining çommonly used workflows.<br /><br />Since its creation to fill the absence of a standard for encoding musical works, MEI has evolved into a powerful framework capable of representing not only Western common music notation but also specialised systems such as mensural and neumatic notations.<br /><br />The article emphasises that adopting MEI is not simply a technical choice but one that entails significant human considerations.";
        
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test with multiple LaTeX characters and text wrapping
        $input = "Authors: Fran\\c{c}ois M\\\"uller and Tom Smith\\k{a}\nwith line wrapping\n\nAbstract follows here.";
        $expected = "Authors: François Müller and Tom Smithą with line wrapping<br /><br />Abstract follows here.";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
    }

    /**
     * Test decodeLatex edge cases
     */
    public function testDecodeLatexEdgeCases(): void
    {
        // Test multiple consecutive line breaks (treated as one big paragraph break)
        $input = "\n\n\n\n";
        $expected = "<br /><br />";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test only LaTeX characters (no line breaks)
        $input = "\\c{c}\\k{a}\\l{}";
        $expected = "çął";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test string that starts/ends with single line breaks (treated as intentional)
        $input = "\nMiddle content\n";
        $expected = "<br />Middle content ";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test single line break at end (text wrapping)
        $input = "Content\n";
        $expected = "Content ";
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, true));
        
        // Test null-like values
        $this->assertSame('', Episciences_Tools::decodeLatex('', true));
        
        // Test line break without preserveLineBreaks (ensure backward compatibility)
        $input = "Line 1\nLine 2";
        $expected = "Line 1\nLine 2"; // Should remain unchanged
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input));
        $this->assertSame($expected, Episciences_Tools::decodeLatex($input, false));
    }

    /**
     * Test decodeLatex with realistic academic content (similar to Zenodo example)
     */
    public function testDecodeLatexRealWorldContent(): void
    {
        // Test content similar to the user's example with proper paragraph breaks and bullet points
        $input = "We pose the fine-grained hardness hypothesis that the textbook algorithm for the NFA Acceptance problem is optimal.\n\n- It gives a tight lower bound for Context-Free Language Reachability.\n- It gives a tight \$(n+nm^{1/3})^{1-o(1)}\$ lower bound for the Word Break problem.\n- It implies the popular OMv hypothesis.\n\nThus, a proof of the NFA Acceptance hypothesis would resolve several interesting barriers.";
        
        $expected = "We pose the fine-grained hardness hypothesis that the textbook algorithm for the NFA Acceptance problem is optimal.<br /><br />- It gives a tight lower bound for Context-Free Language Reachability.<br />- It gives a tight \$(n+nm^{1/3})^{1-o(1)}\$ lower bound for the Word Break problem.<br />- It implies the popular OMv hypothesis.<br /><br />Thus, a proof of the NFA Acceptance hypothesis would resolve several interesting barriers.";
        
        $result = Episciences_Tools::decodeLatex($input, true);
        $this->assertSame($expected, $result);
        
        // Verify that without line break preservation, line breaks remain unchanged
        $resultNoLineBreaks = Episciences_Tools::decodeLatex($input, false);
        $this->assertSame($input, $resultNoLineBreaks);
    }

    /**
     * Test decodeLatex with wrapped text (like user's problematic example)
     */
    public function testDecodeLatexWithWrappedText(): void
    {
        // Test content with text wrapping that should NOT create <br> tags
        $input = "We incorporate strong negation in the theory of computable functionals TCF, a\ncommon extension of Plotkin's PCF and Gödel's system \$\\mathbf{T}\$, by\ndefining simultaneously strong negation \$A^{\\mathbf{N}}\$ of a formula \$A\$ and\nstrong negation \$P^{\\mathbf{N}}\$ of a predicate \$P\$ in TCF.";
        
        $expected = "We incorporate strong negation in the theory of computable functionals TCF, a common extension of Plotkin's PCF and Gödel's system \$\\mathbf{T}\$, by defining simultaneously strong negation \$A^{\\mathbf{N}}\$ of a formula \$A\$ and strong negation \$P^{\\mathbf{N}}\$ of a predicate \$P\$ in TCF.";
        
        $result = Episciences_Tools::decodeLatex($input, true);
        $this->assertSame($expected, $result);
        
        // Test with actual paragraph breaks in the same content
        $inputWithParagraphs = "We incorporate strong negation in the theory of computable functionals TCF, a\ncommon extension of Plotkin's PCF and Gödel's system \$\\mathbf{T}\$, by\ndefining simultaneously strong negation \$A^{\\mathbf{N}}\$ of a formula \$A\$ and\nstrong negation \$P^{\\mathbf{N}}\$ of a predicate \$P\$ in TCF.\n\nWe prove appropriate versions of the Ex falso quodlibet and\nof double negation elimination for strong negation in TCF.";
        
        $expectedWithParagraphs = "We incorporate strong negation in the theory of computable functionals TCF, a common extension of Plotkin's PCF and Gödel's system \$\\mathbf{T}\$, by defining simultaneously strong negation \$A^{\\mathbf{N}}\$ of a formula \$A\$ and strong negation \$P^{\\mathbf{N}}\$ of a predicate \$P\$ in TCF.<br /><br />We prove appropriate versions of the Ex falso quodlibet and of double negation elimination for strong negation in TCF.";
        
        $resultWithParagraphs = Episciences_Tools::decodeLatex($inputWithParagraphs, true);
        $this->assertSame($expectedWithParagraphs, $resultWithParagraphs);
    }

    /**
     * Test isRtlLanguage function with known RTL languages
     */
    public function testIsRtlLanguageWithRtlCodes(): void
    {
        // Test all supported RTL language codes
        $rtlLanguages = ['ar', 'he', 'fa', 'ur', 'ps', 'syr', 'dv', 'ku', 'yi', 'arc'];
        
        foreach ($rtlLanguages as $langCode) {
            $this->assertTrue(
                Episciences_Tools::isRtlLanguage($langCode),
                "Language code '$langCode' should be recognized as RTL"
            );
        }
    }

    /**
     * Test isRtlLanguage function with case variations
     */
    public function testIsRtlLanguageWithCaseVariations(): void
    {
        // Test uppercase
        $this->assertTrue(Episciences_Tools::isRtlLanguage('AR'));
        $this->assertTrue(Episciences_Tools::isRtlLanguage('HE'));
        $this->assertTrue(Episciences_Tools::isRtlLanguage('FA'));
        
        // Test mixed case
        $this->assertTrue(Episciences_Tools::isRtlLanguage('Ar'));
        $this->assertTrue(Episciences_Tools::isRtlLanguage('He'));
        $this->assertTrue(Episciences_Tools::isRtlLanguage('Fa'));
        
        // Test with whitespace
        $this->assertTrue(Episciences_Tools::isRtlLanguage(' ar '));
        $this->assertTrue(Episciences_Tools::isRtlLanguage('  he  '));
    }

    /**
     * Test isRtlLanguage function with non-RTL languages
     */
    public function testIsRtlLanguageWithLtrCodes(): void
    {
        // Test common LTR language codes
        $ltrLanguages = ['en', 'fr', 'de', 'es', 'it', 'pt', 'ru', 'zh', 'ja', 'ko'];
        
        foreach ($ltrLanguages as $langCode) {
            $this->assertFalse(
                Episciences_Tools::isRtlLanguage($langCode),
                "Language code '$langCode' should NOT be recognized as RTL"
            );
        }
    }

    /**
     * Test isRtlLanguage function with edge cases
     */
    public function testIsRtlLanguageEdgeCases(): void
    {
        // Test null
        $this->assertFalse(Episciences_Tools::isRtlLanguage(null));
        
        // Test empty string
        $this->assertFalse(Episciences_Tools::isRtlLanguage(''));
        
        // Test whitespace only
        $this->assertFalse(Episciences_Tools::isRtlLanguage('   '));
        
        // Test invalid/unknown codes
        $this->assertFalse(Episciences_Tools::isRtlLanguage('xx'));
        $this->assertFalse(Episciences_Tools::isRtlLanguage('invalid'));
        $this->assertFalse(Episciences_Tools::isRtlLanguage('123'));
        
        // Test very long strings (should still work due to trim)
        $this->assertTrue(Episciences_Tools::isRtlLanguage('    ar    '));
    }

    /**
     * Test isRtlLanguage function with specific real-world language codes
     */
    public function testIsRtlLanguageRealWorldCases(): void
    {
        // Arabic variants
        $this->assertTrue(Episciences_Tools::isRtlLanguage('ar')); // Modern Standard Arabic
        
        // Hebrew
        $this->assertTrue(Episciences_Tools::isRtlLanguage('he')); // Modern Hebrew
        
        // Persian/Farsi variants  
        $this->assertTrue(Episciences_Tools::isRtlLanguage('fa')); // Persian
        
        // Urdu
        $this->assertTrue(Episciences_Tools::isRtlLanguage('ur')); // Urdu
        
        // Less common RTL languages
        $this->assertTrue(Episciences_Tools::isRtlLanguage('ps')); // Pashto
        $this->assertTrue(Episciences_Tools::isRtlLanguage('syr')); // Syriac
        $this->assertTrue(Episciences_Tools::isRtlLanguage('dv')); // Divehi
        $this->assertTrue(Episciences_Tools::isRtlLanguage('ku')); // Kurdish
        $this->assertTrue(Episciences_Tools::isRtlLanguage('yi')); // Yiddish
        $this->assertTrue(Episciences_Tools::isRtlLanguage('arc')); // Aramaic
        
        // Ensure common LTR languages are not detected as RTL
        $this->assertFalse(Episciences_Tools::isRtlLanguage('en')); // English
        $this->assertFalse(Episciences_Tools::isRtlLanguage('fr')); // French
        $this->assertFalse(Episciences_Tools::isRtlLanguage('de')); // German
        $this->assertFalse(Episciences_Tools::isRtlLanguage('es')); // Spanish
    }

    // ============================================================================
    // Tests for validateDoi()
    // ============================================================================

    public function testValidateDoi_WithValidDoi_ReturnsCleanedDoi(): void
    {
        $validDoi = '10.1234/test-doi';
        $result = Episciences_Tools::validateDoi($validDoi);
        $this->assertSame($validDoi, $result);
    }

    public function testValidateDoi_WithWhitespace_ReturnsTrimmedDoi(): void
    {
        $result = Episciences_Tools::validateDoi('  10.1234/test-doi  ');
        $this->assertSame('10.1234/test-doi', $result);
    }

    public function testValidateDoi_WithEmptyString_ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOI cannot be empty');
        Episciences_Tools::validateDoi('');
    }

    public function testValidateDoi_WithInvalidFormat_ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DOI format');
        Episciences_Tools::validateDoi('not-a-doi');
    }

    public function testValidateDoi_WithExcessiveLength_ThrowsException(): void
    {
        // Create a DOI that's longer than default MAX_DOI_LENGTH (200 characters)
        $longDoi = '10.1234/' . str_repeat('a', 200);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOI exceeds maximum length');
        Episciences_Tools::validateDoi($longDoi);
    }

    public function testValidateDoi_WithCustomMaxLength_ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DOI exceeds maximum length of 20 characters');
        Episciences_Tools::validateDoi('10.1234/this-is-a-very-long-doi', 20);
    }

    public function testValidateDoi_WithComplexValidDoi_ReturnsCleanedDoi(): void
    {
        // Test with complex but valid DOI containing special characters
        $validDoi = '10.1234/test-DOI.with_special(chars)';
        $result = Episciences_Tools::validateDoi($validDoi);
        $this->assertSame($validDoi, $result);
    }

    public function testValidateDoi_WithRealWorldDois_ReturnsCleanedDoi(): void
    {
        // Test with real-world DOI examples
        $realDois = [
            '10.1016/j.neuron.2018.01.023',
            '10.1038/nature12373',
            '10.1126/science.aaa1234',
            '10.48550/arXiv.2104.12345',
        ];

        foreach ($realDois as $doi) {
            $result = Episciences_Tools::validateDoi($doi);
            $this->assertSame($doi, $result);
        }
    }

    // ============================================================================
    // Tests for isValidOrcid()
    // ============================================================================

    public function testIsValidOrcid_WithValidOrcid_ReturnsTrue(): void
    {
        $validOrcids = [
            '0000-0002-1825-0097',
            '0000-0001-5000-0007',
            '0000-0002-9079-593X', // X is valid checksum
            '0000-0003-1234-5678',
        ];

        foreach ($validOrcids as $orcid) {
            $result = Episciences_Tools::isValidOrcid($orcid);
            $this->assertTrue($result, "ORCID $orcid should be valid");
        }
    }

    public function testIsValidOrcid_WithInvalidOrcid_ReturnsFalse(): void
    {
        $invalidOrcids = [
            '0000-0002-1825',        // Too short
            '0000-0002-1825-00971',  // Too long
            '0000-00021-1825-0097',  // Wrong format
            'not-an-orcid',          // Completely invalid
            '',                      // Empty
            '0000-0002-1825-009Y',   // Invalid checksum (Y not allowed)
        ];

        foreach ($invalidOrcids as $orcid) {
            $result = Episciences_Tools::isValidOrcid($orcid);
            $this->assertFalse($result, "ORCID '$orcid' should be invalid");
        }
    }

    public function testIsValidOrcid_WithWhitespace_HandlesTrimming(): void
    {
        $result = Episciences_Tools::isValidOrcid('  0000-0002-1825-0097  ');
        $this->assertTrue($result);
    }

    public function testIsValidOrcid_WithRealWorldOrcids_ReturnsTrue(): void
    {
        // Test with some real ORCID examples (format-wise)
        $realOrcids = [
            '0000-0002-1694-233X',
            '0000-0001-9448-0967',
            '0000-0003-0000-0001',
        ];

        foreach ($realOrcids as $orcid) {
            $result = Episciences_Tools::isValidOrcid($orcid);
            $this->assertTrue($result, "ORCID $orcid should be valid");
        }
    }

    // ============================================================================
    // Tests for isSha1()
    // ============================================================================

    public function testIsSha1_WithValidSha1_ReturnsTrue(): void
    {
        $validSha1Hashes = [
            'da39a3ee5e6b4b0d3255bfef95601890afd80709', // SHA1 of empty string
            'a94a8fe5ccb19ba61c4c0873d391e987982fbbd3', // SHA1 of "test"
            '2fd4e1c67a2d28fced849ee1bb76e7391b93eb12', // SHA1 of "The quick brown fox..."
            '0000000000000000000000000000000000000000', // All zeros
            'ffffffffffffffffffffffffffffffffffffffff', // All f's
            'ABCDEF0123456789ABCDEF0123456789ABCDEF01', // Mixed case
        ];

        foreach ($validSha1Hashes as $hash) {
            $this->assertTrue(
                Episciences_Tools::isSha1($hash),
                "SHA1 hash '$hash' should be valid"
            );
        }
    }

    public function testIsSha1_WithInvalidSha1_ReturnsFalse(): void
    {
        $invalidSha1Hashes = [
            '',                                          // Empty string
            'da39a3ee5e6b4b0d3255bfef95601890afd8070',   // 39 characters (too short)
            'da39a3ee5e6b4b0d3255bfef95601890afd807090', // 41 characters (too long)
            'ga39a3ee5e6b4b0d3255bfef95601890afd80709',  // Invalid character 'g'
            'not-a-sha1-hash',                           // Random string
            '12345',                                     // Too short
            'da39a3ee5e6b4b0d3255bfef95601890afd8070!',  // Special character
            'da39a3ee 5e6b4b0d3255bfef95601890afd80709', // Space in middle
        ];

        foreach ($invalidSha1Hashes as $hash) {
            $this->assertFalse(
                Episciences_Tools::isSha1($hash),
                "String '$hash' should not be a valid SHA1"
            );
        }
    }

    // ============================================================================
    // Tests for isJson()
    // ============================================================================

    public function testIsJson_WithValidJson_ReturnsTrue(): void
    {
        $validJsonStrings = [
            '{}',                                    // Empty object
            '[]',                                    // Empty array
            'null',                                  // Null value
            'true',                                  // Boolean true
            'false',                                 // Boolean false
            '123',                                   // Number
            '"string"',                              // String
            '{"key": "value"}',                      // Simple object
            '[1, 2, 3]',                             // Simple array
            '{"nested": {"key": "value"}}',          // Nested object
            '{"array": [1, 2, 3]}',                  // Object with array
            '[{"a": 1}, {"b": 2}]',                  // Array of objects
            '{"unicode": "été café"}',               // Unicode characters
            '{"number": 3.14159}',                   // Float number
            '{"negative": -42}',                     // Negative number
        ];

        foreach ($validJsonStrings as $json) {
            $this->assertTrue(
                Episciences_Tools::isJson($json),
                "String '$json' should be valid JSON"
            );
        }
    }

    public function testIsJson_WithInvalidJson_ReturnsFalse(): void
    {
        $invalidJsonStrings = [
            '',                          // Empty string
            '{',                         // Incomplete object
            '[',                         // Incomplete array
            '{"key": }',                 // Missing value
            '{key: "value"}',            // Unquoted key
            "{'key': 'value'}",          // Single quotes
            '{\"key\": \"value\"}',      // Escaped quotes (not valid JSON string)
            'undefined',                 // JavaScript undefined
            'NaN',                       // JavaScript NaN
            '{trailing: "comma",}',      // Trailing comma
        ];

        foreach ($invalidJsonStrings as $json) {
            $this->assertFalse(
                Episciences_Tools::isJson($json),
                "String '$json' should not be valid JSON"
            );
        }
    }

    public function testIsJson_WithNonStringInput_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_Tools::isJson(123));
        $this->assertFalse(Episciences_Tools::isJson(null));
        $this->assertFalse(Episciences_Tools::isJson([]));
        $this->assertFalse(Episciences_Tools::isJson(true));
    }

    // ============================================================================
    // Tests for isUuid()
    // ============================================================================

    public function testIsUuid_WithValidUuid_ReturnsTrue(): void
    {
        $validUuids = [
            '550e8400-e29b-41d4-a716-446655440000', // Standard UUID v4
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8', // UUID v1
            '6ba7b811-9dad-11d1-80b4-00c04fd430c8', // Another UUID v1
            'f47ac10b-58cc-4372-a567-0e02b2c3d479', // UUID v4
            '00000000-0000-0000-0000-000000000000', // Nil UUID
            'ffffffff-ffff-ffff-ffff-ffffffffffff', // Max UUID
            '550E8400-E29B-41D4-A716-446655440000', // Uppercase
        ];

        foreach ($validUuids as $uuid) {
            $this->assertTrue(
                Episciences_Tools::isUuid($uuid),
                "UUID '$uuid' should be valid"
            );
        }
    }

    public function testIsUuid_WithInvalidUuid_ReturnsFalse(): void
    {
        $invalidUuids = [
            '',                                      // Empty string
            '550e8400-e29b-41d4-a716-44665544000',   // Too short
            '550e8400-e29b-41d4-a716-4466554400000', // Too long
            '550e8400e29b41d4a716446655440000',      // No hyphens
            '550e8400-e29b-41d4-a716_446655440000',  // Wrong separator
            'not-a-uuid-at-all',                     // Random string
            '550e8400-e29b-41d4-a716-44665544000g',  // Invalid character
            '   ',                                   // Whitespace only
        ];

        foreach ($invalidUuids as $uuid) {
            $this->assertFalse(
                Episciences_Tools::isUuid($uuid),
                "String '$uuid' should not be a valid UUID"
            );
        }
    }

    public function testIsUuid_WithNullInput_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_Tools::isUuid(null));
    }

    // ============================================================================
    // Tests for getCleanedUuid()
    // ============================================================================

    public function testGetCleanedUuid_WithValidUuid_ReturnsCleanedUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $expected = '550e8400e29b41d4a716446655440000';

        $this->assertSame($expected, Episciences_Tools::getCleanedUuid($uuid));
    }

    public function testGetCleanedUuid_WithInvalidUuid_ReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_Tools::getCleanedUuid('not-a-uuid'));
        $this->assertSame('', Episciences_Tools::getCleanedUuid(''));
        $this->assertSame('', Episciences_Tools::getCleanedUuid(null));
    }

    // ============================================================================
    // Tests for isIPv6()
    // ============================================================================

    public function testIsIPv6_WithValidIPv6_ReturnsTrue(): void
    {
        $validIPv6Addresses = [
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334', // Full notation
            '2001:db8:85a3:0:0:8a2e:370:7334',         // Leading zeros omitted
            '2001:db8:85a3::8a2e:370:7334',            // Consecutive zeros compressed
            '::1',                                     // Loopback address
            '::',                                      // All zeros
            'fe80::1',                                 // Link-local
            '2001:db8::',                              // Trailing zeros compressed
            '::ffff:192.0.2.1',                        // IPv4-mapped IPv6
            'FF02::1',                                 // Multicast (uppercase)
        ];

        foreach ($validIPv6Addresses as $ip) {
            $this->assertTrue(
                Episciences_Tools::isIPv6($ip),
                "IPv6 address '$ip' should be valid"
            );
        }
    }

    public function testIsIPv6_WithInvalidIPv6_ReturnsFalse(): void
    {
        $invalidIPv6Addresses = [
            '',                                        // Empty string
            '192.168.1.1',                             // IPv4 address
            '2001:db8:85a3:0:0:8a2e:370:7334:extra',   // Too many groups
            '2001:db8:85a3:0:0:8a2e:370',              // Too few groups
            '2001:db8:85a3:0:0:8a2e:370:gggg',         // Invalid characters
            '2001::db8::1',                            // Multiple :: compressions
            'not-an-ip-address',                       // Random string
            '2001:db8:85a3:0:0:8a2e:370:7334:',        // Trailing colon
        ];

        foreach ($invalidIPv6Addresses as $ip) {
            $this->assertFalse(
                Episciences_Tools::isIPv6($ip),
                "String '$ip' should not be a valid IPv6 address"
            );
        }
    }

    // ============================================================================
    // Tests for isRorIdentifier()
    // ============================================================================

    public function testIsRorIdentifier_WithValidRor_ReturnsTrue(): void
    {
        $validRorIdentifiers = [
            '0abcdef12',                              // 9 alphanumeric characters
            'https://ror.org/0abcdef12',              // Full URL with https
            'http://ror.org/0abcdef12',               // Full URL with http
            'ror.org/0abcdef12',                      // URL without protocol
            '03yrm5c26',                              // Real ROR example (CERN)
            '05dxps055',                              // Real ROR example
            'HTTPS://ROR.ORG/03YRM5C26',              // Uppercase
        ];

        foreach ($validRorIdentifiers as $ror) {
            $this->assertTrue(
                Episciences_Tools::isRorIdentifier($ror),
                "ROR identifier '$ror' should be valid"
            );
        }
    }

    public function testIsRorIdentifier_WithInvalidRor_ReturnsFalse(): void
    {
        $invalidRorIdentifiers = [
            '',                                       // Empty string
            '0abcdef1',                               // 8 characters (too short)
            '0abcdef123',                             // 10 characters (too long)
            '0abcdef1!',                              // Invalid character
            'https://example.org/0abcdef12',          // Wrong domain
            'ror.org/',                               // Missing identifier
            'not-a-ror',                              // Random string
        ];

        foreach ($invalidRorIdentifiers as $ror) {
            $this->assertFalse(
                Episciences_Tools::isRorIdentifier($ror),
                "String '$ror' should not be a valid ROR identifier"
            );
        }
    }

    // ============================================================================
    // Tests for isDoiWithUrl()
    // ============================================================================

    public function testIsDoiWithUrl_WithValidDoiUrl_ReturnsTrue(): void
    {
        $validDoiUrls = [
            '10.1000/182',                            // Plain DOI
            'https://doi.org/10.1000/182',            // HTTPS doi.org URL
            'http://doi.org/10.1000/182',             // HTTP doi.org URL
            'https://dx.doi.org/10.1000/182',         // dx.doi.org URL
            'http://dx.doi.org/10.1000/182',          // HTTP dx.doi.org
            'doi.org/10.1000/182',                    // Without protocol
            'dx.doi.org/10.1000/182',                 // dx without protocol
            '10.1038/nature12373',                    // Real DOI
            'https://doi.org/10.1038/nature12373',    // Real DOI with URL
        ];

        foreach ($validDoiUrls as $doi) {
            $this->assertTrue(
                Episciences_Tools::isDoiWithUrl($doi),
                "DOI '$doi' should be valid"
            );
        }
    }

    public function testIsDoiWithUrl_WithInvalidDoiUrl_ReturnsFalse(): void
    {
        $invalidDoiUrls = [
            '',                                       // Empty string
            'https://example.org/10.1000/182',        // Wrong domain
            '10.10/',                                 // Invalid DOI format
            'not-a-doi',                              // Random string
            'https://doi.org/',                       // Missing DOI
        ];

        foreach ($invalidDoiUrls as $doi) {
            $this->assertFalse(
                Episciences_Tools::isDoiWithUrl($doi),
                "String '$doi' should not be a valid DOI with URL"
            );
        }
    }

    // ============================================================================
    // Tests for getHalIdAndVer()
    // ============================================================================

    public function testGetHalIdAndVer_WithValidHalId_ReturnsMatches(): void
    {
        // Test HAL ID without version
        $result = Episciences_Tools::getHalIdAndVer('hal-01234567');
        $this->assertSame('hal-01234567', $result[0]);
        $this->assertSame('hal-01234567', $result[1]);

        // Test HAL ID with version
        $result = Episciences_Tools::getHalIdAndVer('hal-01234567v2');
        $this->assertSame('hal-01234567v2', $result[0]);
        $this->assertSame('hal-01234567', $result[1]);
        $this->assertSame('v2', $result[2]);

        // Test other archive prefixes
        $result = Episciences_Tools::getHalIdAndVer('inria-01234567v1');
        $this->assertSame('inria-01234567v1', $result[0]);
        $this->assertSame('inria-01234567', $result[1]);

        // Test with underscore separator
        $result = Episciences_Tools::getHalIdAndVer('hal_01234567v3');
        $this->assertSame('hal_01234567v3', $result[0]);
        $this->assertSame('hal_01234567', $result[1]);
        $this->assertSame('v3', $result[2]);
    }

    public function testGetHalIdAndVer_WithInvalidHalId_ReturnsEmptyArray(): void
    {
        $result = Episciences_Tools::getHalIdAndVer('not-a-hal-id');
        $this->assertEmpty($result);

        $result = Episciences_Tools::getHalIdAndVer('');
        $this->assertEmpty($result);

        $result = Episciences_Tools::getHalIdAndVer('hal-123'); // Too short
        $this->assertEmpty($result);
    }

    // ============================================================================
    // Tests for getHalIdInString()
    // ============================================================================

    public function testGetHalIdInString_WithValidUrl_ReturnsHalId(): void
    {
        // Test HAL URL
        $result = Episciences_Tools::getHalIdInString('https://hal.science/hal-04202866v1');
        $this->assertSame('hal-04202866v1', $result[0]);

        // Test HAL URL without version
        $result = Episciences_Tools::getHalIdInString('https://hal.archives-ouvertes.fr/hal-01234567');
        $this->assertSame('hal-01234567', $result[0]);

        // Test embedded in longer URL
        $result = Episciences_Tools::getHalIdInString('https://hal.science/hal-01234567v2/document');
        $this->assertSame('hal-01234567v2', $result[0]);
    }

    public function testGetHalIdInString_WithInvalidUrl_ReturnsEmptyArray(): void
    {
        $result = Episciences_Tools::getHalIdInString('https://example.com/not-hal');
        $this->assertEmpty($result);

        $result = Episciences_Tools::getHalIdInString('');
        $this->assertEmpty($result);
    }

    // ============================================================================
    // Tests for checkIsArxivUrl()
    // ============================================================================

    public function testCheckIsArxivUrl_WithValidArxivUrl_ReturnsMatches(): void
    {
        // Test new-style arXiv URL
        $result = Episciences_Tools::checkIsArxivUrl('https://arxiv.org/abs/2104.12345');
        $this->assertNotEmpty($result);
        $this->assertSame('2104.12345', $result[1]);

        // Test with version
        $result = Episciences_Tools::checkIsArxivUrl('https://arxiv.org/abs/2104.12345v2');
        $this->assertNotEmpty($result);
        $this->assertSame('2104.12345v2', $result[1]);

        // Test old-style arXiv URL
        $result = Episciences_Tools::checkIsArxivUrl('https://arxiv.org/abs/math.AG/0601001');
        $this->assertNotEmpty($result);
        $this->assertSame('math.AG/0601001', $result[1]);

        // Test with http
        $result = Episciences_Tools::checkIsArxivUrl('http://arxiv.org/abs/1501.00001');
        $this->assertNotEmpty($result);
        $this->assertSame('1501.00001', $result[1]);
    }

    public function testCheckIsArxivUrl_WithInvalidUrl_ReturnsEmptyArray(): void
    {
        $result = Episciences_Tools::checkIsArxivUrl('https://example.com/abs/2104.12345');
        $this->assertEmpty($result);

        $result = Episciences_Tools::checkIsArxivUrl('https://arxiv.org/pdf/2104.12345');
        $this->assertEmpty($result);

        $result = Episciences_Tools::checkIsArxivUrl('');
        $this->assertEmpty($result);
    }

    // ============================================================================
    // Tests for checkIsDoiFromArxiv()
    // ============================================================================

    public function testCheckIsDoiFromArxiv_WithArxivDoi_ReturnsMatches(): void
    {
        // Test arXiv DOI
        $result = Episciences_Tools::checkIsDoiFromArxiv('10.48550/arXiv.2104.12345');
        $this->assertNotEmpty($result);

        // Test case insensitivity
        $result = Episciences_Tools::checkIsDoiFromArxiv('10.48550/ARXIV.2104.12345');
        $this->assertNotEmpty($result);
    }

    public function testCheckIsDoiFromArxiv_WithNonArxivDoi_ReturnsEmptyArray(): void
    {
        $result = Episciences_Tools::checkIsDoiFromArxiv('10.1038/nature12373');
        $this->assertEmpty($result);

        $result = Episciences_Tools::checkIsDoiFromArxiv('10.1000/182');
        $this->assertEmpty($result);

        $result = Episciences_Tools::checkIsDoiFromArxiv('');
        $this->assertEmpty($result);
    }

    // ============================================================================
    // Tests for getSoftwareHeritageDirId()
    // ============================================================================

    public function testGetSoftwareHeritageDirId_WithValidDirSwhid_ReturnsMatches(): void
    {
        // Test directory SWHID
        $swhid = 'swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505';
        $result = Episciences_Tools::getSoftwareHeritageDirId($swhid);
        $this->assertNotEmpty($result);
        $this->assertSame($swhid, $result[0]);

        // Test with qualifiers
        $swhid = 'swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505;origin=https://github.com/torvalds/linux';
        $result = Episciences_Tools::getSoftwareHeritageDirId($swhid);
        $this->assertNotEmpty($result);
    }

    public function testGetSoftwareHeritageDirId_WithNonDirSwhid_ReturnsEmptyArray(): void
    {
        // Test content SWHID (not directory)
        $result = Episciences_Tools::getSoftwareHeritageDirId('swh:1:cnt:94a9ed024d3859793618152ea559a168bbcbb5e2');
        $this->assertEmpty($result);

        // Test revision SWHID
        $result = Episciences_Tools::getSoftwareHeritageDirId('swh:1:rev:309cf2674ee7a0749978cf8265ab91a60aea0f7d');
        $this->assertEmpty($result);

        $result = Episciences_Tools::getSoftwareHeritageDirId('');
        $this->assertEmpty($result);
    }

    // ============================================================================
    // Tests for decodeAmpersand()
    // ============================================================================

    public function testDecodeAmpersand_WithEncodedAmpersand_DecodesCorrectly(): void
    {
        $this->assertSame('foo & bar', Episciences_Tools::decodeAmpersand('foo &amp; bar'));
        $this->assertSame('a & b & c', Episciences_Tools::decodeAmpersand('a &amp; b &amp; c'));
        $this->assertSame('no change', Episciences_Tools::decodeAmpersand('no change'));
        $this->assertSame('', Episciences_Tools::decodeAmpersand(''));
    }

    public function testDecodeAmpersand_PreservesOtherEntities(): void
    {
        // Should only decode &amp; not other entities
        $this->assertSame('&lt; & &gt;', Episciences_Tools::decodeAmpersand('&lt; &amp; &gt;'));
        $this->assertSame('&nbsp;test', Episciences_Tools::decodeAmpersand('&nbsp;test'));
    }

    public function testDecodeAmpersand_WithArray_DecodesAllElements(): void
    {
        $input = ['foo &amp; bar', 'a &amp; b'];
        $expected = ['foo & bar', 'a & b'];
        $this->assertSame($expected, Episciences_Tools::decodeAmpersand($input));
    }

    // ============================================================================
    // Tests for isHal() - direct tests
    // ============================================================================

    public function testIsHal_WithValidHalId_ReturnsTrue(): void
    {
        $validHalIds = [
            'hal-01234567',
            'hal_01234567',
            'hal-01234567v1',
            'hal-01234567v10',
            'inria-01234567',
            'cea-01234567v2',
            'tel-01234567',
            'dumas-01234567v1',
        ];

        foreach ($validHalIds as $halId) {
            $this->assertTrue(
                Episciences_Tools::isHal($halId),
                "HAL ID '$halId' should be valid"
            );
        }
    }

    public function testIsHal_WithInvalidHalId_ReturnsFalse(): void
    {
        $invalidHalIds = [
            '',
            'hal-123',          // Too short
            'hal-1234567',      // 7 digits instead of 8
            'hal-123456789',    // 9 digits
            '01234567',         // No prefix
            'HAL-01234567',     // Uppercase
            'hal01234567',      // No separator
        ];

        foreach ($invalidHalIds as $halId) {
            $this->assertFalse(
                Episciences_Tools::isHal($halId),
                "HAL ID '$halId' should be invalid"
            );
        }
    }

    // ============================================================================
    // Tests for isHalUrl()
    // ============================================================================

    public function testIsHalUrl_WithValidHalUrl_ReturnsTrue(): void
    {
        $validHalUrls = [
            'https://hal.science/hal-04202866v1',
            'https://hal.archives-ouvertes.fr/hal-01234567',
            'http://hal.inria.fr/inria-12345678',
            'https://hal.inria.fr/inria-12345678v2',
        ];

        foreach ($validHalUrls as $url) {
            $this->assertTrue(
                Episciences_Tools::isHalUrl($url),
                "HAL URL '$url' should be valid"
            );
        }
    }

    public function testIsHalUrl_WithInvalidHalUrl_ReturnsFalse(): void
    {
        $invalidHalUrls = [
            'https://example.com/hal-01234567',  // Not a HAL domain
            'https://hal.science/invalid',       // No valid HAL ID
            'https://hal.science',               // No path
            'hal-01234567',                      // Not a URL
            '',
        ];

        foreach ($invalidHalUrls as $url) {
            $this->assertFalse(
                Episciences_Tools::isHalUrl($url),
                "URL '$url' should not be a valid HAL URL"
            );
        }
    }

    // ============================================================================
    // Tests for isDoi() - direct tests
    // ============================================================================

    public function testIsDoi_WithValidDoi_ReturnsTrue(): void
    {
        $validDois = [
            '10.1000/182',
            '10.1038/nature12373',
            '10.1016/j.cell.2013.05.039',
            '10.12345/ABC.DEF-123_456',
            '10.48550/arXiv.2104.12345',
            '10.1234/test-DOI.with_special(chars)',
        ];

        foreach ($validDois as $doi) {
            $this->assertTrue(
                Episciences_Tools::isDoi($doi),
                "DOI '$doi' should be valid"
            );
        }
    }

    public function testIsDoi_WithInvalidDoi_ReturnsFalse(): void
    {
        $invalidDois = [
            '',
            '10.',
            '10.10/',
            '11.1000/182',      // Wrong registrant prefix
            'doi:10.1000/182',  // With "doi:" prefix
            'not-a-doi',
        ];

        foreach ($invalidDois as $doi) {
            $this->assertFalse(
                Episciences_Tools::isDoi($doi),
                "DOI '$doi' should be invalid"
            );
        }
    }

    // ============================================================================
    // Tests for isArxiv() - direct tests
    // ============================================================================

    public function testIsArxiv_WithValidArxivId_ReturnsTrue(): void
    {
        $validArxivIds = [
            '1501.00001',
            '2101.12345',
            '0704.0001',
            'math.AG/0601001',
            'hep-th/9901001',
            'cs.AI/0601001',
            'cond-mat/0601001',
        ];

        foreach ($validArxivIds as $arxivId) {
            $this->assertTrue(
                Episciences_Tools::isArxiv($arxivId),
                "arXiv ID '$arxivId' should be valid"
            );
        }
    }

    public function testIsArxiv_WithInvalidArxivId_ReturnsFalse(): void
    {
        $invalidArxivIds = [
            '',
            '150100001',           // No dot
            '1501.001',            // 3 digits after dot (too short)
            'arxiv:1501.00001',    // With prefix
            '1501.123456',         // 6 digits after dot (too long)
        ];

        foreach ($invalidArxivIds as $arxivId) {
            $this->assertFalse(
                Episciences_Tools::isArxiv($arxivId),
                "arXiv ID '$arxivId' should be invalid"
            );
        }
    }

    // ============================================================================
    // Tests for isSoftwareHeritageId() - direct tests
    // ============================================================================

    public function testIsSoftwareHeritageId_WithValidSwhid_ReturnsTrue(): void
    {
        $validSwhids = [
            'swh:1:cnt:94a9ed024d3859793618152ea559a168bbcbb5e2',
            'swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505',
            'swh:1:rel:22ece559cc7c5c0781a5a8a0a8b9cb3b87b1f2a4',
            'swh:1:rev:309cf2674ee7a0749978cf8265ab91a60aea0f7d',
            'swh:1:snp:1a8893e6a86f444e8be8e7bda6cb34fb1735a00e',
            'swh:1:dir:d198bc9d7a6bcf6db04f476d29314f157507d505;origin=https://github.com/torvalds/linux',
        ];

        foreach ($validSwhids as $swhid) {
            $this->assertTrue(
                Episciences_Tools::isSoftwareHeritageId($swhid),
                "SWHID '$swhid' should be valid"
            );
        }
    }

    public function testIsSoftwareHeritageId_WithInvalidSwhid_ReturnsFalse(): void
    {
        $invalidSwhids = [
            '',
            'swh:1:invalid:94a9ed024d3859793618152ea559a168bbcbb5e2', // Invalid type
            'swh:2:cnt:94a9ed024d3859793618152ea559a168bbcbb5e2',     // Wrong version
            'swh:1:cnt:invalid',                                       // Invalid hash
            'swh:1:cnt:94a9ed024d3859793618152ea559a168bbcbb5e',       // Hash too short
        ];

        foreach ($invalidSwhids as $swhid) {
            $this->assertFalse(
                Episciences_Tools::isSoftwareHeritageId($swhid),
                "SWHID '$swhid' should be invalid"
            );
        }
    }

    // ============================================================================
    // Tests for isHandle() - direct tests
    // ============================================================================

    public function testIsHandle_WithValidHandle_ReturnsTrue(): void
    {
        $validHandles = [
            '1721.1/12345',
            '2027/mdp.39015012345678',
            '11245/1.2345',
            '20.500.12345/abc123def',
            'hdl.handle.net/1721.1/12345',          // URL format (will be cleaned)
            'https://hdl.handle.net/1721.1/12345',  // Full URL
        ];

        foreach ($validHandles as $handle) {
            $this->assertTrue(
                Episciences_Tools::isHandle($handle),
                "Handle '$handle' should be valid"
            );
        }
    }

    public function testIsHandle_WithInvalidHandle_ReturnsFalse(): void
    {
        $invalidHandles = [
            '',
            '1721.1/',              // Missing suffix
            '1721.1',               // No slash
            '10.1000/182',          // This is a DOI, not a handle
            'not-a-handle',
        ];

        foreach ($invalidHandles as $handle) {
            $this->assertFalse(
                Episciences_Tools::isHandle($handle),
                "Handle '$handle' should be invalid"
            );
        }
    }

    public function testIsHandle_DoiExclusion_ReturnsFalse(): void
    {
        // DOIs should be explicitly excluded from handle detection
        $dois = [
            '10.1000/182',
            '10.1038/nature12373',
            '10.48550/arXiv.2104.12345',
        ];

        foreach ($dois as $doi) {
            $this->assertFalse(
                Episciences_Tools::isHandle($doi),
                "DOI '$doi' should not be detected as a Handle"
            );
        }
    }

    // ============================================================================
    // Tests for cleanHandle()
    // ============================================================================

    public function testCleanHandle_WithUrlFormat_ReturnsCleanedHandle(): void
    {
        $this->assertSame('1721.1/12345', Episciences_Tools::cleanHandle('hdl.handle.net/1721.1/12345'));
        $this->assertSame('1721.1/12345', Episciences_Tools::cleanHandle('https://hdl.handle.net/1721.1/12345'));
        $this->assertSame('1721.1/12345', Episciences_Tools::cleanHandle('http://hdl.handle.net/1721.1/12345'));
    }

    public function testCleanHandle_WithPlainHandle_ReturnsUnchanged(): void
    {
        $this->assertSame('1721.1/12345', Episciences_Tools::cleanHandle('1721.1/12345'));
    }

    public function testCleanHandle_WithDoiUrl_ReturnsUnchanged(): void
    {
        // DOI URLs should not be cleaned
        $doiUrl = 'https://doi.org/10.1000/182';
        $this->assertSame($doiUrl, Episciences_Tools::cleanHandle($doiUrl));
    }

    // ============================================================================
    // Tests for addDateInterval()
    // ============================================================================

    public function testAddDateInterval_WithDays_ReturnsCorrectDate(): void
    {
        // Add days
        $this->assertSame('2024-01-15', Episciences_Tools::addDateInterval('2024-01-10', '5 days'));
        $this->assertSame('2024-02-01', Episciences_Tools::addDateInterval('2024-01-25', '7 days'));

        // Cross month boundary
        $this->assertSame('2024-02-05', Episciences_Tools::addDateInterval('2024-01-31', '5 days'));
    }

    public function testAddDateInterval_WithWeeks_ReturnsCorrectDate(): void
    {
        $this->assertSame('2024-01-17', Episciences_Tools::addDateInterval('2024-01-10', '1 week'));
        $this->assertSame('2024-01-24', Episciences_Tools::addDateInterval('2024-01-10', '2 weeks'));
    }

    public function testAddDateInterval_WithMonths_ReturnsCorrectDate(): void
    {
        $this->assertSame('2024-02-10', Episciences_Tools::addDateInterval('2024-01-10', '1 month'));
        $this->assertSame('2024-04-10', Episciences_Tools::addDateInterval('2024-01-10', '3 months'));

        // Cross year boundary
        $this->assertSame('2025-01-10', Episciences_Tools::addDateInterval('2024-01-10', '12 months'));
    }

    public function testAddDateInterval_WithYears_ReturnsCorrectDate(): void
    {
        $this->assertSame('2025-01-10', Episciences_Tools::addDateInterval('2024-01-10', '1 year'));
        $this->assertSame('2029-01-10', Episciences_Tools::addDateInterval('2024-01-10', '5 years'));
    }

    public function testAddDateInterval_WithCustomFormat_ReturnsCorrectFormat(): void
    {
        // Default format is Y-m-d
        $this->assertSame('2024-01-15', Episciences_Tools::addDateInterval('2024-01-10', '5 days'));

        // Custom format d/m/Y
        $this->assertSame('15/01/2024', Episciences_Tools::addDateInterval('2024-01-10', '5 days', 'd/m/Y'));

        // Full datetime format
        $this->assertSame(
            '2024-01-15 00:00:00',
            Episciences_Tools::addDateInterval('2024-01-10', '5 days', 'Y-m-d H:i:s')
        );

        // Year only
        $this->assertSame('2025', Episciences_Tools::addDateInterval('2024-01-10', '1 year', 'Y'));
    }

    public function testAddDateInterval_WithMixedIntervals_ReturnsCorrectDate(): void
    {
        // Combined intervals
        $this->assertSame(
            '2024-02-15',
            Episciences_Tools::addDateInterval('2024-01-10', '1 month 5 days')
        );
    }

    public function testAddDateInterval_LeapYear_HandlesCorrectly(): void
    {
        // February 29 in leap year
        $this->assertSame('2024-02-29', Episciences_Tools::addDateInterval('2024-02-28', '1 day'));
        $this->assertSame('2024-03-01', Episciences_Tools::addDateInterval('2024-02-29', '1 day'));

        // Non-leap year
        $this->assertSame('2023-03-01', Episciences_Tools::addDateInterval('2023-02-28', '1 day'));
    }

    // ============================================================================
    // Tests for isValidDate()
    // ============================================================================

    public function testIsValidDate_WithValidDates_ReturnsTrue(): void
    {
        // Y-m-d format
        $this->assertTrue(Episciences_Tools::isValidDate('2024-01-15', 'Y-m-d'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-12-31', 'Y-m-d'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-02-29', 'Y-m-d')); // Leap year

        // d/m/Y format
        $this->assertTrue(Episciences_Tools::isValidDate('15/01/2024', 'd/m/Y'));
        $this->assertTrue(Episciences_Tools::isValidDate('31/12/2024', 'd/m/Y'));

        // Y-m-d H:i:s format
        $this->assertTrue(Episciences_Tools::isValidDate('2024-01-15 14:30:00', 'Y-m-d H:i:s'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-01-15 00:00:00', 'Y-m-d H:i:s'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-01-15 23:59:59', 'Y-m-d H:i:s'));

        // Y-m format (year-month only)
        $this->assertTrue(Episciences_Tools::isValidDate('2024-01', 'Y-m'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-12', 'Y-m'));

        // Y format (year only)
        $this->assertTrue(Episciences_Tools::isValidDate('2024', 'Y'));
        $this->assertTrue(Episciences_Tools::isValidDate('1999', 'Y'));
    }

    public function testIsValidDate_WithInvalidDates_ReturnsFalse(): void
    {
        // Invalid day
        $this->assertFalse(Episciences_Tools::isValidDate('2024-01-32', 'Y-m-d'));
        $this->assertFalse(Episciences_Tools::isValidDate('2024-01-00', 'Y-m-d'));

        // Invalid month
        $this->assertFalse(Episciences_Tools::isValidDate('2024-13-15', 'Y-m-d'));
        $this->assertFalse(Episciences_Tools::isValidDate('2024-00-15', 'Y-m-d'));

        // February 29 in non-leap year
        $this->assertFalse(Episciences_Tools::isValidDate('2023-02-29', 'Y-m-d'));

        // Invalid time
        $this->assertFalse(Episciences_Tools::isValidDate('2024-01-15 25:00:00', 'Y-m-d H:i:s'));
        $this->assertFalse(Episciences_Tools::isValidDate('2024-01-15 14:60:00', 'Y-m-d H:i:s'));

        // Wrong format
        $this->assertFalse(Episciences_Tools::isValidDate('15/01/2024', 'Y-m-d'));
        $this->assertFalse(Episciences_Tools::isValidDate('2024-01-15', 'd/m/Y'));

        // Empty or invalid strings
        $this->assertFalse(Episciences_Tools::isValidDate('', 'Y-m-d'));
        $this->assertFalse(Episciences_Tools::isValidDate('not-a-date', 'Y-m-d'));
        $this->assertFalse(Episciences_Tools::isValidDate('2024/01/15', 'Y-m-d'));
    }

    public function testIsValidDate_EdgeCases(): void
    {
        // End of months
        $this->assertTrue(Episciences_Tools::isValidDate('2024-01-31', 'Y-m-d'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-04-30', 'Y-m-d'));
        $this->assertFalse(Episciences_Tools::isValidDate('2024-04-31', 'Y-m-d')); // April has 30 days
        $this->assertFalse(Episciences_Tools::isValidDate('2024-06-31', 'Y-m-d')); // June has 30 days

        // February edge cases
        $this->assertTrue(Episciences_Tools::isValidDate('2024-02-28', 'Y-m-d'));
        $this->assertTrue(Episciences_Tools::isValidDate('2024-02-29', 'Y-m-d')); // 2024 is leap year
        $this->assertFalse(Episciences_Tools::isValidDate('2024-02-30', 'Y-m-d'));
    }

    // ============================================================================
    // Tests for isValidSQLDate()
    // ============================================================================

    public function testIsValidSQLDate_WithValidDates_ReturnsTrue(): void
    {
        $validDates = [
            '2024-01-15',
            '2024-12-31',
            '2024-02-29', // Leap year
            '1999-01-01',
            '2099-12-31',
        ];

        foreach ($validDates as $date) {
            $this->assertTrue(
                Episciences_Tools::isValidSQLDate($date),
                "Date '$date' should be a valid SQL date"
            );
        }
    }

    public function testIsValidSQLDate_WithInvalidDates_ReturnsFalse(): void
    {
        $invalidDates = [
            '',
            '2024-1-15',        // Single digit month
            '2024-01-5',        // Single digit day
            '24-01-15',         // Two digit year
            '2024/01/15',       // Wrong separator
            '15-01-2024',       // Wrong order
            '2024-13-15',       // Invalid month
            '2024-01-32',       // Invalid day
            '2023-02-29',       // Non-leap year
            '2024-01-15 14:30', // Includes time
            'not-a-date',
        ];

        foreach ($invalidDates as $date) {
            $this->assertFalse(
                Episciences_Tools::isValidSQLDate($date),
                "Date '$date' should not be a valid SQL date"
            );
        }
    }

    // ============================================================================
    // Tests for isValidSQLDateTime()
    // ============================================================================

    public function testIsValidSQLDateTime_WithValidDateTimes_ReturnsTrue(): void
    {
        $validDateTimes = [
            '2024-01-15 14:30:00',
            '2024-01-15 00:00:00',
            '2024-01-15 23:59:59',
            '2024-12-31 12:00:00',
            '2024-02-29 10:15:30', // Leap year
        ];

        foreach ($validDateTimes as $datetime) {
            $this->assertTrue(
                Episciences_Tools::isValidSQLDateTime($datetime),
                "DateTime '$datetime' should be a valid SQL datetime"
            );
        }
    }

    public function testIsValidSQLDateTime_WithInvalidDateTimes_ReturnsFalse(): void
    {
        $invalidDateTimes = [
            '',
            '2024-01-15',              // Missing time
            '2024-01-15 14:30',        // Missing seconds
            '2024-01-15 14:30:00.123', // Has milliseconds
            '2024-01-15T14:30:00',     // ISO format with T
            '2024-01-15 25:00:00',     // Invalid hour
            '2024-01-15 14:60:00',     // Invalid minutes
            '2024-01-15 14:30:60',     // Invalid seconds
            '2024-13-15 14:30:00',     // Invalid month
            '2023-02-29 14:30:00',     // Non-leap year
            'not-a-datetime',
        ];

        foreach ($invalidDateTimes as $datetime) {
            $this->assertFalse(
                Episciences_Tools::isValidSQLDateTime($datetime),
                "DateTime '$datetime' should not be a valid SQL datetime"
            );
        }
    }

    // ============================================================================
    // Tests for getValidSQLDate()
    // ============================================================================

    public function testGetValidSQLDate_WithFullDate_ReturnsUnchanged(): void
    {
        $this->assertSame('2024-01-15', Episciences_Tools::getValidSQLDate('2024-01-15'));
        $this->assertSame('2024-12-31', Episciences_Tools::getValidSQLDate('2024-12-31'));
        $this->assertSame('2024-02-29', Episciences_Tools::getValidSQLDate('2024-02-29'));
    }

    public function testGetValidSQLDate_WithYearMonth_AddsDay(): void
    {
        // Y-m format should add -01 as day
        $this->assertSame('2024-01-01', Episciences_Tools::getValidSQLDate('2024-01'));
        $this->assertSame('2024-12-01', Episciences_Tools::getValidSQLDate('2024-12'));
        $this->assertSame('2024-06-01', Episciences_Tools::getValidSQLDate('2024-06'));
    }

    public function testGetValidSQLDate_WithYearOnly_AddsMonthAndDay(): void
    {
        // Y format should add -01-01 as month and day
        $this->assertSame('2024-01-01', Episciences_Tools::getValidSQLDate('2024'));
        $this->assertSame('1999-01-01', Episciences_Tools::getValidSQLDate('1999'));
        $this->assertSame('2099-01-01', Episciences_Tools::getValidSQLDate('2099'));
    }

    public function testGetValidSQLDate_WithInvalidDate_ReturnsNull(): void
    {
        $this->assertNull(Episciences_Tools::getValidSQLDate(''));
        $this->assertNull(Episciences_Tools::getValidSQLDate('not-a-date'));
        $this->assertNull(Episciences_Tools::getValidSQLDate('2024-13-15')); // Invalid month
        $this->assertNull(Episciences_Tools::getValidSQLDate('2024-01-32')); // Invalid day
        $this->assertNull(Episciences_Tools::getValidSQLDate('15/01/2024')); // Wrong format
    }

    // ============================================================================
    // Tests for getValidSQLDateTime()
    // ============================================================================

    public function testGetValidSQLDateTime_WithFullDateTime_ReturnsUnchanged(): void
    {
        $this->assertSame(
            '2024-01-15 14:30:00',
            Episciences_Tools::getValidSQLDateTime('2024-01-15 14:30:00')
        );
        $this->assertSame(
            '2024-12-31 23:59:59',
            Episciences_Tools::getValidSQLDateTime('2024-12-31 23:59:59')
        );
    }

    public function testGetValidSQLDateTime_WithDateOnly_AddsTime(): void
    {
        // Full date should add 00:00:00 as time
        $this->assertSame(
            '2024-01-15 00:00:00',
            Episciences_Tools::getValidSQLDateTime('2024-01-15')
        );
        $this->assertSame(
            '2024-12-31 00:00:00',
            Episciences_Tools::getValidSQLDateTime('2024-12-31')
        );
    }

    public function testGetValidSQLDateTime_WithYearMonth_AddsFullTime(): void
    {
        // Y-m format should add -01 as day and 00:00:00 as time
        $this->assertSame(
            '2024-01-01 00:00:00',
            Episciences_Tools::getValidSQLDateTime('2024-01')
        );
        $this->assertSame(
            '2024-06-01 00:00:00',
            Episciences_Tools::getValidSQLDateTime('2024-06')
        );
    }

    public function testGetValidSQLDateTime_WithYearOnly_AddsFullDateTime(): void
    {
        // Y format should add -01-01 00:00:00
        $this->assertSame(
            '2024-01-01 00:00:00',
            Episciences_Tools::getValidSQLDateTime('2024')
        );
        $this->assertSame(
            '1999-01-01 00:00:00',
            Episciences_Tools::getValidSQLDateTime('1999')
        );
    }

    public function testGetValidSQLDateTime_WithInvalidInput_ReturnsNull(): void
    {
        $this->assertNull(Episciences_Tools::getValidSQLDateTime(''));
        $this->assertNull(Episciences_Tools::getValidSQLDateTime('not-a-date'));
        $this->assertNull(Episciences_Tools::getValidSQLDateTime('2024-13-15')); // Invalid month
        $this->assertNull(Episciences_Tools::getValidSQLDateTime('2024-01-32')); // Invalid day
        $this->assertNull(Episciences_Tools::getValidSQLDateTime('15/01/2024')); // Wrong format
    }

    // ============================================================================
    // Tests for toHumanReadable()
    // ============================================================================

    public function testToHumanReadable_WithZeroBytes_ReturnsZero(): void
    {
        $this->assertSame('0.00 B', Episciences_Tools::toHumanReadable(0));
    }

    public function testToHumanReadable_WithBytes_ReturnsBytes(): void
    {
        $this->assertSame('1 B', Episciences_Tools::toHumanReadable(1));
        $this->assertSame('100 B', Episciences_Tools::toHumanReadable(100));
        $this->assertSame('512 B', Episciences_Tools::toHumanReadable(512));
        $this->assertSame('1023 B', Episciences_Tools::toHumanReadable(1023));
    }

    public function testToHumanReadable_WithKilobytes_ReturnsKB(): void
    {
        $this->assertSame('1 KB', Episciences_Tools::toHumanReadable(1024));
        $this->assertSame('1.5 KB', Episciences_Tools::toHumanReadable(1536));
        $this->assertSame('10 KB', Episciences_Tools::toHumanReadable(10240));
        $this->assertSame('500 KB', Episciences_Tools::toHumanReadable(512000));
    }

    public function testToHumanReadable_WithMegabytes_ReturnsMB(): void
    {
        $this->assertSame('1 MB', Episciences_Tools::toHumanReadable(1048576));
        $this->assertSame('1.5 MB', Episciences_Tools::toHumanReadable(1572864));
        $this->assertSame('10 MB', Episciences_Tools::toHumanReadable(10485760));
        $this->assertSame('100 MB', Episciences_Tools::toHumanReadable(104857600));
    }

    public function testToHumanReadable_WithGigabytes_ReturnsGB(): void
    {
        $this->assertSame('1 GB', Episciences_Tools::toHumanReadable(1073741824));
        $this->assertSame('2.5 GB', Episciences_Tools::toHumanReadable(2684354560));
        $this->assertSame('10 GB', Episciences_Tools::toHumanReadable(10737418240));
    }

    public function testToHumanReadable_WithTerabytes_ReturnsTB(): void
    {
        $this->assertSame('1 TB', Episciences_Tools::toHumanReadable(1099511627776));
        $this->assertSame('2 TB', Episciences_Tools::toHumanReadable(2199023255552));
    }

    public function testToHumanReadable_WithPetabytes_ReturnsPB(): void
    {
        $this->assertSame('1 PB', Episciences_Tools::toHumanReadable(1125899906842624));
    }

    public function testToHumanReadable_WithCustomPrecision_ReturnsCorrectDecimals(): void
    {
        // Default precision is 2
        $this->assertSame('1.5 KB', Episciences_Tools::toHumanReadable(1536));

        // Precision 0
        $this->assertSame('2 KB', Episciences_Tools::toHumanReadable(1536, 0));

        // Precision 1
        $this->assertSame('1.5 KB', Episciences_Tools::toHumanReadable(1536, 1));

        // Precision 3
        $this->assertSame('1.5 KB', Episciences_Tools::toHumanReadable(1536, 3));

        // More precise value
        $this->assertSame('1.34 MB', Episciences_Tools::toHumanReadable(1400000, 2));
        $this->assertSame('1.335 MB', Episciences_Tools::toHumanReadable(1400000, 3));
    }

    public function testToHumanReadable_WithRealWorldFileSizes(): void
    {
        // Common file sizes
        $this->assertSame('4 KB', Episciences_Tools::toHumanReadable(4096));       // Small text file
        $this->assertSame('64 KB', Episciences_Tools::toHumanReadable(65536));     // Small image
        $this->assertSame('5 MB', Episciences_Tools::toHumanReadable(5242880));    // PDF document
        $this->assertSame('700 MB', Episciences_Tools::toHumanReadable(734003200)); // CD image
        $this->assertSame('4.7 GB', Episciences_Tools::toHumanReadable(5046586573)); // DVD image
    }

    // ============================================================================
    // Tests for convertToBytes()
    // ============================================================================

    public function testConvertToBytes_WithBytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(0, Episciences_Tools::convertToBytes('0'));
        $this->assertSame(0, Episciences_Tools::convertToBytes('0b'));
        $this->assertSame(0, Episciences_Tools::convertToBytes('0B'));
        $this->assertSame(1, Episciences_Tools::convertToBytes('1'));
        $this->assertSame(1, Episciences_Tools::convertToBytes('1b'));
        $this->assertSame(100, Episciences_Tools::convertToBytes('100'));
        $this->assertSame(100, Episciences_Tools::convertToBytes('100B'));
        $this->assertSame(512, Episciences_Tools::convertToBytes('512b'));
    }

    public function testConvertToBytes_WithKilobytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(1024, Episciences_Tools::convertToBytes('1k'));
        $this->assertSame(1024, Episciences_Tools::convertToBytes('1K'));
        $this->assertSame(2048, Episciences_Tools::convertToBytes('2k'));
        $this->assertSame(10240, Episciences_Tools::convertToBytes('10k'));
        $this->assertSame(512000, Episciences_Tools::convertToBytes('500k'));
    }

    public function testConvertToBytes_WithMegabytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(1048576, Episciences_Tools::convertToBytes('1m'));
        $this->assertSame(1048576, Episciences_Tools::convertToBytes('1M'));
        $this->assertSame(2097152, Episciences_Tools::convertToBytes('2m'));
        $this->assertSame(10485760, Episciences_Tools::convertToBytes('10m'));
        $this->assertSame(104857600, Episciences_Tools::convertToBytes('100M'));
    }

    public function testConvertToBytes_WithGigabytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(1073741824, Episciences_Tools::convertToBytes('1g'));
        $this->assertSame(1073741824, Episciences_Tools::convertToBytes('1G'));
        $this->assertSame(2147483648, Episciences_Tools::convertToBytes('2g'));
        $this->assertSame(10737418240, Episciences_Tools::convertToBytes('10G'));
    }

    public function testConvertToBytes_WithTerabytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(1099511627776, Episciences_Tools::convertToBytes('1t'));
        $this->assertSame(1099511627776, Episciences_Tools::convertToBytes('1T'));
        $this->assertSame(2199023255552, Episciences_Tools::convertToBytes('2t'));
    }

    public function testConvertToBytes_WithPetabytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(1125899906842624, Episciences_Tools::convertToBytes('1p'));
        $this->assertSame(1125899906842624, Episciences_Tools::convertToBytes('1P'));
    }

    public function testConvertToBytes_WithExabytesUnit_ReturnsCorrectValue(): void
    {
        $this->assertSame(1152921504606846976, Episciences_Tools::convertToBytes('1e'));
        $this->assertSame(1152921504606846976, Episciences_Tools::convertToBytes('1E'));
    }

    public function testConvertToBytes_WithWhitespace_TrimsInput(): void
    {
        $this->assertSame(1048576, Episciences_Tools::convertToBytes('  1m  '));
        $this->assertSame(1024, Episciences_Tools::convertToBytes(' 1k '));
        $this->assertSame(100, Episciences_Tools::convertToBytes('  100  '));
    }

    public function testConvertToBytes_WithEmptyString_ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Episciences_Tools::convertToBytes('');
    }

    public function testConvertToBytes_WithWhitespaceOnly_ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Episciences_Tools::convertToBytes('   ');
    }

    public function testConvertToBytes_WithNegativeValue_ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Episciences_Tools::convertToBytes('-5m');
    }

    public function testConvertToBytes_WithInvalidUnit_ThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Conversion from { x } to { bytes } is not available.');
        Episciences_Tools::convertToBytes('10x');
    }

    public function testConvertToBytes_WithInvalidUnit2_ThrowsException(): void
    {
        $this->expectException(\Exception::class);
        Episciences_Tools::convertToBytes('5z');
    }

    public function testConvertToBytes_WithRealWorldValues(): void
    {
        // PHP ini-style values
        $this->assertSame(134217728, Episciences_Tools::convertToBytes('128M')); // memory_limit
        $this->assertSame(8388608, Episciences_Tools::convertToBytes('8M'));     // upload_max_filesize
        $this->assertSame(2097152, Episciences_Tools::convertToBytes('2M'));     // post_max_size
        $this->assertSame(1073741824, Episciences_Tools::convertToBytes('1G'));  // Large memory limit
        $this->assertSame(0, Episciences_Tools::convertToBytes('0'));            // post_max_size disabled
    }

    public function testConvertToBytes_RoundTrip_WithToHumanReadable(): void
    {
        // Test that converting to bytes and back gives consistent results
        $originalBytes = 10485760; // 10 MB
        $humanReadable = Episciences_Tools::toHumanReadable($originalBytes, 0);
        $this->assertSame('10 MB', $humanReadable);

        // Note: convertToBytes uses shorthand (10m), not full format (10 MB)
        $this->assertSame($originalBytes, Episciences_Tools::convertToBytes('10m'));
    }

    // ============================================================================
    // Tests for formatUser()
    // ============================================================================

    public function testFormatUser_WithAllParameters_ReturnsFormattedName(): void
    {
        // Full name with civility
        $this->assertSame('Mr. John Doe', Episciences_Tools::formatUser('John', 'Doe', 'Mr.'));
        $this->assertSame('Dr. Jane Smith', Episciences_Tools::formatUser('Jane', 'Smith', 'Dr.'));
        $this->assertSame('Prof. Albert Einstein', Episciences_Tools::formatUser('Albert', 'Einstein', 'Prof.'));
    }

    public function testFormatUser_WithoutCivility_ReturnsNameOnly(): void
    {
        $this->assertSame('John Doe', Episciences_Tools::formatUser('John', 'Doe'));
        $this->assertSame('Jane Smith', Episciences_Tools::formatUser('Jane', 'Smith', ''));
    }

    public function testFormatUser_WithFirstnameOnly_ReturnsFirstname(): void
    {
        $this->assertSame('John', Episciences_Tools::formatUser('John'));
        $this->assertSame('John', Episciences_Tools::formatUser('John', ''));
        $this->assertSame('John', Episciences_Tools::formatUser('John', '', ''));
    }

    public function testFormatUser_WithLastnameOnly_ReturnsLastname(): void
    {
        $this->assertSame('Doe', Episciences_Tools::formatUser('', 'Doe'));
        $this->assertSame('Doe', Episciences_Tools::formatUser('', 'Doe', ''));
    }

    public function testFormatUser_WithCivilityOnly_ReturnsCivility(): void
    {
        $this->assertSame('Mr.', Episciences_Tools::formatUser('', '', 'Mr.'));
        $this->assertSame('Dr.', Episciences_Tools::formatUser('', '', 'Dr.'));
    }

    public function testFormatUser_WithEmptyParameters_ReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_Tools::formatUser());
        $this->assertSame('', Episciences_Tools::formatUser(''));
        $this->assertSame('', Episciences_Tools::formatUser('', ''));
        $this->assertSame('', Episciences_Tools::formatUser('', '', ''));
    }

    public function testFormatUser_NormalizesCase_UppercaseInput(): void
    {
        // Uppercase input should be normalized to Ucfirst
        $this->assertSame('John Doe', Episciences_Tools::formatUser('JOHN', 'DOE'));
        $this->assertSame('Mr. John Doe', Episciences_Tools::formatUser('JOHN', 'DOE', 'Mr.'));
    }

    public function testFormatUser_NormalizesCase_LowercaseInput(): void
    {
        // Lowercase input should be normalized to Ucfirst
        $this->assertSame('John Doe', Episciences_Tools::formatUser('john', 'doe'));
        $this->assertSame('Jane Smith', Episciences_Tools::formatUser('jane', 'smith'));
    }

    public function testFormatUser_NormalizesCase_MixedCaseInput(): void
    {
        // Mixed case input should be normalized to Ucfirst
        $this->assertSame('John Doe', Episciences_Tools::formatUser('jOHN', 'dOE'));
        $this->assertSame('Mcdonald', Episciences_Tools::formatUser('', 'McDonald')); // Note: McD becomes Mcdonald
    }

    public function testFormatUser_WithUnicodeCharacters_HandlesCorrectly(): void
    {
        // French names with accents
        $this->assertSame('François Müller', Episciences_Tools::formatUser('François', 'Müller'));
        $this->assertSame('José García', Episciences_Tools::formatUser('José', 'García'));

        // Uppercase with accents
        $this->assertSame('François Müller', Episciences_Tools::formatUser('FRANÇOIS', 'MÜLLER'));

        // Names with special characters (note: ucfirst doesn't handle multi-byte first chars)
        $this->assertSame('åke Björk', Episciences_Tools::formatUser('Åke', 'Björk'));
    }

    public function testFormatUser_WithNonStringParameters_IgnoresNonStrings(): void
    {
        // Non-string parameters should be ignored (not cause errors)
        $this->assertSame('Doe', Episciences_Tools::formatUser(123, 'Doe'));
        $this->assertSame('John', Episciences_Tools::formatUser('John', 456));
        $this->assertSame('John Doe', Episciences_Tools::formatUser('John', 'Doe', 789));
        $this->assertSame('', Episciences_Tools::formatUser(null, null, null));
        $this->assertSame('', Episciences_Tools::formatUser([], [], []));
    }

    public function testFormatUser_TrimsResult(): void
    {
        // Result should be trimmed (no leading/trailing spaces)
        $this->assertSame('John Doe', Episciences_Tools::formatUser('John', 'Doe', ''));
        $this->assertSame('Doe', Episciences_Tools::formatUser('', 'Doe', ''));
    }

    public function testFormatUser_RealWorldExamples(): void
    {
        // Common academic names
        $this->assertSame('Prof. Marie Curie', Episciences_Tools::formatUser('Marie', 'Curie', 'Prof.'));
        $this->assertSame('Dr. Albert Einstein', Episciences_Tools::formatUser('Albert', 'Einstein', 'Dr.'));
        $this->assertSame('Jean-pierre Dupont', Episciences_Tools::formatUser('Jean-Pierre', 'Dupont'));

        // Single names
        $this->assertSame('Madonna', Episciences_Tools::formatUser('Madonna', ''));
        $this->assertSame('Prince', Episciences_Tools::formatUser('', 'Prince'));
    }

    // ============================================================================
    // Tests for checkUrl()
    // ============================================================================

    public function testCheckUrl_WithHttpsUrl_ReturnsUnchanged(): void
    {
        $this->assertSame('https://example.com', Episciences_Tools::checkUrl('https://example.com'));
        $this->assertSame('https://www.example.com', Episciences_Tools::checkUrl('https://www.example.com'));
        $this->assertSame('https://example.com/path/to/page', Episciences_Tools::checkUrl('https://example.com/path/to/page'));
        $this->assertSame('https://example.com?query=1', Episciences_Tools::checkUrl('https://example.com?query=1'));
    }

    public function testCheckUrl_WithHttpUrl_ReturnsUnchanged(): void
    {
        $this->assertSame('http://example.com', Episciences_Tools::checkUrl('http://example.com'));
        $this->assertSame('http://www.example.com', Episciences_Tools::checkUrl('http://www.example.com'));
        $this->assertSame('http://example.com/path', Episciences_Tools::checkUrl('http://example.com/path'));
    }

    public function testCheckUrl_WithoutProtocol_AddsHttp(): void
    {
        $this->assertSame('http://example.com', Episciences_Tools::checkUrl('example.com'));
        $this->assertSame('http://www.example.com', Episciences_Tools::checkUrl('www.example.com'));
        $this->assertSame('http://subdomain.example.com', Episciences_Tools::checkUrl('subdomain.example.com'));
    }

    public function testCheckUrl_WithPath_AddsHttpAndPreservesPath(): void
    {
        $this->assertSame('http://example.com/path/to/page', Episciences_Tools::checkUrl('example.com/path/to/page'));
        $this->assertSame('http://example.com/index.html', Episciences_Tools::checkUrl('example.com/index.html'));
    }

    public function testCheckUrl_WithQueryString_AddsHttpAndPreservesQuery(): void
    {
        $this->assertSame('http://example.com?query=value', Episciences_Tools::checkUrl('example.com?query=value'));
        $this->assertSame('http://example.com/page?a=1&b=2', Episciences_Tools::checkUrl('example.com/page?a=1&b=2'));
    }

    public function testCheckUrl_WithFragment_AddsHttpAndPreservesFragment(): void
    {
        $this->assertSame('http://example.com#section', Episciences_Tools::checkUrl('example.com#section'));
        $this->assertSame('http://example.com/page#anchor', Episciences_Tools::checkUrl('example.com/page#anchor'));
    }

    public function testCheckUrl_WithPort_AddsHttpAndPreservesPort(): void
    {
        $this->assertSame('http://example.com:8080', Episciences_Tools::checkUrl('example.com:8080'));
        $this->assertSame('http://localhost:3000', Episciences_Tools::checkUrl('localhost:3000'));
        $this->assertSame('https://example.com:443', Episciences_Tools::checkUrl('https://example.com:443'));
    }

    public function testCheckUrl_WithIpAddress_AddsHttp(): void
    {
        $this->assertSame('http://192.168.1.1', Episciences_Tools::checkUrl('192.168.1.1'));
        $this->assertSame('http://127.0.0.1:8080', Episciences_Tools::checkUrl('127.0.0.1:8080'));
        $this->assertSame('https://192.168.1.1', Episciences_Tools::checkUrl('https://192.168.1.1'));
    }

    public function testCheckUrl_CaseSensitivity_HandlesUppercaseProtocol(): void
    {
        // HTTP/HTTPS check is case-sensitive in the regex
        $this->assertSame('http://HTTP://example.com', Episciences_Tools::checkUrl('HTTP://example.com'));
        $this->assertSame('http://HTTPS://example.com', Episciences_Tools::checkUrl('HTTPS://example.com'));
    }

    public function testCheckUrl_WithEmptyString_AddsHttp(): void
    {
        $this->assertSame('http://', Episciences_Tools::checkUrl(''));
    }

    public function testCheckUrl_RealWorldUrls(): void
    {
        // Academic/research URLs
        $this->assertSame('https://hal.science', Episciences_Tools::checkUrl('https://hal.science'));
        $this->assertSame('http://arxiv.org', Episciences_Tools::checkUrl('arxiv.org'));
        $this->assertSame('https://doi.org/10.1000/182', Episciences_Tools::checkUrl('https://doi.org/10.1000/182'));
        $this->assertSame('http://orcid.org/0000-0001-2345-6789', Episciences_Tools::checkUrl('orcid.org/0000-0001-2345-6789'));

        // Journal URLs
        $this->assertSame('http://episciences.org', Episciences_Tools::checkUrl('episciences.org'));
        $this->assertSame('https://www.episciences.org', Episciences_Tools::checkUrl('https://www.episciences.org'));
    }

    // ============================================================================
    // Tests for startsWithNumber()
    // ============================================================================

    public function testStartsWithNumber_WithDigitAtStart_ReturnsTrue(): void
    {
        // Single digits
        $this->assertTrue(Episciences_Tools::startsWithNumber('0'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('1'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('5'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('9'));

        // Digits followed by text
        $this->assertTrue(Episciences_Tools::startsWithNumber('1abc'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('2024-01-15'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('42 is the answer'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('007 James Bond'));
    }

    public function testStartsWithNumber_WithLetterAtStart_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_Tools::startsWithNumber('a'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('abc'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('Hello World'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('Test123'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('A1B2C3'));
    }

    public function testStartsWithNumber_WithSpecialCharAtStart_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_Tools::startsWithNumber('!abc'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('@123'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('#hashtag'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('$100'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('-5'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('+10'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('.5'));
        $this->assertFalse(Episciences_Tools::startsWithNumber(' 123')); // Space at start
    }

    public function testStartsWithNumber_WithEmptyString_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_Tools::startsWithNumber(''));
    }

    public function testStartsWithNumber_WithWhitespaceAtStart_ReturnsFalse(): void
    {
        $this->assertFalse(Episciences_Tools::startsWithNumber(' '));
        $this->assertFalse(Episciences_Tools::startsWithNumber('  123'));
        $this->assertFalse(Episciences_Tools::startsWithNumber("\t123"));
        $this->assertFalse(Episciences_Tools::startsWithNumber("\n123"));
    }

    public function testStartsWithNumber_WithUnicodeDigits_ReturnsFalse(): void
    {
        // ctype_digit only recognizes ASCII digits 0-9
        // Unicode digits like ① ② ٣ should return false
        $this->assertFalse(Episciences_Tools::startsWithNumber('①abc')); // Circled digit
        $this->assertFalse(Episciences_Tools::startsWithNumber('٣abc')); // Arabic-Indic digit
    }

    public function testStartsWithNumber_RealWorldExamples(): void
    {
        // Document/paper identifiers
        $this->assertTrue(Episciences_Tools::startsWithNumber('2024.12345'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('10.1000/182'));

        // Version numbers
        $this->assertTrue(Episciences_Tools::startsWithNumber('1.0.0'));
        $this->assertTrue(Episciences_Tools::startsWithNumber('2.3.4-beta'));

        // File names
        $this->assertTrue(Episciences_Tools::startsWithNumber('001_introduction.pdf'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('chapter_1.pdf'));

        // Dates
        $this->assertTrue(Episciences_Tools::startsWithNumber('2024-01-15'));
        $this->assertFalse(Episciences_Tools::startsWithNumber('January 15, 2024'));
    }

    // ============================================================================
    // Tests for formatText()
    // ============================================================================

    public function testFormatText_WithNull_ReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_Tools::formatText(null));
    }

    public function testFormatText_WithEmptyString_ReturnsEmptyString(): void
    {
        $this->assertSame('', Episciences_Tools::formatText(''));
    }

    public function testFormatText_WithSimpleText_ReturnsUnchanged(): void
    {
        $this->assertSame('Hello World', Episciences_Tools::formatText('Hello World'));
        $this->assertSame('Simple text', Episciences_Tools::formatText('Simple text'));
    }

    public function testFormatText_WithNewlines_ConvertsToBr(): void
    {
        // Single newline
        $this->assertSame("Line 1<br />\nLine 2", Episciences_Tools::formatText("Line 1\nLine 2"));

        // Multiple newlines
        $this->assertSame(
            "Line 1<br />\nLine 2<br />\nLine 3",
            Episciences_Tools::formatText("Line 1\nLine 2\nLine 3")
        );

        // Windows-style line endings
        $this->assertSame(
            "Line 1<br />\r\nLine 2",
            Episciences_Tools::formatText("Line 1\r\nLine 2")
        );
    }

    public function testFormatText_WithTabs_ConvertsToNbsp(): void
    {
        $htmlTab = '&nbsp;&nbsp;&nbsp;&nbsp;';

        // Single tab
        $this->assertSame("Hello{$htmlTab}World", Episciences_Tools::formatText("Hello\tWorld"));

        // Multiple tabs
        $this->assertSame(
            "Col1{$htmlTab}Col2{$htmlTab}Col3",
            Episciences_Tools::formatText("Col1\tCol2\tCol3")
        );

        // Tab at beginning
        $this->assertSame("{$htmlTab}Indented", Episciences_Tools::formatText("\tIndented"));
    }

    public function testFormatText_WithFourOrMoreSpaces_ConvertsToNbsp(): void
    {
        $htmlTab = '&nbsp;&nbsp;&nbsp;&nbsp;';

        // Exactly 4 spaces
        $this->assertSame("Hello{$htmlTab}World", Episciences_Tools::formatText("Hello    World"));

        // More than 4 spaces (5, 6, etc.)
        $this->assertSame("Hello{$htmlTab}World", Episciences_Tools::formatText("Hello     World"));
        $this->assertSame("Hello{$htmlTab}World", Episciences_Tools::formatText("Hello      World"));

        // 8 spaces (two groups)
        $this->assertSame("Hello{$htmlTab}World", Episciences_Tools::formatText("Hello        World"));
    }

    public function testFormatText_WithLessThanFourSpaces_PreservesSpaces(): void
    {
        // 1 space - unchanged
        $this->assertSame("Hello World", Episciences_Tools::formatText("Hello World"));

        // 2 spaces - unchanged
        $this->assertSame("Hello  World", Episciences_Tools::formatText("Hello  World"));

        // 3 spaces - unchanged
        $this->assertSame("Hello   World", Episciences_Tools::formatText("Hello   World"));
    }

    public function testFormatText_WithMixedWhitespace_HandlesCorrectly(): void
    {
        $htmlTab = '&nbsp;&nbsp;&nbsp;&nbsp;';

        // Tab and newline
        $this->assertSame(
            "{$htmlTab}Line 1<br />\n{$htmlTab}Line 2",
            Episciences_Tools::formatText("\tLine 1\n\tLine 2")
        );

        // Multiple whitespace types
        $this->assertSame(
            "Start{$htmlTab}middle<br />\nend",
            Episciences_Tools::formatText("Start\tmiddle\nend")
        );
    }

    public function testFormatText_WithCodeLikeContent_FormatsCorrectly(): void
    {
        $htmlTab = '&nbsp;&nbsp;&nbsp;&nbsp;';

        // Indented code block
        $input = "function test() {\n\treturn true;\n}";
        $expected = "function test() {<br />\n{$htmlTab}return true;<br />\n}";
        $this->assertSame($expected, Episciences_Tools::formatText($input));

        // Python-style indentation (4 spaces)
        $input = "def test():\n    return True";
        $expected = "def test():<br />\n{$htmlTab}return True";
        $this->assertSame($expected, Episciences_Tools::formatText($input));
    }

    public function testFormatText_WithUnicodeContent_PreservesUnicode(): void
    {
        // French text with accents
        $this->assertSame('Café à la crème', Episciences_Tools::formatText('Café à la crème'));

        // Unicode with newlines
        $this->assertSame(
            "Première ligne<br />\nDeuxième ligne",
            Episciences_Tools::formatText("Première ligne\nDeuxième ligne")
        );
    }

    public function testFormatText_RealWorldExamples(): void
    {
        $htmlTab = '&nbsp;&nbsp;&nbsp;&nbsp;';

        // Abstract with paragraphs
        $input = "This is the first paragraph.\n\nThis is the second paragraph.";
        $expected = "This is the first paragraph.<br />\n<br />\nThis is the second paragraph.";
        $this->assertSame($expected, Episciences_Tools::formatText($input));

        // Bullet list (using tabs for indentation)
        $input = "Items:\n\t- First item\n\t- Second item";
        $expected = "Items:<br />\n{$htmlTab}- First item<br />\n{$htmlTab}- Second item";
        $this->assertSame($expected, Episciences_Tools::formatText($input));

        // Table-like data with tabs
        $input = "Name\tAge\tCity\nJohn\t30\tParis";
        $expected = "Name{$htmlTab}Age{$htmlTab}City<br />\nJohn{$htmlTab}30{$htmlTab}Paris";
        $this->assertSame($expected, Episciences_Tools::formatText($input));
    }

}