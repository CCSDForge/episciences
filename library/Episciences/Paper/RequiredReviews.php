<?php

declare(strict_types=1);

namespace Episciences\Paper;

use Episciences_Auth;
use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_Review;
use Zend_Db_Table_Abstract;

/**
 * Minimum number of completed reviews a version needs before an editor can accept it, publish it or ask for
 * a revision. The journal sets a minimum for its first N review rounds and another one for the later rounds;
 * without N, the minimum applies to every round.
 *
 * A round is the rank of the version among the versions of the paper (temporary versions included): the first
 * version is round 1.
 */
final class RequiredReviews
{
    /**
     * Minimum for the given round, from the raw journal settings (empty or invalid values count as 0;
     * an empty or non-positive number of rounds means every round).
     */
    public static function forRound(int $round, mixed $minimum, mixed $rounds = null, mixed $laterMinimum = null): int
    {
        $firstRoundsMinimum = self::nonNegative($minimum);
        $numberOfRounds = self::nonNegative($rounds);

        if ($numberOfRounds === 0 || $round <= $numberOfRounds) {
            return $firstRoundsMinimum;
        }

        return self::nonNegative($laterMinimum);
    }

    /**
     * Whether some round of the journal needs a minimum number of reviews.
     */
    public static function isEnabled(Episciences_Review $review): bool
    {
        [$minimum, $rounds, $laterMinimum] = self::settingsOf($review);

        return self::forRound(1, $minimum, $rounds, $laterMinimum) > 0
            || self::forRound(PHP_INT_MAX, $minimum, $rounds, $laterMinimum) > 0;
    }

    /**
     * Minimum the given version needs, from the settings of its journal.
     */
    public static function requiredFor(Episciences_Paper $paper, Episciences_Review $review): int
    {
        return self::forRound(self::roundOf($paper), ...self::settingsOf($review));
    }

    /**
     * Rank of the version among the versions of the paper, by submission date (temporary versions included).
     */
    public static function roundOf(Episciences_Paper $paper): int
    {
        $paperId = (int)$paper->getPaperid();
        $when = $paper->getWhen();

        if ($paperId <= 0 || $paperId === (int)$paper->getDocid() || !$when) {
            return 1;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['NB' => 'COUNT(*)'])
            ->where('PAPERID = ?', $paperId)
            ->where('`WHEN` < ?', $when);

        return 1 + (int)$db->fetchOne($select);
    }

    /**
     * Number of completed reviews of the version that count towards the minimum.
     */
    public static function completedFor(Episciences_Paper $paper): int
    {
        $paper->loadRatings(null, Episciences_Rating_Report::STATUS_COMPLETED);

        // only the review reports, and the reports of the request types set to count as such (#319)
        return count(Episciences_Paper::countedReportsOf($paper->getRatings(null, Episciences_Rating_Report::STATUS_COMPLETED)));
    }

    public static function isReached(Episciences_Paper $paper, Episciences_Review $review): bool
    {
        $required = self::requiredFor($paper, $review);

        return $required === 0 || self::completedFor($paper) >= $required;
    }

    /**
     * Whether the logged user may not accept, publish or ask for a revision of the version yet. Only editors and
     * guest editors are held back, as in the status menu; secretaries, chief editors and administrators never are.
     */
    public static function blocksLoggedEditor(Episciences_Paper $paper, Episciences_Review $review): bool
    {
        return !Episciences_Auth::isSecretary() && !self::isReached($paper, $review);
    }

    /**
     * @return array{mixed, mixed, mixed} minimum, number of rounds, later minimum
     */
    private static function settingsOf(Episciences_Review $review): array
    {
        return [
            $review->getSetting(Episciences_Review::SETTING_REQUIRED_REVIEWERS),
            $review->getSetting(Episciences_Review::SETTING_REQUIRED_REVIEWERS_ROUNDS),
            $review->getSetting(Episciences_Review::SETTING_REQUIRED_REVIEWERS_LATER_ROUNDS),
        ];
    }

    private static function nonNegative(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_string($value) && preg_match('/^\s*\d+\s*$/', $value) === 1) {
            return (int)$value;
        }

        return 0;
    }
}
