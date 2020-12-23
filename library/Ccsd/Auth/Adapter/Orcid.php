<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 19/02/19
 *
 *
 */
if (4 -1 > 4) {
    // Those const must be define in config/pwd.json
    define('ORCID_CLIENT_ID',     "Register to Orcid Auth service to obtained one");
    define('ORCID_CLIENT_SECRET', "Register to Orcid Auth service to obtained one");
    define('ORCID_ENDPOINT', "https://pub.orcid.org/oauth/token");
    // Registred url at Orcid
    define('ORCID_REDIRECT', "https://hal.archives-ouvertes.fr");
}

/**
 * Class Ccsd_Auth_Adapter_Orcid
 */
class Ccsd_Auth_Adapter_Orcid implements Ccsd\Auth\Adapter\AdapterInterface {

    const GRANT_TYPE="authorization_code";
    const AdapterName = 'ORCID';

    var $token;

    /**
     * @var \Ccsd_User_Models_User
     */
    protected $_identityStructure = null;

    /** @var ArrayObject Attributs obtenus suite a l'authentification
     *             Sera retourne par le post_auth
     */
    protected $authAttrs = null;


    /**
     * @return void|Zend_Auth_Result
     */
    public function authenticate()
    {

        //authentication
        $data = self::getOrcidWithToken($this->token);
        $this->authAttrs=new ArrayObject($data);

        if (array_key_exists('orcid', $data)) {
            $uid = $data['orcid'];
        } else {
            $uid=0;
        }

        if ($uid) {
            // Un orcid en retour: bonne authentification
            return new \Zend_Auth_Result(\Zend_Auth_Result::SUCCESS, new \Ccsd_User_Models_User(), array());
        } else {
            return new \Zend_Auth_Result(\Zend_Auth_Result::FAILURE, new \Ccsd_User_Models_User(), array("Echec de l'authentification depuis ORCID"));
        }
    }



    /**
     * @param $controller Zend_Controller_Action
     *
     * @return boolean | void
     *
     */
    public function pre_auth($controller){
        /** @var Zend_Controller_Request_Http $request */
        $request = $controller->getRequest();
        $params = $request->getParams();
        $token = $this->token = $request->getParam('code', null);
        $urlRetour = $params['url'];
        if (isset($params['forceCreate']) && (bool) $params['forceCreate'] === true ){
            $urlForceCreate = '&forceCreate=true';
        }
        else {
            $urlForceCreate = '';
        }

        if (!$token) {
            // Pas encore chez Orcid
            // Attention tester l'erreur de retour (si annuler... qu'on ne le renvois pas directement la bas!
            $urlOrcid = "https://orcid.org/oauth/authorize";
            $urlOrcid .= "?client_id=" . ORCID_CLIENT_ID . "&response_type=code";
            $urlOrcid .= "&scope=/authenticate";
            $urlOrcid .= "&redirect_uri=" . urlencode(ORCID_REDIRECT . "/user/login2?authType=ORCID".$urlForceCreate."&url=$urlRetour");
            $controller->redirect($urlOrcid);

        } else {
            // Retour d'authentification Orcid
            $redirect = $this->getRedirection($params);
            if ($redirect !== NULL) {
                // On vient de revenir sur HAL mais l'utilisateur venait d'un portail, il faut retourner sur le portail
                $controller->redirect($redirect);
                return false;
            }
            // Sinon, c'est bon, on peut passer a l'authentification
        }
    }


    /**
     * @param $params
     * @return string|NULL
     */
    private function getRedirection($params){
        $url = $params['url'];
        $hostPortail  = parse_url(Hal_Site::getCurrentPortail()->getUrl(), PHP_URL_HOST); //Portail Local
        //$hostPortail = 'halv3-local.ccsd.cnrs.fr';
        $hostSouhaite = parse_url($url, PHP_URL_HOST); //Portal of redirection

        if (($hostPortail === $hostSouhaite) || ($hostSouhaite === null)) {
            // On est sur le bon host
            return NULL;
        }
        else {
            // Pas sur le host de depart, redirection vers le portail souhaite
            $urlScheme = parse_url($url, PHP_URL_SCHEME); //Protocol de redirection
            $token = $this->token = $params['code'];
            return ''.$urlScheme. '://'. $hostSouhaite. "/user/login?authType=ORCID&code=$token&url=$url";
        }
    }
    /**
     *
     * @param \Zend_Controller_Action $controller
     * @param Zend_Auth_Result $authinfo
     * @return ArrayAccess
     */
    public function post_auth($controller, $authinfo){
        return $this->authAttrs;
    }

