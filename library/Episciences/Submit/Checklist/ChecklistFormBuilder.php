<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

use Ccsd_Form;
use Zend_Form_Element;

/**
 * Adds the checklist checkboxes to the submission and new version forms (Submit.php).
 *
 * - Journal without custom checklist: disclaimer1 / disclaimer2, exactly as before
 *   (same names, IDs disclaimers-disclaimer1/2, translated labels).
 * - Journal with a custom checklist: one checkbox per item, named disclaimer_<key>
 *   (ID disclaimers-disclaimer_c_xxxxxx), label in the UI language, always required.
 *
 * Every checkbox is required and gets the class js-submission-checklist, read by the submission
 * page JS to enable the submit button once every checkbox is ticked.
 */
final class ChecklistFormBuilder
{
    public const BELONGS_TO = 'disclaimers';
    public const ELEMENT_PREFIX = 'disclaimer_';
    public const CSS_CLASS = 'js-submission-checklist';

    private const DECORATORS = [
        'ViewHelper',
        ['Label', ['placement' => 'APPEND']],
        ['HtmlTag', ['tag' => 'div', 'class' => 'col-md-9 col-md-offset-3']],
        ['Errors', ['placement' => 'APPEND']],
    ];

    public function __construct(
        private readonly ChecklistProvider $provider,
        private readonly ?DefaultChecklist $defaultChecklist = null,
    ) {
    }

    /**
     * Builder reading the application database.
     */
    public static function create(): self
    {
        return new self(ChecklistProvider::create());
    }

    /**
     * Adds the checkboxes to the form and their names to the display group.
     *
     * @param list<string> $group element names of the form display group, completed here
     */
    public function addElements(Ccsd_Form $form, array &$group, int $rvid, string $locale): void
    {
        $items = $this->usableItems($this->provider->items($rvid));

        if ($items === []) {
            $this->addDefaultElements($form, $group);
            return;
        }

        foreach ($items as $item) {
            /** @var string $key checked by usableItems() */
            $key = $item->key;
            // Ccsd_Form_Decorator_Label renders the label without escaping it (escape = false, not
            // configurable): journal texts are escaped here. Default labels come from the translation files.
            $label = htmlspecialchars($item->getLabel($locale), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $element = $this->createCheckbox($form, self::ELEMENT_PREFIX . $key, $label);
            // Journal texts are not translation keys: never pass them to the translator
            $element->setDisableTranslator(true);
            $this->add($form, $group, $element);
        }
    }

    /**
     * @param list<string> $group
     */
    private function addDefaultElements(Ccsd_Form $form, array &$group): void
    {
        $defaultChecklist = $this->defaultChecklist ?? new DefaultChecklist();

        foreach ($defaultChecklist->elements() as $element) {
            $this->add($form, $group, $this->createCheckbox($form, $element['name'], $element['label']));
        }
    }

    /**
     * Items whose key can be used as an element name. Keys are checked on save, but the stored JSON
     * is read tolerantly: an item without a valid key is skipped rather than rendered under a wrong name.
     *
     * @param list<ChecklistItem> $items
     * @return list<ChecklistItem>
     */
    private function usableItems(array $items): array
    {
        return array_values(array_filter(
            $items,
            static fn (ChecklistItem $item): bool => $item->key !== null
                && preg_match(ChecklistValidator::KEY_PATTERN, $item->key) === 1
        ));
    }

    private function createCheckbox(Ccsd_Form $form, string $name, string $label): Zend_Form_Element
    {
        return $form->createElement('checkbox', $name, [
            'required' => true,
            'uncheckedValue' => null,
            'label' => $label,
            'belongsTo' => self::BELONGS_TO,
            'class' => self::CSS_CLASS,
            'decorators' => self::DECORATORS,
        ]);
    }

    /**
     * @param list<string> $group
     */
    private function add(Ccsd_Form $form, array &$group, Zend_Form_Element $element): void
    {
        $form->addElement($element);
        $group[] = $element->getName();
    }
}
