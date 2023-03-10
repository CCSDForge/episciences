<?php

class Episciences_User_InvitationAnswer
{
    public const ANSWER_YES = 'yes';
    public const ANSWER_NO = 'no';

    // Détails de la réponse
    public const DETAIL_DELAY = 'delay';
    public const DETAIL_SUGGEST = 'reviewer_suggest';
    public const DETAIL_COMMENT = 'comment';

    /**
     * Id de l'invitation
     * @var int
     */
    protected $_id;

    /**
     * Réponse à l'invitation (yes, no)
     * @var string(10)
     */
    protected $_answer;

    /**
     * Date de la réponse
     * @var datetime
     */
    protected $_answer_date;


    protected $_details = [];

    /**
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst(strtolower($key));
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }
        return $this;
    }

    /**
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function save()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($db === null) {
            trigger_error('Failed to init DB in ' . __CLASS__, E_USER_WARNING);
            return false;
        }

        // Préparation des valeurs à insérer
        $values = [
            'ID' => $this->getId(),
            'ANSWER' => $this->getAnswer(),
            'ANSWER_DATE' => new Zend_Db_Expr('NOW()')
        ];

        // Enregistrement de la réponse en base
        if (!$db->insert(T_USER_INVITATION_ANSWER, $values)) {
            trigger_error('Failed to insert in ' . T_USER_INVITATION_ANSWER, E_USER_WARNING);
            return false;
        }

        // Enregistrement des détails en base (USER_INVITATION_ANSWER_DETAILS)
        $details = $this->getDetails();

        if ($details) {

            $values = [];
            foreach ($details as $setting => $value) {
                $setting = $db->quote($setting);
                $value = $db->quote($value);
                $values[] = '(' . $this->getId() . ',' . $setting . ',' . $value . ')';
            }

            $sql = 'INSERT INTO ' . T_USER_INVITATION_ANSWER_DETAIL . ' (ID, NAME, VALUE) VALUES ' . implode(',', $values);
            $db->query($sql);
        }

        return true;

    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param $id
     * @return void
     */
    public function setId($id)
    {
        $this->_id = $id;
    }

    /**
     * @return string
     */
    public function getAnswer()
    {
        return $this->_answer;
    }

    /**
     * @param $answer
     * @return void
     */
    public function setAnswer($answer)
    {
        $this->_answer = $answer;
    }

    /**
     * @return array|mixed
     */
    public function getDetails()
    {
        return $this->cleanDetailValue($this->_details);
    }

    /**
     * @param array $details
     * @return void
     */
    public function setDetails(array $details)
    {
        $this->_details = $this->cleanDetailValue($details);
    }

    /**
     * @param $value
     * @return array|string
     */
    private function cleanDetailValue($value)
    {

        if (is_array($value)) {
            $value = array_map('strip_tags', $value);
            $value = array_map('htmlspecialchars', $value);
            $value = array_map('trim', $value);
        } else {
            $value = strip_tags($value);
            $value = htmlspecialchars($value);
            $value = trim($value);
        }

        return $value;
    }

    /**
     * @return datetime
     */
    public function getAnswer_date()
    {
        return $this->_answer_date;
    }

    /**
     * @param $answer_date
     * @return void
     */
    public function setAnswer_date($answer_date)
    {
        $this->_answer_date = $answer_date;
    }

    public function getDetail($name)
    {
        $details = $this->getDetails();

        if (array_key_exists($name, $details)) {
            return $this->cleanDetailValue($details[$name]);
        }

        return false;
    }

    /**
     * @param $name
     * @param $value
     */
    public function setDetail($name, $value)
    {
        $this->_details[$name] = $this->cleanDetailValue($value);
    }


}
