<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 25/02/19
 * Time: 10:13
 */

class Ccsd_Auth_Asso_Ext
{

    /**
     * @var bool
     */
    private $modified = true;

    /**
     * @var int ID CCSD
     */
    protected $uidCcsd;


    /**
     * @var int Server ID
     */
    protected $serverId;

    /**
     * @var string Server Name
     */
    protected $serverName;

    /**
     * @var string Server Url
     */
    protected $serverUrl;

    /**
     * @var string Server type
     */
    protected $serverType;

    /**
     * @var int Server Order
     */
    protected $serverOrder;

    /**
     * @var string Ext ID
     */
    protected $uidExt;

    /**
     * @var boolean valid
     */
    protected $valid;

    /**
     * @var string $EXT_TABLE nom de la table en BDD où sont stockées les informations
     */
    protected static $EXT_TABLE = 'REF_IDHAL_EXT';

    /**
     * @var string $SERVER_EXT_TABLE
     */
    protected static $SERVER_EXT_TABLE = 'REF_SERVEREXT';



    /**
     * Ccsd_Auth_Asso_Ext constructor.
     * @param int $uidCcsd
     * @param string $uidExt
     * @param int $serverId
     * @param string $serverName
     * @param string $serverUrl
     * @param string $serverType
     * @param int $serverOrder
     * @param boolean $valid
     */
    function __construct($uidCcsd, $uidExt, $serverId, $serverName, $serverUrl, $serverType, $serverOrder, $valid = true)
    {

        $this->setUidCcsd($uidCcsd);
        $this->setUidExt($uidExt);
        $this->setServerId($serverId);
        $this->setServerName($serverName);
        $this->setServerUrl($serverUrl);
        $this->setServerType($serverType);
        $this->setServerOrder($serverOrder);
        $this->setValid($valid);

    }


    /**
     * @return array
     */
    private function toArray()
    {
        $bind = [ 'uidCcsd'=>$this->getUidCcsd(),
                  'uidExt' =>$this->getUidExt(),
                  'serverId'=>$this->getServerId(),
                  'serverName'=>$this->getServerName(),
                  'serverUrl' =>$this->getServerUrl(),
                  'serverType' =>$this->getServerType(),
                  'serverOrder' =>$this->getServerOrder()

        ];
        return $bind;
    }

    /**
     * @param $row
     * @param bool $valid
     * @return Ccsd_Auth_Asso_Ext
     */
    private static function array2obj($row, $valid = true){

        return new self($row['idhal'], $row['id'],$row['serverid'],$row['name'],$row['url'],$row['type'],$row['order'],$valid);

    }


    /**
     *
     */

    private static function mapToServer($row){
        return ['SERVERID'=>$row['serverid'],
                'NAME'=>$row['name'],
                'URL'=>$row['url'],
                'TYPE'=>$row['order']];
    }

    private static function mapToAsso($row){
        return ['IDHAL'=>$row['idhal'],
                'SERVERID'=>$row['serverid'],
                'ID'=>$row['id']];
    }

    /**
     * @param string $serverId
     * @param string $uidExt
     * @return object if exists, null either
     */
    public static function load($serverId,$uidExt) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from([self::$EXT_TABLE=>'EXT'])
            ->join([self::$SERVER_EXT_TABLE=>'SERVER'],'EXT.SERVERID = SERVER.SERVERID')
            ->where('SERVER.UID = ?', $serverId)
            ->where('EXT.ID = ?',$uidExt);

        $row = $db->fetchRow($select);
        if ($row) {
            $obj = self::array2obj($row, false);
            $obj ->modified = false;
            return $obj;
        } else {
            return null;
        }
    }

    /**
     * fonction d'insertion en base
     * @throws Exception
     * @return boolean
     */
    public function save()
    {
        if (! $this -> valid()) {
            //throw new Ccsd_Auth_Asso_Exception("Association is not valid , can't save it!")
            throw new Exception("Association is not valid , can't save it!");
        }
        if ($this ->modified) {
            // sauvegarde si besoin d'un nouveau server
            if ($this->getServerId() === NULL )
            {
                // insertion des infos dans serverext
                $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $bind = self::mapToServer($this->toArray());
                $idServer = $db->insert(self::$SERVER_EXT_TABLE, $bind);
                $this->setServerId($idServer);
            }
            // sauvegarde si besoin dans
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $bind = self::mapToAsso($this->toArray());
            return $db->insert(self::$EXT_TABLE, $bind);
        }
        return true;
    }

    /**
     * @return int
     */
    public function getUidCcsd(): int
    {
        return $this->uidCcsd;
    }

    /**
     * @param int $uidCcsd
     */
    public function setUidCcsd(int $uidCcsd)
    {
        $this->uidCcsd = $uidCcsd;
    }

    /**
     * @return int
     */
    public function getServerId(): int
    {
        return $this->serverId;
    }

    /**
     * @param int $serverId
     */
    public function setServerId(int $serverId)
    {
        $this->serverId = $serverId;
    }

    /**
     * @return string
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * @param string $serverName
     */
    public function setServerName(string $serverName)
    {
        $this->serverName = $serverName;
    }

    /**
     * @return string
     */
    public function getServerUrl(): string
    {
        return $this->serverUrl;
    }

    /**
     * @param string $serverUrl
     */
    public function setServerUrl(string $serverUrl)
    {
        $this->serverUrl = $serverUrl;
    }

    /**
     * @return string
     */
    public function getServerType(): string
    {
        return $this->serverType;
    }

    /**
     * @param string $serverType
     */
    public function setServerType(string $serverType)
    {
        $this->serverType = $serverType;
    }

    /**
     * @return int
     */
    public function getServerOrder(): int
    {
        return $this->serverOrder;
    }

    /**
     * @param int $serverOrder
     */
    public function setServerOrder(int $serverOrder)
    {
        $this->serverOrder = $serverOrder;
    }

    /**
     * @return string
     */
    public function getUidExt(): string
    {
        return $this->uidExt;
    }

    /**
     * @param string $uidExt
     */
    public function setUidExt(string $uidExt)
    {
        $this->uidExt = $uidExt;
    }

    /**
     * @return bool
     */
    public function valid() {
        return true;
    }

    /**
     * @param $valid boolean
     */
    public function setValid($valid)
    {
        $this->valid=$valid;
    }










}