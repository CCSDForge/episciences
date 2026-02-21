<?php
declare(strict_types=1);
/**
 * HTML rendering layer for paper citations.
 * Builds the HTML fragments displayed in journal paper pages.
 *
 * Bug fixes applied here (vs original CitationsManager):
 *  - Double htmlspecialchars on author metadata (was escaped twice)
 *  - Unstable compound sort (two usort() calls replaced by one)
 *  - XSS via unquoted href attributes on DOI / OA links
 *  - ORCID not validated before URL construction
 */
class Episciences_Paper_Citations_ViewFormatter
{
    public const NUMBER_OF_AUTHORS_WANTED_VIEWS = 5;

    private const DOI_ORG_DOMAIN = 'https://doi.org/';
    private const ORCID_BASE_URL = 'https://orcid.org/';
    private const ORCID_REGEX_WITH_COMMA = '/, \d{4}-\d{4}-\d{4}-\d{3}(?:\d|X)/';
    private const ORCID_REGEX = '/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/';
    private const JSON_DECODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;

    /**
     * Build the full citations HTML block and count total citations for a document.
     *
     * @return array{template: string, counterCitations: int}
     * @throws JsonException
     */
    public static function formatCitationsForViewPaper(int $docId): array
    {
        $allCitation = Episciences_Paper_Citations_Repository::findByDocId($docId);
        $templateCitation = '';
        $counterCitations = 0;

        foreach ($allCitation as $value) {
            /** @var array $decodeCitations */
            $decodeCitations = json_decode(
                (string) $value['citation'],
                true,
                512,
                self::JSON_DECODE_FLAGS
            );
            $counterCitations += count($decodeCitations);
            $decodeCitations = self::sortAuthorAndYear($decodeCitations);

            foreach ($decodeCitations as $citationMetadataArray) {
                $templateCitation .= "<ul class='list-unstyled'>";
                $templateCitation .= '<li>';
                $citationMetadataArray = array_map(strip_tags(...), $citationMetadataArray);

                $citationType = $citationMetadataArray['type'] ?? null;

                switch ($citationType) {
                    case 'book-chapter':
                        $citationMetadataArray = self::reorganizeForBookChapter($citationMetadataArray);
                        break;
                    case 'proceedings-article':
                        $citationMetadataArray = self::reorganizeForProceedingsArticle($citationMetadataArray);
                        break;
                    default:
                        break;
                }

                foreach ($citationMetadataArray as $keyMetadata => $metadata) {
                    if ($metadata === '') {
                        continue;
                    }

                    if ($keyMetadata === 'type') {
                        continue;
                    }

                    $templateCitation .= self::renderMetadataField(
                        $keyMetadata,
                        $metadata,
                        $citationMetadataArray
                    );
                    $templateCitation .= ', ';
                }

                $templateCitation = substr_replace($templateCitation, '.', -2);
                $templateCitation .= '</li>';
            }

            $templateCitation .= '</ul>';
            $templateCitation .= '<small class=\'label label-default\'>'
                . Zend_Registry::get('Zend_Translate')->translate('Sources :')
                . ' OpenCitations, OpenAlex &amp; Crossref</small>';
            $templateCitation .= '<br>';
        }

        return ['template' => $templateCitation, 'counterCitations' => $counterCitations];
    }

