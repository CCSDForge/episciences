<?php

declare(strict_types=1);

namespace unit\library\Episciences\Upload;

use Episciences\Upload\MimeTypePolicy;
use Episciences\Upload\UploadChecker;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Real files, checked against the policy configured for the journals.
 *
 * @covers \Episciences\Upload\UploadChecker
 * @covers \Episciences_Form_Validate_MimeType
 */
final class UploadCheckerTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private string $directory;
    private MimeTypePolicy $policy;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/upload_checker_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
        $this->policy = MimeTypePolicy::fromJsonFile(APPLICATION_PATH . '/configs/journal.configurable.constants.json');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * @dataProvider providerAcceptedFiles
     */
    public function testAcceptsAFileWhoseContentMatchesItsExtension(string $name, string $contents): void
    {
        self::assertNull(UploadChecker::firstError($this->store($contents), $name, UPLOAD_ERR_OK, $this->policy), $name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerAcceptedFiles(): array
    {
        $tex = "\\NeedsTeXFormat{LaTeX2e}\n\\ProvidesPackage{example}\n";

        return [
            'png' => ['figure.png', (string)base64_decode(self::PNG)],
            'uppercase extension' => ['FIGURE.PNG', (string)base64_decode(self::PNG)],
            'pdf' => ['article.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"],
            'txt' => ['notes.txt', "Some notes\nsecond line\n"],
            'markdown' => ['README.md', "# Title\n\nSome text\n"],
            'json' => ['data.json', "{\"key\": \"value\"}\n"],
            'tex' => ['main.tex', "\\documentclass{article}\n\\begin{document}Hello\\end{document}\n"],
            'bbl' => ['main.bbl', "\\begin{thebibliography}{1}\n\\bibitem{a} Author\n\\end{thebibliography}\n"],
            'bib' => ['refs.bib', "@article{a,\n  title={Title}\n}\n"],
            'sty' => ['style.sty', $tex],
            'cls' => ['style.cls', $tex],
            'def' => ['style.def', $tex],
            'dtx' => ['style.dtx', $tex],
            'bst' => ['style.bst', "ENTRY { address author }\n{}\n{}\nFUNCTION {x} { skip$ }\n"],
            'css' => ['style.css', "body { color: red; }\n"],
            'eps' => ['figure.eps', "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 10 10\nshowpage\n"],
            'zip' => ['archive.zip', self::zip(['a.txt' => 'content'])],
            'docx' => ['letter.docx', self::zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w/>'])],
        ];
    }

    /**
     * @dataProvider providerRefusedFiles
     */
    public function testRefusesAFileWhoseContentDoesNotMatchItsExtension(string $name, string $contents): void
    {
        $error = UploadChecker::firstError($this->store($contents), $name, UPLOAD_ERR_OK, $this->policy);

        self::assertNotNull($error, $name);
        self::assertStringContainsString($name, $error);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerRefusedFiles(): array
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";

        return [
            'png renamed to pdf' => ['article.pdf', (string)base64_decode(self::PNG)],
            'pdf renamed to png' => ['figure.png', $pdf],
            'svg renamed to png' => ['figure.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'],
            'html script renamed to pdf' => ['article.pdf', '<html><script>alert(1)</script></html>'],
            'a type accepted elsewhere, under the wrong extension' => ['notes.docx', "plain text pretending to be a document\n"],
            'extension not accepted at all' => ['payload.exe', (string)base64_decode(self::PNG)],
            'no extension' => ['article', (string)base64_decode(self::PNG)],
        ];
    }

    public function testRefusesAnEmptyFile(): void
    {
        $error = UploadChecker::firstError($this->store(''), 'notes.txt', UPLOAD_ERR_OK, $this->policy);

        self::assertNotNull($error);
        self::assertStringContainsString('empty', $error);
    }

    public function testRefusesAFileThatWasNotTransferred(): void
    {
        $error = UploadChecker::firstError($this->directory . '/missing.pdf', 'article.pdf', UPLOAD_ERR_PARTIAL, $this->policy);

        self::assertNotNull($error);
        self::assertStringContainsString('article.pdf', $error);
    }

    public function testRefusesAMissingFile(): void
    {
        self::assertNotNull(UploadChecker::firstError($this->directory . '/missing.pdf', 'article.pdf', UPLOAD_ERR_OK, $this->policy));
    }

    public function testMessagesAreWrittenForUsers(): void
    {
        $messages = [
            UploadChecker::firstError($this->store(''), 'notes.txt', UPLOAD_ERR_OK, $this->policy),
            UploadChecker::firstError($this->store((string)base64_decode(self::PNG)), 'article.pdf', UPLOAD_ERR_OK, $this->policy),
            UploadChecker::firstError($this->directory . '/missing.pdf', 'article.pdf', UPLOAD_ERR_PARTIAL, $this->policy),
        ];

        foreach ($messages as $message) {
            self::assertNotNull($message);
            self::assertDoesNotMatchRegularExpression('/mime|octet-stream|image\/png|application\//i', $message, 'no technical wording');
            self::assertStringContainsString('Please', $message, 'tells the user what to do');
        }
    }

    /**
     * @param array<string, string> $entries
     */
    private static function zip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $archive = new ZipArchive();
        $archive->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $archive->addFromString($name, $contents);
        }

        $archive->close();
        $contents = (string)file_get_contents($path);
        unlink($path);

        return $contents;
    }

    private function store(string $contents): string
    {
        $path = $this->directory . '/' . bin2hex(random_bytes(6));
        file_put_contents($path, $contents);

        return $path;
    }
}
