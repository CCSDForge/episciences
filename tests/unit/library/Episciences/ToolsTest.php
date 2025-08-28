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

}