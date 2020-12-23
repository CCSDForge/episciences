<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 13/02/19
 * Time: 10:28
 */

namespace Ccsd\Auth\Asso ;

use Ccsd\Auth\Asso;

class Idp extends Asso
{

    /**
     * @var string $ASSO_TABLE nom de la table en BDD où sont stockées les informations
     */
    protected static $ASSO_TABLE = 'REF_IDHAL_IDP';

}