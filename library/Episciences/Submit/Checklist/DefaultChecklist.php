<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

use Zend_Registry;
use Zend_Translate;

/**
 * Checklist shown when the journal has no custom list: the platform checkboxes,
 * defined in code and translation files, not editable by journals.
 *
 * Also provides the EN + FR texts used to pre-fill the checklist editor
 * the first time a journal opens it.
 *
 * Labels are translation keys written in French (the project source language):
 * the French text is the key itself, the English text comes from the translator.
 *
 * Nothing is stored in the database for journals that keep this checklist.
 *
 */
final class DefaultChecklist
{
    /**
     * Element name => translation key (French source string), as used today in Submit.php.
     * Names must not change: e2e tests and journals' custom CSS rely on the resulting IDs.
     */
    public const ELEMENTS = [
        'disclaimer1' => "Je certifie être l'auteur de cet article, ou être mandaté par l'un des auteurs",
        'disclaimer2' => 'Je certifie ne pas avoir publié/soumis cet article ailleurs',
    ];

    private readonly Zend_Translate $translator;

    /**
     * @param ?Zend_Translate $translator defaults to the application translator
     */
    public function __construct(?Zend_Translate $translator = null)
    {
        $this->translator = $translator ?? Zend_Registry::get('Zend_Translate');
    }

    /**
     * Checkboxes of the submission form when the journal has no custom list.
     *
     * @return list<array{name: string, label: string}> label = translation key, translated by the form
     */
    public function elements(): array
    {
        $elements = [];
        foreach (self::ELEMENTS as $name => $label) {
            $elements[] = ['name' => $name, 'label' => $label];
        }

        return $elements;
    }

    /**
     * Items used to pre-fill the checklist editor: the platform checkboxes,
     * without key (the repository assigns keys on save).
     *
     * @return list<ChecklistItem>
     */
    public function toPrefill(): array
    {
        return array_map(
            fn (string $label): ChecklistItem => new ChecklistItem(null, [
                'en' => $this->translateToEnglish($label),
                'fr' => $label,
            ]),
            array_values(self::ELEMENTS)
        );
    }

    private function translateToEnglish(string $key): string
    {
        return (string) $this->translator->getAdapter()->translate($key, 'en');
    }
}
