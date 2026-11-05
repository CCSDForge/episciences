<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Zend_Form_Decorator_Callback;
use Zend_Form_Decorator_HtmlTag;
use Zend_Form_Element;
use Zend_Registry;
use Zend_Translate;

/**
 * Recipient fields (To, Cc, Bcc) of the mail forms: list of the chosen recipients, search input and
 * help text (see public/js/library/es.mail-combobox.js, which makes the input a combobox, and
 * public/js/library/es.mail.js).
 *
 * The label stays a plain <label for> of the input.
 */
final class RecipientFieldDecorator
{
    public const TARGETS = ['to' => 'À', 'cc' => 'Cc', 'bcc' => 'Bcc'];

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
                // Limits checked when the mail is sent, also applied when recipients are added
                'data-max-recipients' => RecipientListValidator::MAX_PER_FIELD,
                'data-max-total' => RecipientListValidator::MAX_TOTAL,
                'data-warning-above' => RecipientListValidator::WARNING_ABOVE,
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
                $decorators['recipientHelp'] = new Zend_Form_Decorator_Callback([
                    'callback' => static fn(): string => self::help($helpId),
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

    /**
     * Help text of the input: how to browse and search, limits of the mail
     */
    private static function help(string $helpId): string
    {
        $html = '<span id="' . self::escape($helpId) . '" class="help-block recipient-help">'
            . self::escape(sprintf(
                self::translate('Cliquez dans le champ ou utilisez la flèche bas pour parcourir les destinataires, ou saisissez au moins %d caractères pour rechercher.'),
                RecipientSearchService::MIN_QUERY_LENGTH
            ))
            . ' ' . self::escape(sprintf(
                self::translate('%d destinataires au maximum (À, Cc et Bcc).'),
                min(RecipientListValidator::MAX_PER_FIELD, RecipientListValidator::MAX_TOTAL)
            ));

        return $html . '</span>';
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
