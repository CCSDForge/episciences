<?php

declare(strict_types=1);

namespace Episciences\Security;

use Episciences\Log\LoggerFactory;
use Episciences\User\SuLogger;
use Episciences_Auth;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Throwable;
use Zend_Controller_Front;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;

/**
 * Security log of denied accesses: one JSON line per event in the daily "security" file, shared by all journals.
 *
 * It serves two purposes: spotting pages closed by mistake (ACL regressions, actions missing from acl.ini)
 * and keeping track of potentially malicious attempts (repeated ACL denials, CSRF failures, invalid tokens,
 * failed logins). Callers only give the event and a reason: the user, journal, client and route context
 * is added here. Logging never breaks the request.
 */
final class AccessLog
{
    public const CHANNEL = 'security';
    public const RETENTION_DAYS = 180;

    /** A guest reached a protected resource and is sent to the login page */
    public const AUTH_REQUIRED = 'auth_required';
    /** A logged-in user lacks the role required by the ACL */
    public const ACL_DENIED = 'acl_denied';
    /** An existing action is not declared in the ACL: the page is closed by mistake (configuration bug) */
    public const ACL_MISSING = 'acl_missing';
    /** An XHR request targets a resource that does not exist */
    public const UNKNOWN_RESOURCE = 'unknown_resource';
    /** A 401/403 response not logged by a more specific event */
    public const HTTP_FORBIDDEN = 'http_forbidden';
    public const CSRF_FAILED = 'csrf_failed';
    public const LOGIN_FAILED = 'login_failed';
    /** The access is refused by redirecting the user elsewhere */
    public const ACCESS_REDIRECTED = 'access_redirected';
    /** The access is refused by answering "not found", to hide the existence of the resource */
    public const NOT_FOUND_MASKED = 'not_found_masked';
    /** An invitation, merge or similar token is invalid */
    public const TOKEN_INVALID = 'token_invalid';
    /** A file name or path coming from the request tries to leave its directory (traversal, control characters) */
    public const UNSAFE_PATH = 'unsafe_path';

    public const REASON_CSRF_NO_SESSION_TOKEN = 'no_session_token';
    public const REASON_CSRF_MISSING = 'missing';
    public const REASON_CSRF_MISMATCH = 'mismatch';

    private const DEFAULT_LEVELS = [
        self::AUTH_REQUIRED => Level::Info,
        self::HTTP_FORBIDDEN => Level::Notice,
        self::LOGIN_FAILED => Level::Notice,
        self::ACCESS_REDIRECTED => Level::Notice,
        self::NOT_FOUND_MASKED => Level::Notice,
        self::ACL_DENIED => Level::Warning,
        self::UNKNOWN_RESOURCE => Level::Warning,
        self::CSRF_FAILED => Level::Warning,
        self::TOKEN_INVALID => Level::Warning,
        self::UNSAFE_PATH => Level::Warning,
        self::ACL_MISSING => Level::Error,
    ];

    /** Route parameters already logged under their own key */
    private const ROUTE_KEYS = ['module', 'controller', 'action'];

    private const MAX_VALUE_LENGTH = 200;
    private const MAX_PARAMS = 50;

    private static ?LoggerInterface $logger = null;
    private static bool $logged = false;

    /**
     * Record a denied access.
     *
     * @param string $event one of the event constants
     * @param string $reason short machine-readable reason, empty when the event says it all
     * @param array<string, mixed> $context event-specific data (resource, docid...), never credentials;
     *                                     string values are truncated like the request parameters
     * @param Level|null $level defaults to the level of the event
     */
    public static function denied(string $event, string $reason = '', array $context = [], ?Level $level = null): void
    {
        self::$logged = true;

        try {
            $record = self::requestContext();
            if ($reason !== '') {
                $record['reason'] = $reason;
            }
            $record += self::shorten($context);

            $level ??= self::DEFAULT_LEVELS[$event] ?? Level::Warning;
            self::logger()->log($level->toPsrLogLevel(), $event, $record);
        } catch (Throwable $e) {
            error_log('Security access log failure: ' . $e->getMessage());
        }
    }

    /**
     * Whether a denied access has already been recorded for the current request
     */
    public static function wasLogged(): bool
    {
        return self::$logged;
    }

    /**
     * Forget the request state (tests, long-running processes)
     */
    public static function reset(): void
    {
        self::$logged = false;
    }

    /**
     * @param LoggerInterface|null $logger null restores the default daily file logger
     */
    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Created on first use: requests without any denial never open the file.
     */
    private static function logger(): LoggerInterface
    {
        return self::$logger ??= LoggerFactory::rotating(
            self::CHANNEL,
            null,
            Level::Info,
            self::RETENTION_DAYS,
            new JsonFormatter()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function requestContext(): array
    {
        $context = self::userContext();
        $context['rvid'] = defined('RVID') ? RVID : null;
        $context['rvcode'] = defined('RVCODE') ? RVCODE : null;

        $request = Zend_Controller_Front::getInstance()->getRequest();
        if ($request instanceof Zend_Controller_Request_Abstract) {
            $context += self::routeContext($request);
        }

        $client = SuLogger::requestContext($request);
        $context['ip'] = $client['ipAddress'];
        $context['forwarded_for'] = $client['details'][SuLogger::DETAILS_FORWARDED_FOR] ?? null;
        $context['user_agent'] = $client['userAgent'] !== null ? self::truncate($client['userAgent']) : null;

        $sessionId = session_id();
        $context['session'] = SuLogger::hashSessionId($sessionId === false ? null : $sessionId);

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private static function userContext(): array
    {
        if (!Episciences_Auth::isLogged()) {
            return ['uid' => null, 'roles' => Episciences_Auth::getRoles()];
        }

        $context = ['uid' => (int)Episciences_Auth::getUid(), 'roles' => Episciences_Auth::getRoles()];
        if (Episciences_Auth::isImpersonating()) {
            $context['original_uid'] = Episciences_Auth::getOriginalIdentity()?->getUid();
        }

        return $context;
    }

    /**
     * Route and query parameters only: the POST body is never logged, nor the raw URI
     * (tokens are passed as path segments, e.g. /token/xxx).
     *
     * @return array<string, mixed>
     */
    private static function routeContext(Zend_Controller_Request_Abstract $request): array
    {
        $context = [
            'module' => defined('APPLICATION_MODULE') ? APPLICATION_MODULE : $request->getModuleName(),
            'controller' => $request->getControllerName(),
            'action' => $request->getActionName(),
        ];

        $params = $request->getUserParams();
        if ($request instanceof Zend_Controller_Request_Http) {
            $context['method'] = $request->getMethod();
            $context['xhr'] = $request->isXmlHttpRequest();
            $params += (array)$request->getQuery();
        }

        $params = array_diff_key($params, array_flip(self::ROUTE_KEYS));
        $context['params'] = self::shorten(ParamRedactor::redact(array_slice($params, 0, self::MAX_PARAMS, true)));

        return $context;
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    private static function shorten(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = self::shorten(array_slice($value, 0, self::MAX_PARAMS, true));
            } elseif (is_string($value)) {
                $params[$key] = self::truncate($value);
            } elseif (!is_scalar($value) && $value !== null) {
                $params[$key] = get_debug_type($value);
            }
        }

        return $params;
    }

    private static function truncate(string $value): string
    {
        return mb_substr($value, 0, self::MAX_VALUE_LENGTH);
    }
}
