<?php

class Episciences_User_Invitation
{
    // CONSTANTES *****************************************************************

    // Statuts possibles d'une invitation
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_CANCELLED = 'cancelled';

    // Types possibles d'une invitation
    const TYPE_REVIEWER = 'reviewer'; // invitation d'un utilisateur à relire un article
    const TYPE_EDITOR = 'editor';    // invitation d'un utilisateur à devenir éditeur d'un article

    // ATTRIBUTS *******************************************************************

    /**
     * Id de l'invitation
     * @var int
     */
    protected $_id;

    /**
     * Id de l'assignement auquel est lié l'invitation
     * @var int
     */
    protected $_aid;

    /**
     * Statut de l'invitation (pending, accepted, declined)
     * @var string(50)
     */
    protected $_status;


    /**
     * Date de l'envoi
     * @var datetime
     */
    protected $_sending_date;

    /**
     * Date d'expiration
     * @var datetime
     */
    protected $_expiration_date;

    /**
     * Uid de l'expéditeur de l'invitation
     * @var int
     */
    protected $_sender_uid;

    /**
     * Détails de l'expéditeur de l'invitation
     * @var Episciences_User
     */
    protected $_sender;

    /**
     * Réponse à l'invitation
     * @var Episciences_User_InvitationAnswer
     */
    protected $_answer;


    // METHODES ******************************************************************


    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }

    }

    public function setOptions(array $options): \Episciences_User_Invitation
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst(strtolower($key));
            if (in_array($method, $methods, true)) {
                $this->$method($value);
            }
        }
        return $this;
    }

    /**
     * save invitation to database (each save creates a new row)
     * @return boolean
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $rvId = !defined('RVID') ? Episciences_Review::$_currentReviewId : RVID;

        // Délai avant expiration de l'invitation (en jours)
        $review = Episciences_ReviewsManager::find($rvId);
        $review->loadSettings();
        $expiration_delay = ($review->getSetting('invitation_deadline')) ?: Episciences_Review::DEFAULT_INVITATION_DEADLINE;

        // Préparation des valeurs à insérer
        $values = [
            'SENDING_DATE' => new Zend_Db_Expr('NOW()'),
            'EXPIRATION_DATE' => new Zend_Db_Expr('DATE_ADD(NOW(), INTERVAL ' . $expiration_delay . ')')
        ];
        if ($this->getAid()) {
            $values['AID'] = $this->getAid();
        }
        if ($this->getStatus()) {
            $values['STATUS'] = $this->getStatus();
        }
        if ($this->getSender_uid()) {
            $values['SENDER_UID'] = $this->getSender_uid();
        }

        // Insertion en BDD
        if ($db->insert(T_USER_INVITATIONS, $values)) {
            $id = (int) $db->lastInsertId();
            $this->setId($id);
        } else {
            return false;
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
     * @return int
     */
    public function getAid()
    {
        return (int)$this->_aid;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * @return datetime
     */
    public function getSending_date()
    {
        return $this->_sending_date;
    }

    /**
     * @return datetime
     */
    public function getExpiration_date()
    {
        return $this->_expiration_date;
    }

    /**
     * @return int
     */
    public function getSender_uid()
    {
        return $this->_sender_uid;
    }

    /**
     * @return Episciences_User
     */
    public function getSender(): \Episciences_User
    {
        return $this->_sender;
    }

    /**
     * @return Episciences_User_InvitationAnswer
     */
    public function getAnswer()
    {
        return $this->_answer;
    }


    // SETTERS ************************************************************************


    /**
     * @param number $_id
     */
    public function setId($_id)
    {
        $this->_id = $_id;
    }

    /**
     * Id de l'assignement auquel est lié l'invitation
     * @param number $_aid
     */
    public function setAid($_aid)
    {
        $this->_aid = $_aid;
    }

    /**
     * @param string(50) $_status
     */
    public function setStatus($_status)
    {
        $this->_status = $_status;
    }

    /**
     * @param $sender_uid
     * @return bool
     */
    public function setSender_uid($sender_uid): bool
    {
        if (is_numeric($sender_uid)) {
            $this->_sender_uid = $sender_uid;
            $sender = new Episciences_User();
            try {
                if ($sender->findWithCAS($sender_uid)) {
                    $this->setSender($sender);
                }
            } catch (Zend_Db_Statement_Exception $e) {
                error_log($e->getMessage());
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * @param Episciences_User $sender
     */
    public function setSender(Episciences_User $sender)
    {
        $this->_sender = $sender;
    }

    /**
     * @param Episciences_User_InvitationAnswer $answer
     */
    public function setAnswer(Episciences_User_InvitationAnswer $answer)
    {
        $this->_answer = $answer;
    }

    /**
     * @param datetime $_sending_date
     */
    public function setSending_date($_sending_date)
    {
        $this->_sending_date = $_sending_date;
    }

    /**
     * @param datetime $_expiration_date
     */
    public function setExpiration_date($_expiration_date)
    {
        $this->_expiration_date = $_expiration_date;
    }


    /**
     * check if this invitation has expired
     * @return boolean
     */
    public function hasExpired(): bool
    {
        return (time() > strtotime($this->getExpiration_date()));
    }

    /**
     * check if this invitation already has been answered
     * @return boolean
     */
    public function isAnswered(): bool
    {
        return ($this->getAnswer() instanceof Episciences_User_InvitationAnswer);
    }

    public function isCancelled(): bool
    {
        $original = Episciences_User_InvitationsManager::find(['AID' => $this->getAid()]);
        return $original ?
            $original->getStatus() === self::STATUS_CANCELLED :
            $this->getStatus() === self::STATUS_CANCELLED;
    }


    /**
     * try to load invitation answer
     * @return bool
     */
    public function loadAnswer(): bool
    {
        if ($this->getId()) {
            $oAnswer = Episciences_User_InvitationAnswersManager::findById($this->getId());
            if ($oAnswer) {
                $this->setAnswer($oAnswer);
                return true;
            }
        }
        return false;
    }

}
