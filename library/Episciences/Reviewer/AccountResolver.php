<?php

declare(strict_types=1);

/**
 * Resolves (looks up or creates) a real CAS reviewer account from an invitation
 * that was sent to a temporary account / bare email address.
 *
 * The pure helpers (login generation, password generation) carry no side effect
 * and are unit-testable without a database; the lookup/creation helpers are thin
 * wrappers around the CAS user mapper and the Episciences reviewer entity.
 */
class Episciences_Reviewer_AccountResolver
{
    /** Fallback login base when neither the name nor the email yields usable characters. */
    public const DEFAULT_LOGIN_BASE = 'reviewer';

    /** Hard cap on suffix increments while searching for a free login. */
    private const MAX_LOGIN_ATTEMPTS = 100000;

    /**
     * Builds the base login: first letter of the first name + last name, lowercased
     * and transliterated to ASCII, keeping only [a-z0-9].
     *
     * When the first name OR the last name is empty, the local part of the email
     * (before '@') is used instead.
     *
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @return string Non-empty login base (defaults to {@see self::DEFAULT_LOGIN_BASE}).
     */
    public static function generateLoginBase(string $firstname, string $lastname, string $email): string
    {
        $normalizedFirst = self::normalize($firstname);
        $normalizedLast = self::normalize($lastname);

        if ($normalizedFirst !== '' && $normalizedLast !== '') {
            $base = substr($normalizedFirst, 0, 1) . $normalizedLast;
        } else {
            $localPart = strstr($email, '@', true);
            $base = self::normalize($localPart === false ? $email : $localPart);
        }

        return $base !== '' ? $base : self::DEFAULT_LOGIN_BASE;
    }

    /**
     * Returns an available login derived from the base, appending an incrementing
     * numeric suffix until the provided existence checker reports it free.
     *
     * @param string $base
     * @param callable(string):bool $exists Returns true when the candidate already exists.
     * @return string
     */
    public static function resolveAvailableLogin(string $base, callable $exists): string
    {
        $base = $base !== '' ? $base : self::DEFAULT_LOGIN_BASE;

        if (!$exists($base)) {
            return $base;
        }

        for ($suffix = 1; $suffix <= self::MAX_LOGIN_ATTEMPTS; $suffix++) {
            $candidate = $base . $suffix;
            if (!$exists($candidate)) {
                return $candidate;
            }
        }

        // Extremely unlikely fallback: append a random component.
        return $base . random_int(self::MAX_LOGIN_ATTEMPTS, PHP_INT_MAX);
    }