    /** Fonction permettant de notifier un adapter que la personne a deja un login et qu'elle peut etre associe
     * au login courant en cas d'auth reussi avec cet adapter
     * @param ArrayAccess $array_attr    (les attributs retournes par cet adapter lors du post_auth
     * @param \Ccsd_User_Models_User $loginUser  Le login courant
     * @return bool   (true=success, false=echec)
     * @throws \Ccsd\Auth\Asso\Exception
     * @throws \Zend_Db_Adapter_Exception
     *
     * TODO: devrait traiter au moins une des deux exceptions
     */
    public function alt_login($loginUser, $array_attr) {
        $asso = \Ccsd\Auth\Asso\Orcid::exists([ 'userId' => $array_attr['orcid']]);

        if ($asso === null ){
            // Pas encore l'association, on la cree: pas de mail dans Orcid
            $asso = new \Ccsd\Auth\Asso\Orcid($array_attr['orcid'], $loginUser->getUid(), $array_attr['name'],'');
            $asso->save();
        }
        return true;
    }
    /**
     * @param ArrayAccess $array_attr
     * @return Ccsd_User_Models_User|bool
     */
    public function pre_login($array_attr){

        //login
        try {
            $asso = \Ccsd\Auth\Asso\Orcid::exists($array_attr['orcid']);

            if ($asso === NULL) {
                return false;
            } else {
                $user = new \Ccsd_User_Models_User();
                $refUser = new \Ccsd_User_Models_UserMapper();
                $refUser->find($asso->getUidCcsd(),$user);
                return $user;
            }
        } catch (\Zend_Db_Adapter_Exception $e) {
            Ccsd_Tools::panicMsg(__FILE__, __LINE__, "Orcid association give a Db adpter exception");
        }
        return false;
    }

    /**
     * @param ArrayAccess $array_attr
     * @param string $loginUser
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Ccsd\Auth\Asso\Exception
     */
    public function post_login($loginUser,$array_attr){
        $this->alt_login($loginUser, $array_attr);
    }


    /**
     * Retourne les données ORCID à partir d'un token
     * @param string $token
     * @return array $data
     */
    static public function getOrcidWithToken ($token) {

        $params = array(
            "client_id" => ORCID_CLIENT_ID,
            "client_secret" => ORCID_CLIENT_SECRET,
            "grant_type" => self::GRANT_TYPE,
            "code" => $token);

        $curl = curl_init(ORCID_ENDPOINT);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HEADER,'Content-Type: application/json');

        $postData = "";

        foreach($params as $k => $v) {
            $postData .= $k . '='.urlencode($v).'&';
        }

        $postData = rtrim($postData, '&');

        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);

        $json_response = curl_exec($curl);

        $data = json_decode($json_response, true);
        curl_close($curl);
        return $data;
    }

    /**
     * @param $param
     * @return bool|void
     */
    public function logout($param) {
    }

    /**
     * @param ArrayAccess $attr
     * @return string
     */
    public function toHtml($attr) {
        return self::AdapterName;
    }


    /**
     * fonction permettant de forcer la creation d'un compte utilisateur
     * à partir des informations du fournisseur d'identité
     * @param array $array_attr tableau d'informations fournies par le fournisseur d'identité
     * @param boolean $forceCreate
     * @return Ccsd_User_Models_User | bool
     * @throws Exception
     */
    public function createUserFromAdapter($array_attr,$forceCreate)
    {
        if ($forceCreate) {
            $orcidAttr = self::adaptCreateValueFromOrcid($this->authAttrs);

            $user = new \Ccsd_User_Models_User($orcidAttr);
            $user->setValid(1); // compte valide par défaut
            $user->setUid(null);
            $user->setTime_registered();
            $user->setPassword(\Ccsd_Tools::generatePw());
            $uid = $user->save();
            $user->setUid($uid);

            $asso = new \Ccsd\Auth\Asso\Orcid($orcidAttr['ORCID'],$uid, $orcidAttr['USERNAME'], $orcidAttr['EMAIL'], 1);
            $asso->save();

            return $user;
        }
        else {
            return false;
        }
    }

    /**
     * fonction permettant d'aligner les informations données par l'identity provider
     * avec les informations nécessaires à la création d'un compte Ccsd
     * @param $attributes
     * @return array
     */
    public static function adaptCreateValueFromOrcid($attributes)
    {
        return array(
            'ORCID' => $attributes['orcid'],
            'USERNAME' => $attributes['orcid'],
            'EMAIL' =>  null,
            'LASTNAME' => $attributes['name'],
            'FIRSTNAME' => null
        );
    }


}
