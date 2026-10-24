<?php

declare(strict_types=1);

namespace Episciences\User\Token;

use Closure;
use DateTimeImmutable;
use Zend_Db_Adapter_Abstract;

/**
 * One-off import of the account links sent before tokens were stored in the application database.
 *
 * Reads the CAS table T_UTILISATEURS_TOKENS (shared with other applications: read only, nothing is deleted) and keeps
 * the tokens of Episciences accounts that would still be valid under the new lifetimes. A legacy password token cannot
 * be told apart from the first password link of a reviewer account created by an editor, so it gets the longer
 * account setup lifetime.
 */
final class LegacyTokenImporter
{
    public const LEGACY_TABLE_NAME = 'T_UTILISATEURS_TOKENS';
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    /** @var array<string, TokenPurpose> legacy USAGE => purpose */
    private const PURPOSES = [
        'VALID' => TokenPurpose::ACCOUNT_ACTIVATION,
        'PASSWORD' => TokenPurpose::PASSWORD_RESET,
    ];

    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param Zend_Db_Adapter_Abstract $casDb CAS database (legacy tokens)
     * @param Zend_Db_Adapter_Abstract $appDb application database (Episciences accounts)
     * @param string $usersTable application table of the Episciences accounts
     * @param ?Closure(): DateTimeImmutable $clock defaults to the current time
     */
    public function __construct(
        private readonly Zend_Db_Adapter_Abstract $casDb,
        private readonly Zend_Db_Adapter_Abstract $appDb,
        private readonly AccountTokenRepository   $repository,
        private readonly string                   $usersTable = 'USER',
        ?Closure                                  $clock = null
    )
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @return array{candidates: int, imported: int, skipped: int}
     */
    public function import(bool $dryRun = false): array
    {
        $now = ($this->clock)();
        $rows = $this->fetchCandidates($now);
        $episciencesUids = $this->findEpisciencesUids(array_map(static fn(array $row): int => (int)$row['UID'], $rows));

        $imported = 0;

        foreach ($rows as $row) {
            // USAGE is compared case-insensitively in SQL (column collation): same here
            $purpose = self::PURPOSES[strtoupper((string)$row['USAGE'])] ?? null;
            $createdAt = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, (string)$row['TIME_MODIFIED']);

            if ($purpose === null || $createdAt === false || !isset($episciencesUids[(int)$row['UID']])) {
                continue;
            }

            $expiresAt = $createdAt->modify(sprintf('+%d seconds', self::legacyLifetime($purpose)));

            if ($expiresAt <= $now) {
                continue;
            }

            if ($dryRun) {
                $imported += $this->repository->canImportLegacy((string)$row['TOKEN']) ? 1 : 0;
                continue;
            }

            if ($this->repository->importLegacy((int)$row['UID'], (string)$row['EMAIL'], $purpose, (string)$row['TOKEN'], $createdAt, $expiresAt)) {
                $imported++;
            }
        }

        return ['candidates' => count($rows), 'imported' => $imported, 'skipped' => count($rows) - $imported];
    }

    private static function legacyLifetime(TokenPurpose $purpose): int
    {
        return $purpose === TokenPurpose::PASSWORD_RESET ? TokenPurpose::ACCOUNT_SETUP->ttlSeconds() : $purpose->ttlSeconds();
    }

    /**
     * Legacy tokens recent enough to be still valid under the longest lifetime
     * @return array<int, array<string, mixed>>
     */
    private function fetchCandidates(DateTimeImmutable $now): array
    {
        $longest = max(array_map(static fn(TokenPurpose $purpose): int => self::legacyLifetime($purpose), self::PURPOSES));

        $select = $this->casDb->select()
            ->from(self::LEGACY_TABLE_NAME, ['UID', 'EMAIL', 'TOKEN', 'TIME_MODIFIED', 'USAGE'])
            ->where($this->casDb->quoteIdentifier('USAGE') . ' IN (?)', array_keys(self::PURPOSES)) // USAGE is a reserved word
            ->where('TIME_MODIFIED > ?', $now->modify(sprintf('-%d seconds', $longest))->format(self::DATE_FORMAT));

        return $this->casDb->fetchAll($select);
    }

    /**
     * @param int[] $uids
     * @return array<int, true>
     */
    private function findEpisciencesUids(array $uids): array
    {
        $uids = array_values(array_unique($uids));

        if ($uids === []) {
            return [];
        }

        $found = [];

        foreach (array_chunk($uids, 1000) as $chunk) {
            $select = $this->appDb->select()->from($this->usersTable, ['UID'])->where('UID IN (?)', $chunk);

            foreach ($this->appDb->fetchCol($select) as $uid) {
                $found[(int)$uid] = true;
            }
        }

        return $found;
    }
}
