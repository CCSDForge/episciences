<?php
declare(strict_types=1);
class Episciences_Paper_Authors_AffiliationHelper
{
    public const ID_TYPE_ROR = 'ROR';
    private const KEY_NAME = 'name';
    private const KEY_ID = 'id';
    private const KEY_ID_TYPE = 'id-type';
    private const KEY_ACRONYM = 'acronym';
    private const ACRONYM_SEPARATOR = '||';

    /**
     * Build a structured affiliation array with ROR identifier
     *
     * @param array{name: string, ROR: string, acronym?: string} $affiliationData
     * @return array structured affiliation for DB storage
     */
    public static function buildWithRor(array $affiliationData): array
    {
        $structured = [
            self::KEY_NAME => $affiliationData[self::KEY_NAME],
            self::KEY_ID => [
                [
                    self::KEY_ID => $affiliationData[self::ID_TYPE_ROR],
                    self::KEY_ID_TYPE => self::ID_TYPE_ROR
                ]
            ]
        ];

        if (array_key_exists(self::KEY_ACRONYM, $affiliationData)) {
            $structured[self::KEY_ID][0][self::KEY_ACRONYM] = $affiliationData[self::KEY_ACRONYM];
        }

        return $structured;
    }

    /**
     * Build a structured affiliation array with only a name (no ROR)
     *
     * @param string $name affiliation name
     * @return array{name: string}
     */
    public static function buildNameOnly(string $name): array
    {
        return [self::KEY_NAME => $name];
    }

    /**
     * Build an identifier-only array (ROR + optional acronym) for attaching to an existing affiliation
     *
     * @param string $rorUrl full ROR URL
     * @param string|null $acronym optional acronym
     * @return array[] single-element array with ROR identifier
     */
    public static function buildRorOnly(string $rorUrl, ?string $acronym): array
    {
        $identifier = [
            self::KEY_ID => $rorUrl,
            self::KEY_ID_TYPE => self::ID_TYPE_ROR
        ];

        if ($acronym !== null && $acronym !== '') {
            $identifier[self::KEY_ACRONYM] = $acronym;
        }

        return [$identifier];
    }

    /**
     * Check whether an affiliation already has a ROR identifier
     *
     * @param array $authorAffiliation single affiliation entry from the DB
     */
    public static function hasRor(array $authorAffiliation): bool
    {
        if (!isset($authorAffiliation[self::KEY_ID])) {
            return false;
        }

        foreach ($authorAffiliation[self::KEY_ID] as $identifier) {
            if ($identifier[self::KEY_ID_TYPE] === self::ID_TYPE_ROR) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an affiliation has an acronym attached
     *
     * @param array $authorAffiliation single affiliation entry from the DB
     */
    public static function hasAcronym(array $authorAffiliation): bool
    {
        if (!isset($authorAffiliation[self::KEY_ID])) {
            return false;
        }

        foreach ($authorAffiliation[self::KEY_ID] as $identifier) {
            if (array_key_exists(self::KEY_ACRONYM, $identifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a given acronym already exists in an affiliation identifier
     *
     * @param array $affiliationIdentifiers identifier array (e.g. from $affiliation['id'])
     * @param string $acronym acronym to check
     */
    public static function isAcronymDuplicate(array $affiliationIdentifiers, string $acronym): bool
    {
        foreach ($affiliationIdentifiers as $identifier) {
            if (isset($identifier[self::KEY_ACRONYM]) && $identifier[self::KEY_ACRONYM] === $acronym) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect all unique acronyms from an author's affiliations, formatted as "[ACRONYM]"
     *
     * @param array $affiliationsFromDb all affiliations for one author
     * @return string formatted acronym list separated by "||", or empty string
     */
    public static function getExistingAcronyms(array $affiliationsFromDb): string
    {
        $uniqueAcronyms = [];

        foreach ($affiliationsFromDb as $affiliation) {
            if (!isset($affiliation[self::KEY_ID])) {
                continue;
            }

            foreach ($affiliation[self::KEY_ID] as $identifier) {
                if (array_key_exists(self::KEY_ACRONYM, $identifier)) {
                    $uniqueAcronyms[] = $identifier[self::KEY_ACRONYM];
                }
            }
        }

        if ($uniqueAcronyms === []) {
            return '';
        }

        $uniqueAcronyms = array_unique($uniqueAcronyms);
        $bracketedAcronyms = array_map(static fn(string $acronym): string => '[' . $acronym . ']', $uniqueAcronyms);

        return self::formatAcronymList($bracketedAcronyms);
    }

    /**
     * Join acronym entries with the "||" separator
     *
     * @param array $acronymList list of formatted acronym strings
     */
    public static function formatAcronymList(array $acronymList): string
    {
        return implode(self::ACRONYM_SEPARATOR, $acronymList);
    }

    /**
     * Find the matching acronym from a list that appears in the given haystack string
     *
     * @param array $acronyms list of acronyms to search for
     * @param string $haystack string to search within
     * @return string matched acronym, or empty string if none found
     */
    public static function setOrUpdateRorAcronym(array $acronyms, string $haystack): string
    {
        $matchedAcronym = '';

        foreach ($acronyms as $acronym) {
            if ($acronym !== '' && str_contains($haystack, (string) $acronym)) {
                $matchedAcronym = $acronym;
                break;
            }
        }

        return $matchedAcronym;
    }

    /**
     * Remove an acronym substring from an affiliation name (used for export)
     *
     * @param string $affiliationName full affiliation name
     * @param string $acronym acronym to remove
     * @return string cleaned name
     */
    public static function eraseAcronymInName(string $affiliationName, string $acronym): string
    {
        return rtrim(str_replace($acronym, '', $affiliationName));
    }

    /**
     * Strip surrounding brackets from an acronym string, e.g. "[CNRS]" → "CNRS"
     *
     * @param string $bracketedAcronym acronym with brackets
     * @return string acronym without brackets
     */
    public static function cleanAcronym(string $bracketedAcronym): string
    {
        return substr(trim($bracketedAcronym), 1, -1);
    }

    /**
     * Format affiliations for the ROR input widget in the paper view
     *
     * @param array $affiliations list of affiliation entries
     * @return array<int, string> formatted affiliation strings
     */
    public static function formatAffiliationForInputRor(array $affiliations): array
    {
        $formatted = [];

        foreach ($affiliations as $affiliation) {
            $rorSuffix = '';
            $acronymSuffix = '';

            if (array_key_exists(self::KEY_ID, $affiliation)) {
                $rorSuffix = ' #' . $affiliation[self::KEY_ID][0][self::KEY_ID];

                if (array_key_exists(self::KEY_ACRONYM, $affiliation[self::KEY_ID][0])) {
                    $acronymSuffix = ' [' . $affiliation[self::KEY_ID][0][self::KEY_ACRONYM] . ']';
                }
            }

            $formatted[] = $affiliation[self::KEY_NAME] . $acronymSuffix . $rorSuffix;
        }

        return $formatted;
    }
}
