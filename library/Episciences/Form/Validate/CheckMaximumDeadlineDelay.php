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

    public function isValid($value)
    {
        $this->_setValue($value);

        if (!is_numeric($value)) {
            $this->_error(self::INVALID_ESTIMATION);
            return false;
        }

        $request = Zend_Controller_Front::getInstance()->getRequest();
        $deadline_max = trim($value) . ' ' . $request->getPost('rating_deadline_max_unit');
        $deadline_min = trim($request->getPost('rating_deadline_min')) . ' ' . $request->getPost('rating_deadline_min_unit');

        if (strtotime($deadline_max) < strtotime($deadline_min)) {
            $this->_error(self::DEADLINE_LESS_THAN_MIN);
            return false;
        }

        return true;
    }

}