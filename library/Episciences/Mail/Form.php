<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 26/07/17
 * Time: 17:11
 */

use Episciences\Mail\RecipientFieldDecorator;

class Episciences_Mail_Form extends Ccsd_Form
{
    public $prefix = null;
    public $toEnabled = true;
    public $submitEnabled = true;

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }

        $this->init();
        $this->generate();
    }

    /**
     * set mail form options
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key);
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    public function generate()
    {
        $this->setAttrib('class', 'form-horizontal');
        $this->setAction('/administratemail/send');
        $this->setAttrib('id', $this->getPrefixedName('send_form'));

/*
        $decorators = array_keys($this->getDecorators());
        var_dump ($decorators);
        $decorators = array_merge([
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            $decorators);
        var_dump ($decorators);
        die;

        $this->setDecorators([[
            'ViewScript', [
                'viewScript' => '/administratemail/form.phtml'
            ]],
            'FormActions',
            'Form',
            'FormCss',
            'FormJavascript',
            'FormRequired'
        ]);
*/

        // from
        // default from: recipient name <rvcode@episciences.org>
        $this->addElement('text', $this->getPrefixedName('from'), array(
            'label' => 'De',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . RVCODE . '@' . DOMAIN . '>'));

        // reply-to
        // default reply-to: recipient name <recipient@domain.com>
        $this->addElement('text', $this->getPrefixedName('reply-to'), array(
            'label' => 'Répondre à',
            'disabled' => true,
            'placeholder' => RVCODE . '@' . DOMAIN,
            'value' => Episciences_Auth::getFullName() . ' <' . Episciences_Auth::getEmail() . '>'));

        // to
        if (!$this->getToEnabled()) {
            $this->addElement('text', $this->getPrefixedName('to'), [
                'label' => 'À',
                'disabled' => true,
            ]);
        } else {
            $this->addRecipientField('to');
        }

        $this->addElement('hidden', $this->getPrefixedName('hidden_to'));

        // cc
        $this->addRecipientField('cc');
        $this->addElement('hidden', $this->getPrefixedName('hidden_cc'));

        // bcc
        $this->addRecipientField('bcc');
        $this->addElement('hidden', $this->getPrefixedName('hidden_bcc'));


        // subject
        $this->addElement('text', $this->getPrefixedName('subject'), ['label' => 'Sujet']);

        // content
        $this->addElement('textarea', $this->getPrefixedName('content'), ['label' => 'Contenu', 'class' => 'tinymce']);

        // get a copy
        $this->addElement('checkbox', $this->getPrefixedName('copy'), array(
            'uncheckedValue' => null,
            'label' => "Recevoir une copie de ce message",
            'decorators' => array(
                'ViewHelper',
                array('Label', array('placement' => 'APPEND')),
                array('HtmlTag', array('tag' => 'div', 'class' => 'col-md-9 col-md-offset-3'))
            )
        ));

        // submit button
        if ($this->getSubmitEnabled()) {

            $this->addElement('button', $this->getPrefixedName('submit'), array(
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'label' => 'Envoyer'
            ));
        }

        return $this;
    }

    /**
     * Recipient field (to, cc, bcc) with its recipients list and search
     * @throws Zend_Form_Exception
     */
    private function addRecipientField(string $target): void
    {
        $name = $this->getPrefixedName($target);
        $this->addElement('text', $name);
        RecipientFieldDecorator::apply($this->getElement($name), $target, $target . '_tags', $this->getPrefixedName('hidden_' . $target));
    }

    private function getPrefixedName($name)
    {
        if ($this->hasPrefix()) {
            $name = $this->getPrefix() . '-' . $name;
        }

        return $name;
    }

    /**
     * @return bool
     */
    public function getToEnabled()
    {
        return $this->toEnabled;
    }

    /**
     * @param bool $toEnabled
     */
    public function setToEnabled($toEnabled)
    {
        $this->toEnabled = $toEnabled;
    }

    /**
     * @return string|null
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * @param bool $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return bool
     */
    public function hasPrefix()
    {
        return ($this->prefix != null);
    }

    /**
     * @return bool
     */
    public function getSubmitEnabled()
    {
        return $this->submitEnabled;
    }

    /**
     * @param bool $submitEnabled
     */
    public function setSubmitEnabled(bool $submitEnabled)
    {
        $this->submitEnabled = $submitEnabled;
    }


}
