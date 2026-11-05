<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Episciences_Auth;
use Zend_Form_Decorator_Callback;
use Zend_Form_Decorator_HtmlTag;
use Zend_Form_Element;
use Zend_Registry;
use Zend_Translate;

/**
 * Recipient fields (To, Cc, Bcc) of the mail forms: list of the chosen recipients, search input,
 * help text and button opening the contacts picker (see public/js/library/es.mail-combobox.js and
 * public/js/library/es.contacts-list.js).
 *
 * The label stays a plain <label for> of the input: the picker button is outside of it.
 */
final class RecipientFieldDecorator
{
    public const TARGETS = ['to' => 'À', 'cc' => 'Cc', 'bcc' => 'Bcc'];
    public const MAILING_LISTS_URL = '/administratemailinglist/index';

    /**
     * @param string $target to, cc or bcc
     * @param string $tagsId id of the list of the chosen recipients
     * @param string $hiddenId id of the hidden input holding the chosen recipients (JSON)
     */
    public static function apply(Zend_Form_Element $element, string $target, string $tagsId, string $hiddenId): Zend_Form_Element
    {
        if (!isset(self::TARGETS[$target])) {
            throw new \InvalidArgumentException('Unknown recipient field: ' . $target);
        }

        $inputId = (string)$element->getId();
        $helpId = $inputId . '-help';

        $element->setLabel(self::TARGETS[$target]);
        $element->setAttribs([
            'class' => 'recipient-input',
            'autocomplete' => 'off',
            'data-recipient-target' => $target,
            'placeholder' => self::translate('Nom, identifiant ou adresse e-mail'),
            'aria-describedby' => $helpId,
        ]);

        $decorators = [
            'recipientTagsOpen' => new Zend_Form_Decorator_HtmlTag([
                'tag' => 'ul',
                'id' => $tagsId,
                'class' => 'recipient-tags list-unstyled',
                'data-recipient-target' => $target,
                'data-hidden-input' => $hiddenId,
                'aria-label' => self::listLabel($target),
                'placement' => 'APPEND',
                'openOnly' => true,
            ]),
            'recipientTagsClose' => new Zend_Form_Decorator_HtmlTag([
                'tag' => 'ul',
                'placement' => 'APPEND',
                'closeOnly' => true,
            ]),
        ];

        foreach ($element->getDecorators() as $name => $decorator) {
            $decorators[$name] = $decorator;

            if (str_ends_with((string)$name, 'ViewHelper')) {
                $decorators['recipientControls'] = new Zend_Form_Decorator_Callback([
                    'callback' => static fn(): string => self::controls($target, $inputId, $helpId),
                    'placement' => 'APPEND',
                ]);
            }
        }

        $element->clearDecorators();
        $element->addDecorators($decorators);

        return $element;
    }

    /**
     * Accessible name of the list of the chosen recipients
     */
    public static function listLabel(string $target): string
    {
        return sprintf(self::translate('Destinataires (%s)'), self::translate(self::TARGETS[$target] ?? $target));
    }

    private static function controls(string $target, string $inputId, string $helpId): string
    {
        $fieldName = self::translate(self::TARGETS[$target]);
        $mailingListsUrl = Episciences_Auth::isRoot() ? self::MAILING_LISTS_URL : '';

        return '<button type="button" class="btn btn-default btn-sm show_contacts_button"'
            . ' data-target="' . self::escape($target) . '"'
            . ' data-input-id="' . self::escape($inputId) . '"'
            . ' aria-expanded="false" aria-controls="' . self::escape($inputId . '-picker') . '"'
            . ($mailingListsUrl !== '' ? ' data-mailing-lists-url="' . self::escape($mailingListsUrl) . '"' : '')
            . '>'
            . '<span class="glyphicon glyphicon-user" aria-hidden="true"></span> '
            . self::escape(self::translate('Choisir'))
            . '<span class="sr-only"> ' . self::escape(sprintf(self::translate('des destinataires (%s)'), $fieldName)) . '</span>'
            . '</button>'
            . '<span id="' . self::escape($helpId) . '" class="help-block recipient-help">'
            . self::escape(sprintf(self::translate('Au moins %d caractères pour lancer la recherche.'), RecipientSearchService::MIN_QUERY_LENGTH))
            . '</span>';
    }

    private static function translate(string $message): string
    {
        try {
            $translator = Zend_Registry::get('Zend_Translate');
        } catch (\Zend_Exception) {
            return $message;
        }

        return $translator instanceof Zend_Translate ? (string)$translator->translate($message) : $message;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
