<?php

// Gestion des tokens utilisateurs d'Episciences
class Episciences_User_Token
{
	const USAGE_REVIEWER_INVITATION = 'REVIEWER_INVITATION';
	
    const TOKEN_STRING_LENGTH = 40;

    /**
     * @var int
     */
	protected $_uid;

    // E-mail de l'utilisateur
    protected $_email;

    // Token de l'utilisateur
    protected $_token;

    // Date de dernière modification du Token
	protected $_time_modified;

    // Usage pour lequel le token a été créé
    protected $_usage;

    public function __construct (array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions (array $options)
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
     * Génère un jeton unique qui sert
     * - 1 pour accéder au formulaire de réponse à une invitation;
     */
    public function generateUserToken ()
    {
        $this->setToken(sha1(time() . uniqid(mt_rand(), true)));
    }
    
    /**
     *
     * @return the $_uid
     */
    public function getUid ()
    {
        return $this->_uid;
    }

    /**
     *
     * @return the $_email
     */
    public function getEmail ()
    {
        return $this->_email;
    }

    /**
     *
     * @return the $_token
     */
    public function getToken ()
    {
        return $this->_token;
    }

    /**
     *
     * @return the $_time_modified
     */
    public function getTime_modified ()
    {
        return $this->_time_modified;
    }

    /**
     *
     * @param field_type $_uid
     */
    public function setUid ($_uid)
    {
        if ($_uid == '') {
            $this->_uid = null;
            return $this;
        }

        $this->_uid = filter_var($_uid, FILTER_SANITIZE_NUMBER_INT);

        if ($this->_uid <= 0) {
            throw new InvalidArgumentException(
                    'Le UID utilisateur doit être supérieur à 0.');
        }

        return $this;
    }

    /**
     *
     * @param field_type $_email
     */
    public function setEmail ($_email)
    {
        $this->_email = filter_var($_email, FILTER_SANITIZE_EMAIL);
        return $this;
    }

    /**
     *
     * @param string $_token
     */
    public function setToken ($_token)
    {

        $_token = filter_var($_token, FILTER_DEFAULT);


        if (strlen($_token) != self::TOKEN_STRING_LENGTH ) {
            throw new InvalidArgumentException("Le jeton n'est pas valide");
        }

        $this->_token = $_token;
        return $this;
    }

    /**
     *
     * @param field_type $_time_modified
     */
    public function setTime_modified ($_time_modified)
    {
        $this->_time_modified = $_time_modified;
        return $this;
    }

    /**
     *
     * @return the $_usage
     */
    public function getUsage ()
    {
        return $this->_usage;
    }

    /**
     * Fixe le type d'usage pour lequel le jeton a été prévu
     *
     * @param string $_usage
     */
    public function setUsage ($_usage)
    {
        $this->_usage = filter_var($_usage, FILTER_DEFAULT);
        return $this;
    }
}

