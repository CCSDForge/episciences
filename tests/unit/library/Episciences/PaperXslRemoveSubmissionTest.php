<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Episciences_Csrf_Helper;
use PHPUnit\Framework\TestCase;
use XSLTProcessor;
use Zend_Registry;
use Zend_Translate;

/**
 * On the full paper page, the author of a submission that has not been handled yet
 * can delete it: the button opens a confirmation dialog, whose form posts the session
 * request token.
 */
final class PaperXslRemoveSubmissionTest extends TestCase
{
    private const OWN_SUBMISSION = '<status></status><uid>0</uid><hasOtherVersions></hasOtherVersions>';

    /** French source strings of the dialog, which need an English translation */
    private const DIALOG_STRINGS = [
        'Supprimer la soumission',
        'Voulez-vous vraiment supprimer cette soumission ?',
        'Cette action est irréversible.',
        'La revue ne traitera plus cet article, et les relecteurs et rédacteurs qui y sont assignés en seront retirés.',
        'Annuler',
        'Fermer',
        'Supprimer définitivement',
    ];

    /** @var array<string, mixed> registry entries present before the test */
    private array $previousRegistry = [];

    protected function setUp(): void
    {
        $this->previousRegistry = [];

        foreach (['Zend_Translate', 'lang'] as $key) {
            if (Zend_Registry::isRegistered($key)) {
                $this->previousRegistry[$key] = Zend_Registry::get($key);
            }
        }

        Zend_Registry::set('lang', 'en');
        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => require APPLICATION_PATH . '/languages/en/views.php',
            'locale' => 'en',
            'disableNotices' => true,
        ]));
    }

    protected function tearDown(): void
    {
        foreach (['Zend_Translate', 'lang'] as $key) {
            if (array_key_exists($key, $this->previousRegistry)) {
                Zend_Registry::set($key, $this->previousRegistry[$key]);
            } elseif (Zend_Registry::isRegistered($key)) {
                Zend_Registry::getInstance()->offsetUnset($key);
            }
        }
    }

    public function testButtonOpensTheConfirmationDialog(): void
    {
        // Nobody is logged in during the tests: the current uid is 0, so the record belongs to the current user
        $xpath = $this->xpath($this->render(self::OWN_SUBMISSION));

        $button = $xpath->query('//button[@id="remove-submission-button"]')->item(0);
        self::assertInstanceOf(DOMElement::class, $button);
        self::assertSame('button', $button->getAttribute('type'), 'the button itself deletes nothing');
        self::assertSame('modal', $button->getAttribute('data-toggle'));
        self::assertSame('#remove-submission-modal', $button->getAttribute('data-target'));
        self::assertStringContainsString('Delete the submission', $button->textContent);

        self::assertSame(1, $xpath->query('//div[@id="remove-submission-modal"][contains(@class, "modal")]')->length);
    }

    public function testDialogNamesThePaperAndTheConsequences(): void
    {
        $xpath = $this->xpath($this->render(self::OWN_SUBMISSION));
        $dialog = $xpath->query('//div[@id="remove-submission-modal"]')->item(0);

        self::assertInstanceOf(DOMElement::class, $dialog);
        self::assertSame('remove-submission-modal-title', $dialog->getAttribute('aria-labelledby'));
        self::assertStringContainsString('Delete the submission', $this->text($xpath, '//*[@id="remove-submission-modal-title"]'));

        $description = $this->text($xpath, '//*[@id="remove-submission-modal-description"]');
        self::assertStringContainsString('Do you really want to delete this submission?', $description);
        self::assertStringContainsString('A paper title', $description);
        self::assertStringContainsString('This action cannot be undone.', $description);
        self::assertStringContainsString('will be removed from it', $description);

        $cancel = $xpath->query('//*[@id="remove-submission-modal"]//button[@data-dismiss="modal"][not(@aria-label)]')->item(0);
        self::assertInstanceOf(DOMElement::class, $cancel);
        self::assertSame('Cancel', trim($cancel->textContent));
    }

    public function testDialogFormPostsTheRequestToken(): void
    {
        $xpath = $this->xpath($this->render(self::OWN_SUBMISSION));
        $form = $xpath->query('//*[@id="remove-submission-modal"]//form[@id="remove-submission"]')->item(0);

        self::assertInstanceOf(DOMElement::class, $form);
        self::assertSame('/paper/remove/id/42', $form->getAttribute('action'));
        self::assertSame('post', $form->getAttribute('method'));

        $token = $xpath->query('.//input[@type="hidden"][@name="csrf_token"]', $form)->item(0);
        self::assertInstanceOf(DOMElement::class, $token);
        self::assertSame(Episciences_Csrf_Helper::getSessionToken(), $token->getAttribute('value'));

        $submit = $xpath->query('.//button[@type="submit"]', $form)->item(0);
        self::assertInstanceOf(DOMElement::class, $submit);
        self::assertSame('Delete permanently', trim($submit->textContent));
    }

    public function testDialogStringsAreTranslatedInEnglish(): void
    {
        $translations = require APPLICATION_PATH . '/languages/en/views.php';

        foreach (self::DIALOG_STRINGS as $string) {
            self::assertArrayHasKey($string, $translations);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherRecordsProvider(): array
    {
        return [
            'paper with other versions' => ['<status></status><uid>0</uid><hasOtherVersions>1</hasOtherVersions>'],
            'paper of another user' => ['<status></status><uid>7</uid><hasOtherVersions></hasOtherVersions>'],
            'paper already handled' => ['<status>4</status><uid>0</uid><hasOtherVersions></hasOtherVersions>'],
        ];
    }

    /**
     * @dataProvider otherRecordsProvider
     */
    public function testNeitherButtonNorDialogForOtherRecords(string $episciences): void
    {
        $xpath = $this->xpath($this->render($episciences));

        self::assertSame(0, $xpath->query('//*[@id="remove-submission-button"]')->length);
        self::assertSame(0, $xpath->query('//*[@id="remove-submission-modal"]')->length);
    }

    private function render(string $episciences): string
    {
        $record = new DOMDocument();
        $record->loadXML(
            '<record><episciences><id>42</id>' . $episciences . '</episciences><metadata>'
            . '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title xml:lang="en">A paper title</dc:title>'
            . '</oai_dc:dc></metadata></record>'
        );

        $xsl = new DOMDocument();
        $xsl->load(APPLICATION_PATH . '/../public/xsl/full_paper.xsl');

        $processor = new XSLTProcessor();
        $processor->registerPHPFunctions();
        $processor->importStylesheet($xsl);

        return (string)$processor->transformToXml($record);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return new DOMXPath($document);
    }

    private function text(DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)->item(0);
        self::assertNotNull($node, $query . ' is rendered');

        return preg_replace('/\s+/', ' ', (string)$node->textContent);
    }
}
