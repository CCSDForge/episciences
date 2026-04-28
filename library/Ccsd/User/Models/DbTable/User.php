<?php

/**
 * Modèle pour la table des utilisateurs CCSD
 * @author rtournoy
 *
 */
class Ccsd_User_Models_DbTable_User extends Zend_Db_Table_Abstract {

    protected $_name = 'T_UTILISATEURS';
    protected $_primary = 'UID';

    /**
     * Ccsd_User_Models_DbTable_User constructor.
     * @param array|false|string $env
     */
    public function __construct() {
        parent::__construct();
        $this->_setAdapter(Ccsd_Db_Adapter_Cas::getAdapter());
    }

    /**
     * @param $q
     * @param int $limit
     * @param bool $valid
     * @return array
     */
    public function search($q, $limit = 100, $valid = false) {
        $q = trim($q);
        $sql = $this->select()->from(['U' => $this->_name], ['UID', 'USERNAME', 'EMAIL', 'CIV', 'LASTNAME', 'FIRSTNAME', 'MIDDLENAME', 'TIME_REGISTERED', 'TIME_MODIFIED', 'VALID']);
        if (is_numeric($q)) {
            $sql->where('U.UID = ?', $q);
            $sql->limit(1);
        } else {

            $finalQuery = '%' . $q . '%';

            if (str_contains($q, ' ')) {
                //Recherche nom prenom + prenom nom
                $sql->where("(CONCAT_WS(' ', FIRSTNAME, LASTNAME) LIKE ? OR CONCAT_WS(' ', LASTNAME, FIRSTNAME) LIKE ? )", $finalQuery);
            } else {
                $sql->where('(LASTNAME LIKE ? OR USERNAME LIKE ? OR FIRSTNAME LIKE ? OR EMAIL LIKE ? )', $finalQuery);
            }
            $sql->order(['LASTNAME ASC', 'FIRSTNAME ASC', 'EMAIL ASC'])->limit($limit);
        }
        if ($valid) {
            $sql->where('VALID = 1');
        }
        return $this->fetchAll($sql)->toArray();
    }

    /**
     * Retourne la liste des UID associés à une adresse mail
     * @deprecated : cette fonction devrait rendre l'ensenmble des champs de la table voire, mieux un objet user
     *               utiliser cette fonction qui ne rends que des uid est contre productif!
     * @param $email
     * @return array
     */
    public function getUidByEmail($email)
    {
        $db = $this->getAdapter();
        $sql = $db->select()->from($this->_name, 'UID')->where('EMAIL = ?', $email);
        return $db->fetchCol($sql);
    }

    /**
     * Retourne le dernier compte Ccsd crée et valide en fonction de l'adresse email
     *
     * @param $email string
     * @param $triDate string
     * @param $triValid string
     * @param bool $nullIFmoreThanOne
     *
     *@return Ccsd_User_Models_User | NULL
     *
     */
    public function selectAccountByEmail($email,$triDate = 'DESC',$triValid = 'DESC', $nullIFmoreThanOne = false)
    {
        if (($triDate != 'DESC') || ($triDate != 'ASC')) {
            $triDate = 'DESC';
        }
        if (($triValid != 'DESC') || ($triValid != 'ASC')) {
            $triValid = 'DESC';
        }

        $db = $this->getAdapter();

        $sql = $db->select()->from($this->_name, ['UID', 'USERNAME', 'EMAIL', 'CIV', 'LASTNAME', 'FIRSTNAME', 'MIDDLENAME', 'TIME_REGISTERED', 'TIME_MODIFIED', 'VALID'])
            ->where('EMAIL = ?', $email)->order([ "VALID $triValid", "TIME_REGISTERED $triDate" ]);

        if (! $nullIFmoreThanOne) {
            $sql->limit(1);
        }
        $res = $db->fetchAll($sql);
        if ($nullIFmoreThanOne && (count($res) > 1)) {
            return null;
        }
        $array =  $res[0];   /* user datas */
        if ($array) {
            return new Ccsd_User_Models_User($array);
        } else {
            return NULL;
        }
    }
}
