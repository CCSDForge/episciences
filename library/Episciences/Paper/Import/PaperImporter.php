<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences_Editor;
use Episciences_Paper;
use Episciences_Paper_Logger;
use Episciences_PapersManager;
use Episciences_Review;
use Episciences_Submit;
use Episciences_UserManager;
use Episciences_User_Assignment;
use RuntimeException;
use Throwable;
use Zend_Db_Table_Abstract;

/**
 * Imports a new paper or updates an existing one from one CSV row.
 *
 * Ported from scripts/update_papers.php's process_single_paper()/savePaper()/
 * processEditors()/reindex()/getMatchingPapers()/processInputParams()/
 * hasRequiredParams()/getDefaultUid().
 */
final class PaperImporter
{
    public function __construct(
        private readonly bool $dryRun,
        private readonly VolumeSectionResolver $volumeSectionResolver,
        private readonly PublicationDateResolver $publicationDateResolver,
    ) {
    }

    /**
     * @throws RuntimeException on failure — the caller is expected to catch \Throwable per row.
     */
    public function import(Row $row, int $rvid): Result
    {
        $identifier = $row->identifier;
        $version = $row->version !== null ? (float)$row->version : null;

        // getDoc() resolves $identifier/$version by reference (e.g. "latest" version lookup).
        $metadata = Episciences_Submit::getDoc($row->repoid, $identifier, $version, null, false);

        if (!$metadata || $metadata['status'] == 0) {
            throw new RuntimeException(sprintf(
                'metadata not found for: %s - %s - v%s',
                $row->repoid,
                $identifier,
                $version ?: 'latest'
            ));
        }

        $vid = $this->volumeSectionResolver->resolveVolumeId($rvid, $row);
        $sid = $this->volumeSectionResolver->resolveSectionId($rvid, $row);

        $matchingPapers = $this->getMatchingPapers($identifier, $row->docid, $rvid, $version);

        if (count($matchingPapers) === 0) {
            $isAnUpdate = false;
            $paper = new Episciences_Paper();
        } elseif (count($matchingPapers) === 1) {
            $isAnUpdate = true;
            $docid = array_shift($matchingPapers)['DOCID'];
            $paper = Episciences_PapersManager::get($docid);
            if (!$paper) {
                throw new RuntimeException('Paper #' . $docid . ' not found');
            }
        } else {
            throw new RuntimeException(
                'cannot update paper ' . $identifier . ': multiple matching papers ('
                . implode(',', array_keys($matchingPapers)) . ')'
            );
        }

        $editorsUids = $row->editors;

        $params = $this->processInputParams([
            'rvid' => $rvid,
            'repoid' => $row->repoid,
            'identifier' => $identifier,
            'version' => $version,
            'status' => $row->validatedStatus(),
            'vid' => $vid,
            'sid' => $sid,
            'uid' => $row->uid,
            'uuid' => $row->uid !== null ? Episciences_UserManager::getUuidFromUid($row->uid) : null,
            'publication_date' => $row->publicationDate,
            'editors' => $row->editors,
            'doi' => $row->doi,
            'docid' => $row->docid,
            'submission_date' => $row->submissionDate ?: date('Y-m-d H:i:s'),
        ], $paper, $isAnUpdate);

        $paper->setOptions($params);
        $paper->setVersion($version);
        $paper->setFlag('imported');
        $paper->setRecord($metadata['record']);

        if (!$paper->getUid()) {
            $paper->setUid($this->getDefaultUid());
        }

        if ($paper->isPublished()) {
            $newPublicationDate = $this->publicationDateResolver->resolve($row, $paper);
            if ($newPublicationDate !== $paper->getPublication_date()) {
                $paper->setPublication_date($newPublicationDate);
            }
            $paper->setType([Episciences_Paper::TITLE_TYPE => Episciences_Paper::ARTICLE_TYPE_TITLE]);
        }

        if (!$this->dryRun) {
            $this->savePaper($paper, $isAnUpdate, $editorsUids);
        }

        return new Result($isAnUpdate, (int)$paper->getDocid());
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function getMatchingPapers(?string $identifier, ?int $docid, int $rvid, ?float $version): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['DOCID'])
            ->where('RVID = ?', $rvid);

        if ($docid) {
            $select->where('DOCID = ?', $docid);
        } elseif ($identifier) {
            $select->where('IDENTIFIER LIKE ?', $identifier);
            if ($version) {
                $select->where('VERSION = ?', $version);
            }
        } else {
            return [];
        }

