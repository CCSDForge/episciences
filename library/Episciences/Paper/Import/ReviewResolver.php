<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

use Episciences_Review;
use Episciences_ReviewsManager;

/**
 * Resolves a journal from a CSV "rvid" column that may hold either a numeric
 * RVID or a review RVCODE, so a CSV author can use whichever they know.
 */
final class ReviewResolver
{
    public static function resolve(string $rvidOrCode): Episciences_Review|false
    {
        $value = trim($rvidOrCode);

        if ($value === '') {
            return false;
        }

        if (self::isNumericId($value)) {
            return Episciences_ReviewsManager::findByRvid((int)$value);
        }

        return Episciences_ReviewsManager::findByRvcode($value);
    }

    public static function isNumericId(string $value): bool
    {
        return ctype_digit($value);
    }
}