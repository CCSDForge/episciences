<?php

class Episciences_Mail_Send
{
    public const ENCODING_TYPE = 'UTF-8';
    public const TEMPLATE_EXTENSION = '.phtml';
    public const DEFAULT_LANG = 'en';
    public const FROM_MAILING = 'mailing';
    public const ATTACHMENTS = 'attachments';

    /**
     * mailing form
     * if $to_enabled is false, recipient is automatically filled, and user can't change it
     * @param null $prefix
     * @param bool $button_enabled
     * @param bool $to_enabled
     * @param int|null $docId
     * @return Ccsd_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function getForm($prefix = null, bool $button_enabled = true, bool $to_enabled = true, ?int $docId = 0): Ccsd_Form
    {
        $translator = Zend_Registry::get('Zend_Translate');

        $form = new Ccsd_Form;
        $form->setAction('/administratemail/send');
        $form->setAttrib('id', 'send_form');
        $form->setAttrib('class', 'form-horizontal');

        $form->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);

        // from
        // default from: recipient name <rvcode@episciences.org>
        $form->addElement('text', self::getElementName('from', $prefix), array(
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getScreenName() . ' <' . RVCODE . '@' . DOMAIN . '>'));

        // reply-to
        // default reply-to: recipient name <recipient@domain.com>
        $form->addElement('text', self::getElementName('reply-to', $prefix), array(
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getScreenName() . ' <' . Episciences_Auth::getEmail() . '>'));

        $title = $translator->translate('Ajouter des destinataires');

        // to
        $to_element = self::getElementName('to', $prefix);

        if (!$to_enabled) {

            $options = [
                'label' => 'À',
                'disabled' => true
            ];


            $form->addElement('text', $to_element, $options);


        } else {

            $options = [
                'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=to">' . $translator->translate('À') . '</a>',
                'class' => 'autocomplete'

            ];

            $form->addElement('text', $to_element, $options);

            self::decorate($form->getElement($to_element), 'to');

        }

        $form->addElement('hidden', self::getElementName('hidden_to', $prefix));


        $coauthors = Episciences_PapersManager::getCoAuthors($docId);
        if (!empty($coauthors)) {
            foreach ($coauthors as $coAuthor) {
                /** @var Episciences_User $coAuthor */
                $form->addElement('hidden', 'coauthorsInfo-' . $coAuthor->getUid(), [
                    'id' => 'coAuthorsInfo',
                    'value' => Zend_Json::encode([
                        'uid' => $coAuthor->getUid(),
                        'username' => $coAuthor->getUsername(),
                        'lastname' => $coAuthor->getLastname(),
                        'fullname' => $coAuthor->getFullName(),
                        'screen_name' => $coAuthor->getScreenName(),
                        'mail' => $coAuthor->getEmail(),
                        'label' => $coAuthor->getFullName() . ' <' . $coAuthor->getEmail() . '>',
                        'htmlLabel' => "<div>" . $coAuthor->getFullName() . "</div><div class=\"grey\">" . $coAuthor->getEmail() . "</div>"
                    ])
                ]);
            }
        }
        // cc
        $cc_element = self::getElementName('cc', $prefix);
        $form->addElement('text', $cc_element, [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=cc">' . $translator->translate('Cc') . '</a>',
            'class' => 'autocomplete'
        ]);


        self::decorate($form->getElement($cc_element), 'cc');

        $form->addElement('hidden', self::getElementName('hidden_cc', $prefix));

        // bcc
        $bcc_element = self::getElementName('bcc', $prefix);
        $form->addElement('text', $bcc_element, [
            'label' => '<a class="show_contacts_button" title="' . $title . '" href="/administratemail/getcontacts?target=bcc">' . $translator->translate('Bcc') . '</a>',
            'class' => 'autocomplete'
        ]);

        self::decorate($form->getElement($bcc_element), 'bcc');

        $form->addElement('hidden', self::getElementName('hidden_bcc', $prefix));


        // subject
        $form->addElement('text', self::getElementName('subject', $prefix), ['label' => 'Sujet', 'value' => !empty($docId) ? RVCODE . ' #' . $docId : '']);

        // content
        $form->addElement('textarea', self::getElementName('content', $prefix), ['label' => 'Contenu', 'class' => 'tinymce']);

        // get a copy
        $form->addElement('checkbox', self::getElementName('copy', $prefix), array(
            'uncheckedValue' => null,
            'label' => "Recevoir une copie de ce message",
            'decorators' => [
                'ViewHelper',
                ['Label', array('placement' => 'APPEND')],
                ['HtmlTag', array('tag' => 'div', 'class' => 'col-md-9 col-md-offset-3')]
            ]
        ));

        // Git #61
        if ($docId !== 0) {
            $form->addElement('hidden', self::getElementName('docid', $prefix), ['value' => $docId]);
        }

        if (!$button_enabled && !$to_enabled) {
            $form->addElement('hidden', self::getElementName('in_modal', $prefix), ['value' => true]);
        }

        // submit button
        if ($button_enabled) {

            $form->addElement('button', self::getElementName('submit', $prefix), array(
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'label' => 'Envoyer'
            ));
        }

        return $form;
    }

    /**
     * @param $name
     * @param null $prefix
     * @return string
     */

    private static function getElementName($name, $prefix = null): string
    {
        if ($prefix) {
            $name = $prefix . '-' . $name;
        }

        return $name;
    }


    /**
     * @param Episciences_User $recipient
     * @param string $templateType
     * @param array $tags
     * @param Episciences_Paper|null $paper
     * @param int|null $authUid
     * @param array $attachmentsFiles ['key' => file name, 'value' => 'file path']
     * @param bool $makeACopy : si true faire une copie
     * @param array $CC : cc recipients
     * @param array|null $journalOptions
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    public static function sendMailFromReview(
        Episciences_User  $recipient,
        string            $templateType,
        array             $tags = [],
        Episciences_Paper $paper = null,
        int               $authUid = null,
        array             $attachmentsFiles = [],
        bool              $makeACopy = false,
        array             $CC = [],
        ?array            $journalOptions = null
    ): bool
    {

        $template = new Episciences_Mail_Template();

        if (empty($journalOptions) && !Ccsd_Tools::isFromCli()) {
            $journalOptions = ['rvCode' => RVCODE, 'rvId' => RVID];
        }

        if (isset($journalOptions['rvCode'])) {
            $template->setRvcode($journalOptions['rvCode']);
        }

        if (!$template->findByKey($templateType)) {
            trigger_error(sprintf('Template key [%s] was not found in [MAIL_TEMPLATE]: Empty mail sent to: [%s<%s>]', $templateType, $recipient->getScreenName(), $recipient->getEmail()));
        }

        $template->loadTranslations(null, $journalOptions['rvCode']);

        $locale = $recipient->getLangueid();
        $template->setLocale($locale);

        $mail = new Episciences_Mail(self::ENCODING_TYPE, $journalOptions['rvCode']);

        if ($paper) {
            $mail->setDocid($paper->getDocid());
        }

        foreach ($tags as $tag => $value) {
            if (!array_key_exists($tag, $mail->getTags())) {
                $mail->addTag($tag, $value);
            }
        }

        if (isset($journalOptions['sender']) && $journalOptions['sender'] instanceof Episciences_User) {
            $mail->setFromWithTags($journalOptions['sender'], $journalOptions['rvCode']);
        } else {
            $mail->setFromReview($journalOptions['rvCode']);
        }

        $mail->setTo($recipient, $journalOptions['rvCode']);
        /** @var Episciences_User $ccRep */
        if (!empty($CC)) {
            foreach ($CC as $ccRep) {
                $mail->addCc($ccRep->getEmail(), $ccRep->getFullName());
            }
        }

        $mail->setSubject($template->getSubject());
        $mail->setTemplate($template->getPath(null, $journalOptions['rvCode']), $template->getKey() . self::TEMPLATE_EXTENSION);

        // Consideration of attached files
        if (!empty($attachmentsFiles)) {
            try {
                // if necessary, we force the creation of folders (e.g. copy editing: the files are stored in a
                // different path from the attached files)
                $attachmentPath = Episciences_Tools::getAttachmentsPath($paper->getPaperid(), true);

            } catch (Exception $e) {
                $attachmentPath = '';
                trigger_error($e->getMessage());
            }

            if($attachmentPath !== '') {
                foreach ($attachmentsFiles as $fileName => $filePath) {
                    if (file_exists($filePath . $fileName)) {
                        if (!$makeACopy) {
                            $mail->addAttachedFile($attachmentPath . $fileName);
                        } else {
                            $newName = Episciences_Tools::filenameRotate($attachmentPath, $fileName);
                            if (copy($filePath . $fileName, $attachmentPath . $newName)) {
                                $mail->addAttachedFile($attachmentPath . $newName);
                            }
                        }
                    }
                }

            }

        }

        $mail->writeMail($journalOptions['rvCode'], $journalOptions['rvId'], isset($journalOptions['debug']) && $journalOptions['debug']);

        /*if (!$mail->writeMail($journalOptions['rvCode'], $journalOptions['rvId'], isset($journalOptions['debug']) && $journalOptions['debug'])) {
            trigger_error('APPLICATION WARNING: the email (id = ' . $mail->getId() . ') was not sent', E_USER_WARNING);
            return false;
        }*/

        $paper?->log(Episciences_Paper_Logger::CODE_MAIL_SENT, $authUid, ['id' => $mail->getId(), 'mail' => $mail->toArray()]);

        return true;
    }

    /**
     * @param Zend_Form_Element $element
     * @param string $id
     * @return Zend_Form_Element
     */
    private static function decorate(Zend_Form_Element $element, string $id): Zend_Form_Element
    {

        $decorators = $element->getDecorators();

        try {
            $element->clearDecorators()
                ->addDecorator(['openDiv' => 'HtmlTag'], ['tag' => 'span', 'id' => $id . '_tags', 'placement' => 'APPEND', 'openOnly' => true])
                ->addDecorator(['closeDiv' => 'HtmlTag'], ['tag' => 'span', 'placement' => 'APPEND', 'closeOnly' => true])
                ->addDecorators($decorators);
        } catch (Zend_Form_Exception $e) {
            trigger_error($e->getMessage());
        }

        return $element;

    }
}
