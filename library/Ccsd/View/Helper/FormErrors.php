<?php

class Ccsd_View_Helper_FormErrors extends Zend_View_Helper_FormErrors
{
    /**
     * @var Zend_Form_Element
     */
    protected $_element;

    /**#@+
     * @var string Element block start/end tags and separator
     */
    protected $_htmlElementEnd       = '</span>';
    protected $_htmlElementStart     = '<span%s>';
    protected $_htmlElementSeparator = '</span><span%s>';
    /**#@-*/

    /**
     * Render form errors
     *
     * @param  string|array $errors Error(s) to render
     * @param  array $options
     * @return string
     */
    public function formErrors($errors, array $options = null)
    {
        $escape = true;
        if (isset($options['escape'])) {
            $escape = (bool) $options['escape'];
            unset($options['escape']);
        }

        if (empty($options['class'])) {
            $options['class'] = 'help-block error';
        }

        $start = $this->getElementStart();
        if (strstr($start, '%s')) {
            $attribs = $this->_htmlAttribs($options);
            $start   = sprintf($start, $attribs);
        }

        if ($escape) {
            // On ajoute la possiblite pour un champs d'avoir plusieurs erreurs
            // Donc $error est traite par defaut comme un tableau
            foreach ($errors as $key => $error) {
                if (! is_array($error)) {
                    $error = [$error];
                }
                $errors[$key] = '';
                foreach ($error as $err) {
                    /** @var Ccsd_View $view */
                    $view = $this->view;
                    $errors[$key] .= $view->escape($err);
                }
            }
        }

        $html  = $start
               . implode($this->getElementSeparator(), (array) $errors)
               . $this->getElementEnd();

        return $html;
    }
}