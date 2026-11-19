<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Comment;
use Episciences_CommentsManager;
use Episciences_Paper;
use Episciences_Review;
use Zend_Db_Table_Abstract;
use Zend_Json;

/**
 * Request type of the reviewers on the next version of a paper (#319, lot B).
 *
 * When asking for a revision, the editorial team may choose the type of the new request made to the reviewers on the
 * version the author will submit. The choice is stored with the revision request; without a choice, the reviewers are
 * invited again with the type of each of their current requests, as before.
 */
final class NextVersionRequestType
{
    /** Option of the revision request (Episciences_Comment) and name of the form field */
    public const OPTION = 'next_request_type_id';

    /** Label of the empty choice */
    public const SAME_TYPE_LABEL = 'Même type que la demande en cours';

    /**
     * Choices of the revision request form: the empty "same type" choice first, then the enabled types of the journal.
     *
     * @return array<int|string, string>
     */
    public static function choices(int $rvid, string $locale, ?RequestTypeRepository $repository = null): array
    {
        $choices = ['' => self::SAME_TYPE_LABEL];

        foreach (($repository ?? new RequestTypeRepository())->findAllForJournal($rvid, true) as $type) {
            $choices[(int)$type->getId()] = $type->getLabel($locale);
        }

        return $choices;
    }

    /**
     * The posted type, when it is an enabled type of the journal; null for the "same type" choice or any other value.
     */
    public static function fromPost(mixed $value, int $rvid, ?RequestTypeRepository $repository = null): ?int
    {
        if (!is_scalar($value) || preg_match('/^[1-9]\d*$/', (string)$value) !== 1) {
            return null;
        }

        $type = ($repository ?? new RequestTypeRepository())->findUsable((int)$value, $rvid);

        return $type?->getId();
    }

    /**
     * Type chosen in a revision request, null without a choice.
     */
    public static function ofRequest(Episciences_Comment $revisionRequest): ?int
    {
        return self::positiveInt($revisionRequest->getOption(self::OPTION));
    }

    /**
     * Type chosen for this version: the one of the latest revision request of the previous version, if any.
     */
    public static function forVersion(Episciences_Paper $paper): ?int
    {
        $paperId = (int)$paper->getPaperid();
        $when = $paper->getWhen();

        if ($paperId <= 0 || $paperId === (int)$paper->getDocid() || !$when) {
            return null;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $previousDocId = $db->fetchOne(
            $db->select()
                ->from(T_PAPERS, ['DOCID'])
                ->where('PAPERID = ?', $paperId)
                ->where('`WHEN` < ?', $when)
                ->order('WHEN DESC')
                ->limit(1)
        );

        if (!$previousDocId) {
            return null;
        }

        $options = $db->fetchOne(
            $db->select()
                ->from(T_PAPER_COMMENTS, ['OPTIONS'])
                ->where('DOCID = ?', (int)$previousDocId)
                ->where('TYPE = ?', Episciences_CommentsManager::TYPE_REVISION_REQUEST)
                ->order('WHEN DESC')
                ->limit(1)
        );

        if (!is_string($options) || $options === '') {
            return null;
        }

        try {
            $decoded = Zend_Json::decode($options);
        } catch (\Zend_Json_Exception) {
            return null;
        }

        return is_array($decoded) ? self::positiveInt($decoded[self::OPTION] ?? null) : null;
    }

    /**
     * Reviewer requests of the new invitations: with a chosen type, one request of this type per reviewer;
     * without, the given requests (one per request of each reviewer, with its type).
     *
     * @template T
     * @param list<array{T, int|null}> $requests reviewer and request type of each current request
     * @return list<array{T, int|null}>
     */
    public static function requestsFor(array $requests, ?int $chosenTypeId): array
    {
        if ($chosenTypeId === null) {
            return $requests;
        }

        $byReviewer = [];

        foreach ($requests as [$reviewer, $requestTypeId]) {
            $key = is_object($reviewer) ? spl_object_id($reviewer) : (string)$reviewer;
            $byReviewer[$key] ??= [$reviewer, $chosenTypeId];
        }

        return array_values($byReviewer);
    }

    /**
     * Rating deadline interval of a new request: the default deadline of the chosen type, else the journal setting.
     */
    public static function deadlineInterval(?RequestType $chosenType, Episciences_Review $review): string
    {
        $interval = $chosenType?->getDefaultDeadline();

        if ($interval !== null && $interval !== '') {
            return $interval;
        }

        return (string)$review->getSetting(Episciences_Review::SETTING_RATING_DEADLINE);
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }
}
