<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 26/07/17
 * Time: 17:11
 */

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
        $translator = Zend_Registry::get('Zend_Translate');

        $this->setAttrib('class', 'form-horizontal');
        $this->setAction($this->_view->url(['controller' => 'administratemail', 'action' => 'send']));
        $this->setAttrib('id', $this->getPrefixedName('send_form'));

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

        $title = $translator->translate('Ajouter des destinataires');

        // to
        $to_element = $this->getPrefixedName('to');
        if (!$this->getToEnabled()) {
            $this->addElement('text', $to_element, [
                'label' => 'À',
                'disabled' => true,
            ]);
        } else {
            $this->addElement('text', $to_element, [
                'label' => sprintf('<a class="show_contacts_button" title="%s" href="%sadministratemail/getcontacts?target=to">%s</a>', $title, PREFIX_URL, $translator->translate('À')),
                'class' => 'autocomplete'
            ]);

            $decorators = $this->getElement($to_element)->getDecorators();

            $this->getElement($to_element)
                ->clearDecorators()
                ->addDecorator(array('openDiv' => 'HtmlTag'), array('tag' => 'span', 'id' => 'to_tags', 'placement' => 'APPEND', 'openOnly' => true))
                ->addDecorator(array('closeDiv' => 'HtmlTag'), array('tag' => 'span', 'placement' => 'APPEND', 'closeOnly' => true))
                ->addDecorators($decorators);
        }

        $this->addElement('hidden', $this->getPrefixedName('hidden_to'));


        // cc
        $cc_element = $this->getPrefixedName('cc');
        $this->addElement('text', $cc_element, [
            'label' => sprintf('<a class="show_contacts_button" title="%s" href="%sadministratemail/getcontacts?target=cc">%s</a>', $title, PREFIX_URL, $translator->translate('Cc'))
            ,
            'class' => 'autocomplete'
        ]);

        $decorators = $this->getElement($cc_element)->getDecorators();

        $this->getElement($cc_element)
            ->clearDecorators()
            ->addDecorator(array('openDiv' => 'HtmlTag'), array('tag' => 'span', 'id' => 'cc_tags', 'placement' => 'APPEND', 'openOnly' => true))
            ->addDecorator(array('closeDiv' => 'HtmlTag'), array('tag' => 'span', 'placement' => 'APPEND', 'closeOnly' => true))
            ->addDecorators($decorators);

        $this->addElement('hidden', $this->getPrefixedName('hidden_cc'));

        // bcc
        $bcc_element = $this->getPrefixedName('bcc');
        $this->addElement('text', $bcc_element, [
            'label' => sprintf('<a class="show_contacts_button" title="%s" href="%sadministratemail/getcontacts?target=bcc">%s</a>', $title, PREFIX_URL, $translator->translate('Bcc')),
            'class' => 'autocomplete'
        ]);

        $decorators = $this->getElement($bcc_element)->getDecorators();

        $this->getElement($bcc_element)
            ->clearDecorators()
            ->addDecorator(array('openDiv' => 'HtmlTag'), array('tag' => 'span', 'id' => 'bcc_tags', 'placement' => 'APPEND', 'openOnly' => true))
            ->addDecorator(array('closeDiv' => 'HtmlTag'), array('tag' => 'span', 'placement' => 'APPEND', 'closeOnly' => true))
            ->addDecorators($decorators);

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
