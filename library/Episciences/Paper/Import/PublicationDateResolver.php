<?php
declare(strict_types=1);

namespace Episciences\Paper\Import;

use Ccsd_Tools;
use DOMDocument;
use DOMXPath;
use Episciences\Hal\HalTeiSource;
use Episciences_Hal_TeiCacheManager;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Tools;

/**
 * Resolves the publication date for a paper being imported/updated, trying in order:
 *   1. the CSV row's publication_date column
 *   2. the paper's own current publication date (relevant for updates)
 *   3. the OAI dc:date field of the freshly fetched record
 *   4. HAL papers: the publication date of the paper version, read from its HAL TEI
 *   5. now()
 *
 * Ported from scripts/update_papers.php's getPublicationDate*() methods, fixing a bug
 * where steps 2 and 3 validated the wrong (always-empty) variable and were dead code.
 */
final class PublicationDateResolver
{
    /**
     * Where the HAL TEI holds the publication date, in order of preference
     */
    private const HAL_PUBLICATION_DATE_PATHS = [
        '//tei:biblFull//tei:monogr/tei:imprint/tei:date[@type="datePub"][1]',
        '//tei:biblFull/tei:editionStmt/tei:edition[@type="current"]/tei:date[@type="whenProduced"][1]',
    ];

    public function resolve(Row $row, Episciences_Paper $paper): string
    {
        return $this->fromArgs($row)
            ?? $this->fromPaper($paper)
            ?? $this->fromOai($paper->getRecord())
            ?? $this->fromHalTei($paper)
            ?? date('Y-m-d H:i:s');
    }

    private function fromArgs(Row $row): ?string
    {
        if ($row->publicationDate === null) {
            return null;
        }

        return $this->validated(Episciences_Tools::getValidSQLDateTime($row->publicationDate));
    }

    private function fromPaper(Episciences_Paper $paper): ?string
    {
        $current = $paper->getPublication_date();
        if (!$current) {
            return null;
        }

        return $this->validated(Episciences_Tools::getValidSQLDateTime($current));
    }

    /**
     * @param mixed $record
     */
    private function fromOai($record): ?string
    {
        if (!$record) {
            return null;
        }

        $dcDate = Ccsd_Tools::xpath($record, '//dc:date');
        if (!$dcDate) {
            return null;
        }

        if (is_array($dcDate)) {
            $dcDate = array_shift($dcDate);
        }

        return $this->validated(Episciences_Tools::getValidSQLDateTime($dcDate));
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function fromHalTei(Episciences_Paper $paper): ?string
    {
        if ((string)$paper->getIdentifier() === '' || !Episciences_Repositories::isFromHalRepository((int)$paper->getRepoid())) {
            return null;
        }

        // Same TEI as the linked data (Episciences_Submit::datasetsProcessing()): 0 = latest version
        $tei = Episciences_Hal_TeiCacheManager::fetchAndGet(HalTeiSource::ofPaper($paper));

        return self::publicationDateFromTei($tei);
    }

    /**
     * Publication date of the HAL document, as the publicationDate_tdate field of the HAL search API:
     * the date of publication (<imprint><date type="datePub">), or, for a document without one
     * (preprint), the production date of the current version (<edition><date type="whenProduced">).
     * A year or a month is completed with the first day; a date that is not valid is skipped.
     *
     * @return string|null SQL date time, null when the TEI holds no valid publication date
     */
    public static function publicationDateFromTei(string $tei): ?string
    {
        if (trim($tei) === '') {
            return null;
        }

        $dom = new DOMDocument();

        if (!@$dom->loadXML($tei)) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('tei', 'http://www.tei-c.org/ns/1.0');

        foreach (self::HAL_PUBLICATION_DATE_PATHS as $path) {
            $date = self::toSqlDateTime(trim((string)$xpath->evaluate('string(' . $path . ')')));

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param string $rawDate YYYY, YYYY-MM or YYYY-MM-DD
     * @return string|null SQL date time, null when the date is not valid
     */
    private static function toSqlDateTime(string $rawDate): ?string
    {
        if (preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?$/', $rawDate, $matches) !== 1) {
            return null;
        }

        $year = $matches[1];
        $month = ($matches[2] ?? '') ?: '01';
        $day = ($matches[3] ?? '') ?: '01';

        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            return null;
        }

        return sprintf('%s-%s-%s 00:00:00', $year, $month, $day);
    }

    private function validated(mixed $dateTested): ?string
    {
        return Episciences_Tools::isValidSQLDateTime($dateTested) ? $dateTested : null;
    }
}
