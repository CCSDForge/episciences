<?php

class Episciences_User_Assignment
{

    // CONSTANTES *****************************************************************

    // Types d'item possibles pour l'assignation
    const ITEM_PAPER = 'paper';
    const ITEM_SECTION = 'section';
    const ITEM_VOLUME = 'volume';

    // Roles possibles pour l'assignation
    const ROLE_REVIEWER = 'reviewer';
    const ROLE_EDITOR = 'editor';
    const ROLE_COPY_EDITOR = 'copyeditor';

    // Statuts possibles d'une assignation
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_DECLINED = 'declined';


    // ATTRIBUTS *******************************************************************

    /**
     * ID de l'assignation
     * @var int
     */
    protected $_id;

    /**
     * ID de l'invitation
     * @var int
     */
    protected $_invitation_id;


    /**
     * ID de l'item auquel l'utilisateur est assigné
     * @var int
     */
    protected $_itemid;

    /**
     * ID de la revue
     * @var int
     */
    protected $_rvid;

    /**
     * Type de l'item auquel l'utilisateur est assigné
     * @var string(50)
     */
    protected $_item;

    /**
     * UID de l'utilisateur
     * @var int
     */
    protected $_uid;

    /**
     * Pour associer une invitation provenant d'un autre compte.
     * La valeur par défaut est null,
     * sinon l'ID du compte auquel l'invitation est envoyée
     * @var int|null
     */

    protected ?int $_from_uid = null;

    /**
     * Utilisateur temporaire
     * @var bool
     */
    protected $_tmp_user;

    /**
     * Rôle assigné à l'utilisateur
     * @var string(50)
     */
    protected $_roleid;

    /**
     * Statut de l'assignation (pending|active|inactive)
     * @var string(20)
     */
    protected $_status;

    /**
     * Date de l'assignation
     * @var datetime
     */
    protected $_when;

    /**
     * Deadline de l'assignation
     * @var datetime
     */
    protected $_deadline;


    // METHODES ******************************************************************


    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options): \Episciences_User_Assignment
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
     * Enregistre l'assignation en BDD
     * @return boolean
     * @throws Zend_Db_Adapter_Exception
     */
    public function save(): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Préparation des valeurs à insérer
        $values = [
            'INVITATION_ID' => $this->getInvitation_id(),
            'ITEMID' => $this->getItemid(),
            'ITEM' => $this->getItem(),
            'RVID' => !$this->getRvid() ? RVID : $this->getRvid(),
            'UID' => $this->getUid(),
            'FROM_UID' => $this->getFrom_uid(),
            'TMP_USER' => $this->isTmp_user(),
            'ROLEID' => $this->getRoleid(),
            'STATUS' => $this->getStatus(),
            'WHEN' => new Zend_Db_Expr('NOW()'),
            'DEADLINE' => $this->getDeadline()
        ];

        // Enregistrement en BDD
        if ($this->getId()) {
            $db->update(T_ASSIGNMENTS, $values, array('ID = ?' => $this->getId()));
            return true;
        }

        if ($db->insert(T_ASSIGNMENTS, $values)) {
            $this->setId((int)$db->lastInsertId());
            return true;
        }

        return false;
    }


    // GETTERS *************************************************************************

    /**
     * @return int $_id
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @return int $_invitation_id
     */
    public function getInvitation_id()
    {
        return $this->_invitation_id;
    }

    /**
     * @return int $_itemid
     */
    public function getItemid()
    {
        return $this->_itemid;
    }

    /**
     * @return int $_rvid
     */
    public function getRvid()
    {
        return $this->_rvid;
    }

    /**
     * @return string $_item
     */
    public function getItem()
    {
        return $this->_item;
    }

    /**
     * @return int $_uid
     */
    public function getUid()
    {
        return $this->_uid;
    }


    public function getFrom_uid(): ?int
    {
        return $this->_from_uid;
    }

    public function setFrom_uid(?int $linkedUid = null): self
    {
        $this->_from_uid = $linkedUid;
        return $this;
    }


    /**
     * @return bool|int $_tmp_user
     */
    public function isTmp_user()
    {
        return (!is_null($this->_tmp_user)) ? $this->_tmp_user : 0;
    }

    /**
     * @return string $_roleid
     */
    public function getRoleid()
    {
        return $this->_roleid;
    }

    /**
     * @return string $_status
     */
    public function getStatus()
    {
        return $this->_status;
    }

    /**
     * @return datetime $_when
     */
    public function getWhen()
    {
        return $this->_when;
    }

    /**
     * @return datetime $_deadline
     */
    public function getDeadline()
    {
        return $this->_deadline;
    }

    // SETTERS *************************************************************************

    /**
     * @param number $_id
     */
    public function setId($_id)
    {
        $this->_id = $_id;
    }

    /**
     * @param number $_invitation_id
     */
    public function setInvitation_id($_invitation_id)
    {
        $this->_invitation_id = $_invitation_id;
    }

    /**
     * @param number $_itemid
     */
    public function setItemid($_itemid)
    {
        $this->_itemid = (int)$_itemid;
    }

    /**
     * @param number $_rvid
     */
    public function setRvid($_rvid)
    {
        $this->_rvid = $_rvid;
    }

    /**
     * @param string(50) $_item
     */
    public function setItem($_item)
    {
        $this->_item = $_item;
    }

    /**
     * @param number $_uid
     */
    public function setUid($_uid)
    {
        $this->_uid = (int)$_uid;
    }

    /**
     * @param bool $_tmp_user
     */
    public function setTmp_user($_tmp_user)
    {
        $this->_tmp_user = $_tmp_user;
    }

    /**
     * @param string(50) $_roleid
     */
    public function setRoleid($_roleid)
    {
        $this->_roleid = $_roleid;
    }

    /**
     * @param string(20) $_status
     */
    public function setStatus($_status)
    {
        $this->_status = $_status;
    }

    /**
     * @param datetime $_when
     */
    public function setWhen($_when)
    {
        $this->_when = $_when;
    }

    /**
     * @param datetime $_deadline
     */
    public function setDeadline($_deadline)
    {
        $this->_deadline = $_deadline;
    }

    /**
     * Retourne l'utilisateur concerné par l'aasignation
     * @return bool|Episciences_Reviewer|Episciences_User_Tmp
     * @throws Zend_Db_Statement_Exception
     */
    public function getAssignedUser()
    {
        if ($this->isTmp_user()) {
            $reviewer = Episciences_TmpUsersManager::findById($this->getUid());
            if ($reviewer) {
                $reviewer->generateScreen_name();
            }
        } else {
            $reviewer = new Episciences_Reviewer;
            $reviewer->findWithCAS($this->getUid());
        }

        return $reviewer;
    }
}
