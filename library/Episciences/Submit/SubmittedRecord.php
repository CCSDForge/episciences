<?php

declare(strict_types=1);

namespace Episciences\Submit;

use Episciences_Repositories;
use Episciences_Repositories_Common;
use Episciences_Submit;
use Zend_Session_Namespace;

/**
 * Metadata record of a document submitted to a journal.
 *
 * The record shown to the author when the document is searched in the repository is the one
 * stored with the paper: it must never be read back from the submission form, where the client
 * can change it. It is remembered in the session when the document is searched, and fetched
 * again from the repository when it is no longer there.
 */
final class SubmittedRecord
{
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
     * Remember the record sent to the author after a repository search.
     */
    public static function remember(string $repoId, string $docId, float $version, string $record): void
    {
        $session = new Zend_Session_Namespace(self::SESSION_NAMESPACE);
        $records = is_array($session->records) ? $session->records : [];

        unset($records[self::key($repoId, $docId, $version)]);
        $records[self::key($repoId, $docId, $version)] = $record;

        $session->records = array_slice($records, -self::MAX_REMEMBERED, null, true);
    }

    /**
     * Record to store for a submitted document: the one remembered in the session, otherwise
     * fetched again from the repository. Returns null when the repository gives no record.
     */
    public static function resolve(string $repoId, string $docId, float $version, ?int $latestObsoleteDocId = null): ?string
    {
        $session = new Zend_Session_Namespace(self::SESSION_NAMESPACE);
        $records = is_array($session->records) ? $session->records : [];
        $key = self::key($repoId, $docId, $version);

        if (isset($records[$key]) && is_string($records[$key])) {
            return $records[$key];
        }

        $id = $docId;
        $fetchedVersion = $version;
        $response = Episciences_Submit::getDoc((int)$repoId, $id, $fetchedVersion, $latestObsoleteDocId);

        if (array_key_exists('error', $response) || !array_key_exists('record', $response)) {
            return null;
        }

        $record = self::clean($response, $repoId)['record'] ?? null;

        return is_string($record) && $record !== '' ? $record : null;
    }

    private static function key(string $repoId, string $docId, float $version): string
    {
        return sha1($repoId . '|' . $docId . '|' . $version);
    }
}