        return $db->fetchAssoc($select);
    }

    /**
     * If this is an update, missing/blank params are filled in from the existing paper.
     * Applies defaults for status/vid/sid, then checks required params.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function processInputParams(array $params, Episciences_Paper $paper, bool $isUpdate): array
    {
        foreach ($params as $param => $value) {
            if ($isUpdate && ($value === '' || $value === null)) {
                $method = 'get' . ucfirst(strtolower($param));
                if (method_exists($paper, $method)) {
                    $paperParam = $paper->$method();
                    if (is_array($paperParam)) {
                        foreach ($paperParam as $paramValueKey => $paramValue) {
                            $params[$param][$paramValueKey] = $paramValue;
                        }
                    } else {
                        $params[$param] = $paperParam;
                    }
                }
            }
        }

        $defaultParams = [
            'status' => Episciences_Paper::STATUS_PUBLISHED,
            'vid' => 0,
            'sid' => 0,
        ];

        foreach ($defaultParams as $paramKey => $defaultValue) {
            if (!array_key_exists($paramKey, $params) || $params[$paramKey] === null) {
                $params[$paramKey] = $defaultValue;
            }
        }

        if (!$this->hasRequiredParams($params)) {
            throw new RuntimeException('Missing required parameters (repoid, identifier, rvid)');
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function hasRequiredParams(array $params): bool
    {
        foreach (['repoid', 'identifier', 'rvid'] as $key) {
            if (empty($params[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pick a chief editor uid to use as the default contributor, oldest-registered first.
     */
    private function getDefaultUid(string $method = 'oldest'): ?int
    {
        $chiefEditors = Episciences_Review::getChiefEditors();
        unset($chiefEditors[1]); // root uid

        if (empty($chiefEditors)) {
            throw new RuntimeException('Missing contributor uid: this journal has no chief editors');
        }

        $uids = [];
        foreach ($chiefEditors as $uid => $user) {
            $uids[$uid] = $user->getTime_registered();
        }

        return match ($method) {
            'random' => (int)array_rand($uids),
            'oldest' => (int)array_search(min($uids), $uids, true),
            default => null,
        };
    }

    private function savePaper(Episciences_Paper $paper, bool $isUpdate, ?string $editorsUids): void
    {
        if (!$paper->save()) {
            throw new RuntimeException('paper could not be saved');
        }

        if (!$isUpdate) {
            $paper->log(Episciences_Paper_Logger::CODE_DOCUMENT_IMPORTED, $paper->getUid(), ['status' => $paper->getStatus()]);
            $paper->log(Episciences_Paper_Logger::CODE_STATUS, $paper->getUid(), ['status' => $paper->getStatus()]);
        }

        if ($editorsUids) {
            $this->processEditors(explode('-', $editorsUids), $paper);
        }

        if ($paper->isPublished()) {
            $this->reindex($paper);
        }
    }

    /**
     * @param array<int, string> $editorUids
     */
    private function processEditors(array $editorUids, Episciences_Paper $paper): void
    {
        foreach ($editorUids as $uid) {
            $uid = (int)$uid;
            $editor = new Episciences_Editor();
            // findWithCAS()'s @return docblock lies (claims always non-null); it can return null/false at runtime.
            // @phpstan-ignore booleanNot.alwaysFalse
            if (!$editor->findWithCAS($uid)) {
                continue;
            }

            $editor->setUuid(Episciences_UserManager::getUuidFromUid($editor->getUid()));

            $aid = $paper->assign($uid, Episciences_User_Assignment::ROLE_EDITOR);
            $paper->log(
                Episciences_Paper_Logger::CODE_EDITOR_ASSIGNMENT,
                EPISCIENCES_UID,
                ['aid' => $aid, 'user' => $editor->toArray()]
            );

            if ($paper->isPublished()) {
                $aid = $paper->unassign($uid, Episciences_User_Assignment::ROLE_EDITOR);
                $paper->log(
                    Episciences_Paper_Logger::CODE_EDITOR_UNASSIGNMENT,
                    EPISCIENCES_UID,
                    ['aid' => $aid, 'user' => $editor->toArray()]
                );
            }
        }
    }

    private function reindex(Episciences_Paper $paper): void
    {
        try {
            SolrIndexing::enqueueIndex($paper->getDocid());
        } catch (Throwable $e) {
            throw new RuntimeException('paper indexation failed for ' . $paper->getDocid() . ': ' . $e->getMessage());
        }
    }
}
