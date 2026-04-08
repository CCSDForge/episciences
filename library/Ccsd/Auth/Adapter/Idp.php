<?php

/**
 * Adapter Zend_Auth pour l'authentification via Fédération d'identité
 *
 * @author ccsd
 *
 */

namespace Ccsd\Auth\Adapter;
/**
 * Class Idp
 * @package Ccsd\Auth\Adapter
 */
class Idp implements AdapterInterface {
    const AdapterName = 'IDP';
    /**
     * @var $auth \SimpleSaml\Auth\Simple authentication's object
     */
    protected $auth ;
    /**
     * @var $authority string federation identifier
     */
    protected $authority = null;
    /**
     * @var $uid_idp string  ldap identifier
     */
    protected $uid_idp = null ;
    /**
     * @var $forceCreate bool behaviour flag
     */
    protected $forceCreate = false;
    /**
     * @var \Ccsd_User_Models_User
     */
    protected $_identityStructure = null;

    /**
     * @var string
     */
    protected $returnUrl = '';

    /**
     * before authentication
     * @param $controller \Zend_Controller_Action
     * @return bool
     */
    public function pre_auth($controller)
    {
        /** @var \Zend_Controller_Request_Http $request */
        $request = $controller->getRequest();
        $params = $request->getParams();
        $localUri = $request->getHttpHost();
        $redirect = $this->getRedirection($params,$localUri);
        $paramFromIDP = $request->getParam('FI', false);
        $url = array_key_exists('url', $params) ? $params['url'] : '';

        $returnTo = HAL_URL . '/user/login?authType=IDP&FI=1&url='  . $url;
        $this->returnUrl = $returnTo;
        if ($redirect !==NULL) {
            if ($paramFromIDP) {
                // retour d'authentification, il faut le passe au portail si necessaire
                // Avec le cookie SimpleSAML
                $SimpleSAMLCookie= $request->getCookie('SimpleSAML');
                $SimpleSAMLAuthTokenCookie = $request->getCookie('SimpleSAMLAuthToken');
                $redirect .= "&S=$SimpleSAMLCookie";
                $redirect .= "&ST=$SimpleSAMLAuthTokenCookie";
                $controller->redirect($redirect);
                return false;
            }
        }
        if ($st = $request ->getParam('ST', false)) {
            $SimpleSAMLCookie = $request ->getParam('S', false);
            setcookie('SimpleSAML', $SimpleSAMLCookie);
            setcookie('SimpleSAMLAuthToken', $st);
        }

        if ($st && !$request->getCookie('SimpleSAML')) {
            // Les cookies ne sont pas positionne au premier chargement... on recharge
            // TODO: ce serait bien de trouver mieux...
            $controller->redirect($request->getRequestUri());
            return false;
        }

        if ($this->authority !== null){
            $this->auth = new \SimpleSAML\Auth\Simple($this->authority);
        }
        else {
            $this->auth = new \SimpleSAML\Auth\Simple('renater');
        }

        if (isset($params['forceCreate']) && $params['forceCreate']){
            $this->setForceCreate();
        }
        return true;
    }

    /**
     * fonction permettant de flagger la creation forcée de compte
     */
    public function setForceCreate(){
        $this->forceCreate = true;
    }

    /**
     * @param $params
     * @param $localUri
     * @return string|NULL
     */
    private function getRedirection($params, $localUri){
        $url = $params['url'];

        $hostPortail  = parse_url(\Hal_Site::getCurrentPortail()->getUrl(), PHP_URL_HOST); //Portail Local
        $hostSouhaite = parse_url($url, PHP_URL_HOST); //Portal of redirection

        if (APPLICATION_ENV==='development'){
            $hostPortail = parse_url($url,PHP_URL_HOST);
        }

        if (($hostPortail == $hostSouhaite) || ($hostSouhaite === null)) {
            // On est sur le bon host
            return NULL;
        }

        // Pas sur le host de depart, redirection vers le portail souhaite
        $urlScheme = parse_url($url, PHP_URL_SCHEME); //Protocol de redirection
        return ''.$urlScheme. '://'. $hostSouhaite. '/user/login2?authType=IDP&url='.$url;

    }
    /**
     * @param array $params
     * @return string
     */
    public function getPage($params){

        return $params['url'];
    }
    /**
     * fonction d'authentification
     */

