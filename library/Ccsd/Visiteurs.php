<?php

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;

/**
 * @deprecated Will be removed. Use Episciences\Paper\Visits\BotDetector for bot detection.
 *             GeoIP lookup and domain resolution are now handled by ProcessStatTempCommand (stats:process).
 */
class Ccsd_Visiteurs
{
    const TIMEOUT = 15; // nbre de seconde après lequel on considère que le visiteur est parti
    const TABLE = 'VISITEURS';
    /* table à créer sur chaque plateforme :
     *  CREATE TABLE IF NOT EXISTS `VISITEURS` (
     *	 `APPID` int(11) UNSIGNED NOT NULL,
     *	 `IP` int(11) UNSIGNED NOT NULL,
     *	 `UID` int(11) UNSIGNED NOT NULL,
     *	 `DATEHIT` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
     *	 PRIMARY KEY (`APPIP`, `IP`),
     *   KEY `IDX_APP` (`APPID`)
     *	) ENGINE=MEMORY DEFAULT CHARACTER SET = utf8 COLLATE = utf8_general_ci COMMENT = 'Locale Visitors Logs' ;*/

    private $_ip;
    static $nonResolvedIps = array();
    private $_userAgent;
    # Liste de regexp recupere de http://www.projectcounter.org/r4/APPJ.pdf
    private $_regexCounterRobotList = array(
        'bot',
        'spider',
        'crawl',
        '^.?$',
        '[^a]fish',
        '^IDA$',
        '^ruby$',
        '^voyager\/',
        '^@ozilla\/\d',
        'alexa',
        'Alexandria(\s|\+)prototype(\s|\+)project',
        'AllenTrack',
        'almaden',
        'appie',
        'Arachmo',
        'architext',
        'aria2\/\d',
        'arks',
        '^Array$',
        'asterias',
        'atomz',
        'BDFetch',
        'Betsie',
        'biadu',
        'biglotron',
        'BingPreview',
        'bjaaland',
        'Blackboard[\+\s]Safeassign',
        'blaiz\-bee',
        'bloglines',
        'blogpulse',
        'boitho\.com\-dc',
        'bookmark\-manager',
        'Brutus\/AET',
        'bwh3_user_agent',
        'CakePHP',
        'celestial',
        'cfnetwork',
        'checkprivacy',
        'China\sLocal\sBrowse\s2\.6',
        'cloakDetect',
        'coccoc\/1\.0',
        'Code\sSample\sWeb\sClient',
        'ColdFusion',
        'combine',
        'contentmatch',
        'ContentSmartz',
        'core',
        'CoverScout',
        'curl\/7',
        'cursor',
        'custo',
        'DataCha0s\/2\.0',
        'daumoa',
        '^\%?default\%?$',
        'Dispatch\/\d',
        'docomo',
        'Download\+Master',
        'DSurf',
        'easydl',
        'EBSCO\sEJS\sContent\sServer',
        'ELinks\/',
        'EmailSiphon',
        'EmailWolf',
        'EndNote',
        'EThOS\+\(British\+Library\)',
        'facebookexternalhit\/',
        'favorg',
        'FDM(\s|\+)\d',
        'feedburner',
        'FeedFetcher',
        'feedreader',
        'ferret',
        'Fetch(\s|\+)API(\s|\+)Request',
        'findlinks',
        '^FileDown$',
        '^Filter$',
        '^firefox$',
        '^FOCA',
        'Fulltext',
        'Funnelback',
        'GetRight',
        'geturl',
        'GLMSLinkAnalysis',
        'Goldfire(\s|\+)Server',
        'google',
        'grub',
        'gulliver',
        'gvfs\/',
        'harvest',
        'heritrix',
        'holmes',
        'htdig',
        'htmlparser',
        'HttpComponents\/1.1',
        'HTTPFetcher',
        'http.?client',
        'httpget',
        'httrack',
        'ia_archiver',
        'ichiro',
        'iktomi',
        'ilse',
        'Indy Library',
        '^integrity\/\d',
        'internetseer',
        'intute',
        'iSiloX',
        'java',
        'jeeves',
        'jobo',
        'kyluka',
        'larbin',
        'libcurl',
        'libhttp',
        'libwww',
        'lilina',
        'link.?check',
        'LinkLint-checkonly',
        '^LinkParser\/',
        '^LinkSaver\/',
        'linkscan',
        'linkwalker',
        'livejournal\.com',
        'LOCKSS',
        'LongURL.API',
        'ltx71',
        'lwp',
        'lycos[\_\+]',
        'mail.ru',
        'MarcEdit.5.2.Web.Client',
        'mediapartners\-google',
        'megite',
        'MetaURI[\+\s]API\/\d\.\d',
        'Microsoft(\s|\+)URL(\s|\+)Control',
        'Microsoft Office Existence Discovery',
        'Microsoft Office Protocol Discovery',
        'Microsoft-WebDAV-MiniRedir',
        'mimas',
        'mnogosearch',
        'moget',
        'motor',
        '^Mozilla$',
        '^Mozilla.4\.0$',
        '^Mozilla\/4\.0\+\(compatible;\)$',
        '^Mozilla\/4\.0\+\(compatible;\+ICS\)$',
        '^Mozilla\/4\.5\+\[en]\+\(Win98;\+I\)$',
        '^Mozilla.5\.0$',
        '^Mozilla\/5.0\+\(compatible;\+MSIE\+6\.0;\+Windows\+NT\+5\.0\)$',
        '^Mozilla\/5\.0\+like\+Gecko$',
        '^Mozilla\/5.0(\s|\+)Gecko\/20100115(\s|\+)Firefox\/3.6$',
        '^MSIE',
        'MuscatFerre',
        'myweb',
        'nagios',
        '^NetAnts\/\d',
        'netcraft',
        'netluchs',
        'ng\/2\.',
        'Ning',
        'no_user_agent',
        'nomad',
        'nutch',
        'ocelli',
        'Offline(\s|\+)Navigator',
        'onetszukaj',
        '^Opera\/4$',
        'OurBrowser',
        'parsijoo',
        'pear.php.net',
        'perman',
        'PHP\/',
        'pioneer',
        'playmusic\.com',
        'playstarmusic\.com',
        '^Postgenomic(\s|\+)v2',
        'powermarks',
        'PycURL',
        'python',
        'Qwantify',
        'rambler',
        'Readpaper',
        'redalert|robozilla',
        'rss',
        'scan4mail',
        'scientificcommons',
        'scirus',
        'scooter',
        '^scrutiny\/\d',
        'SearchBloxIntra',
        'shoutcast',
        'slurp',
        'sogou',
        'speedy',
        'Strider',
        'sunrise',
        'T\-H\-U\-N\-D\-E\-R\-S\-T\-O\-N\-E',
        'tailrank',
        'Teleport(\s|\+)Pro',
        'Teoma',
        'titan',
        '^Traackr\.com$',
        'twiceler',
        'ucsd',
        'ultraseek',
        '^undefined$',
        '^unknown$',
        'URL2File',
        'urlaliasbuilder',
        'urllib',
        '^user.?agent$',
        'validator',
        'virus.detector',
        'voila',
        '^voltron$',
        'w3af.org',
        'w3c\-checklink',
        'Wanadoo',
        'Web(\s|\+)Downloader',
        'WebCloner',
        'webcollage',
        'WebCopier',
        'Webinator',
        'weblayers',
        'Webmetrics',
        'webmirror',
        'webreaper',
        'WebStripper',
        'WebZIP',
        'Wget',
        'wordpress',
        'worm',
        'www.gnip.com ',
        'WWW\-Mechanize',
        'xenu',
        'Xenu(\s|\+)Link(\s|\+)Sleuth',
        'y!j',
        'yacy',
        'yahoo',
        'yandex',
        'zeus',
        'zyborg',
        '^\$'
    );

