<?php

class Episciences_Form_Validate_CheckMinimumDeadlineDelay extends Zend_Validate_Abstract
{
    /**
     * Error codes
     * @const string
     */
    const INVALID_ESTIMATION = 'invalid_estimation';
    const DEADLINE_GREATER_THAN_MAX = 'greater_than_max';

    /**
     * Error messages
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID_ESTIMATION => "Vous devez saisir une estimation de temps valide.",
        self::DEADLINE_GREATER_THAN_MAX => "Le délai de relecture minimum ne peut pas être supérieur au délai de relecture maximum.",
    );

    public function isValid($value, $context = null)
    {
        $this->_setValue($value);

        if (!is_numeric($value)) {
            $this->_error(self::INVALID_ESTIMATION);
            return false;
        }

        $post = is_array($context) ? $context : Zend_Controller_Front::getInstance()->getRequest()->getPost();
        $post['rating_deadline_min'] = $value;
        $deadline_min = Episciences\Form\Validate\DeadlineUnit::buildInterval($post, 'rating_deadline_min');
        $deadline_max = Episciences\Form\Validate\DeadlineUnit::buildInterval($post, 'rating_deadline_max');

        // an unusable value or unit is reported by the element that owns it
        if ($deadline_min === null || $deadline_max === null) {
            return true;
        }

        if (strtotime($deadline_min) > strtotime($deadline_max)) {
            $this->_error(self::DEADLINE_GREATER_THAN_MAX);
            return false;
        }

        return true;
    }

}