<?php

declare(strict_types=1);

namespace Episciences\Submit;

use Episciences\AppRegistry;
use Episciences_Repositories;
use Episciences_Repositories_Common;
use Episciences_Repositories_CryptologyePrint_Hooks;
use Episciences_Submit;
use Zend_Session_Namespace;

/**
 * Metadata record of a document submitted to a journal.
 *
 * The record shown to the author when the document is searched in the repository is the one
 * stored with the paper: it must never be read back from the submission form, where the client
 * can change it. It is remembered in the session when the document is searched, and fetched
 * again from the repository when it is no longer there. A remembered record is consumed by
 * resolve(): it does not stay in the session once the document is submitted.
 *
 * The other values computed from the repository response and posted back by the form are remembered
 * with the record for the same reason: the concept identifier (repositories exposing one, e.g. Zenodo),
 * the version given by the repository hook and the datetime pointing a specific version (e.g. IACR).
 */
final class SubmittedRecord
{
    public const RECORD = 'record';
    public const CONCEPT_IDENTIFIER = Episciences_Repositories_Common::CONCEPT_IDENTIFIER_KEY;
    public const VERSION = 'hookVersion';
    public const UPDATE_DATETIME = Episciences_Repositories_CryptologyePrint_Hooks::UPDATE_DATETIME;

    private const SESSION_NAMESPACE = 'submitted_records';
    private const MAX_REMEMBERED = 10;

    /**
     * Clean a record returned by Episciences_Submit::getDoc() (namespace, repository hooks).
     *
     * @param array<string, mixed> $response Response of Episciences_Submit::getDoc() holding a 'record'
     * @return array<string, mixed>
     */
    public static function clean(array $response, string $repoId): array
    {
        $response['record'] = (string)preg_replace('#xmlns="(.*)"#', '', (string)$response['record']);

        if ($repoId === Episciences_Repositories::CWI_REPO_ID) {
            $response['record'] = Episciences_Repositories_Common::checkAndCleanRecord($response['record']);
        }

        $hookResult = Episciences_Repositories::callHook('hookCleanXMLRecordInput', array_merge($response, ['repoId' => $repoId]));
        unset($hookResult['repoId']);

        return !empty($hookResult) ? $hookResult : $response;
    }

    /**
     * Remember the document sent to the author after a repository search, under the identifier and
     * version that the form posts back.
     *
     * @param array<string, mixed> $response cleaned response of Episciences_Submit::getDoc() holding a 'record'
     */
    public static function remember(string $repoId, string $docId, float $version, array $response): void
    {
        $document = self::documentFrom($response);

        if ($document === null) {
            return;
        }

        $session = new Zend_Session_Namespace(self::SESSION_NAMESPACE);
        $records = is_array($session->records) ? $session->records : [];

        unset($records[self::key($repoId, $docId, $version)]);
        $records[self::key($repoId, $docId, $version)] = $document;

        $session->records = array_slice($records, -self::MAX_REMEMBERED, null, true);
    }

    /**
     * Record to store for a submitted document: the one remembered in the session (and forgotten
     * at once), otherwise fetched again from the repository. Returns null when the repository
     * gives no record.
     */
    public static function resolve(string $repoId, string $docId, float $version, ?int $latestObsoleteDocId = null): ?string
    {
        return self::resolveDocument($repoId, $docId, $version, $latestObsoleteDocId)[self::RECORD] ?? null;
    }

    /**
     * Document to store for a submission (record, concept identifier, version, update datetime),
     * resolved as resolve() does. Returns null when the repository gives no record.
     *
     * @return array{record: string, conceptIdentifier: ?string, hookVersion: ?float, update: ?string}|null
     */
    public static function resolveDocument(string $repoId, string $docId, float $version, ?int $latestObsoleteDocId = null): ?array
    {
        $session = new Zend_Session_Namespace(self::SESSION_NAMESPACE);
        $records = is_array($session->records) ? $session->records : [];
        $key = self::key($repoId, $docId, $version);

        // A record remembered alone (former format) is fetched again, so as not to lose the other values
        if (isset($records[$key])) {
            $remembered = is_array($records[$key]) ? self::documentFrom($records[$key]) : null;
            unset($records[$key]);
            $session->records = $records;

            if ($remembered !== null) {
                return $remembered;
            }
        }

        AppRegistry::getMonoLogger()?->warning(sprintf(
            'Submitted record not found in session (repository #%s): fetching it again',
            $repoId
        ));

        $id = $docId;
        $fetchedVersion = $version;
        $response = Episciences_Submit::getDoc((int)$repoId, $id, $fetchedVersion, $latestObsoleteDocId);

        if (array_key_exists('error', $response) || !array_key_exists('record', $response)) {
            AppRegistry::getMonoLogger()?->warning(sprintf(
                'Submitted record could not be fetched again from repository #%s',
                $repoId
            ));

            return null;
        }

        return self::documentFrom(self::clean($response, $repoId));
    }

    /**
     * @param array<mixed> $response
     * @return array{record: string, conceptIdentifier: ?string, hookVersion: ?float, update: ?string}|null
     */
    private static function documentFrom(array $response): ?array
    {
        $record = $response[self::RECORD] ?? null;

        if (!is_string($record) || $record === '') {
            return null;
        }

        $version = $response[self::VERSION] ?? null;

        return [
            self::RECORD => $record,
            self::CONCEPT_IDENTIFIER => self::nonEmptyString($response[self::CONCEPT_IDENTIFIER] ?? null),
            self::VERSION => is_numeric($version) ? (float)$version : null,
            self::UPDATE_DATETIME => self::nonEmptyString($response[self::UPDATE_DATETIME] ?? null),
        ];
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_scalar($value) && (string)$value !== '' ? (string)$value : null;
    }

    private static function key(string $repoId, string $docId, float $version): string
    {
        return sha1($repoId . '|' . $docId . '|' . $version);
    }
}
