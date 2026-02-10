<?php

class Episciences_Paper_Authors_ViewFormatter
{
    private const KEY_AFFILIATION = 'affiliation';
    private const KEY_ORCID = 'orcid';
    private const KEY_FULLNAME = 'fullname';
    private const KEY_NAME = 'name';
    private const KEY_ID = 'id';
    private const KEY_ID_TYPE = 'id-type';
    private const KEY_ACRONYM = 'acronym';
    private const KEY_URL = 'url';
    private const KEY_TYPE = 'type';

    private const ORCID_BASE_URL = 'https://orcid.org/';
    private const ORCID_PLACEHOLDER = 'NULL';
    private const AUTHOR_SEPARATOR = '; ';
    private const AUTHOR_LIST_SEPARATOR = ';';
    private const ORCID_SEPARATOR = '##';

    private const RESULT_KEY_TEMPLATE = 'template';
    private const RESULT_KEY_ORCID = 'orcid';
    private const RESULT_KEY_LIST_AFFI = 'listAffi';
    private const RESULT_KEY_AUTHORS_LIST = 'authorsList';
    private const RESULT_KEY_AFFILIATION_NUMERIC = 'affiliationNumeric';
    private const RESULT_KEY_AUTHORS = 'authors';

    /**
     * Build HTML template for author enrichment display (ORCID icons, affiliation superscripts, affiliation list)
     *
     * @param int|string $paperId
     * @return array{template: string, orcid: string, listAffi: string, authorsList: string}
     */
    public static function formatAuthorEnrichmentForViewByPaper(int|string $paperId): array
    {
        $decodedAuthors = [];
        // One row per paper expected; loop processes the single row
        foreach (Episciences_Paper_Authors_Repository::getAuthorByPaperId($paperId) as $row) {
            $decodedAuthors = json_decode($row['authors'], true);
        }

        $templateHtml = '';
        $orcidText = '';
        $authorsList = '';
        $affiliationListHtml = '';

        if (empty($decodedAuthors)) {
            return self::buildResult($templateHtml, $orcidText, $affiliationListHtml, $authorsList);
        }

        $uniqueAffiliations = self::collectUniqueAffiliations($decodedAuthors);
        $authorCount = count($decodedAuthors);

        foreach ($decodedAuthors as $authorIndex => $author) {
            $rawFullname = $author[self::KEY_FULLNAME];
            $escapedFullname = htmlspecialchars($rawFullname, ENT_QUOTES, 'UTF-8');
            $authorsList .= $rawFullname;

            $templateHtml .= self::buildAuthorHtml($author, $escapedFullname);
            $orcidText .= self::buildOrcidText($author);

            if (array_key_exists(self::KEY_AFFILIATION, $author)) {
                $templateHtml .= self::buildAffiliationSuperscripts($author[self::KEY_AFFILIATION], $uniqueAffiliations);
            }

            if ($authorIndex !== $authorCount - 1) {
                $authorsList .= self::AUTHOR_LIST_SEPARATOR;
                $templateHtml .= self::AUTHOR_SEPARATOR;
                $orcidText .= self::ORCID_SEPARATOR;
            }
        }

        $affiliationListHtml = self::buildAffiliationListHtml($uniqueAffiliations);

        return self::buildResult($templateHtml, $orcidText, $affiliationListHtml, $authorsList);
    }

    /**
     * Collect all unique affiliations from all authors (deduplicated)
     *
     * @param array $decodedAuthors all authors with their affiliations
     * @return array<int, array{affiliation: string, url?: string, acronym?: string}> reindexed unique affiliations
     */
    private static function collectUniqueAffiliations(array $decodedAuthors): array
    {
        $allAffiliations = [];

        foreach ($decodedAuthors as $author) {
            if (!array_key_exists(self::KEY_AFFILIATION, $author)) {
                continue;
            }

            foreach ($author[self::KEY_AFFILIATION] as $affiliation) {
                $affiliationEntry = [self::KEY_AFFILIATION => $affiliation[self::KEY_NAME]];

                if (array_key_exists(self::KEY_ID, $affiliation)) {
                    $affiliationEntry[self::KEY_URL] = $affiliation[self::KEY_ID][0][self::KEY_ID];
                    if (array_key_exists(self::KEY_ACRONYM, $affiliation[self::KEY_ID][0])) {
                        $affiliationEntry[self::KEY_ACRONYM] = $affiliation[self::KEY_ID][0][self::KEY_ACRONYM];
                    }
                }

                $allAffiliations[] = $affiliationEntry;
            }
        }

        $serialized = array_map('serialize', $allAffiliations);
        $uniqueSerialized = array_unique($serialized);

        return array_values(array_map('unserialize', $uniqueSerialized));
    }

