<?php

declare(strict_types=1);

namespace Episciences\Security;

use Episciences_Acl;

/**
 * Masks credential-like request parameters before they reach the logs.
 */
final class ParamRedactor
{
    public const MASK = '***';

    private const SENSITIVE_FRAGMENTS = ['password', 'passwd', 'pwd', 'token', 'secret', 'csrf', 'api_key', 'apikey'];

    /**
     * Names too generic to be used as fragments (e.g. the volume access code)
     */
    private const SENSITIVE_EXACT = ['code', 'access_code'];

    /**
     * Replace the value of credential-like parameters.
     *
     * A parameter is masked when its lowercased name contains one of the sensitive
     * fragments (e.g. PASSWORD_VERIFY, API_PASSWORD_VERIFY, paperPassword, csrf_token),
     * or exactly matches a name too generic to be used as a fragment (e.g. the volume
     * access code). Role names containing a fragment (secretary) are not masked.
     *
     * @param array<mixed> $params
     * @return array<mixed>
     */
    public static function redact(array $params): array
    {
        $notSensitive = [Episciences_Acl::ROLE_SECRETARY, Episciences_Acl::ROLE_SECRETARY_PLURAL];

        foreach ($params as $key => $value) {
            $name = strtolower((string)$key);
            $isSensitive = in_array($name, self::SENSITIVE_EXACT, true)
                || (!in_array($name, $notSensitive, true) && self::isSensitiveKey($name));

            if ($isSensitive) {
                $params[$key] = self::MASK;
            } elseif (is_array($value)) {
                $params[$key] = self::redact($value);
            }
        }

        return $params;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
