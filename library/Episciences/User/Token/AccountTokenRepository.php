<?php

declare(strict_types=1);

namespace Episciences\User\Token;

use Closure;
use DateTimeImmutable;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Account link tokens (account activation, password reset, account setup) stored in the application database.
 *
 * A link token is 32 random bytes in hexadecimal; only its SHA-256 is stored, so reading the table does not give
 * usable links. A token is accepted only for its purpose and until it expires, and only once: consuming it deletes it.
 * Issuing a token revokes the previous ones of the same account and purpose.
 */
class AccountTokenRepository
{
    public const TABLE_NAME = 'user_token';

    private const TOKEN_BYTES = 32;
    private const HASH_ALGORITHM = 'sha256';
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    /** Link token format: 64 hexadecimal characters */
    private const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';
    /**
     * Format of the tokens imported from the former CAS table (user:import-legacy-tokens).
     * To be removed once the imported tokens have expired.
     */
    private const LEGACY_TOKEN_PATTERN = '/^[0-9a-f]{40}$/';

    private ?Zend_Db_Adapter_Abstract $db;

    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param ?Zend_Db_Adapter_Abstract $db defaults to the application database
     * @param ?Closure(): DateTimeImmutable $clock defaults to the current time
     */
    public function __construct(?Zend_Db_Adapter_Abstract $db = null, ?Closure $clock = null)
    {
        $this->db = $db;
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * Creates a token and revokes the previous ones of the same account and purpose
     * @return string the link token, to be sent to the user (it cannot be read back later)
     */
    public function issue(int $uid, string $email, TokenPurpose $purpose): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $now = $this->now();
        $db = $this->getDb();

        $db->insert(self::TABLE_NAME, [
            'uid' => $uid,
            'email' => $email,
            'purpose' => $purpose->value,
            'token_hash' => self::hash($token),
            'created_at' => $now->format(self::DATE_FORMAT),
            'expires_at' => $now->modify(sprintf('+%d seconds', $purpose->ttlSeconds()))->format(self::DATE_FORMAT),
        ]);

        // Inserting first, then revoking the older tokens, keeps a single token even when two requests issue one
        // at the same time: each of them revokes the tokens issued before its own, so only the latest one is left
        $db->delete(self::TABLE_NAME, [
            'uid = ?' => $uid,
            'purpose = ?' => $purpose->value,
            'id < ?' => (int)$db->lastInsertId(self::TABLE_NAME, 'id'),
        ]);

        return $token;
    }

    /**
     * Valid token for one of the given purposes, without consuming it
     * @param mixed $presented the token taken from the request (any type: request values are untrusted)
     */
    public function find(mixed $presented, TokenPurpose ...$purposes): ?AccountToken
    {
        $hash = self::hashPresented($presented);

        if ($hash === null || $purposes === []) {
            return null;
        }

        $db = $this->getDb();
        $select = $db->select()
            ->from(self::TABLE_NAME, ['uid', 'email', 'purpose', 'expires_at'])
            ->where('token_hash = ?', $hash)
            ->where('purpose IN (?)', self::purposeValues($purposes))
            ->where('expires_at > ?', $this->now()->format(self::DATE_FORMAT));

        $row = $db->fetchRow($select);

        if (!is_array($row)) {
            return null;
        }

        $purpose = TokenPurpose::tryFrom((string)$row['purpose']);
        $expiresAt = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, (string)$row['expires_at']);

        if ($purpose === null || $expiresAt === false) {
            return null;
        }