    /**
     * Build the HTML for a single author (name + optional ORCID icon)
     *
     * @param array $author single author data
     * @param string $fullname sanitized full name
     * @return string HTML string
     */
    private static function buildAuthorHtml(array $author, string $fullname): string
    {
        if (!array_key_exists(self::KEY_ORCID, $author)) {
            return ' ' . $fullname . ' ';
        }

        $orcid = htmlspecialchars($author[self::KEY_ORCID], ENT_QUOTES, 'UTF-8');
        $orcidUrl = htmlspecialchars(self::ORCID_BASE_URL . $author[self::KEY_ORCID], ENT_QUOTES, 'UTF-8');

        return $fullname
            . ' <a rel="noopener" href="' . $orcidUrl
            . '" data-toggle="tooltip" data-placement="bottom" data-original-title="' . $orcid
            . '" target="_blank">'
            . '<img srcset="/img/orcid_id.svg" src="/img/ORCID-iD.png" height="16px" alt="ORCID"/></a>';
    }

    /**
     * Build the ORCID text token for a single author
     *
     * @param array $author single author data
     * @return string ORCID value or "NULL" placeholder
     */
    private static function buildOrcidText(array $author): string
    {
        if (array_key_exists(self::KEY_ORCID, $author)) {
            return htmlspecialchars($author[self::KEY_ORCID]);
        }

        return self::ORCID_PLACEHOLDER;
    }

    /**
     * Build superscript HTML for an author's affiliations (e.g. "<sup>1,</sup><sup>2</sup>")
     *
     * @param array $authorAffiliations affiliations of the current author
     * @param array $uniqueAffiliations global unique affiliation list
     * @return string HTML superscript string
     */
    private static function buildAffiliationSuperscripts(array $authorAffiliations, array $uniqueAffiliations): string
    {
        $html = '';
        $totalAffiliations = count($authorAffiliations);
        $displayedCount = 0;

        foreach ($authorAffiliations as $authorAffiliation) {
            foreach ($uniqueAffiliations as $globalIndex => $globalAffiliation) {
                if (!in_array($globalAffiliation[self::KEY_AFFILIATION], $authorAffiliation, true)) {
                    continue;
                }

                $isMatchingUrl = isset($globalAffiliation[self::KEY_URL], $authorAffiliation[self::KEY_ID])
                    && $globalAffiliation[self::KEY_URL] === $authorAffiliation[self::KEY_ID][0][self::KEY_ID];

                $isMatchingNameOnly = !isset($authorAffiliation[self::KEY_ID]) && !isset($globalAffiliation[self::KEY_URL]);

                if ($isMatchingUrl || $isMatchingNameOnly) {
                    $superscriptNumber = $globalIndex + 1;
                    $separator = ($displayedCount === $totalAffiliations - 1) ? '' : ',';
                    $html .= '<sup>' . $superscriptNumber . $separator . '</sup>';
                    $displayedCount++;
                }
            }
        }

        return $html;
    }