    /**
     * Generates a cryptographically strong random password containing at least one
     * lowercase letter, one uppercase letter, one digit and one symbol.
     *
     * @param int $length Desired length (forced to a minimum of 16).
     * @return string
     * @throws Exception When no source of randomness is available.
     */
    public static function generateStrongPassword(int $length = 32): string
    {
        $length = max(16, $length);

        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%^&*()-_=+[]{}?';
        $all = $upper . $lower . $digits . $symbols;

        $chars = [
            self::randomChar($upper),
            self::randomChar($lower),
            self::randomChar($digits),
            self::randomChar($symbols),
        ];

        for ($i = count($chars); $i < $length; $i++) {
            $chars[] = self::randomChar($all);
        }

        // Fisher-Yates shuffle with a CSPRNG so the guaranteed characters are not
        // always at the front.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /**
     * Returns the UID and username of the most recently modified VALID CAS account
     * for this email, or null when none exists.
     *
     * @param string $email
     * @return array{uid: int, username: string}|null
     * @throws Exception
     */
    public static function findValidAccountByEmail(string $email): ?array
    {
        $mapper = new Ccsd_User_Models_UserMapper();
        $account = $mapper->findMostRecentValidByEmail($email);

        if ($account === null) {
            return null;
        }

        return ['uid' => $account['UID'], 'username' => $account['USERNAME']];
    }

    /**
     * Returns the UID of the most recently modified VALID CAS account for this email,
     * or null when none exists.
     *
     * @param string $email
     * @return int|null
     * @throws Exception
     */
    public static function findValidAccountUidByEmail(string $email): ?int
    {
        $account = self::findValidAccountByEmail($email);

        return $account !== null ? $account['uid'] : null;
    }

    /**
     * Whether a CAS username already exists (valid or not).
     *
     * @param string $username
     * @return bool
     * @throws Exception
     */
    public static function usernameExists(string $username): bool
    {
        return (new Ccsd_User_Models_UserMapper())->usernameExists($username);
    }

    /**
     * Loads an existing CAS account as a reviewer, granting the reviewer role and
     * filling the screen name / language when missing. Does NOT change the session
     * identity.
     *
     * @param int $uid
     * @return Episciences_Reviewer|null Null when the CAS account cannot be loaded.
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public static function attachExistingAccount(int $uid): ?Episciences_Reviewer
    {
        $user = new Episciences_Reviewer();
        $user->findWithCAS($uid);

        // findWithCAS() loads CAS + local data; an unknown UID leaves it unpopulated.
        if (!$user->getUid()) {
            return null;
        }

        $needsSave = false;

        if (!$user->getScreenName()) {
            $user->setScreenName($user->getFullName());
            $needsSave = true;
        }

        if (!$user->getLangueid()) {
            $user->setLangueid(Episciences_Review::DEFAULT_LANG);
            $needsSave = true;
        }

        if ($needsSave) {
            $user->save();
        }

        $user->addRole(Episciences_Acl::ROLE_REVIEWER);

        return $user;
    }

    /**
     * Creates a brand-new valid CAS reviewer account with a strong random password,
     * granting the reviewer role. Does NOT change the session identity.
     *
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @param string|null $lang Two-letter language code; defaults to the review default.
     * @param string $login Pre-resolved, available login.
     * @param string $plainPassword Strong random password (will be hashed by the entity).
     * @return Episciences_Reviewer
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public static function createReviewerAccount(
        string  $firstname,
        string  $lastname,
        string  $email,
        ?string $lang,
        string  $login,
        string  $plainPassword
    ): Episciences_Reviewer
    {
        $user = new Episciences_Reviewer();
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setEmail($email);
        $user->setUsername($login);
        $user->setPassword($plainPassword);
        $user->setLangueid($lang ?: Episciences_Review::DEFAULT_LANG);
        $user->setTime_registered();
        $user->setValid(1);

        $uid = (int)$user->save();
        $user->setUid($uid);

        $user->addRole(Episciences_Acl::ROLE_REVIEWER);
        $user->setScreenName($user->getFullName());

        return $user;
    }

    /**
     * Extracts the reviewer identity (names, email, language) from an assignment,
     * whether it points to a temporary account or a real CAS account.
     *
     * @param Episciences_User_Assignment $assignment
     * @return array{isTmp: bool, firstname: string, lastname: string, email: string, lang: ?string, username: string}|null
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public static function getIdentityFromAssignment(Episciences_User_Assignment $assignment): ?array
    {
        if ($assignment->isTmp_user()) {
            $tmp = Episciences_TmpUsersManager::findById($assignment->getUid());
            if (!$tmp) {
                return null;
            }

            return [
                'isTmp'     => true,
                'firstname' => (string) $tmp->getFirstname(),
                'lastname'  => (string) $tmp->getLastname(),
                'email'     => (string) $tmp->getEmail(),
                'lang'      => $tmp->getLangueid(true) ?: null,
                'username'  => '',
            ];
        }

        $reviewer = new Episciences_Reviewer();
        $reviewer->findWithCAS($assignment->getUid());
        if (!$reviewer->getUid()) {
            return null;
        }

        return [
            'isTmp'     => false,
            'firstname' => (string) $reviewer->getFirstname(),
            'lastname'  => (string) $reviewer->getLastname(),
            'email'     => (string) $reviewer->getEmail(),
            'lang'      => $reviewer->getLangueid(true) ?: null,
            'username'  => (string) $reviewer->getUsername(),
        ];
    }

    /**
     * Computes a non-destructive preview of the account that will be used (existing
     * valid CAS account) or created (new login) when accepting on behalf.
     *
     * @param Episciences_User_Assignment $assignment
     * @return array{fullName: string, email: string, mode: string, login: string}|null
     * @throws Zend_Db_Adapter_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public static function buildAcceptancePreview(Episciences_User_Assignment $assignment): ?array
    {
        $identity = self::getIdentityFromAssignment($assignment);
        if ($identity === null) {
            return null;
        }

        $fullName = trim($identity['firstname'] . ' ' . $identity['lastname']);

        if (!$identity['isTmp']) {
            return ['fullName' => $fullName, 'email' => $identity['email'], 'mode' => 'attach', 'login' => $identity['username']];
        }

        $existing = self::findValidAccountByEmail($identity['email']);
        if ($existing !== null) {
            return ['fullName' => $fullName, 'email' => $identity['email'], 'mode' => 'attach', 'login' => $existing['username']];
        }

        $base  = self::generateLoginBase($identity['firstname'], $identity['lastname'], $identity['email']);
        $login = self::resolveAvailableLogin(
            $base,
            static fn(string $username): bool => self::usernameExists($username)
        );

        return ['fullName' => $fullName, 'email' => $identity['email'], 'mode' => 'create', 'login' => $login];
    }

    /**
     * Resolves (looks up or creates) the real reviewer account that the invitation
     * acceptance will be attached to.
     *
     * @param Episciences_User_Assignment $assignment
     * @param string $preferredLogin Login computed at preview time; used as-is when still
     *                               available to guarantee the modal confirmation is accurate.
     * @return array{user: Episciences_Reviewer, created: bool}|null
     * @throws Exception
     */
    public static function resolveForAcceptance(Episciences_User_Assignment $assignment, string $preferredLogin = ''): ?array
    {
        $identity = self::getIdentityFromAssignment($assignment);
        if ($identity === null) {
            return null;
        }

        if (!$identity['isTmp']) {
            $user = self::attachExistingAccount((int) $assignment->getUid());
            return $user ? ['user' => $user, 'created' => false] : null;
        }

        $existing = self::findValidAccountByEmail($identity['email']);
        if ($existing !== null) {
            $user = self::attachExistingAccount($existing['uid']);
            return $user ? ['user' => $user, 'created' => false] : null;
        }

        $base = self::generateLoginBase($identity['firstname'], $identity['lastname'], $identity['email']);

        // Re-use the login shown in the confirmation modal when it is still free and
        // syntactically valid; recompute otherwise (extremely unlikely race condition).
        $isValidLogin = $preferredLogin !== '' && (bool) preg_match('/^[a-z0-9]+$/', $preferredLogin);
        $login = ($isValidLogin && !self::usernameExists($preferredLogin))
            ? $preferredLogin
            : self::resolveAvailableLogin(
                $base,
                static fn(string $username): bool => self::usernameExists($username)
            );

        $password = self::generateStrongPassword();

        $user = self::createReviewerAccount(
            $identity['firstname'],
            $identity['lastname'],
            $identity['email'],
            $identity['lang'],
            $login,
            $password
        );

        return ['user' => $user, 'created' => true];
    }

    /**
     * Sends a password-reset e-mail to a freshly created account so the reviewer
     * can set their own password and access the review.
     *
     * @param Episciences_Reviewer $user
     * @return void
     * @throws Zend_Exception
     * @throws Zend_Mail_Exception
     */
    public static function sendNewAccountPasswordEmail(Episciences_Reviewer $user): void
    {
        $userToken = new Ccsd_User_Models_UserTokens([
            'UID'   => $user->getUid(),
            'EMAIL' => $user->getEmail(),
            'USAGE' => 'PASSWORD',
        ]);
        $userToken->generateUserToken();
        (new Ccsd_User_Models_UserTokensMapper())->save($userToken);

        $locale = $user->getLangueid(true);

        $template = new Episciences_Mail_Template();
        $template->findByKey(Episciences_Mail_TemplatesManager::TYPE_USER_LOST_PASSWORD);
        $template->loadTranslations();
        $template->setLocale($locale);

        $url = APPLICATION_URL . Zend_Controller_Front::getInstance()->getRouter()->assemble([
            'controller' => 'user',
            'action'     => 'resetpassword',
            'lang'       => $locale,
            'token'      => $userToken->getToken(),
        ], null, true);

        $mail = new Episciences_Mail();
        $mail->addTag(Episciences_Mail_Tags::TAG_TOKEN_VALIDATION_LINK, $url);
        $mail->setFromReview();
        $mail->setTo($user);
        $mail->setSubject($template->getSubject());
        $mail->setTemplate($template->getPath(), $template->getKey() . '.phtml');
        $mail->writeMail();
    }

    /**
     * Transliterates to ASCII, lowercases and keeps only [a-z0-9].
     *
     * @param string $text
     * @return string
     */
    private static function normalize(string $text): string
    {
        $text = Ccsd_Tools::stripAccents($text);
        $text = strtolower($text);

        return (string)preg_replace('/[^a-z0-9]/', '', $text);
    }

    /**
     * Returns one random character from the given non-empty alphabet using a CSPRNG.
     *
     * @param string $alphabet
     * @return string
     * @throws Exception
     */
    private static function randomChar(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