    public function __construct($ip = null, $ua = null)
    {
        if ($ip != null && (is_int($ip) || filter_var($ip, FILTER_VALIDATE_IP))) {
            $this->_ip = $ip;
        }
        if (null != $ua) {
            $this->_userAgent = $ua;
        }
    }

    public static function init($appid = null, $ip = null, $uid = 0)
    {
        if (null !== $appid && null !== $ip) {
            self::delete();
            self::add($appid, $ip, $uid);
        }
    }

    /**
     * ajoute ou modifie une entrée visiteur (ip, uid)
     *
     * @param int
     * @param string
     * @param int
     */
    private static function add($appid, $ip, $uid)
    {
        Zend_Db_Table_Abstract::getDefaultAdapter()->query("INSERT INTO " . self::TABLE . " (`APPID`, `IP`, `UID`) VALUES (" . (int)$appid . ", IFNULL(INET_ATON('" . $ip . "'), 0), " . (int)$uid . ") ON DUPLICATE KEY UPDATE DHIT=NOW(), UID=" . (int)$uid);
    }

    /**
     * supprime les visiteurs non actif depuis plus de self::TIMEOUT sur la plateforme
     */
    private static function delete()
    {
        Zend_Db_Table_Abstract::getDefaultAdapter()->delete(self::TABLE, "DHIT < '" . date('Y-m-d H:i:s', time() - self::TIMEOUT) . "'");
    }