    /**
     * Render a single citation metadata field as an HTML fragment.
     * Centralises all escaping and XSS-safe href construction.
     *
     * @param string $metadata raw (strip_tags applied, not yet escaped)
     * @param array $fullCitationMetadata the full citation row, used for oa_link context
     * @return string HTML fragment (may be empty string)
     */
    private static function renderMetadataField(
        string $keyMetadata,
        string $metadata,
        array $fullCitationMetadata
    ): string {
        switch ($keyMetadata) {
            case 'source_title':
                return '<i>' . htmlspecialchars($metadata, ENT_QUOTES, 'UTF-8') . '</i>';

            case 'author':
                // Escape once, then reduce and format — no double-escaping
                $escaped = htmlspecialchars($metadata, ENT_QUOTES, 'UTF-8');
                $reduced = self::reduceAuthorsView($escaped);
                return '<b>' . self::formatAuthors($reduced) . '</b>';

            case 'page':
                return 'pp.&nbsp;' . trim(htmlspecialchars($metadata, ENT_QUOTES, 'UTF-8'));

            case 'doi':
                // href wrapped in double quotes, ENT_QUOTES prevents injection
                $safeHref = htmlspecialchars(self::DOI_ORG_DOMAIN . $metadata, ENT_QUOTES, 'UTF-8');
                $safeLabel = htmlspecialchars($metadata, ENT_QUOTES, 'UTF-8');
                return '<a rel="noopener" target="_blank" href="' . $safeHref . '">'
                    . $safeLabel . '</a>';

            case 'oa_link':
                // Only render if doi exists and is different from oa_link
                if (!isset($fullCitationMetadata['doi']) || $fullCitationMetadata['doi'] === $metadata) {
                    return '';
                }
                $safeHref = htmlspecialchars($metadata, ENT_QUOTES, 'UTF-8');
                return '<i class=\'fas fa-lock-open\'></i>'
                    . ' <a rel="noopener" target="_blank" href="' . $safeHref . '">'
                    . $safeHref . '</a>';

            default:
                return htmlspecialchars($metadata, ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Sort citations by year descending, then by author ascending (compound sort).
     * Replaces the original two sequential usort() calls that lost author ordering.
     */
    public static function sortAuthorAndYear(array $arrayMetadata = []): array
    {
        usort($arrayMetadata, static function (array $a, array $b): int {
            $yearDiff = ($b['year'] ?? 0) <=> ($a['year'] ?? 0);
            if ($yearDiff !== 0) {
                return $yearDiff;
            }
            return strcmp($a['author'] ?? '', $b['author'] ?? '');
        });

        return $arrayMetadata;
    }

    /**
     * Replace ORCID identifiers found in a semicolon-separated author string with ORCID icon links.
     * Expects an already HTML-escaped string; does not escape again.
     *
     * @param string $author semicolon-separated author string (pre-escaped)
     * @return string author string with ORCID links injected
     */
    public static function formatAuthors(string $author): string
    {
        $authorRows = array_map(trim(...), explode(';', $author));

        foreach ($authorRows as $value) {
            preg_match(self::ORCID_REGEX_WITH_COMMA, $value, $matches);
            if ($matches !== []) {
                $orcidLink = self::createOrcidStringForView($matches[0]);
                $author = str_replace(
                    $value,
                    (string) preg_replace(self::ORCID_REGEX_WITH_COMMA, $orcidLink, $value),
                    $author
                );
            }
        }

        return rtrim($author);
    }

    /**
     * Truncate a semicolon-separated author list to NUMBER_OF_AUTHORS_WANTED_VIEWS authors,
     * appending "et al." when truncated.
     *
     * @param string $author semicolon-separated author string
     * @return string truncated author string
     */
    public static function reduceAuthorsView(string $author): string
    {
        $authorRows = array_map(trim(...), explode(';', $author));

        if (count($authorRows) > self::NUMBER_OF_AUTHORS_WANTED_VIEWS) {
            $authorRows = array_slice($authorRows, 0, self::NUMBER_OF_AUTHORS_WANTED_VIEWS, true);
            $authorRows[] = 'et al.';
        }

        return rtrim(implode(';', $authorRows));
    }

    /**
     * Build an ORCID icon link HTML fragment for display next to an author name.
     * Validates the ORCID format before building the URL to prevent injection.
     * The leading comma and space (from the regex match) are stripped on input.
     *
     * @param string $orcid ORCID string, possibly prefixed with ", " from regex match
     * @return string HTML link fragment, or empty string for invalid ORCID
     */
    public static function createOrcidStringForView(string $orcid): string
    {
        $orcid = trim(ltrim(trim($orcid), ','));

        if (!preg_match(self::ORCID_REGEX, $orcid)) {
            return '';
        }

        $safeOrcid = htmlspecialchars($orcid, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars(self::ORCID_BASE_URL . $orcid, ENT_QUOTES, 'UTF-8');

        return '<small style="margin-left: 4px;">'
            . '<a rel="noopener" href="' . $safeUrl . '"'
            . ' data-toggle="tooltip" data-placement="bottom"'
            . ' data-original-title="' . $safeOrcid . '" target="_blank">'
            . '<img srcset="/img/orcid_id.svg" src="/img/ORCID-iD.png" height="12px" alt="ORCID"/>'
            . '</a></small>';
    }

    /**
     * Reorder citation array keys for book-chapter display.
     */
    public static function reorganizeForBookChapter(array $citation): array
    {
        $template = [
            'author' => '',
            'source_title' => '',
            'title' => '',
            'volume' => '',
            'issue' => '',
            'page' => '',
            'year' => '',
            'doi' => '',
            'oa_link' => '',
        ];

        foreach ($citation as $key => $val) {
            $template[$key] = $val;
        }

        return $template;
    }

    /**
     * Reorder citation array keys for proceedings-article display.
     */
    public static function reorganizeForProceedingsArticle(array $citation): array
    {
        $template = [
            'author' => '',
            'source_title' => '',
            'title' => '',
            'volume' => '',
            'page' => '',
            'issue' => '',
            'year' => '',
            'event_place' => '',
            'doi' => '',
            'oa_link' => '',
        ];

        foreach ($citation as $key => $val) {
            $template[$key] = $val;
        }

        return $template;
    }
}
