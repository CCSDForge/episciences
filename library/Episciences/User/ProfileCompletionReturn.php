<?php

declare(strict_types=1);

namespace Episciences\User;

use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;
use Zend_Session_Namespace;

/**
 * A signed-in account without any Episciences data (created in another application sharing the accounts) is sent to
 * the profile form first, whatever the page it asked for: that page is kept in the session, to go back to it once
 * the profile is saved (e.g. the review invitation the account was about to accept).
 */
final class ProfileCompletionReturn
{
    private const SESSION_KEY = 'profileCompletionReturnUri';

    /**
     * Remembers the page asked for, when it can be displayed again as it is: a page, not a form sent or a script call
     */
    public static function remember(Zend_Controller_Request_Abstract $request): void
    {
        if (!$request instanceof Zend_Controller_Request_Http || !$request->isGet() || $request->isXmlHttpRequest()) {
            return;
        }

        $uri = (string)$request->getRequestUri();
        self::session()->{self::SESSION_KEY} = self::isLocalPath($uri) ? $uri : null;
    }

    /**
     * The page to go back to once the profile is saved, forgotten once read
     */
    public static function take(): ?string
    {
        $session = self::session();
        $uri = $session->{self::SESSION_KEY} ?? null;
        unset($session->{self::SESSION_KEY});

        return is_string($uri) && self::isLocalPath($uri) ? $uri : null;
    }

    /**
     * A path of this site only: never another host ("//host", "/\host")
     */
    private static function isLocalPath(string $uri): bool
    {
        return str_starts_with($uri, '/') && !str_starts_with($uri, '//') && !str_contains($uri, '\\');
    }

    private static function session(): Zend_Session_Namespace
    {
        return new Zend_Session_Namespace(SESSION_NAMESPACE);
    }
}