    public function authenticate()
    {
        $options =[];
        if ($this->returnUrl != '') {
            $options ['ReturnTo'] = $this->returnUrl;
        }
        if ($this->uid_idp !== null) {
            $options['saml:idp']=$this->uid_idp;
            // Le @ car SimpleSaml fait un class_exists sur une classe inexistant
        }
        @$this->auth->requireAuth($options);

        if ($this->auth->isAuthenticated()) {
            return new \Zend_Auth_Result(\Zend_Auth_Result::SUCCESS, new \Ccsd_User_Models_User(), array());
        } else {
            return new \Zend_Auth_Result(\Zend_Auth_Result::FAILURE, new \Ccsd_User_Models_User(), array("Echec de l'authentification depuis la Fédération"));
        }
    }
    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le postérieur à l'authentification
     * @param $controller \Zend_Controller_Action
     * @param \Zend_Auth_Result $authinfo
     * @return \ArrayAccess
     * @throws \Exception
     */

    public function post_auth($controller, $authinfo){
        $authority = $this->auth->getAuthData('Authority');
        $uid_idp = $this->auth->getAuthData('saml:sp:IdP');
        $attributes = $this->auth->getAttributes();
        $session =\SimpleSAML\Session::getSessionFromRequest();
        $session->cleanup();
        $request = $controller->getRequest();
        $params = $request->getParams();
        $localUri = $request->getHttpHost();
        $redirect = $this->getRedirection($params,$localUri);

        if ($redirect !==NULL) {
            // retour d'authentification pour un portail, il faut le passe au portail si necessaire
            // Avec le cookie SimpleSAML
            $SimpleSAMLCookie= $request->getCookie('SimpleSAML');
            $SimpleSAMLAuthTokenCookie = $request->getCookie('SimpleSAMLAuthToken');
            $redirect .= "&S=$SimpleSAMLCookie";
            $redirect .= "&ST=$SimpleSAMLAuthTokenCookie";
            $controller->redirect($redirect);
            return null;
        }
        // on crée un tableau qui pourra être traité dans pre_login et qui contient toutes les informations utiles
        return new \ArrayObject(['type'=>'IDP',
                'authority'=>$authority,
                'uid_idp'=>$uid_idp,
                'attributes'=>$attributes
        ]);

    }

    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le préalable à l'identification et instanciation de l'utilisateur
     *
     * @param \ArrayAccess $array_attr of attribute
     * @return \Ccsd_User_Models_User | false
     * @throws \Ccsd\Auth\Asso\Exception
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */

    public function pre_login($array_attr)
    {
        $idPValue = self::adaptCreateValueFromIdp($array_attr['attributes']);
        $idpLogin = self::getIdpLogin($array_attr['attributes']);
        $asso = \Ccsd\Auth\Asso\Idp::exists($array_attr['uid_idp'], $array_attr['authority'],$idpLogin);

        $user = false;
        if ($asso === NULL) {

            //verification si l'email existe en base
            if (IDP_ASSO_AUTO || $this->forceCreate) {
                $refUser = new \Ccsd_User_Models_DbTable_User();
                $user = $refUser->selectAccountByEmail($idPValue['EMAIL'], 'DESC', 'DESC', true);
            }

            if ($user !== false) {
                $asso = new \Ccsd\Auth\Asso\Idp($idpLogin,$array_attr['authority'],$array_attr['uid_idp'],$user->getUid(),$idPValue['LASTNAME'],$idPValue['FIRSTNAME'],$idPValue['EMAIL']);
                $asso->save();
            }
        }
        else if ($asso !== NULL ){
            $user = new \Ccsd_User_Models_User();
            $refUser = new \Ccsd_User_Models_UserMapper();
            $refUser->find($asso->getuidCcsd(),$user);
        }

        return $user;
    }
    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * fonction alternative de login permettant d'enregistré l'association entre deux comptes
     * @param \Ccsd_User_Models_User $loginUser
     * @param string[][] $array_attr
     * @throws \Ccsd\Auth\Asso\Exception
     * @throws \Zend_Db_Adapter_Exception
     */
    public function alt_login($loginUser,$array_attr)
    {
        $idPValue = self::adaptCreateValueFromIdp($array_attr['attributes']);
        $idpLogin = self::getIdpLogin($array_attr['attributes']);
        $asso = new \Ccsd\Auth\Asso\Idp($idpLogin,$array_attr['authority'],$array_attr['uid_idp'],$loginUser->getUid(),$idPValue['LASTNAME'],$idPValue['FIRSTNAME'],$idPValue['EMAIL']);
        $asso->save();
    }
    /**
     * fonction héritée de Ccsd_Auth_Adapter
     * organise le postérieur à l'identification et instanciation de l'utilisateur
     * @param \Ccsd_User_Models_User $loginUser
     * @param string[][] $array_attr
     * @throws \Ccsd\Auth\Asso\Exception
     * @throws \Zend_Db_Adapter_Exception
     */
    public function post_login($loginUser,$array_attr)
    {
        if ($array_attr['loginUser'] === NULL ){
            $idPValue = self::adaptCreateValueFromIdp($array_attr['attributes']);
            $idpLogin = self::getIdpLogin($array_attr['attributes']);
            $asso = new \Ccsd\Auth\Asso\Idp($idpLogin,$array_attr['authority'],$array_attr['uid_idp'],$loginUser->getUid(),$idPValue['LASTNAME'],$idPValue['FIRSTNAME'],$idPValue['EMAIL']);
            $asso->save();
        }
    }

