<?php

namespace unit\library\Ccsd;

use Ccsd_Lang_Reader;
use Ccsd_Lang_Writer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for Ccsd_Lang_Reader, Ccsd_Lang_Writer
 *
 * Reader/Writer filesystem methods are tested via reflection (createFileContent)
 * and a temporary directory.
 */
class Ccsd_LangTest extends TestCase
{
    // ------------------------------------------------------------------
    // Ccsd_Lang_Reader — get() (load=false, data injected via reflection)
    // ------------------------------------------------------------------

    private function makeReader(array $data, array $languages): Ccsd_Lang_Reader
    {
        $reader = new Ccsd_Lang_Reader('dummy', '/nonexistent/', $languages, false);
        $prop = new ReflectionProperty(Ccsd_Lang_Reader::class, '_data');
        $prop->setAccessible(true);
        $prop->setValue($reader, $data);
        return $reader;
    }

    public function testReaderGetKeyAndLang(): void
    {
        $reader = $this->makeReader(
            ['hello' => ['en' => 'Hello', 'fr' => 'Bonjour']],
            ['en', 'fr']
        );
        $this->assertSame('Hello', $reader->get('hello', 'en'));
        $this->assertSame('Bonjour', $reader->get('hello', 'fr'));
    }

    public function testReaderGetMissingKeyReturnsEmptyString(): void
    {
        $reader = $this->makeReader(
            ['hello' => ['en' => 'Hello']],
            ['en']
        );
        $this->assertSame('', $reader->get('missing', 'en'));
    }

    public function testReaderGetAllKeysForLang(): void
    {
        $reader = $this->makeReader(
            ['a' => ['en' => 'A'], 'b' => ['en' => 'B']],
            ['en']
        );
        $result = $reader->get('', 'en');
        $this->assertSame(['a' => 'A', 'b' => 'B'], $result);
    }

    public function testReaderGetAllLanguages(): void
    {
        $data = ['hello' => ['en' => 'Hello', 'fr' => 'Bonjour']];
        $reader = $this->makeReader($data, ['en', 'fr']);
        $result = $reader->get();
        $this->assertSame($data, $result);
    }

    public function testReaderGetKeyAllLanguages(): void
    {
        $reader = $this->makeReader(
            ['hello' => ['en' => 'Hello', 'fr' => 'Bonjour']],
            ['en', 'fr']
        );
        $result = $reader->get('hello');
        $this->assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $result);
    }

    public function testReaderGetUnknownLangReturnsFalse(): void
    {
        $reader = $this->makeReader(['hello' => ['en' => 'Hello']], ['en']);
        $this->assertFalse($reader->get('hello', 'xx'));
    }

    // ------------------------------------------------------------------
    // Ccsd_Lang_Writer — constructor + createFileContent (via reflection)
    // ------------------------------------------------------------------

    private function callCreateFileContent(Ccsd_Lang_Writer $writer, array $data): string
    {
        $m = new ReflectionMethod(Ccsd_Lang_Writer::class, 'createFileContent');
        $m->setAccessible(true);
        return $m->invoke($writer, $data);
    }

    public function testWriterConstructorExtractsLanguages(): void
    {
        $data = ['hello' => ['en' => 'Hello', 'fr' => 'Bonjour']];
        $writer = new Ccsd_Lang_Writer($data);

        $prop = new ReflectionProperty(Ccsd_Lang_Writer::class, '_languages');
        $prop->setAccessible(true);
        $languages = $prop->getValue($writer);

        $this->assertContains('en', $languages);
        $this->assertContains('fr', $languages);
    }

    public function testWriterConstructorOrganizesDataByLang(): void
    {
        $data = ['hello' => ['en' => 'Hello', 'fr' => 'Bonjour']];
        $writer = new Ccsd_Lang_Writer($data);

        $prop = new ReflectionProperty(Ccsd_Lang_Writer::class, '_data');
        $prop->setAccessible(true);
        $internalData = $prop->getValue($writer);

        $this->assertSame('Hello', $internalData['en']['hello']);
        $this->assertSame('Bonjour', $internalData['fr']['hello']);
    }

    public function testCreateFileContentContainsPhpTag(): void
    {
        $writer = new Ccsd_Lang_Writer([]);
        $content = $this->callCreateFileContent($writer, ['key' => 'value']);
        $this->assertStringContainsString('<?php', $content);
    }

    public function testCreateFileContentContainsReturnArray(): void
    {
        $writer = new Ccsd_Lang_Writer([]);
        $content = $this->callCreateFileContent($writer, ['key' => 'value']);
        $this->assertStringContainsString('return array(', $content);
        $this->assertStringContainsString('"key" => "value"', $content);
    }

    public function testCreateFileContentEscapesSpecialChars(): void
    {
        $writer = new Ccsd_Lang_Writer([]);
        $content = $this->callCreateFileContent($writer, ['key' => 'say "hello"']);
        // addcslashes escapes " in value
        $this->assertStringContainsString('say \"hello\"', $content);
    }

    public function testCreateFileContentEmptyData(): void
    {
        $writer = new Ccsd_Lang_Writer([]);
        $content = $this->callCreateFileContent($writer, []);
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('return array(', $content);
        $this->assertStringContainsString(');', $content);
    }

    // ------------------------------------------------------------------
    // Ccsd_Lang_Writer — round-trip: write then read back via readFile
    // ------------------------------------------------------------------

    public function testWriterRoundTrip(): void
    {
        $data = [
            'greeting' => ['en' => 'Hello', 'fr' => 'Bonjour'],
            'farewell' => ['en' => 'Goodbye', 'fr' => 'Au revoir'],
        ];
        $writer = new Ccsd_Lang_Writer($data);

        $tmpDir = sys_get_temp_dir() . '/ccsd_lang_test_' . uniqid() . '/';
        try {
            $writer->write($tmpDir, 'test');

            // Verify the written files can be included and match the original data
            $enFile = $tmpDir . 'en/test.php';
            $frFile = $tmpDir . 'fr/test.php';

            $this->assertFileExists($enFile);
            $this->assertFileExists($frFile);

            $enData = include $enFile;
            $frData = include $frFile;

            $this->assertSame('Hello', $enData['greeting']);
            $this->assertSame('Goodbye', $enData['farewell']);
            $this->assertSame('Bonjour', $frData['greeting']);
            $this->assertSame('Au revoir', $frData['farewell']);
        } finally {
            // Clean up
            foreach (glob($tmpDir . '*/*.php') as $f) {
                @unlink($f);
            }
            foreach (glob($tmpDir . '*/') as $d) {
                @rmdir($d);
            }
            @rmdir($tmpDir);
        }
    }
}
