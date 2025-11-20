<?php

namespace Episciences\Trait;

trait LocaleByCookieTrait
{
    public static string $_locale_cookie_name = 'epi_lang';


    final public function setLocaleCookie(string $lang = null, int $expire = 3600 * 24 * 30): void
    {
        $assembledName = sprintf('%s_%s',RVCODE, self::$_locale_cookie_name);

        if (
            !isset($_COOKIE[$assembledName]) ||
            $_COOKIE[$assembledName] !== $lang
        ) {

            setcookie(
                $assembledName,
                $lang,
                time() + $expire,
                '/',
                sprintf('.%s', DOMAIN), // domaine et sous domaine
                false,
                true
            );
        }
    }

    final public function getLocaleCookie(): ?string
    {
        $assembledName = sprintf('%s_%s',RVCODE, self::$_locale_cookie_name);
        return $_COOKIE[$assembledName] ?? null;
    }

}