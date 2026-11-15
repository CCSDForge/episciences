<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Episciences\Volume\ProceedingsValidator;
use Episciences_Volume;
use Episciences_Volume_DoiQueue;
use Episciences_VolumesManager;
use Ccsd_Form;
use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Proceedings block of the volume form (volume/proceedings_fieldset.phtml and
 * volume/doi_proceedings_row.phtml): the markup public/js/volume/confproceeding.js relies on,
 * and its accessibility (labels, descriptions, errors, live region).
 */
final class ProceedingsFieldsetViewTest extends TestCase
{
    private const PREFIX = '10.46298/mbj.proceedings.';

    private mixed $previousTranslate = null;

    protected function setUp(): void
    {
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        Zend_Registry::set('Zend_Translate', new Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr']));
    }

    protected function tearDown(): void
    {
        if ($this->previousTranslate !== null) {
            Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
    }

    /**
     * @param array<string, mixed> $doi
     * @param array<string, string> $errors
     */
    private function render(bool $isProceeding, array $doi = [], array $errors = []): DOMXPath
    {
        $form = new Ccsd_Form();
        Episciences_VolumesManager::getProceedingForm($form);
        $form->getElement(Episciences_Volume::VOLUME_IS_PROCEEDING)->setValue($isProceeding ? '1' : '0');
        $form->getElement(Episciences_Volume::VOLUME_CONFERENCE_START_DATE)->setValue('2026-10-27');
        foreach ($errors as $field => $message) {
            $form->getElement($field)->addError($message);
        }

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $html = $view->partial('volume/proceedings_fieldset.phtml', [
            'form' => $form,
            'doi' => $doi + [
                'prefix' => self::PREFIX,
                'suffix' => '',
                'status' => Episciences_Volume_DoiQueue::STATUS_NOT_ASSIGNED,
                'locked' => false,
                'doi' => '',
                'vid' => null,
                'error' => null,
                'canEditDoiSettings' => false,
            ],
        ]);

        $dom = new DOMDocument();
        self::assertTrue(@$dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>'));

        return new DOMXPath($dom);
    }

    private function element(DOMXPath $xpath, string $id): ?DOMElement
    {
        $node = $xpath->query(sprintf('//*[@id="%s"]', $id))->item(0);

        return $node instanceof DOMElement ? $node : null;
    }

    /**
     * @return list<DOMElement> inputs of the proceedings fieldset
     */
    private function inputs(DOMXPath $xpath): array
    {
        $inputs = [];
        foreach ($xpath->query('//fieldset[@id="conference-fields"]//input') ?: [] as $input) {
            if ($input instanceof DOMElement) {
                $inputs[] = $input;
            }
        }

        return $inputs;
    }

    /**
     * Every id of aria-describedby points to an element of the page.
     */
    private function assertDescriptionsExist(DOMXPath $xpath, DOMElement $input): void
    {
        foreach (preg_split('/\s+/', trim($input->getAttribute('aria-describedby'))) as $id) {
            self::assertNotNull($this->element($xpath, $id), "Missing description #$id of #{$input->getAttribute('id')}");
        }
    }

    public function testProceedingsFieldsAreGroupedLabelledAndRequired(): void
    {
        $xpath = $this->render(true);

        $fieldset = $this->element($xpath, 'conference-fields');
        self::assertFalse($fieldset->hasAttribute('hidden'));
        self::assertNotSame('', trim($xpath->query('//fieldset[@id="conference-fields"]/legend')->item(0)->textContent));

        $ids = [];
        foreach ($this->inputs($xpath) as $input) {
            $id = $input->getAttribute('id');
            $ids[] = $id;
            self::assertSame(1, $xpath->query(sprintf('//label[@for="%s"]', $id))->length, "#$id has a label");
            self::assertFalse($input->hasAttribute('disabled'));
            if ($input->hasAttribute('aria-describedby')) {
                $this->assertDescriptionsExist($xpath, $input);
            }
        }

        self::assertSame([...Episciences_VolumesManager::PROCEEDINGS_FIELDS, Episciences_Volume::VOLUME_CONFERENCE_DOI], $ids, 'Display order');
        foreach (['conference_name', 'conference_start', 'conference_end'] as $id) {
            $input = $this->element($xpath, $id);
            self::assertTrue($input->hasAttribute('required'));
            self::assertTrue($input->hasAttribute('data-proceedings-required'));
            self::assertSame('true', $input->getAttribute('aria-required'));
        }
        self::assertSame('date', $this->element($xpath, 'conference_start')->getAttribute('type'));
        self::assertSame('2026-10-27', $this->element($xpath, 'conference_start')->getAttribute('value'));
        self::assertFalse($this->element($xpath, 'conference_acronym')->hasAttribute('required'));
    }

    public function testHiddenFieldsAreDisabledAndNotRequired(): void
    {
        $xpath = $this->render(false);

        self::assertTrue($this->element($xpath, 'conference-fields')->hasAttribute('hidden'));
        foreach ($this->inputs($xpath) as $input) {
            self::assertTrue($input->hasAttribute('disabled'), $input->getAttribute('id'));
            self::assertFalse($input->hasAttribute('required'), $input->getAttribute('id'));
        }
    }

    public function testDoiSuffixMirrorsTheServerRuleAndDescribesTheResultingDoi(): void
    {
        $xpath = $this->render(true, ['suffix' => '51', 'status' => Episciences_Volume_DoiQueue::STATUS_ASSIGNED, 'doi' => self::PREFIX . '51', 'vid' => 1134]);

        $group = $this->element($xpath, 'conference_doi_group');
        self::assertSame(self::PREFIX, $group->getAttribute('data-prefix'));
        self::assertSame('assigned', $group->getAttribute('data-status'));
        self::assertStringContainsString('%s', $group->getAttribute('data-text-preview'));

        $input = $this->element($xpath, 'conference_proceedings_doi');
        self::assertSame(ProceedingsValidator::DOI_SUFFIX_PATTERN, $input->getAttribute('pattern'));
        self::assertSame((string)ProceedingsValidator::DOI_SUFFIX_MAX_LENGTH, $input->getAttribute('maxlength'));
        self::assertSame('off', $input->getAttribute('autocomplete'));
        self::assertFalse($input->hasAttribute('readonly'));
        $this->assertDescriptionsExist($xpath, $input);
        self::assertStringContainsString('conference_proceedings_doi-prefix', $input->getAttribute('aria-describedby'));

        $preview = $this->element($xpath, 'conference_proceedings_doi-preview');
        self::assertSame('polite', $preview->getAttribute('aria-live'));
        self::assertStringContainsString(self::PREFIX . '51', $preview->textContent);
        self::assertStringContainsString('Dépôt programmé', $xpath->query('//span[contains(@class, "label")]')->item(0)->textContent);
        self::assertSame(1, $xpath->query('//a[@href="/volume/export/id/1134"]')->length, 'Crossref XML preview');
    }

    public function testRegisteredDoiIsReadOnlyAndLinked(): void
    {
        $doi = self::PREFIX . '51';
        $xpath = $this->render(true, ['suffix' => '51', 'status' => Episciences_Volume_DoiQueue::STATUS_PUBLIC, 'locked' => true, 'doi' => $doi, 'vid' => 1134]);

        $input = $this->element($xpath, 'conference_proceedings_doi');
        self::assertTrue($input->hasAttribute('readonly'));
        self::assertFalse($input->hasAttribute('disabled'), 'Still posted');
        self::assertSame(1, $xpath->query(sprintf('//a[@href="https://doi.org/%s"]', $doi))->length);
        self::assertStringContainsString('DOI enregistré', $this->element($xpath, 'conference_doi_group')->textContent);
    }

    public function testServerErrorsAreLinkedToTheirField(): void
    {
        $xpath = $this->render(
            true,
            ['suffix' => 'é"><b>x', 'error' => ProceedingsValidator::MSG_DOI_SUFFIX_FORMAT],
            [Episciences_Volume::VOLUME_CONFERENCE_NAME => ProceedingsValidator::MSG_REQUIRED]
        );

        foreach (['conference_name', 'conference_proceedings_doi'] as $id) {
            $input = $this->element($xpath, $id);
            self::assertSame('true', $input->getAttribute('aria-invalid'), $id);
            self::assertStringContainsString($id . '-error', $input->getAttribute('aria-describedby'));
            self::assertNotNull($this->element($xpath, $id . '-error'));
            self::assertSame(1, $xpath->query(sprintf('//*[@id="%s"]/ancestor::div[contains(@class, "has-error")]', $id))->length);
        }
        self::assertSame('é"><b>x', $this->element($xpath, 'conference_proceedings_doi')->getAttribute('value'), 'Escaped value');
        self::assertSame(0, $xpath->query('//b')->length);
    }

    public function testJournalWithoutPrefixExplainsHowToGetOne(): void
    {
        $xpath = $this->render(true, ['prefix' => '', 'canEditDoiSettings' => true]);

        self::assertNull($this->element($xpath, 'conference_proceedings_doi'));
        self::assertSame(1, $xpath->query('//a[@href="/doi/settings"]')->length);

        $xpath = $this->render(true, ['prefix' => '']);
        self::assertSame(0, $xpath->query('//a[@href="/doi/settings"]')->length);
    }
}
