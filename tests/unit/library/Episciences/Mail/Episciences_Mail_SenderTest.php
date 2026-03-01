<?php

namespace unit\library\Episciences\Mail;

use Episciences_Mail_Sender;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers Episciences_Mail_Sender
 */
final class Episciences_Mail_SenderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/episciences_sender_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }
        rmdir($path);
    }

    /** Calls a private method on a Sender instance via reflection. */
    private function callPrivate(Episciences_Mail_Sender $sender, string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(Episciences_Mail_Sender::class, $method);
        $m->setAccessible(true);
        return $m->invoke($sender, ...$args);
    }

    /** Builds a SimpleXML mail object from a raw XML string. */
    private function buildMailXml(string $xml): \SimpleXMLElement
    {
        $result = simplexml_load_string($xml);
        self::assertNotFalse($result, 'Test fixture XML is invalid');
        return $result;
    }

    // -------------------------------------------------------------------------
    // scan()
    // -------------------------------------------------------------------------

    public function testScanReturnsEmptyArrayForNonExistentPath(): void
    {
        $sender = new Episciences_Mail_Sender();
        $result = $this->callPrivate($sender, 'scan', '/path/that/does/not/exist/12345');

        self::assertSame([], $result);
    }

    public function testScanReturnsEmptyArrayForEmptyDirectory(): void
    {
        $sender = new Episciences_Mail_Sender();
        $result = $this->callPrivate($sender, 'scan', $this->tmpDir);

        self::assertSame([], $result);
    }

    public function testScanReturnsOnlyDirectoryNames(): void
    {
        // Create 2 subdirectories and 1 file inside tmpDir
        mkdir($this->tmpDir . '/dir_a');
        mkdir($this->tmpDir . '/dir_b');
        file_put_contents($this->tmpDir . '/somefile.xml', '<root/>');

        $sender = new Episciences_Mail_Sender();
        $result = $this->callPrivate($sender, 'scan', $this->tmpDir);

        sort($result);
        self::assertSame(['dir_a', 'dir_b'], $result);
    }

    public function testScanDoesNotReturnDotEntries(): void
    {
        mkdir($this->tmpDir . '/real_dir');

        $sender = new Episciences_Mail_Sender();
        $result = $this->callPrivate($sender, 'scan', $this->tmpDir);

        self::assertNotContains('.', $result);
        self::assertNotContains('..', $result);
    }

    // -------------------------------------------------------------------------
    // setPath() / getPath()
    // -------------------------------------------------------------------------

    public function testSetPathReturnsSamePathWhenDirectoryExists(): void
    {
        $sender = new Episciences_Mail_Sender();

        $result = $sender->setPath($this->tmpDir);

        self::assertSame($this->tmpDir, $result);
        self::assertSame($this->tmpDir, $sender->getPath());
    }

    public function testSetPathCreatesDirectoryAndReturnsItWhenMissing(): void
    {
        $newDir = $this->tmpDir . '/created_on_demand';
        self::assertDirectoryDoesNotExist($newDir);

        $sender = new Episciences_Mail_Sender();
        $result = $sender->setPath($newDir);

        self::assertSame($newDir, $result);
        self::assertDirectoryExists($newDir);
    }

    // -------------------------------------------------------------------------
    // getAddressList() — private, accessed via reflection
    // -------------------------------------------------------------------------

    public function testGetAddressListReturnsFalseWhenListIsMissing(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml('<mail errors="0" charset="UTF-8"></mail>');

        $result = $this->callPrivate($sender, 'getAddressList', 'to');

        self::assertFalse($result);
    }

    public function testGetAddressListReturnsFalseWhenListIsEmpty(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(
            '<mail errors="0" charset="UTF-8"><to_list></to_list></mail>'
        );

        $result = $this->callPrivate($sender, 'getAddressList', 'to');

        self::assertFalse($result);
    }

    public function testGetAddressListReturnsSingleRecipient(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <to_list>
                    <to>
                        <mail>alice@example.com</mail>
                        <name>Alice Dupont</name>
                    </to>
                </to_list>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAddressList', 'to');

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame('alice@example.com', $result[0][Episciences_Mail_Sender::MAIL]);
        self::assertSame('Alice Dupont', $result[0][Episciences_Mail_Sender::NAME]);
    }

    public function testGetAddressListReturnsMultipleRecipients(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <cc_list>
                    <cc><mail>bob@example.com</mail><name>Bob</name></cc>
                    <cc><mail>carol@example.com</mail><name>Carol</name></cc>
                </cc_list>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAddressList', 'cc');

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertSame('bob@example.com', $result[0][Episciences_Mail_Sender::MAIL]);
        self::assertSame('carol@example.com', $result[1][Episciences_Mail_Sender::MAIL]);
    }

    public function testGetAddressListUsesMailAsNameWhenNameIsEmpty(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <to_list>
                    <to><mail>noname@example.com</mail></to>
                </to_list>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAddressList', 'to');

        self::assertIsArray($result);
        self::assertSame('noname@example.com', $result[0][Episciences_Mail_Sender::NAME]);
    }

    public function testGetAddressListSkipsEntriesWithNoMailElement(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <to_list>
                    <to><name>No Mail</name></to>
                    <to><mail>valid@example.com</mail><name>Valid</name></to>
                </to_list>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAddressList', 'to');

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame('valid@example.com', $result[0][Episciences_Mail_Sender::MAIL]);
    }

    // -------------------------------------------------------------------------
    // getAddress() — private
    // -------------------------------------------------------------------------

    public function testGetAddressReturnsFalseWhenFieldIsMissing(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml('<mail errors="0" charset="UTF-8"></mail>');

        $result = $this->callPrivate($sender, 'getAddress', 'from');

        self::assertFalse($result);
    }

    public function testGetAddressReturnsFalseWhenMailElementIsMissing(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(
            '<mail errors="0" charset="UTF-8"><from><name>No Mail</name></from></mail>'
        );

        $result = $this->callPrivate($sender, 'getAddress', 'from');

        self::assertFalse($result);
    }

    public function testGetAddressReturnsMailAndName(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <from>
                    <mail>noreply@journal.org</mail>
                    <name>Journal Name</name>
                </from>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAddress', 'from');

        self::assertIsArray($result);
        self::assertSame('noreply@journal.org', $result[Episciences_Mail_Sender::MAIL]);
        self::assertSame('Journal Name', $result[Episciences_Mail_Sender::NAME]);
    }

    public function testGetAddressUsesMailAsNameWhenNameIsEmpty(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <reply-to><mail>reply@example.com</mail></reply-to>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAddress', 'reply-to');

        self::assertIsArray($result);
        self::assertSame('reply@example.com', $result[Episciences_Mail_Sender::NAME]);
    }

    // -------------------------------------------------------------------------
    // getAttachments() — private
    // -------------------------------------------------------------------------

    public function testGetAttachmentsReturnsFalseWhenNoFiles(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(
            '<mail errors="0" charset="UTF-8"><files_list></files_list></mail>'
        );

        $result = $this->callPrivate($sender, 'getAttachments');

        self::assertFalse($result);
    }

    public function testGetAttachmentsReturnsFileNamesAsStrings(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <files_list>
                    <file>attachment1.pdf</file>
                    <file>attachment2.png</file>
                </files_list>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAttachments');

        self::assertIsArray($result);
        self::assertSame(['attachment1.pdf', 'attachment2.png'], $result);
    }

    // -------------------------------------------------------------------------
    // SECURITY: path traversal via attachment filename — fixed with basename()
    // -------------------------------------------------------------------------

    /**
     * send() uses basename($attachment) before passing to addAttachment(),
     * so path traversal sequences are stripped.
     *
     * getAttachments() still returns the raw string from the XML (it is a
     * pure data accessor), but the caller strips directory components.
     * This test verifies the raw value and the expected safe filename.
     */
    public function testGetAttachmentsReturnsRawFilenameFromXml(): void
    {
        $sender = new Episciences_Mail_Sender();
        $sender->mail = $this->buildMailXml(<<<XML
            <mail errors="0" charset="UTF-8">
                <files_list>
                    <file>../../../etc/passwd</file>
                </files_list>
            </mail>
            XML);

        $result = $this->callPrivate($sender, 'getAttachments');

        // getAttachments() returns the raw XML value — sanitisation happens in send().
        self::assertSame(['../../../etc/passwd'], $result);
        // Verify that basename() (used by send()) strips the traversal sequences.
        self::assertSame('passwd', basename($result[0]));
    }

    // -------------------------------------------------------------------------
    // BUG: updateErrorsCount() — inverted ternary (charset logic)
    // -------------------------------------------------------------------------

    /**
     * BUG: Sender.php line ~399
     *
     * Current code (wrong):
     *   $headersCharset = ($this->mail['charset']) ? 'UTF-8' : $this->mail['charset'];
     *
     * When the XML has charset="ISO-8859-1" (truthy), the code writes the
     * hardcoded string 'UTF-8' to the file instead of the actual charset.
     *
     * Expected (correct) behavior: the actual charset from the XML must be preserved.
     */
    public function testUpdateErrorsCountPreservesActualCharset(): void
    {
        $sender = new Episciences_Mail_Sender();

        // Build a two-line XML file: the method rewrites line index 1 (0-based).
        $xmlContent = '<?xml version="1.0" encoding="ISO-8859-1"?>' . PHP_EOL
            . '<mail errors="0" charset="ISO-8859-1">' . PHP_EOL
            . '</mail>' . PHP_EOL;

        $tmpFile = $this->tmpDir . '/mail.xml';
        file_put_contents($tmpFile, $xmlContent);

        $sender->mail = simplexml_load_file($tmpFile);

        $this->callPrivate($sender, 'updateErrorsCount', $tmpFile);

        $written = file_get_contents($tmpFile);

        // Correct behavior: charset must remain 'ISO-8859-1'.
        self::assertStringContainsString(
            'charset="ISO-8859-1"',
            $written,
            'updateErrorsCount() must preserve the actual charset from the XML, not hardcode UTF-8'
        );

        // Also verify the errors counter was incremented.
        self::assertStringContainsString('errors="1"', $written);
    }

    /**
     * When charset is absent/empty in the XML, updateErrorsCount() must default
     * to 'UTF-8' (falsy branch of the fixed ternary).
     */
    public function testUpdateErrorsCountDefaultsToUtf8WhenCharsetIsEmpty(): void
    {
        $sender = new Episciences_Mail_Sender();

        $xmlContent = '<?xml version="1.0"?>' . PHP_EOL
            . '<mail errors="0" charset="">' . PHP_EOL
            . '</mail>' . PHP_EOL;

        $tmpFile = $this->tmpDir . '/mail_empty_charset.xml';
        file_put_contents($tmpFile, $xmlContent);

        $sender->mail = simplexml_load_file($tmpFile);

        $this->callPrivate($sender, 'updateErrorsCount', $tmpFile);

        $written = file_get_contents($tmpFile);

        // When charset attribute is empty (falsy), the method falls back to 'UTF-8'.
        self::assertStringContainsString('charset="UTF-8"', $written);
    }
}
