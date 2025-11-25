<?php

namespace Episciences\Trait;

trait LocaleByCookieTrait
{
    public static string $_locale_cookie_name = 'lang';


    final public function setLocaleCookie(string $lang = null, int $expire = 3600 * 24 * 30): void
    {

        if (
            !isset($_COOKIE[self::$_locale_cookie_name]) ||
            $_COOKIE[self::$_locale_cookie_name] !== $lang
        ) {

            setcookie(
                self::$_locale_cookie_name,
                $lang,
                time() + $expire,
                '/',
                '',
                false,
                true
            );
        }
    }

    final public function getLocaleCookie(): ?string
    {
        return $_COOKIE[self::$_locale_cookie_name] ?? null;
    }

}