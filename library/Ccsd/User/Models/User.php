<?php

/**
 * Gestion des utilisateurs CCSD
 * @author rtournoy
 */
class Ccsd_User_Models_User

{

    /**
     *
     * @var int taille en px
     */
    const IMG_SIZE_THUMB = 16;

    /**
     *
     * @var int taille en px
     */
    const IMG_SIZE_NORMAL = 128;

    /**
     *
     * @var int taille en px
     */
    const IMG_SIZE_LARGE = 384;

    /**
     *
     * @var string préfixe de nom d'image
     */
    const IMG_PREFIX_THUMB = 'thumb_';

    /**
     * @const string
     */
    const IMG_NAME_THUMB = 'thumb';

    /**
     * @const string
     */
    const IMG_NAME_LARGE = 'large';


    /**
     * @const string
     */
    const IMG_NAME_NORMAL = 'normal';

    /**
     *
     * @var string préfixe de nom d'image
     */
    const IMG_PREFIX_NORMAL = '';

    /**
     *
     * @var string préfixe de nom d'image
     */
    const IMG_PREFIX_LARGE = 'large_';

    /**
     * Longueur du répertoire où sont stockés les images des comptes utilisateur
     * @var int
     *
     */
    const USER_PHOTO_PATH_LENGHT = 10;

    /**
     *
     * @var string // chemin vers le répertoire FTP des utilisateurs
     */
    const CCSD_FTP_PATH = '/ftp/';

    const PASSWORD_HASH_SIZE = 128; // sha512
    const INVALID_TOKEN = "Le jeton n'est pas valide";

    const ACCOUNT_INVALID_USERNAME = "Le nom d'utilisateur n'est pas valide";

    const ACCOUNT_VALID_USERNAME = "Le nom d'utilisateur est valide";

    const ACCOUNT_CREATE_FAILURE = 'Échec de la création du compte';

    const ACCOUNT_CREATE_SUCCESS = 'Succès de la création du compte';

    const ACCOUNT_EDIT_FAILURE = 'Échec de la modification du compte';

    const ACCOUNT_EDIT_SUCCESS = 'Succès de la modification du compte';

    const LOGOUT_SUCCESS = 'Déconnexion réussie';

    const LOGOUT_FAILURE = 'Échec de la déconnexion';

    const ACCOUNT_LOST_LOGIN_FOUND = 'Login trouvé';

    const ACCOUNT_LOST_LOGIN_NOT_FOUND = 'Login inconnu';

    const ACCOUNT_RESET_PASSWORD_SUCCESS = 'Changement de mot de passe réussi';

    const ACCOUNT_RESET_PASSWORD_FAILURE = 'Échec du changement de mot de passe';

    /**
     * UID unique de l'utilisateur dans la table des utilisateurs
     *
     * @var integer
     */
    protected $_uid;

    /**
     * Nom d'utilisateur / login
     *
     * @var string
     */
    protected $_username;

    /**
     * Mot de passe de l'utilisateur
     *
     * @var string
     */
    protected $_password;

    /**
     * E-mail de l'utilisateur
     *
     * @var string
     */
    protected $_email;

    /**
     * Civilité de l'utilisateur
     *
     * @var string
     */
    protected $_civ;

    /**
     * Nom de famille de l'utilisateur
     *
     * @var string
     */
    protected $_lastname;

    /**
     * Prénom de l'utilisateur
     *
     * @var string
     */
    protected $_firstname;

    /**
     * Complément de nom de l'utilisateur
     *
     * @var string
     */
    protected $_middlename;

    /**
     * Timestamp de création du compte de l'utilisateur
     *
     * @var string|integer  // timestamp
     */
    protected $_time_registered;

    /**
     * Timestamp de modification du compte de l'utilisateur
     *
     * @var string|integer // timestamp
     */
    protected $_time_modified;

    /**
     * Chemin complet vers le répertoire FTP de l'utilisateur sous la forme
     * "/ftp/$_uid"
     *
     * @var string
     */
    protected $_ftp_home;

