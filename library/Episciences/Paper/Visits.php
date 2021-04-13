<?php

use geertw\IpAnonymizer\IpAnonymizer;

class Episciences_Paper_Visits
{
    const CONSULT_TYPE_NOTICE = 'notice';
    const CONSULT_TYPE_FILE = 'file';

    /**
     * @param $docId
     * @param string $consult
     * @throws Zend_Db_Adapter_Exception
     */
    public static function add($docId, $consult = self::CONSULT_TYPE_NOTICE)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();


        $clientIp = Zend_Controller_Front::getInstance()->getRequest()->getClientIp();
        $clientIpAnon = self::anonymizeClientIp($clientIp);

        $data = [
            'DOCID' => (int)$docId,
            'IP' => new Zend_Db_Expr("IFNULL(INET_ATON('" .
                $clientIpAnon . "'), INET_ATON('127.1'))"),
            'HTTP_USER_AGENT' => self::getUserAgent(),
            'DHIT' => new Zend_Db_Expr('NOW()'),
            'CONSULT' => $consult
        ];

        $db->insert(VISITS_TEMP, $data);

    }

    /**
     * @param $clientIp
     * @return string
     */
    protected static function anonymizeClientIp($clientIp)
    {
        $ipAnonymizer = new IpAnonymizer();
        $ipAnonymizer->ipv4NetMask = "255.255.0.0";

        $anonymizedIp = $ipAnonymizer->anonymize($clientIp);
        if ($anonymizedIp == '') {
            $anonymizedIp = '127.0.0.1';
        }
        return $anonymizedIp;
    }

    /**
     * @return false|string
     */
    public static function getUserAgent()
    {
        $userAgent = false;
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $userAgent = filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $userAgent = substr($userAgent, 0, 2000);
        }
        if (!$userAgent) {
            $userAgent = 'Unknown';
        }
        return $userAgent;
    }

    /**
     * @param $docId
     * @param string $consult
     * @return string
     */
    public static function count($docId, $consult = self::CONSULT_TYPE_NOTICE)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(T_PAPER_VISITS, new Zend_Db_Expr('SUM(COUNTER)'))
            ->where('DOCID = ?', $docId)
            ->where('ROBOT = 0')
            ->where('CONSULT = ?', $consult);
        return $db->fetchOne($sql);
    }

}
