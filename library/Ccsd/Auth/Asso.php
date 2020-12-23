<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 13/02/19
 * Time: 10:28
 */

namespace Ccsd\Auth;
/**
 * Class Asso
 * @package Ccsd\Auth
 */
class Asso
{
    /**
     * @var bool
     */
    protected $modified = true;
    /**
     * @var string $uidCcsd identifiant ccsd de l'utilisateur
     */
    protected $uidCcsd;

    /** @var \Ccsd_User_Models_User */
    protected $ccsdUser;

    /** @var boolean */

    protected $valid;

    /**
     * @var string $uid unique identifier from identity provider
     */

    protected $uid;


    /**
     * @var string $federationName federation's name
     */
    protected $federationName;

    /**
     * @var string $federationId unique identifier for identity provider
     */

    protected $federationId;

    /**
     * @var string $firstName first name
     */

    protected $firstName;

    /**
     * @var string  $lastName last name
     */
    protected $lastName;

    /**
     * @var string $email email
     */

    protected $email;

    /**
     * database's default adapter
     *
     * @var \Zend_Db_Adapter_Pdo_Mysql
     */
    protected $_db = null;

    /**
     * @var string $ASSO_TABLE nom de la table en BDD où sont stockées les informations
     */
    protected static $ASSO_TABLE = 'USER_ID_ASSOCIATION';


    /**
     * Ccsd_Auth_Asso constructor.
     * @param $uid string idp user's id
     * @param $federationName string federation name
     * @param $federationId string idp unique id
     * @param $uidCcsd int unique ID user hal
     * @param $lastName string user's last name
     * @param $firstName string user's first name
     * @param $email string user's email
     * @param bool $valid
     */
    public function __construct($uid, $federationName, $federationId, $uidCcsd, $lastName, $firstName, $email, $valid  = true)
    {
        $this->setUid($uid);
        $this->setFederationName($federationName);
        $this->setFederationId($federationId);
        $this->setUidCcsd($uidCcsd);
        $this->setLastName($lastName);
        $this->setFirstName($firstName);
        $this->setEmail($email);
        $this->setValid($valid);

    }

    /**
     * @return array
     */
    private function toArray() {
        $bind = [
            'uid'=> $this->getUid(),
            'federation'=> $this->getFederationName() ,
            'id_federation'=> $this->getFederationId() ,
            'uidCcsd'=> $this->getUidCcsd() ,
            'nom'=> $this->getLastName(),
            'prenom'=> $this->getFirstname(),
            'email'=> $this->getEmail(),
            'valid'=> 0
        ];
        return $bind;
    }

    /**
     * @param array $row
     * @param bool $valid
     * @return \Ccsd\Auth\Asso
     */

    protected static function array2obj($row, $valid = true) {
        return new static($row['uid'], $row['federation'],$row['id_federation'],$row['uidCcsd'],$row['nom'],$row['prenom'],$row['email'], $valid);
    }

    /**
     * @return bool
     */
    public function valid() {
        return true;
    }

    /**
     * @param bool $valid
     */
    public function setValid($valid)
    {
        $this->valid=$valid;
    }

    /**
     * fonction d'insertion en base
     * @throws Asso\Exception
     * @throws \Zend_Db_Adapter_Exception
     * @return boolean
     */
    public function save()
    {
        if (! $this -> valid()) {
            throw new Asso\Exception("Association is not valid , can't save it!");
        }
        if ($this ->modified) {
            $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
            $bind = $this->toArray();
            return $db->insert(self::$ASSO_TABLE, $bind);
        }
        return true;
    }

    /**
     * @param $federationId string
     * @param $federationName string
     * @param $uid string
     * @return \Ccsd\Auth\Asso object if exists, null either
     */
    public static function exists($federationId, $federationName, $uid) {
        return static::load($federationId, $federationName, $uid);
    }


    /**
     * @param $federationId
     * @param $federationName
     * @param $uid
     * @return \Ccsd\Auth\Asso object if exists, null either
     */
    public static function load($federationId, $federationName, $uid) {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(self::$ASSO_TABLE)->where('UID = ?', $uid)
            -> where('FEDERATION = ?', $federationName)
            -> where('ID_FEDERATION = ?',$federationId);

        $row = $db->fetchRow($select);
        if ($row) {
            // Par construction de laase: unique
            /** @var \Ccsd\Auth\Asso $obj */
            $obj = static::array2obj($row, false);
            $obj ->modified = false;
            return $obj;
        } else {
            return null;
        }
    }

    private function setModified() {
        $this -> modified = true;
    }


    /**
     * @return \Ccsd_User_Models_User|null
     */
    public function getUser() {
        if ($this->ccsdUser ===null) {
            $halId = $this->getUidCcsd();

            $this->ccsdUser = \Hal_User::createUser($halId);
        }
        return $this -> ccsdUser;
    }
    // public static function findByEmail(): Ccsd_Auth_Adapter_Asso[]

    /**
     * Setter de l'uid
     * @param $uid
     */
    private function setUid($uid)
    {
        if ($this->uid != $uid) {
            $this->uid = $uid;
            $this->setModified();
        }
    }

    /**
     * Getter de l'uid
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * Setter de l'uidCcsd
     * @param  int $uidCcsd
     */
    private function setUidCcsd($uidCcsd)
    {
        $this->uidCcsd = $uidCcsd;
    }

    /**
     * Getter de l'uidCcsd
     * @return string
     */
    public function getUidCcsd()
    {
        return $this->uidCcsd;
    }

    /**
     * Setter federation
     * @param $federationName
     */
    private function setFederationName($federationName)
    {
        $this->federationName=$federationName;
    }

    /**
     * Getter federation
     * @return string
     */
    public function getFederationName()
    {
        return $this->federationName;
    }

    /**
     * Setter IDP
     * @param $federationId
     */
    private function setFederationId($federationId)
    {
        $this->federationId = $federationId;
    }

    /**
     * Getter IDP
     * @return string
     */
    public function getFederationId()
    {
        return $this->federationId;
    }

    /**
     * Setter firstName
     * @param $firstName
     */
    private function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    }

    /**
     * Getter firstName
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Setter du Nom de famille
     * @param $lastName
     */
    private function setLastName($lastName)
    {
        $this->lastName = $lastName;
    }

    /**
     * Getter du nom de famille
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Setter de l'email
     * @param $email
     */
    private function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Getter de l'email
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

}