        return new AccountToken((int)$row['uid'], (string)$row['email'], $purpose, $expiresAt);
    }

    /**
     * Valid token for one of the given purposes, deleted so that it cannot be used again.
     * When two requests present the same token, only one of them gets it.
     * @param mixed $presented the token taken from the request (any type: request values are untrusted)
     */
    public function consume(mixed $presented, TokenPurpose ...$purposes): ?AccountToken
    {
        $token = $this->find($presented, ...$purposes);

        if ($token === null) {
            return null;
        }

        $deleted = $this->getDb()->delete(self::TABLE_NAME, [
            'token_hash = ?' => self::hashPresented($presented),
            'purpose IN (?)' => self::purposeValues($purposes),
        ]);

        return $deleted === 1 ? $token : null;
    }

    /**
     * Puts back a consumed token whose action could not be completed, so that its link can be used again
     * until it expires
     */
    public function restore(string $presented, AccountToken $token): void
    {
        $hash = self::hashPresented($presented);

        if ($hash === null || $this->exists($hash)) {
            return;
        }

        $this->getDb()->insert(self::TABLE_NAME, [
            'uid' => $token->uid,
            'email' => $token->email,
            'purpose' => $token->purpose->value,
            'token_hash' => $hash,
            'created_at' => $this->now()->format(self::DATE_FORMAT),
            'expires_at' => $token->expiresAt->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * Revokes all the tokens of an account for the given purposes
     */
    public function revokeAll(int $uid, TokenPurpose ...$purposes): void
    {
        if ($purposes === []) {
            return;
        }

        $this->getDb()->delete(self::TABLE_NAME, [
            'uid = ?' => $uid,
            'purpose IN (?)' => self::purposeValues($purposes),
        ]);
    }

    /**
     * Deletes the expired tokens, by batches
     * @return int number of deleted tokens
     */
    public function purgeExpired(int $batchSize = 1000): int
    {
        $db = $this->getDb();
        $now = $this->now()->format(self::DATE_FORMAT);
        $total = 0;

        do {
            $ids = $db->fetchCol(
                $db->select()
                    ->from(self::TABLE_NAME, ['id'])
                    ->where('expires_at <= ?', $now)
                    ->limit(max(1, $batchSize))
            );

            if ($ids === []) {
                break;
            }

            $total += $db->delete(self::TABLE_NAME, ['id IN (?)' => $ids]);
        } while (count($ids) >= $batchSize);

        return $total;
    }

    /**
     * Stores a token sent before tokens were kept in this table (see user:import-legacy-tokens)
     * @return bool false when the token is malformed or already imported
     */
    public function importLegacy(int $uid, string $email, TokenPurpose $purpose, string $legacyToken, DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): bool
    {
        if (!$this->canImportLegacy($legacyToken)) {
            return false;
        }

        $this->getDb()->insert(self::TABLE_NAME, [
            'uid' => $uid,
            'email' => $email,
            'purpose' => $purpose->value,
            'token_hash' => self::hash($legacyToken),
            'created_at' => $createdAt->format(self::DATE_FORMAT),
            'expires_at' => $expiresAt->format(self::DATE_FORMAT),
        ]);

        return true;
    }

    /**
     * Whether importLegacy() would store a former token: well-formed and not imported yet
     */
    public function canImportLegacy(string $legacyToken): bool
    {
        $hash = self::hashPresented($legacyToken);

        return $hash !== null && !$this->exists($hash);
    }

    private function exists(string $hash): bool
    {
        $db = $this->getDb();
        $id = $db->fetchOne($db->select()->from(self::TABLE_NAME, ['id'])->where('token_hash = ?', $hash));

        return $id !== false && $id !== null;
    }

    private static function hash(string $token): string
    {
        return hash(self::HASH_ALGORITHM, $token);
    }

    /**
     * Hash of a well-formed presented token, null for anything else (no database query for garbage)
     */
    private static function hashPresented(mixed $presented): ?string
    {
        if (!is_string($presented)) {
            return null;
        }

        if (preg_match(self::TOKEN_PATTERN, $presented) !== 1 && preg_match(self::LEGACY_TOKEN_PATTERN, $presented) !== 1) {
            return null;
        }

        return self::hash($presented);
    }

    /**
     * @param TokenPurpose[] $purposes
     * @return string[]
     */
    private static function purposeValues(array $purposes): array
    {
        return array_map(static fn(TokenPurpose $purpose): string => $purpose->value, $purposes);
    }

    private function now(): DateTimeImmutable
    {
        return ($this->clock)();
    }

    private function getDb(): Zend_Db_Adapter_Abstract
    {
        return $this->db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
    }
}