    /**
     * TODO: On devrait avoir un Idp\User avec une interface (ou heritant de Adapter\User getLogin, getMail, getDisplayName, getLastname...
     * Retourne la correspondance entre les champs de l'identity provider et les champs de creation du formulaire
     * @param array attributes
     * @return array tableau formaté de création d'utilisateur
     */
    public static function adaptCreateValueFromIdp($attributes){

        return array(
            'USERNAME'  => $attributes['mail'][0],
            'FULLNAME'  => $attributes['displayName'][0],
            'EMAIL'     => $attributes['mail'][0],
            'LASTNAME'  => $attributes['sn'][0],
            'FIRSTNAME' => $attributes['givenName'][0]
        );
    }

    /**
     * @param array $attributes
     * @return string
     */
    public static function getIdpLogin($attributes) {
        return $attributes['uid'][0];
    }

    /**
     * @param $params
     */
    public function logout($params) {
        $succesAuth = $params[0];
        $user = $params[1];
        if (array_key_exists('authority', $succesAuth)) {
            $this->auth = new \SimpleSAML\Auth\Simple($succesAuth['authority']);
            $this->auth->logout();
        } else {
            // Logout impossible...
            $msg = 'IDP Logout: pas de federation: [';
            foreach ($succesAuth as $k => $v) {
                $msg .= "$k => $v,";
            }
            $msg .= ']';
            \Ccsd_Tools::panicMsg(__FILE__,__LINE__, $msg);
        }
    }
    /**
     * Initialisation de la structure de l'identité utilisateur
     *
     * @param $identity
     */
    public function  setIdentityStructure($identity) {
        // Par compat, on met la structure dans identity aussi
        $this->_identityStructure = $identity;
    }

    /**
     * @return \Ccsd_User_Models_User
     */
    public function  getIdentityStructure() {
        return $this->_identityStructure;
    }
    /**
     * @param \ArrayAccess $attr
     * @return string
     */
    public function toHtml($attr) {
        return self::AdapterName . ' Federation : <b>' .$attr['uid_idp'] . '</b> Institution : <b>'  . $attr['authority'].'</b>';
    }

    /**
     * fonction permettant de forcer la creation d'un compte utilisateur
     * à partir des informations du fournisseur d'identité
     * @param array $array_attr tableau d'informations fournies par le fournisseur d'identité
     * @param boolean $forceCreate
     * @return \Ccsd_User_Models_User | boolean
     * @throws \Ccsd\Auth\Asso\Exception
     * @throws \Zend_Db_Adapter_Exception
     */
    public function createUserFromAdapter($array_attr,$forceCreate)
    {
        $idPValue = self::adaptCreateValueFromIdp($array_attr['attributes']);
        if ($forceCreate || (IDP_CREATE_AUTO && $this->filterEmail($idPValue['EMAIL']))) {

            $user = new \Ccsd_User_Models_User($idPValue);
            $user->setValid(1); // compte valide par défaut
            $user->setUid(null);
            $user->setTime_registered();
            $user->setPassword(\Ccsd_Tools::generatePw());
            $uid = $user->save();
            $user->setUid($uid);
            $idpLogin = self::getIdpLogin($array_attr['attributes']);
            $asso = new \Ccsd\Auth\Asso\Idp($idpLogin, $array_attr['authority'], $array_attr['uid_idp'], $uid, $idPValue['LASTNAME'], $idPValue['FIRSTNAME'], $idPValue['EMAIL']);
            $asso->save();

            return $user;
        }
        // cas où rien ne doit être créer.
        return false;

    }
    /**
     * fonction de filtrage d'email
     * @param string $email email à faire passer par le filtre de reconnaissance
     * @return boolean
     */
    public function filterEmail($email) : bool
    {
        $emailFilters = ['@inra.fr', '@irstea.fr', '@inrae.fr'];

        foreach ($emailFilters as $emailFilter) {
            if (preg_match('/' . preg_quote($emailFilter, '/') . '$/', $email)) {
                return true;
            }
        }
        return false;
    }
}
