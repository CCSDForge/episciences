<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 13/02/19
 * Time: 10:28
 */

namespace Ccsd\Auth\Asso;

/**
 * Class Idp
 * Spécificité d'association pour un IDP
 * @package Ccsd\Auth\Asso
 */
class Orcid extends \Ccsd\Auth\Asso
{
    const ASSOFEDE   = 'Orcid';
    const ASSOFEDEID = 'Orcid';
    /**
     * @var string $ASSO_TABLE nom de la table en BDD où sont stockées les informations
     */
    protected static $ASSO_TABLE = 'USER_ID_ASSOCIATION';

    /**
     * Ccsd_Auth_Asso constructor.
     * @param $orcid string
     * @param $uidCcsd int unique ID user hal
     * @param string $name
     * @param string $email
     * @param bool $valid
     */
    public function __construct($orcid, $uidCcsd, $name, $email,$valid = true)
    {
        parent::__construct($orcid, self::ASSOFEDE, self::ASSOFEDEID, $uidCcsd, $name, '', $email,$valid);

    }

    /**
     * @param array $row
     * @param bool $valid
     * @return \Ccsd\Auth\Asso|Orcid
     */
    protected static function array2obj($row, $valid = true) {
        return new self($row['uid'], $row['uidCcsd'],$row['nom'],$row['email'], $valid);
    }

    /**
     * @param $uid
     * @param $federationId
     * @param $federationName
     * @return \Ccsd\Auth\Asso if exists, null either
     */
    public static function exists($uid,$federationId = self::ASSOFEDEID, $federationName = self::ASSOFEDE) {
        return parent::exists($federationId,$federationName,$uid);
    }

}
