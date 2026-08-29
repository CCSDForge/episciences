<?php

/**
 * CSRF Helper using Zend_Form_Element_Hash
 *
 * Provides CSRF protection using Zend Framework's built-in hash element,
 * which handles token expiration, session binding, and security automatically.
 */
class Episciences_Csrf_Helper
{
    private const DEFAULT_TIMEOUT = 3600; // 1 hour

    /**
     * Generate a CSRF token using Zend_Form_Element_Hash
     *
     * @param string $name Unique identifier for this token (e.g., 'remove_file_123')
     * @param int $timeout Token timeout in seconds (default: 3600)
     * @return array ['name' => element name, 'value' => token value]
     * @throws Zend_Form_Exception
     */
    public static function generateToken(string $name, int $timeout = self::DEFAULT_TIMEOUT): array
    {
        $elementName = self::sanitizeName($name);

        $hashElement = new Zend_Form_Element_Hash($elementName, [
            'salt' => 'episciences_csrf_' . $elementName,
            'timeout' => $timeout
        ]);

        // Initialize the token (this stores it in session)
        $hashElement->initCsrfToken();

        return [
            'name' => $hashElement->getName(),
            'value' => $hashElement->getValue()
        ];
    }

    /**
     * Validate a CSRF token
     *
     * @param string $name The token name used during generation
     * @param string $value The token value from the form submission
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(string $name, string $value): bool
    {
        $elementName = self::sanitizeName($name);

        try {
            $hashElement = new Zend_Form_Element_Hash($elementName, [
                'salt' => 'episciences_csrf_' . $elementName
            ]);

            return $hashElement->isValid($value);
        } catch (Exception $e) {
            trigger_error('CSRF validation error: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * Generate HTML hidden input for CSRF token
     *
     * @param string $name Unique identifier for this token
     * @param int $timeout Token timeout in seconds
     * @return string HTML input element
     * @throws Zend_Form_Exception
     */
    public static function getHiddenInput(string $name, int $timeout = self::DEFAULT_TIMEOUT): string
    {
        $token = self::generateToken($name, $timeout);
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars((string) $token['name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $token['value'], ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Validate CSRF token from Zend_Form without regenerating it
     *
     * This method directly accesses the session namespace where the token was stored
     * to avoid the token regeneration that happens when creating a new Zend_Form_Element_Hash.
     * Use this for forms with multiple CSRF tokens on the same page.
     *
     * @param string $elementName The CSRF element name (e.g., 'csrf_author_reply_form_123')
     * @param array $postData The POST data containing the token
     * @return bool True if valid, false otherwise
     */
    public static function validateFormToken(string $elementName, array $postData): bool
    {
        if (!isset($postData[$elementName])) {
            return false;
        }

        try {
            // Build the session namespace name as Zend_Form_Element_Hash does:
            // Zend_Form_Element_Hash_{salt}_{elementName}
            //
            // In CommentsManager::getForm(), the salt equals the elementName
            // So the namespace = Zend_Form_Element_Hash_{elementName}_{elementName}
            $salt = $elementName;
            $sessionName = 'Zend_Form_Element_Hash_' . $salt . '_' . $elementName;

            $session = new Zend_Session_Namespace($sessionName);

            // Use isset() - property_exists() doesn't work with Zend_Session_Namespace magic methods
            if (!isset($session->hash)) {
                trigger_error("CSRF token not found in session: $sessionName", E_USER_WARNING);
                return false;
            }

            $storedHash = $session->hash;
            $postedHash = $postData[$elementName];

            // Validate BEFORE clearing (prevents race condition)
            $isValid = ($storedHash === $postedHash);

            // Clear the token after validation (one-time use for security)
            unset($session->hash);

            if (!$isValid) {
                trigger_error("CSRF token mismatch for: $elementName", E_USER_WARNING);
            }

            return $isValid;
        } catch (Zend_Session_Exception $e) {
            trigger_error('CSRF session error: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        } catch (Exception $e) {
            trigger_error('CSRF validation error: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    /**
     * Get the per-session request token (creating it on first use).
     *
     * The token is stored under the same session key as the one used by
     * WebsiteDefaultController, so both share a single token per session.
     * Unlike the Zend_Form_Element_Hash tokens above it is reusable across
     * requests, which fits repeated AJAX calls on the same page.
     *
     * @throws Exception
     */
    public static function getSessionToken(): string
    {
        $session = new Zend_Session_Namespace(SESSION_NAMESPACE);

        if (empty($session->csrfToken)) {
            $session->csrfToken = bin2hex(random_bytes(32));
        }

        return (string)$session->csrfToken;
    }

    /**
     * Validate the per-session request token sent with a request, either as the
     * X-CSRF-Token header (AJAX calls) or as the csrf_token POST field (forms).
     */
    public static function validateRequestToken(Zend_Controller_Request_Http $request): bool
    {
        try {
            $session = new Zend_Session_Namespace(SESSION_NAMESPACE);
            $expected = $session->csrfToken;
        } catch (Exception $e) {
            trigger_error('Request token validation error: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $submitted = $request->getHeader('X-CSRF-Token');

        if (!is_string($submitted) || $submitted === '') {
            $submitted = $request->getPost('csrf_token');
        }

        return is_string($submitted) && $submitted !== '' && hash_equals($expected, $submitted);
    }

    /**
     * Sanitize the token name to be a valid form element name
     */
    private static function sanitizeName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    }
}