<?php

class Episciences_Form_Validate_CheckDefaultRatingDeadline extends Zend_Validate_Abstract
{
    /**
     * Error codes
     * @const string
     */
    const INVALID_ESTIMATION = 'invalid_estimation';
    const DEADLINE_GREATER_THAN_MAX = 'greater_than_max';
    const DEADLINE_LESS_THAN_MIN = 'less_than_min';

    /**
     * Error messages
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID_ESTIMATION => "Vous devez saisir une estimation de temps valide.",
        self::DEADLINE_GREATER_THAN_MAX => "Le délai de relecture par défaut ne peut pas être supérieur au délai de relecture maximum.",
        self::DEADLINE_LESS_THAN_MIN => "Le délai de relecture par défaut ne peut pas être inférieur au délai de relecture minimum.",
    );

    public function isValid($value)
    {
        $this->_setValue($value);

        if (!is_numeric($value)) {
            $this->_error(self::INVALID_ESTIMATION);
            return false;
        }

        $request = Zend_Controller_Front::getInstance()->getRequest();
        $default_deadline = trim($value) . ' ' . $request->getPost('rating_deadline_unit');
        $deadline_min = trim($request->getPost('rating_deadline_min')) . ' ' . $request->getPost('rating_deadline_min_unit');
        $deadline_max = trim($request->getPost('rating_deadline_max')) . ' ' . $request->getPost('rating_deadline_max_unit');

        if (strtotime($default_deadline) < strtotime($deadline_min)) {
            $this->_error(self::DEADLINE_LESS_THAN_MIN);
            return false;
        }

        if (strtotime($default_deadline) > strtotime($deadline_max)) {
            $this->_error(self::DEADLINE_LESS_THAN_MIN);
            return false;
        }

        return true;
    }

}