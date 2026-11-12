<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Form_Validate_MimeType;
use Episciences_Tools;
use Zend_Form;
use Zend_Form_Exception;

/**
 * Form of an answer given without an account (#319): a message and an optional file, with the same file checks as the
 * other uploads (count, extension, size, MIME type). The request token is checked by the controller.
 */
final class LightAnswerForm
{
    public const MESSAGE = 'opinion';
    public const FILE = 'opinion_file';
    public const SUBMIT = 'submitopinion';

    /**
     * @throws Zend_Form_Exception
     */
    public static function create(?LightAnswer $answer = null): Zend_Form
    {
        $form = new Zend_Form();
        $form->setAttrib('enctype', Zend_Form::ENCTYPE_MULTIPART);
        $form->setAttrib('id', 'light-answer-form');

        $form->addElement('textarea', self::MESSAGE, [
            'label' => 'Votre avis',
            'rows' => 10,
            'class' => 'form-control',
            'maxlength' => LightAnswer::MESSAGE_MAX_LENGTH,
            'filters' => ['StringTrim'],
            'validators' => [['StringLength', false, ['max' => LightAnswer::MESSAGE_MAX_LENGTH]]],
            'value' => $answer?->getMessage(),
        ]);

        $extensions = ALLOWED_EXTENSIONS;
        $form->addElement('file', self::FILE, [
            'label' => 'Fichier (facultatif)',
            'description' => Episciences_Tools::buildAttachedFilesDescription($extensions),
            'valueDisabled' => true,
            'maxFileSize' => MAX_FILE_SIZE,
            'validators' => [
                'Count' => [false, 1],
                'Extension' => [false, implode(',', $extensions)],
                'Size' => [false, MAX_FILE_SIZE],
                new Episciences_Form_Validate_MimeType(),
            ],
        ]);
        $form->getElement(self::FILE)?->getDecorator('Description')?->setOption('escape', false);

        return $form;
    }
}
