<?php

class Episciences_User extends Ccsd_User_Models_User
{
    /** @var int */
    protected $_uid;

    protected $_langueid;

    /** @var boolean */
    protected $_hasAccountData;

    protected $_roles = null;
    protected $_role;
    protected $_db = null;


    /** @var string */
    protected $_screenName;

    /** @var array */
    protected $_aliases = [];

    /**
     * @var string
     */
    protected $_api_password;
    /**
     * @var int
     */
    protected $_is_valid = 1;
    protected $_registration_date;
    protected $_modification_date;

    private $_papersNotInConflict = [];

    /**
     * Constructeur d'un utilisateur
     * @param array|null $options
     */
    public function __construct(array $options = null)
    {
        parent::__construct($options);
        $this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * @param array $options
     * @return $this|Ccsd_User_Models_User
     */
    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);


        foreach ($options as $key => $value) {

            //because setScreen_name() has been renamed to setScreenName()
            if ($key === 'SCREEN_NAME') {
                $this->setScreenName($value);
                continue;
            }


            $key = strtolower($key); // les noms de champs sont en majuscules dans la BDD

            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * delete user from episciences database
     * @param $uid
     * @param bool $everywhere
     * @return bool
     */
    public static function deleteLocalData($uid, $everywhere = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($everywhere) {
            // Supprime son compte ES et ses rôles pour toutes les revues
            $db->delete(T_USER_ROLES, 'UID = ' . $uid);
            $db->delete(T_USERS, 'UID = ' . $uid);

            // Désactive toutes ses assignations (relecture, édition) pour toutes les revues
            $db->query("INSERT INTO `USER_ASSIGNMENT` (`RVID`, `ITEMID`, `ITEM`, `UID`, `ROLEID`, `STATUS`, `WHEN`)
            SELECT `u`.`RVID`, `u`.`ITEMID`, `u`.`ITEM`, `u`.`UID`, `u`.`ROLEID`, 'disabled', NOW()
            FROM USER_ASSIGNMENT `u`
            WHERE `u`.`UID` = ?
            AND `u`.`WHEN` IN (
            SELECT MAX(`ua`.`WHEN`) AS `MAXDATE`
            FROM USER_ASSIGNMENT `ua`
            WHERE `ua`.`UID` = `u`.`UID`
            AND `ua`.`ITEM` = `u`.`ITEM`
            AND `ua`.`ITEMID` = `u`.`ITEMID`
            AND `ua`.`ROLEID` = `u`.`ROLEID`
            AND `ua`.`RVID` = `u`.`RVID`
            GROUP BY `ua`.`ROLEID`
            )
            AND `u`.UID NOT IN (
            SELECT UID
            FROM USER_ROLES `ur`
            WHERE `ur`.`RVID` = `u`.`RVID`
            AND ROLEID != 'member'
            )", $uid);
        } else {
            // Supprime uniquement ses rôles pour cette revue
            $db->delete(T_USER_ROLES, 'RVID = ' . RVID . ' AND UID = ' . $uid);

            // Désactive toutes ses assignations (relecture, édition) pour cette revue
            $db->query("INSERT INTO `USER_ASSIGNMENT` (`RVID`, `ITEMID`, `ITEM`, `UID`, `ROLEID`, `STATUS`, `WHEN`)
            SELECT `u`.`RVID`, `u`.`ITEMID`, `u`.`ITEM`, `u`.`UID`, `u`.`ROLEID`, 'disabled', NOW()
            FROM USER_ASSIGNMENT `u`
            WHERE `u`.`UID` = ?
            AND `u`.`RVID` = ?
            AND `u`.`WHEN` IN (
            SELECT MAX(`ua`.`WHEN`) AS `MAXDATE`
            FROM USER_ASSIGNMENT `ua`
            WHERE `ua`.`UID` = `u`.`UID`
            AND `ua`.`ITEM` = `u`.`ITEM`
            AND `ua`.`ITEMID` = `u`.`ITEMID`
            AND `ua`.`ROLEID` = `u`.`ROLEID`
            AND `ua`.`RVID` = `u`.`RVID`
            GROUP BY `ua`.`ROLEID`
            )
            AND `u`.UID NOT IN (
            SELECT UID
            FROM USER_ROLES `ur`
            WHERE `ur`.`RVID` = `u`.`RVID`
            AND ROLEID != 'member'
            )", [$uid, RVID]);

        }

        return true;
    }

    /**
     * delete user from CAS database
     * @param $uid
     * @return int
     */
    public static function deleteFromCAS($uid)
    {
        $db = Ccsd_Db_Adapter_Cas::getAdapter();
        return $db->delete(T_CAS_USERS, 'UID = ' . $uid);
    }

    // Retourne les droits de l'utilisateur (pour toutes les revues / portails)

    public static function filterUsers($filter, $withoutRoles = true)
    {
        $result = null;
        $filter = trim($filter);
        $keywords = explode(' ', $filter);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_USERS);
        $subSelect = $db->select()->from(T_USER_ROLES, ['UID'])->where('RVID = ?', RVID);
        if ($withoutRoles) {
            $subSelect->where('ROLEID != "member');
            $select->where('UID NOT IN (' . new Zend_db_Expr($subSelect) . ')');
        } else {
            $select->where('UID IN (' . new Zend_db_Expr($subSelect) . ')');
        }

        $users = $db->fetchAssoc($select);

        if ($users) {
            $casDb = Ccsd_Db_Adapter_Cas::getAdapter();
            $select = $casDb->select()->from(T_CAS_USERS)->where('UID IN (?)', array_keys($users))->order('LASTNAME');

            $where = '(';
            foreach ($keywords as $key => $keyword) {
                if (!empty($keyword)) {
                    if ($key > 0) {
                        $where .= ' AND ';
                    }
                    $where .= '(';
                    $where .= $casDb->quoteInto('FIRSTNAME LIKE ? OR ', '%' . $keyword . '%');
                    $where .= $casDb->quoteInto('LASTNAME LIKE ?', '%' . $keyword . '%');
                    $where .= ')';
                }
            }
            $where .= ' OR ';
            $where .= $casDb->quoteInto('EMAIL LIKE ?', '%' . $filter . '%');
            $where .= ')';

            $select->where($where);
            $select->limit(25);
            $result = $casDb->fetchAll($select);
        }

        return ($result);
    }

    public static function filterCasUsers($filter)
    {
        $filter = trim($filter);
        $keywords = explode(' ', $filter);

        $casDb = Ccsd_Db_Adapter_Cas::getAdapter();
        $select = $casDb->select()->from('V_UTILISATEURS_VALIDES');

        foreach ($keywords as $key => $keyword) {
            if ($keyword != '') {
                $select->where($casDb->quoteInto('FIRSTNAME LIKE ? OR ', '%' . $keyword . '%')
                    . $casDb->quoteInto('LASTNAME LIKE ?', '%' . $keyword . '%'));
            }
        }

        $select->orWhere('EMAIL LIKE ?', '%' . $filter . '%');

        $select->limit(25);

        return ($casDb->fetchAll($select));
    }

    /**
     * Chargement des informations spécifiques d'un utilisateurs (données
     * spécifiques application + roles)
     */
    public function load()
    {
        $this->getRoles();
    }

    /**
     * Retourne les droits d'un utilisateur (pour la revue/portail qu'il consulte)
     *
     * @return array
     */
    public function getRoles()
    {
        if ($this->_roles === null) {
            $this->_roles = $this->loadRoles();
        }

        return (is_array($this->_roles) && array_key_exists(RVID, $this->_roles)) ? $this->_roles[RVID] : null;
    }


    /**
     * @param $roles
     * @return $this
     */
    public function setRoles($roles)
    {
        $this->_roles = $roles;
        return $this;
    }

    /**
     * Récupération des rôles
     */
    public function loadRoles()
    {
        $roles = [];

        if ($this->getUid() == null) {
            $this->setRoles($roles);
            return $roles;
        }


        // Récupération des droits en bdd
        $sql = $this->_db->select()
            ->from(T_USER_ROLES, ['RVID', 'ROLEID'])
            ->where('UID = ?', $this->getUid());


        // Tri des rôles par revue
        // Tri des rôles par revue
        foreach ($this->_db->fetchAll($sql) as $role) {
            $roles[$role['RVID']][] = $role['ROLEID'];
        }

        if (defined('RVID') && !isset($roles[RVID])) {
            $roles[RVID] = [Episciences_Acl::ROLE_MEMBER];
        }

        $this->setRoles($roles);
        return $roles;
    }

    public function toArray()
    {
        $res = parent::toArray();
        $res['SCREEN_NAME'] = $this->getScreenName();
        $res['langueid'] = $this->getLangueid();
        $res['fullname'] = ($this->getFullName()) ? $this->getFullName() : $this->getScreenName();
        $res['ROLES'] = $this->getAllRoles();
        $res['email'] = $this->getEmail();
        return $res;
    }

    /**
     * @param string $_screenName
     * @return string
     */
    private static function cleanScreenName(string $_screenName = ''): string
    {
        return str_replace('/', ' ', $_screenName);
    }

    public function getScreenName()
    {

        return $this->_screenName;
    }

    public function setScreenName($_screenName = null)
    {
        if ($_screenName == '') {
            $_screenName = Ccsd_Tools::formatAuthor($this->getFirstname(), $this->getLastname());
        }
        $_screenName = self::cleanScreenName($_screenName);
        $this->_screenName = filter_var($_screenName, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $this;
    }

    public function getLangueid($forceResult = false)
    {
        if ($this->_langueid) {
            return $this->_langueid;
        }

        return ($forceResult) ? Episciences_Review::getDefaultLanguage() : null;
    }

    public function setLangueid($_langueid)
    {
        $this->_langueid = $_langueid;
        return $this;
    }

    public function getFullName()
    {
        return Episciences_Tools::formatUser($this->getFirstname(), $this->getLastname());
    }

    public function getAllRoles()
    {
        if ($this->_roles === null) {
            $this->_roles = $this->loadRoles();
        }
        return $this->_roles;
    }

    /**
     * Enregistre les propriétés de l'utilisateur
     *
     * @param bool $forceInsert
     * @return bool|string
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Exception
     * @see Ccsd_User_Models_User::save()
     */
    public function save($forceInsert = false)
    {
        // Enregistrement des données CAS
        // et renvoi de l'id si il s'agit d'un nouveau compte
        $casId = parent::save($forceInsert);
        $uid = ($casId) ?: $this->getUid();
        $this->setUid($uid);

        $langId = ($this->getLangueid()) ?: Zend_Registry::get('Zend_Locale')->getLanguage();

        if ($this->getScreenName() === '') {
            $this->setScreenName();
        }

        $data = [
            'UID' => $this->getUid(),
            'LANGUEID' => $langId,
            'SCREEN_NAME' => $this->getScreenName(),
            'USERNAME' => $this->getUsername(),
            'API_PASSWORD' => password_hash(Ccsd_Tools::generatePw(), PASSWORD_DEFAULT),
            'EMAIL' => $this->getEmail(),
            'CIV' => $this->getCiv(),
            'LASTNAME' => $this->getLastname(),
            'FIRSTNAME' => $this->getFirstname(),
            'MIDDLENAME' => $this->getMiddlename(),
            'REGISTRATION_DATE' => $this->getRegistration_date(),
            'MODIFICATION_DATE' => $this->getModification_date(),
            'IS_VALID' => $this->getIs_valid()
        ];

        // Création des données locales (compte ES + rôle)

        $hasLocalData = $this->hasLocalData($this->getUid());
        $hasRolesCurrentUser = $this->hasRoles($this->getUid());


        if (!$hasLocalData || !$hasRolesCurrentUser) {

            // L'utilisateur n'a pas de compte ES : on lui en crée un
            if (!$hasLocalData) {

                // new account new registration date
                $data['REGISTRATION_DATE'] = date("Y-m-d H:i:s");
                try {
                    $resInsert = $this->_db->insert(T_USERS, $data);
                } catch (Exception $e) {
                    $resInsert = false;
                    trigger_error($e->getMessage(), E_USER_ERROR);
                }

                if ($resInsert) {
                    $uid = $this->_db->lastInsertId();
                } else {
                    return false;
                }
            }

            // L'utilisateur n'a pas de rôles pour cette revue : on lui en crée un
            $rData = ['RVID' => RVID, 'UID' => $uid, 'ROLEID' => 'member'];

            if (!$this->hasRoles($uid) && !$this->_db->insert(T_USER_ROLES, $rData)) {
                return false;
            }

            return $uid;
        }

        // Mise à jour des données locales
        $this->_db->update(T_USERS, $data, ['UID = ?' => $this->getUid()]);
        return $this->getUid();

    }

    /**
     * @return mixed
     */
    public function getRegistration_date()
    {
        return $this->_registration_date;
    }

    /**
     * @param mixed $registrationDate
     * @return Episciences_User
     */
    public function setRegistration_date($registrationDate = null): \Episciences_User
    {
        if (null === $registrationDate) {
            $registrationDate = date("Y-m-d H:i:s");
        }

        $this->_registration_date = $registrationDate;

        return $this;

    }

    /**
     * @return mixed
     */
    public function getModification_date()
    {
        return $this->_modification_date;
    }

    /**
     * @param mixed $modificationDate
     * @return Episciences_User
     */
    public function setModification_date($modificationDate = null): \Episciences_User
    {
        if (null === $modificationDate) {
            $modificationDate = date("Y-m-d H:i:s");
        }

        $this->_modification_date = $modificationDate;

        return $this;
    }

    // Renvoie la liste des revues dans lesquelles l'utilisateur a été actif

    /**
     * @return int
     */
    public function getIs_valid()
    {
        return $this->_is_valid;
    }

    /**
     * @param int $valid
     * @return Episciences_User
     */
    public function setIs_valid(int $valid = 1): \Episciences_User
    {
        $this->_is_valid = $valid;
        return $this;
    }

    /**
     * check if user exists in Episciences database
     *
     * @param int $uid
     * @return boolean
     * @throws Zend_Db_Statement_Exception
     */
    public function hasLocalData($uid): bool
    {
        $select = $this->_db->select()
            ->from(T_USERS, ['nombre' => 'COUNT(UID)'])
            ->where('UID = ?', $uid);

        $result = $select->query()->fetch();

        if ($result['nombre'] == 0) {
            $this->setHasAccountData(false);
            return false;
        }

        $this->setHasAccountData(true);
        return true;
    }

    /**
     * check if user already has some roles for this review
     *
     * @param int $uid
     * @return boolean
     * @throws Zend_Db_Statement_Exception
     */
    public function hasRoles($uid)
    {
        $select = $this->_db->select()
            ->from(T_USER_ROLES, 'ROLEID')
            ->where('RVID = ?', RVID)
            ->where('UID = ?', $uid);

        $result = $select->query()->fetch();

        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * given an uid, fetch user local data (episciences database) and CAS data
     *
     * @param int $uid
     * @return Zend_Db_Table_Row_Abstract
     * @throws Zend_Db_Statement_Exception
     */
    public function findWithCAS($uid)
    {
        $this->find($uid);
        $ccsdUserMapper = new Ccsd_User_Models_UserMapper();

        return $ccsdUserMapper->find($uid, $this);
    }

    /**
     * Recherche les propriétés locales d'un utilisateur par son UID
     *
     * @param int $uid
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function find($uid): array
    {
        $select = $this->_db->select()
            ->from(T_USERS, '*')
            ->where('UID = ?', $uid);

        $result = $select->query()->fetch(Zend_Db::FETCH_ASSOC);

        if (!$result || 0 === count($result)) { // false si le user n'existe pas.
            return [];
        }

        // Si les données locales n'existent pas, on crée le Screenname à partir du nom/prénom
        if (!isset($result['SCREEN_NAME']) || ($result['SCREEN_NAME'] === '')) {
            $result['SCREEN_NAME'] = Episciences_Auth::getFullName();
        }

        if (!isset($result['API_PASSWORD']) || ($result['API_PASSWORD'] === '')) {
            $result['API_PASSWORD'] = password_hash(Ccsd_Tools::generatePw(), PASSWORD_BCRYPT);
        }

        if (!isset($result['REGISTRATION_DATE'])) {
            $result['REGISTRATION_DATE'] = $this->getTime_registered(); // cas registration
        }

        if (!isset($result['MODIFICATION_DATE'])) {
            $result['MODIFICATION_DATE'] = $this->setModification_date()->getModification_date(); // cas registration
        }

        if (!isset($result['USERNAME']) || ($result['USERNAME'] === '')) {
            $result['USERNAME'] = $this->getUsername();
        }

        if (!isset($result['EMAIL']) || ($result['EMAIL'] === '')) {
            $result['EMAIL'] = $this->getEmail();
        }

        if (!isset($result['CIV'])) {
            $result['CIV'] = $this->getCiv();
        }

        if (!isset($result['LASTNAME']) || ($result['LASTNAME'] === '')) {
            $result['LASTNAME'] = $this->getLastname();
        }

        if (!isset($result['FIRSTNAME'])) {
            $result['FIRSTNAME'] = $this->getFirstname();
        }

        if (!isset($result['MIDDLENAME'])) {
            $result['MIDDLENAME'] = $this->getMiddlename();
        }

        if (!isset($result['IS_VALID'])) {
            $result['IS_VALID'] = 1;
        } else {
            $result['IS_VALID'] = (int)$result['IS_VALID'];
        }

        $this->setUid($result['UID']);
        $this->setUsername($result['USERNAME']);
        $this->setEmail($result['EMAIL']);
        $this->setScreenName($result['SCREEN_NAME']);
        $this->setLastname($result['LASTNAME']);
        $this->setFirstname($result['FIRSTNAME']);
        $this->setMiddlename($result['MIDDLENAME']);
        $this->setCiv($result['CIV']);
        $this->setLangueid($result['LANGUEID']);
        $this->setApi_password($result['API_PASSWORD']); // Episciences api password
        $this->setIs_valid($result['IS_VALID']); // Episciences validation
        $this->setRegistration_date($result['REGISTRATION_DATE']);  // Episciences registration date
        $this->setModification_date($result['MODIFICATION_DATE']);  // Episciences modification date

        return $result;
    }

    public function isRoot()
    {
        return $this->hasRole(Episciences_Acl::ROLE_ROOT);
    }

    /**
     * check if user has a given role for this review
     * @param $rolecode
     * @return bool
     */
    public function hasRole($rolecode)
    {
        return (is_array($this->getRoles()) && in_array($rolecode, $this->getRoles()));
    }

    // Création des données locales pour un utilisateur CAS

    public function isChiefEditor()
    {
        return $this->hasRole(Episciences_Acl::ROLE_CHIEF_EDITOR);
    }

    public function isGuestEditor()
    {
        return $this->hasRole(Episciences_Acl::ROLE_GUEST_EDITOR);
    }

    public function isAdministrator()
    {
        return $this->hasRole(Episciences_Acl::ROLE_ADMIN);
    }



    // Renvoie la liste des utilisateurs, filtrés par mot clés (sert pour l'autocomplete)
    // Par défaut, renvoie uniquement les utilisateurs qui n'ont pas de rôle dans la revue.

    public function isEditor()
    {
        return $this->hasRole(Episciences_Acl::ROLE_EDITOR);
    }

    public function isReviewer()
    {
        return $this->hasRole(Episciences_Acl::ROLE_REVIEWER);
    }

    public function isSecretary()
    {
        return $this->hasRole(Episciences_Acl::ROLE_SECRETARY);
    }

    public function isWebmaster()
    {
        return $this->hasRole(Episciences_Acl::ROLE_WEBMASTER);
    }

    public function isMember()
    {
        return $this->hasRole(Episciences_Acl::ROLE_MEMBER);
    }

    public function getReviews()
    {
        $reviewIds = array_keys($this->getAllRoles());
        return Episciences_ReviewsManager::getList(['is' => ['rvid' => $reviewIds]]);
    }

    public function getHasAccountData()
    {
        return $this->_hasAccountData;
    }

    public function setHasAccountData($_hasAccountData)
    {
        if (!is_bool($_hasAccountData)) {
            throw new InvalidArgumentException(
                'hasAccountData : boolean attendu');
        } else {

            $this->_hasAccountData = $_hasAccountData;
            return $this;
        }
    }

    public function createLocalData()
    {
        $screenName = $this->getScreenName();
        $langId = $this->getLangueid();
        $uid = $this->getUid();

        $data = [$uid, $langId, $screenName];
        return ($this->_db->insert(T_USERS, $data));
    }

    public function getUserRoleForm($users)
    {
        if ($users) {
            $form = new Zend_Form();
            $form->setAction('/user/saveroles');

            $form->addPrefixPath('Episciences_Form_Element', 'Episciences/Form/Element/', Zend_Form::ELEMENT);
            $form->addPrefixPath('Episciences_Form_Decorator', 'Episciences/Form/Decorator/', 'decorator');

            $columns = ['Utilisateurs', 'Rôles', 'Actions'];
            $options = ['showHeaders' => false, 'columns' => $columns, 'class' => 'table table-condensed', 'id' => 'reviewers'];
            $decorators = ['FormElements', ['Table', $options], 'Form'];
            $form->setDecorators($decorators);

            $acl = new Episciences_Acl();
            $roles = $acl->getRolesCodes();
            $acl = new Episciences_Acl();
            unset($roles[$acl::ROLE_GUEST], $roles[$acl::ROLE_MEMBER], $roles[$acl::ROLE_ROOT]);
            $translator = Zend_Registry::get('Zend_Translate');

            foreach ($users as $uid => $user) {

                $form->addElement('html', 'openTr_' . $uid, ['value' => '<tr>']); // opening tr

                // Cellule 1 : Nom de l'utilisateur **********************************************************************
                $form->addElement('html', 'openTd1_' . $uid, ['value' => '<td style="width: 200px">']); // opening td

                // Infos sur l'utilisateur
                $string = $user['SCREEN_NAME'] . ' <i>(' . $user['USERNAME'] . ')</i><br/>';
                $string .= '<i>' . $user['EMAIL'] . '</i>';
                $form->addElement('html', 'text_' . $uid, ['value' => $string]);

                $form->addElement('html', 'closeTd1_' . $uid, ['value' => '</td>']); // closing td

                // Cellule 2 : Roles de l'utilisateur ********************************************************************
                $form->addElement('html', 'openTd2_' . $uid, ['value' => '<td style="padding:5px">']); // opening td

                // Roles de l'utilisateur
                // Roles actuels
                $form->addElement('html', 'openTags_' . $uid, ['value' => '<div>']); // opening div
                foreach ($user['ROLES'] as $role) {
                    if ($role == Episciences_Acl::ROLE_ROOT) {
                        // root
                        $class = 'label-danger';
                    } elseif ($role == Episciences_Acl::ROLE_CHIEF_EDITOR || $role == Episciences_Acl::ROLE_ADMIN) {
                        // chief editor or admin
                        $class = 'label-warning';
                    } elseif ($role == Episciences_Acl::ROLE_WEBMASTER) {
                        // webmaster
                        $class = 'label-success';
                    } elseif ($role == Episciences_Acl::ROLE_REVIEWER) {
                        // reviewer
                        $class = 'label-info';
                    } else {
                        // member
                        $class = '';
                    }

                    $tag = '<span class="label ' . $class . '">' . $translator->translate($role) . '</span>';
                    $form->addElement('html', 'tag_' . $uid . '_' . $role, ['value' => $tag]);

                }
                $form->addElement('html', 'closeTags_' . $uid, ['value' => '</div>']); // closing div

                // Modifier les roles
                $form->addElement('html', 'openRoles_' . $uid, ['value' => '<div class="roles" style="padding-top:5px; display:none">']); // opening div
                $form->addElement('html', 'hr_' . $uid, ['value' => '<hr style="margin-bottom: 5px" />']);
                /*
                <input
                    style="margin: 2px"
                    $checked;
                    type="checkbox"
                    value="$code"
                    userid="$uid"
                    name="$role.'['.$uid.']'"
                    id="$role.'['.$uid.']'" />
                <label style="display: inline; line-height: 12px; font-size: 12px" for="$role.'['.$uid.']'">
                    echo $this->translate($role);
                </label>
                <br/>
                */

                $element = new Zend_Form_Element_MultiCheckbox('roles_' . $uid, ['multiOptions' => $roles]);
                $element->setValue($user['ROLES']);
                $element->setSeparator(''); // Supprime les <br/>
                $element->removeDecorator('Label');
                $element->removeDecorator('HtmlTag');
                // $element->setDecorators(array('CheckboxGroup',array(array('div' => 'HtmlTag'), array('tag' => 'div')),'Errors'));
                $form->addElement($element);

                $form->addElement('html', 'closeRoles_' . $uid, ['value' => '</div>']); // closing div

                $form->addElement('html', 'closeTd2_' . $uid, ['value' => '</td>']); // closing td

                // Cellule 3 : Boutons d'action ******************************************************************************
                $form->addElement('html', 'openTd3_' . $uid, ['value' => '<td style="text-align: right">']); // opening td
                $string = '<a class="toggleDetails" href="#">';
                $string .= '<span class="darkgrey glyphicon glyphicon-arrow-down"></span>';
                $string .= '</a>';
                $form->addElement('html', 'buttons' . $uid, ['value' => $string]); // boutons
                $form->addElement('html', 'closeTd3_' . $uid, ['value' => '</td>']); // closing td

                $form->addElement('html', 'closeTr_' . $uid, ['value' => '</tr>']); // closing tr
            }

            // Bouton : validation
            $submit = new Zend_Form_Element_Button('updateUsersRoles');
            $submit->setLabel('Valider')
                ->setOptions(["class" => "btn btn-primary"])
                ->setDecorators([
                    'ViewHelper',
                    [['td' => 'HtmlTag'], ['tag' => 'td', 'style' => 'padding: 20px', 'colspan' => count($columns)]],
                    [['tr' => 'HtmlTag'], ['tag' => 'tr']]])
                ->setAttribs(['style' => 'width: 150px', 'type' => 'submit']);
            $form->addElement($submit);
        } else {
            return false;
        }

        return $form;
    }

    public function saveUserRoles($uid, $roles)
    {
        // Reset des rôles de l'utilisateur
        $acl = new Episciences_Acl();
        $editableRoles = $acl->getEditableRoles();

        foreach ($editableRoles as $role) {
            $this->_db->delete(T_USER_ROLES, ['RVID = ?' => RVID, 'UID = ?' => $uid, 'ROLEID = ?' => $role]);
        }

        if (!empty($roles)) {

            // Préparation de la requête (valeurs à insérer)
            foreach ($roles as $roleId) {
                $roleId = $this->_db->quote($roleId);
                $values[] = '(' . $uid . ',' . RVID . ',' . $roleId . ')';
            }

            // Enregistrement des nouveaux rôles
            if (!empty($roles)) {
                $sql = 'INSERT IGNORE INTO ' . T_USER_ROLES . ' (UID, RVID, ROLEID) VALUES ' . implode(',', $values);
                $this->_db->query($sql);
            }

            // Si on update les rôles de son propre compte, il faut mettre à jour la session
            if (PHP_SAPI != 'cli') {
                $user = Episciences_Auth::getInstance()->getIdentity();
                if ($uid == Episciences_Auth::getUid()) {
                    $userRoles[RVID] = $roles;
                    $user->setRoles($userRoles);
                    Episciences_Auth::setIdentity($user);
                }
            }
        }

        return true;
    }

    /**
     * fetch user alias for a given docid
     * @param $docId
     * @param bool $strict (true: only the current version, false: include other versions)
     * @return string|null
     */
    public function getAlias($docId, $strict = true)
    {
        $aliases = $this->getAliases();

        if ($strict) {
            return $aliases[$docId] ?? null;
        }

        foreach ($this->versionIdsProcessing($docId) as $id) {
            if (array_key_exists($id, $aliases)) {  // relecteur de l'une des versions
                return $aliases[$id];
            }
        }

        return null;
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        if (empty($this->_aliases)) {
            $this->loadAliases();
        }
        return $this->_aliases;
    }

    /**
     * @param array $aliases
     */
    public function setAliases(array $aliases)
    {
        $this->_aliases = $aliases;
    }

    /**
     * load user aliases
     */
    public function loadAliases(): void
    {
        $uid = $this->getUid();

        if ($uid) {
            $select = $this->_db->select()
                ->from(T_ALIAS, ['DOCID', 'ALIAS'])
                ->where('UID = ?', $this->getUid());
            $this->setAliases($this->_db->fetchPairs($select));
        }
    }

    /**
     * @param $docId
     * @return array
     */
    private function versionIdsProcessing($docId): array
    {
        $paper = Episciences_PapersManager::get($docId, false);
        /** @var array $versionIdsValues ['version' => 'docId'] */
        $versionIdsValues = $paper->getVersionsIds();
        arsort($versionIdsValues); // high to low
        unset($versionIdsValues[Episciences_Tools::epi_array_key_first($versionIdsValues)]);
        return array_values($versionIdsValues);
    }

    /**
     * check if user has an alias for a given docid
     * @param $docId
     * @param bool $strict (true: only the current version, false: include other versions)
     * @return bool
     */
    public function hasAlias($docId, $strict = true): bool
    {
        $aliases = $this->getAliases();
        $hasAlias = array_key_exists($docId, $aliases);

        if (!$strict && !$hasAlias) {

            foreach ($this->versionIdsProcessing($docId) as $id) {
                if ($hasAlias = array_key_exists($id, $aliases)) {  // relecteur de l'une des versions
                    return $hasAlias;
                }
            }

        }
        return $hasAlias;
    }

    /**
     * create an alias, given a docid
     * @param $docId
     * @param int|null $value
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public function createAlias($docId, int $value = null): bool
    {
        $data = ['UID' => $this->getUid(), 'DOCID' => $docId];

        if (null === $value) {

            $paper = Episciences_PapersManager::get($docId, false);
            $versionIdsValues = $paper->getVersionsIds();

            $select = $this->_db
                ->select()
                ->from(T_ALIAS, new Zend_Db_Expr('MAX(ALIAS) + 1 AS ALIAS'))
                ->where('DOCID IN (?)', $versionIdsValues)
                ->order('ALIAS DESC');

            $alias = $this->_db->fetchOne($select);
            $data['ALIAS'] = (is_numeric($alias)) ? $alias : 1; // if the first one rating ($alias !is_numeric (null))
        } else {
            $data['ALIAS'] = $value;
            $alias = $value;
        }

        if ($this->_db->insert(T_ALIAS, $data)) {
            $this->setAlias($docId, $alias);
            return true;
        }

        return false;

    }

    /**
     * set an alias for a given docid
     * @param $docId
     * @param $alias
     */
    public function setAlias($docId, $alias)
    {
        $this->_aliases[$docId] = $alias;
    }

    /**
     * @return string
     */
    public function getApi_password(): string
    {
        return $this->_api_password;
    }

    /**
     * @param mixed $apiPassword
     */
    public function setApi_password($apiPassword)
    {
        $this->_api_password = $apiPassword;
    }

    /**
     * @return array
     */
    public function getPapersNotInConflict(): array
    {
        if(empty($this->_papersNotInConflict)){
            $this->loadPapersNotInConflict();
        }

        return $this->_papersNotInConflict;
    }

    /**
     * @param array $papersNotInConflict
     * @return Episciences_Editor
     */
    public function setPapersNotInConflict(array $papersNotInConflict): Episciences_User
    {
        $this->_papersNotInConflict = $papersNotInConflict;
        return $this;
    }

    /**
     * loads the papers for which a conflict has not been declared
     */
    public function loadPapersNotInConflict(): void
    {
        $result = [];

        if ($uid = $this->getUid()) {
            $oConflicts = Episciences_Paper_ConflictsManager::findByUidAndAnswer($uid, Episciences_Paper_Conflict::AVAILABLE_ANSWER['no']); // only confirmed: no conflict (answer = 'no')
            /** @var  $oConflict Episciences_Paper_Conflict */
            foreach ($oConflicts as $oConflict) {
                $paperId = $oConflict->getPaperId();
                $result[$paperId] = Episciences_PapersManager::get($paperId, false);
            }
        }

        $this->setPapersNotInConflict($result);
    }

}