    /**
     * Définit la validité d'un compte utilisateur (0 ou 1)
     *
     * @var integer
     */
    protected $_valid;

    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $key = strtolower($key); // les noms de champs sont en majuscules
            // dans la BDD
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }

        return $this;
    }

    /**
     * Pour savoir si l'utilisateur a une photo
     *
     * @param int $uid
     * @return boolean
     */
    public static function hasPhoto($uid = null)
    {
        $u = new Ccsd_User_Models_User(array(
            'uid' => $uid
        ));

        $hasPhoto = $u->getPhotoPathName();

        if (!$hasPhoto) {
            return false;
        }

        return true;
    }

    /**
     * Retourne le chemin complet vers l'image de l'utilisateur avec le format
     * en paramètre
     *
     * @param string $size
     * @return boolean string
     */
    public function getPhotoPathName($size = self::IMG_NAME_THUMB)
    {
        $photoPath = $this->getPhotoPath();
        $photoPath .= '/';

        switch ($size) {
            case self::IMG_NAME_THUMB:
                $photoPath .= self::IMG_PREFIX_THUMB . $this->getUid() . '.jpg';
                break;
            case self::IMG_NAME_LARGE:
                $photoPath .= self::IMG_PREFIX_LARGE . $this->getUid() . '.jpg';
                break;
            default:
                $photoPath .= self::IMG_PREFIX_NORMAL . $this->getUid() . '.jpg';
                break;
        }

        if (!is_readable($photoPath)) {
            return false;
        }

        return $photoPath;
    }

    /**
     * Retourne le chemin vers le répertoire des images d'un utilisateur
     *
     * @return string
     */
    public function getPhotoPath()
    {
        return Ccsd_File::slicedPathFromString($this->getUid(), EPISCIENCES_USER_PHOTO_PATH . '/', self::USER_PHOTO_PATH_LENGHT, 2, '0');
    }

    /**
     *
     * @return int $_uid
     */
    public function getUid()
    {
        return $this->_uid;
    }

    /**
     * @param int $_uid
     * @return $this
     */
    public function setUid($_uid)
    {
        if ($_uid == '') {
            $this->_uid = null;
            return $this;
        }

        $this->_uid = (int)filter_var($_uid, FILTER_SANITIZE_NUMBER_INT);

        if ($this->_uid <= 0) {
            throw new InvalidArgumentException('Le UID utilisateur doit être supérieur à 0.');
        }

        return $this;
    }

    /**
     *
     * @return string $_username
     */
    public function getUsername()
    {
        return $this->_username;
    }

    /**
     * @param string $_username
     * @return $this
     */
    public function setUsername($_username)
    {
        $this->_username = filter_var($_username, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $this;
    }

    /**
     *
     * @return string $_password
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /**
     * @param string $_password
     * @return $this
     */
    public function setPassword($_password)
    {
        if ($_password == '') {
            $this->_password = null;
            return $this;
        }

        $hashedPassword = $this->hashUserPassword($_password);

        if (strlen($hashedPassword) != self::PASSWORD_HASH_SIZE) {
            throw new LengthException(sprintf("La longueur doit être de %u caractères.", self::PASSWORD_HASH_SIZE));
        }

        $this->_password = $hashedPassword;

        return $this;
    }

    /**
     * Génère un hash du mot de passe utilisateur
     *
     * @param string $password
     * @return string
     */
    private function hashUserPassword($password)
    {
        return hash('sha512', $password);
    }

    /**
     *
     * @return string $_email
     */
    public function getEmail()
    {
        return $this->_email;
    }

    /**
     *
     * @param string $_email
     * @return $this
     */
    public function setEmail($_email)
    {
        $this->_email = filter_var($_email, FILTER_SANITIZE_EMAIL);
        return $this;
    }

    /**
     *
     * @return string $_civ
     */
    public function getCiv()
    {
        return $this->_civ;
    }

    /**
     *
     * @param string $_civ
     * @return $this
     */
    public function setCiv($_civ)
    {
        $this->_civ = filter_var($_civ, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $this;
    }

    /**
     *
     * @return string $_middlename
     */
    public function getMiddlename()
    {
        return $this->_middlename;
    }

    /**
     * @param string $_middlename
     * @return $this
     */
    public function setMiddlename($_middlename)
    {
        $this->_middlename = filter_var($_middlename, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $this;
    }

    /**
     *
     * @return string|integer $_time_registered
     */
    public function getTime_registered()
    {
        return $this->_time_registered;
    }

    /**
     * Fixe l'heure de création d'un compte utilisateur
     * @param null|int|string $_time_registered default = date("Y-m-d H:i:s")
     * @return $this
     */
    public function setTime_registered($_time_registered = null)
    {
        if ($_time_registered == null) {
            $_time_registered = date("Y-m-d H:i:s");
        }
        $this->_time_registered = $_time_registered;
        return $this;
    }

    /**
     *
     * @return string|integer $_time_modified
     */
    public function getTime_modified()
    {
        return $this->_time_modified;
    }

    /**
     * Fixe l'heure de modification d'un compte utilisateur
     * @param null|int|string $_time_modified default = date("Y-m-d H:i:s")
     * @return $this
     */
    public function setTime_modified($_time_modified = null)
    {
        if ($_time_modified == null) {
            $_time_modified = date("Y-m-d H:i:s");
        }
        $this->_time_modified = $_time_modified;
        return $this;
    }

    /**
     *
     * @return integer $_valid
     */
    public function getValid()
    {
        return $this->_valid;
    }

    /**
     * Fixe la validité d'un compte utilisateur (0 ou 1)
     *
     * @param integer $_valid
     * @return $this
     */
    public function setValid($_valid)
    {
        $this->_valid = $_valid;

        if (($this->_valid != 0) && ($this->_valid != 1)) {
            throw new InvalidArgumentException('La valeur ne peut être que 0 ou 1');
        }

        return $this;
    }

    public function getFullName()
    {
        return Ccsd_Tools::formatUser($this->getFirstname(), $this->getLastname());
    }

    /**
     *
     * @return string $_firstname
     */
    public function getFirstname()
    {
        return $this->_firstname;
    }

    /**
     * @param string $_firstname
     * @return $this
     */
    public function setFirstname($_firstname)
    {
        $this->_firstname = filter_var($_firstname, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $this;
    }

    /**
     *
     * @return string $_lastname
     */
    public function getLastname()
    {
        return $this->_lastname;
    }

    /**
     *
     * @param string $_lastname
     * @return $this
     */
    public function setLastname($_lastname)
    {
        $this->_lastname = filter_var($_lastname, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
        return $this;
    }

    public function toArray()
    {
        $res = array();
        $fields = array(
            'uid',
            'username',
            'civ',
            'lastname',
            'firstname',
            'middlename',
            'email',
            'time_registered',
            'time_modified',
            'ftp_home'
        );

        foreach ($fields as $key) {
            $method = 'get' . ucfirst($key);
            if (method_exists($this, $method)) {
                $res[$key] = $this->$method();
            }
        }
        return $res;
    }

    public function save($forceInsert = false)
    {
        $userMapper = new Ccsd_User_Models_UserMapper();
        return $userMapper->save($this, $forceInsert);
    }

    /**
     * Enregistre la photo et la converti en jpg
     *
     * @param string $photoFileName
     * @throws Exception
     */
    public function savePhoto($photoFileName)
    {
        $userPhotoPath = $this->getPhotoPath();

        if (!is_dir($userPhotoPath)) {

            $mkdirResult = mkdir($userPhotoPath, 0777, true);
            if (!$mkdirResult) {
                throw new Exception("Le répertoire de stockage n'a pas pu être créé.");
            }
        }

        if (Ccsd_File::canConvertImg($photoFileName) !== true) {
            throw new Exception(htmlspecialchars($photoFileName) . " : Création de miniature : ce type de fichier n'est pas accepté.");
        }

        $userPhotoPath .= '/';

        $resThumb = Ccsd_File::convertImg($photoFileName, $userPhotoPath, self::IMG_SIZE_THUMB, self::IMG_SIZE_THUMB, self::IMG_PREFIX_THUMB . $this->getUid());

        if (!$resThumb) {
            throw new Exception("Échec de création de la taille IMG_SIZE_THUMB.");
        }

        $resUser = Ccsd_File::convertImg($photoFileName, $userPhotoPath, self::IMG_SIZE_NORMAL, self::IMG_SIZE_NORMAL, self::IMG_PREFIX_NORMAL . $this->getUid());
        if (!$resUser) {
            throw new Exception("Échec de création de la taille IMG_SIZE_NORMAL.");
        }

        $resLarge = Ccsd_File::convertImg($photoFileName, $userPhotoPath, self::IMG_SIZE_LARGE, self::IMG_SIZE_LARGE, self::IMG_PREFIX_LARGE . $this->getUid());
        if (!$resLarge) {
            throw new Exception("Échec de création de la taille IMG_SIZE_LARGE.");
        }
    }

    /**
     * Supprime les différentes tailles de photo d'un utilisateur
     *
     * @return array tableau du résultat pour chaque type
     */
    public function deletePhoto()
    {
        $res = array();
        $typesToRm = array(
            self::IMG_NAME_THUMB,
            self::IMG_NAME_NORMAL,
            self::IMG_NAME_LARGE
        );

        foreach ($typesToRm as $type) {

            $res[$type] = unlink($this->getPhotoPathName($type));
        }
        return $res;
    }

    /**
     *
     * @return string $_ftp_home
     */
    public function getFtp_home()
    {
        return $this->_ftp_home;
    }

    /**
     *
     * @param string $_ftp_home
     * @return $this
     */
    public function setFtp_home($_ftp_home = null)
    {
        if (null == $_ftp_home) {
            $_ftp_home = self::CCSD_FTP_PATH . $this->getUid();
        }

        $this->_ftp_home = $_ftp_home;
        return $this;
    }

}