    /**
     * Build the HTML unordered list of all unique affiliations
     *
     * @param array $uniqueAffiliations deduplicated affiliation list
     * @return string HTML <ul> string
     */
    private static function buildAffiliationListHtml(array $uniqueAffiliations): string
    {
        if (empty($uniqueAffiliations)) {
            return '';
        }

        $html = '<ul class="list-unstyled">';

        foreach ($uniqueAffiliations as $index => $affiliation) {
            $displayNumber = $index + 1;
            $affiliationName = htmlspecialchars($affiliation[self::KEY_AFFILIATION]);

            if (isset($affiliation[self::KEY_URL])) {
                $affiliationUrl = htmlspecialchars($affiliation[self::KEY_URL], ENT_QUOTES, 'UTF-8');
                $html .= '<li class="affiliation"><span class="label label-default">' . $displayNumber . '</span> '
                    . '<a href="' . $affiliationUrl . '" target="_blank">' . $affiliationName;

                if (isset($affiliation[self::KEY_ACRONYM])) {
                    $html .= ' [' . $affiliation[self::KEY_ACRONYM] . ']';
                }

                $html .= '</a></li>';
            } else {
                $html .= '<li class="affiliation"><span class="label label-default">' . $displayNumber . '</span> '
                    . $affiliationName . '</li>';
            }
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Build the standard result array
     *
     * @param string $templateHtml author template with ORCID and superscripts
     * @param string $orcidText concatenated ORCID identifiers
     * @param string $affiliationListHtml HTML list of affiliations
     * @param string $authorsList plain text author names
     * @return array{template: string, orcid: string, listAffi: string, authorsList: string}
     */
    private static function buildResult(string $templateHtml, string $orcidText, string $affiliationListHtml, string $authorsList): array
    {
        return [
            self::RESULT_KEY_TEMPLATE => $templateHtml,
            self::RESULT_KEY_ORCID => $orcidText,
            self::RESULT_KEY_LIST_AFFI => $affiliationListHtml,
            self::RESULT_KEY_AUTHORS_LIST => $authorsList,
        ];
    }

    /**
     * Build a numbered affiliation index for all authors, using MD5 keys for deduplication
     *
     * @param int $paperId
     * @return array{affiliationNumeric: array, authors: array}
     * @throws JsonException
     */
    public static function filterAuthorsAndAffiNumeric(int $paperId): array
    {
        $allAuthors = Episciences_Paper_Authors_Repository::getDecodedAuthors($paperId);
        $affiliationIndex = [];

        foreach ($allAuthors as $authorKey => $author) {
            if (!isset($author[self::KEY_AFFILIATION])) {
                continue;
            }

            foreach ($author[self::KEY_AFFILIATION] as $affiliation) {
                $hashSource = $affiliation[self::KEY_NAME];

                if (isset($affiliation[self::KEY_ID])) {
                    $hashSource .= $affiliation[self::KEY_ID][0][self::KEY_ID] . $affiliation[self::KEY_ID][0][self::KEY_ID_TYPE];
                }

                $affiliationHash = md5($hashSource);

                if (array_key_exists($affiliationHash, $affiliationIndex)) {
                    $allAuthors[$authorKey]['idAffi'][$affiliationHash] = $affiliationIndex[$affiliationHash];
                } else {
                    $affiliationEntry = [self::KEY_NAME => $affiliation[self::KEY_NAME]];

                    if (isset($affiliation[self::KEY_ID])) {
                        $affiliationEntry[self::KEY_URL] = $affiliation[self::KEY_ID][0][self::KEY_ID];
                        $affiliationEntry[self::KEY_TYPE] = $affiliation[self::KEY_ID][0][self::KEY_ID_TYPE];

                        if (array_key_exists(self::KEY_ACRONYM, $affiliation[self::KEY_ID][0])) {
                            $affiliationEntry[self::KEY_ACRONYM] = $affiliation[self::KEY_ID][0][self::KEY_ACRONYM];
                        }
                    }

                    $affiliationIndex[$affiliationHash] = $affiliationEntry;
                    $allAuthors[$authorKey]['idAffi'][$affiliationHash] = $affiliationEntry;
                }

                ksort($allAuthors[$authorKey]['idAffi']);
            }
        }

        ksort($affiliationIndex);

        return [
            self::RESULT_KEY_AFFILIATION_NUMERIC => $affiliationIndex,
            self::RESULT_KEY_AUTHORS => $allAuthors,
        ];
    }
}