    /**
     * récupère les visiteurs
     *
     * @param int
     * @return array
     */
    public static function get($appid = null)
    {
        if (null !== $appid) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            if ($appid == 0) {
                $select = $db->select()->from(self::TABLE, array(new Zend_Db_Expr('INET_NTOA(IP) as IP'), 'APPID', 'UID'))->order(new Zend_Db_Expr('APPID, DHIT DESC'));
            } else {
                $select = $db->select()->from(self::TABLE, array(new Zend_Db_Expr('INET_NTOA(IP) as IP'), 'UID'))->where('APPID = ?', $appid)->order('DHIT DESC');
            }
            return $db->fetchAll($select);
        } else {
            return array();
        }
    }

    /**
     * Analyse l'IP et le User_Agent pour déterminer si il s'agit d'un robot
     *
     * @return bool
     * @deprecated Use Episciences\Paper\Visits\BotDetector::isBot() instead.
     */
    public function isRobot()
    {
        if ($this->_ip != null && $this->_userAgent != null) {
            if ($this->_ip == '127.0.0.1') {
                return true;
            }
            $robot = false;
            if ($this->_userAgent != '') {
                foreach ($this->_regexCounterRobotList as $regex) {
                    if (preg_match('/' . $regex . '/', $this->_userAgent)) {
                        $robot = true;
                        break;
                    }
                }
            }
            if (!$robot) {
                $atonIP = ip2long($this->_ip);

                $robot = ($atonIP > 1089052672 && $atonIP < 1089060863)   #  64.233.160.0 --  64.233.191.255 --
                    || ($atonIP > 1113980928 && $atonIP < 1113985023)   #    66.102.0.0 --   66.102.15.255 --
                    || ($atonIP > 1123631104 && $atonIP < 1123639295)   #   66.249.64.0 --   66.249.95.255 --
                    || ($atonIP > 1208926208 && $atonIP < 1208942591)   #   72.14.192.0 --   72.14.255.255 --
                    || ($atonIP > 1249705984 && $atonIP < 1249771519)   #    74.125.0.0 --  74.125.255.255 --
                    || ($atonIP > 3512041472 && $atonIP < 3512074239)   #  209.85.128.0 --  209.85.255.255 --
                    || ($atonIP > 3639549952 && $atonIP < 3639558143)   #  216.239.32.0 --  216.239.63.255 --
                    || ($atonIP > 1074003968 && $atonIP < 1074020351)   #      64.4.0.0 --     64.4.63.255 --
                    || ($atonIP > 1093926912 && $atonIP < 1094189055)   #     65.52.0.0 --   65.55.255.255 --
                    || ($atonIP > 2214401280 && $atonIP < 2214408191)   #  131.253.21.0 --  131.253.47.255 --
                    || ($atonIP > 2637561856 && $atonIP < 2638020607)   #    157.54.0.0 --  157.60.255.255 --
                    || ($atonIP > 3475898368 && $atonIP < 3475963903)   #    207.46.0.0 --  207.46.255.255 --
                    || ($atonIP > 3477372928 && $atonIP < 3477393407)   #  207.68.128.0 --  207.68.207.255 --
                    || ($atonIP > 135041024 && $atonIP < 135041279)    #    8.12.144.0 --    8.12.144.255 --
                    || ($atonIP > 1120157696 && $atonIP < 1120174079)   #   66.196.64.0 --  66.196.127.255 --
                    || ($atonIP > 1122279424 && $atonIP < 1122287615)   #  66.228.160.0 --  66.228.191.255 --
                    || ($atonIP > 1136852992 && $atonIP < 1136918527)   #    67.195.0.0 --  67.195.255.255 --
                    || ($atonIP > 1150205952 && $atonIP < 1150222335)   #  68.142.192.0 --  68.142.255.255 --
                    || ($atonIP > 1209925632 && $atonIP < 1209991167)   #     72.30.0.0 --   72.30.255.255 --
                    || ($atonIP > 1241907200 && $atonIP < 1241972735)   #      74.6.0.0 --    74.6.255.255 --
                    || ($atonIP > 3399528448 && $atonIP < 3399532543)   # 202.160.176.0 -- 202.160.191.255 --
                    || ($atonIP > 3518971904 && $atonIP < 3518988287)   #  209.191.64.0 -- 209.191.127.255 --
                    || ($atonIP > 3024880896 && $atonIP < 3024881407)   #    180.76.5.0 --    180.76.6.255 --
                    || ($atonIP > 2071807744 && $atonIP < 2071807999)   #  123.125.71.0 --  123.125.71.255 --
                    || ($atonIP > 2000667648 && $atonIP < 2000667903)   #  119.63.196.0 --  119.63.196.255 --
                    || ($atonIP > 2011950336 && $atonIP < 2011950591);  # 119.235.237.0 -- 119.235.237.255 --
            }
            return $robot;
        }
        return true;
    }

    /**
     * Détermine les données de géolocalisation d'une IP via GeoIP
     * @param Reader|null $giReader
     * @param bool $closeGeoIpDb
     * @return array
     * @deprecated GeoIP lookup is now handled by ProcessStatTempCommand (stats:process).
     */
    public function getLocalisation(Reader $giReader = null, bool $closeGeoIpDb = false): array
    {
        $data = array('domain' => '', 'continent' => '', 'country' => '', 'city' => '', 'lat' => 0, 'lon' => 0);
        if ($this->_ip != null) {
            if (!array_key_exists($this->_ip, self::$nonResolvedIps)) {
                # Pour une Ip, ne faire le gethostbyaddr qu'une seule fois si l'adresse ne se resoud pas pour eviter les timeout
                $domain = @gethostbyaddr($this->_ip);
                if ($domain == $this->_ip) {
                    # Non resolue, on cache
                    self::$nonResolvedIps[$this->_ip] = 1;
                } else {
                    # Resolue
                    if (preg_match('/(?P<domain>[\w\-]{1,63}\.[a-z\.]{2,6})$/ui', $domain, $regs)) {
                        $data['domain'] = $regs['domain'];
                    }
                }
            }

            if (!$giReader) {

                try {
                    $geoIpReader = new Reader(GEO_IP_DATABASE_PATH . GEO_IP_DATABASE);
                } catch (InvalidDatabaseException $e) {
                    trigger_error($e->getMessage());
                    $geoIpReader = null;
                }

            } else {
                $geoIpReader = $giReader;
            }


            if ($geoIpReader) {

                try {
                    $record = $geoIpReader->city($this->_ip)->jsonSerialize();
                } catch (AddressNotFoundException|InvalidDatabaseException $e) {
                    trigger_error($e->getMessage());
                    $record = null;
                }

                $data['continent'] = utf8_encode($record['continent']['code'] ?? '');
                $data['country'] = utf8_encode($record['country']['iso_code'] ?? '');
                $data['city'] = ''; // unavailable with GeoLite2-City database
                $data['lat'] = (float)($record['location']['latitude'] ?? 0);
                $data['lon'] = (float)($record['location']['longitude'] ?? 0);

                if($closeGeoIpDb){
                    $geoIpReader->close();
                }
            }

        }
        return $data;
    }
}
