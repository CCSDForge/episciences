<?php
/**
 * Created by PhpStorm.
 * User: chibane
 * Date: 10/09/18
 * Time: 11:59
 */


class Episciences_User_UserMapper extends Ccsd_User_Models_UserMapper
{


    /** OpenAire Metrics
     * User accounts according to the registration date REGISTRATION_DATE
     * @param string $registrationDateTime empty for current year
     * @return false|int
     * @throws Zend_Db_Statement_Exception
     */
    public static function getUserCountAfterDate(string $registrationDateTime = '')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($registrationDateTime === '') {
            $registrationDateTime = date('Y') . '-01-01 00:00:00';
        }

        $select = $db->select()
            ->from(T_USERS, [new Zend_Db_Expr("COUNT('UID') AS NbUsers")])
            ->where('REGISTRATION_DATE >= ?', $registrationDateTime);

        $result = $select->query()->fetch();

        if (!$result) {
            return false;
        }

        return (int)$result['NbUsers'];
    }

}