<?php

class Episciences_Paper_Visits
{


    public static function add($docId, $consult = 'notice')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $data = [
            'DOCID' => (int)$docId,
            'IP' => new Zend_Db_Expr("IFNULL(INET_ATON('" . Zend_Controller_Front::getInstance()->getRequest()->getClientIp() . "'), INET_ATON('127.1'))"),
            'HTTP_USER_AGENT' => self::getUserAgent(),
            'DHIT' => new Zend_Db_Expr('NOW()'),
            'CONSULT' => $consult
        ];

        $db->insert(VISITS_TEMP, $data);

    }

    /**
     * @return string
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

    public static function count($docId, $consult = 'notice')
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
