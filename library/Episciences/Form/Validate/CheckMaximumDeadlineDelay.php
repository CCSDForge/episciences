<?php

class Episciences_Form_Validate_CheckMaximumDeadlineDelay extends Zend_Validate_Abstract
{
    /**
     * Error codes
     * @const string
     */
    const INVALID_ESTIMATION = 'invalid_estimation';
    const DEADLINE_LESS_THAN_MIN = 'less_than_min';

    /**
     * Error messages
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID_ESTIMATION => "Vous devez saisir une estimation de temps valide.",
        self::DEADLINE_LESS_THAN_MIN => "Le délai de relecture maximum ne peut pas être inférieur au délai de relecture minimum.",
    );

    public function isValid($value, $context = null)
    {
        $this->_setValue($value);

        if (!is_numeric($value)) {
            $this->_error(self::INVALID_ESTIMATION);
            return false;
        }

        $post = is_array($context) ? $context : Zend_Controller_Front::getInstance()->getRequest()->getPost();
        $post['rating_deadline_max'] = $value;
        $deadline_max = Episciences\Form\Validate\DeadlineUnit::buildInterval($post, 'rating_deadline_max');
        $deadline_min = Episciences\Form\Validate\DeadlineUnit::buildInterval($post, 'rating_deadline_min');

        // an unusable value or unit is reported by the element that owns it
        if ($deadline_max === null || $deadline_min === null) {
            return true;
        }

        if (strtotime($deadline_max) < strtotime($deadline_min)) {
            $this->_error(self::DEADLINE_LESS_THAN_MIN);
            return false;
        }

        return true;
    }

}