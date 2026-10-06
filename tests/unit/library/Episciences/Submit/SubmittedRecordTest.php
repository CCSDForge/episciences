<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences\Submit\SubmittedRecord;
use PHPUnit\Framework\TestCase;
use Zend_Session_Namespace;

/**
 * The record stored with a submitted paper is the one of the searched document, remembered
 * in the session: the form can no longer replace it.
 */
final class SubmittedRecordTest extends TestCase
{
    protected function setUp(): void
    {
        (new Zend_Session_Namespace('submitted_records'))->unsetAll();
    }

    public function testRememberedRecordIsReturnedWithoutFetchingTheRepository(): void
    {
        SubmittedRecord::remember('1', 'arxiv:1234.5678', 2.0, '<record>searched</record>');

        self::assertSame(
            '<record>searched</record>',
            SubmittedRecord::resolve('1', 'arxiv:1234.5678', 2.0)
        );
    }

    public function testRecordIsKeyedByRepositoryIdentifierAndVersion(): void
    {
        SubmittedRecord::remember('1', 'doc-a', 1.0, '<record>a</record>');
        SubmittedRecord::remember('1', 'doc-b', 1.0, '<record>b</record>');
        SubmittedRecord::remember('1', 'doc-a', 2.0, '<record>a2</record>');

        self::assertSame('<record>a</record>', SubmittedRecord::resolve('1', 'doc-a', 1.0));
        self::assertSame('<record>b</record>', SubmittedRecord::resolve('1', 'doc-b', 1.0));
        self::assertSame('<record>a2</record>', SubmittedRecord::resolve('1', 'doc-a', 2.0));
    }

    public function testOnlyTheLatestSearchesAreRemembered(): void
    {
        for ($i = 0; $i < 12; $i++) {
            SubmittedRecord::remember('1', 'doc-' . $i, 1.0, '<record>' . $i . '</record>');
        }

        $records = (new Zend_Session_Namespace('submitted_records'))->records;

        self::assertCount(10, $records);
        self::assertContains('<record>11</record>', $records);
        self::assertNotContains('<record>0</record>', $records);
    }

    public function testSubmissionControllersDoNotReadTheRecordFromThePost(): void
    {
        $submit = (string) file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/SubmitController.php');
        $paper = (string) file_get_contents(APPLICATION_PATH . '/modules/journal/controllers/PaperController.php');

        self::assertStringContainsString("\$formValues['xml'] = \$record;", $submit);
        self::assertStringNotContainsString("setRecord(\$post['xml'])", $paper);
        self::assertStringContainsString('setRecord($record)', $paper);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return ['full paper' => ['full_paper.xsl'], 'admin paper' => ['admin_paper.xsl']];
    }

    /**
     * @dataProvider stylesheetProvider
     */
    public function testRightsLinkOnlyAcceptsHttpUrls(string $stylesheet): void
    {
        $source = (string) file_get_contents(APPLICATION_PATH . '/../public/xsl/' . $stylesheet);

        self::assertStringContainsString(
            "(starts-with(\$doc_rights, 'http://') or starts-with(\$doc_rights, 'https://'))",
            $source
        );
    }
}
