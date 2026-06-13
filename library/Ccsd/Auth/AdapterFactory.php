<?php
declare(strict_types=1);

namespace Ccsd\Auth;
class AdapterFactory
{
    public static function getTypedAdapter($authType): Adapter\Mysql|\Ccsd_Auth_Adapter_Cas
    {
        return match (strtoupper((string)$authType)) {
            'MYSQL' => new Adapter\Mysql(),
            default => new \Ccsd_Auth_Adapter_Cas(),
        };
    }
}
