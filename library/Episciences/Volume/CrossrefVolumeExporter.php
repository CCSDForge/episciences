<?php
declare(strict_types=1);

namespace Episciences\Volume;

use Closure;
use DateTimeImmutable;
use DOMDocument;
use Episciences_Editor;
use Episciences_Review;
use Episciences_ReviewsManager;
use Episciences_Volume;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Zend_View;

/**
 * Crossref deposit XML (schema 5.3.1) of a proceedings volume: the conference and the
 * proceedings DOI only, the papers of the volume are deposited on their own.
 *
 * Used both by the "export" action of the volume controller (download / preview) and by the
 * doi:manage command (deposit), so that what managers preview is what gets deposited.
 */
final class CrossrefVolumeExporter
{
    public const TEMPLATE = 'volume/crossref.phtml';
    private const SCRIPT_PATH = '/modules/journal/views/scripts';
    private const DEFAULT_TITLE_LANGUAGE = 'en';

    /** @var Closure(int): bool */
    private Closure $isNewFrontSwitched;

    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param (Closure(int): bool)|null $isNewFrontSwitched whether a journal (RVID) uses the new front-end
     * @param (Closure(): DateTimeImmutable)|null $clock
     */
    public function __construct(?Closure $isNewFrontSwitched = null, ?Closure $clock = null)
    {
        $this->isNewFrontSwitched = $isNewFrontSwitched
            ?? static fn(int $rvid): bool => Episciences_ReviewsManager::isNewFrontSwitched($rvid);
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    /**
     * @param bool $forDeposit the depositor email is only disclosed in the XML sent to Crossref,
     *                         the public export carries a generic address (as the papers OAI export)
     * @throws InvalidArgumentException the volume is not a proceedings volume or has no DOI
     * @throws RuntimeException the rendered template is not well-formed XML
     */
    public function export(Episciences_Volume $volume, Episciences_Review $journal, bool $forDeposit = false): string
    {
        $doi = self::getDepositableDoi($volume);

        $data = $this->buildViewData($volume, $journal, $doi);
        $data['depositorEmail'] = $forDeposit ? (string)DOI_EMAIL_CONTACT : 'noreply@' . DOMAIN;

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . self::SCRIPT_PATH);
        $xml = $view->partial(self::TEMPLATE, $data);

        return self::formatXml($xml);
    }

    /**
     * The proceedings DOI of the volume, refused when it cannot be deposited: a <doi_data>
     * without <doi> would be rejected by Crossref.
     *
     * @throws InvalidArgumentException
     */
    public static function getDepositableDoi(Episciences_Volume $volume): string
    {
        if (!$volume->isProceeding()) {
            throw new InvalidArgumentException(sprintf('Volume #%s is not a proceedings volume.', $volume->getVid()));
        }

        $doi = trim((string)$volume->getSetting(Episciences_Volume::VOLUME_CONFERENCE_DOI));

        // A suffix may itself end with ".proceedings.": only an empty suffix means no DOI
        if (!str_contains($doi, '/') || ProceedingsValidator::extractDoiSuffix($doi) === '') {
            throw new InvalidArgumentException(sprintf('Volume #%s has no proceedings DOI.', $volume->getVid()));
        }

        // Required by the Crossref schema: an empty element would get the deposit rejected
        if (trim((string)$volume->getSetting(Episciences_Volume::VOLUME_CONFERENCE_NAME)) === '') {
            throw new InvalidArgumentException(sprintf('Volume #%s has no conference name.', $volume->getVid()));
        }

        if (self::getProceedingsTitle($volume->getTitles())['title'] === '') {
            throw new InvalidArgumentException(sprintf('Volume #%s has no title.', $volume->getVid()));
        }

        return $doi;
    }

    /**
     * Landing page of the volume registered with the DOI. Always HTTPS: from the command line,
     * SERVER_PROTOCOL (hence Episciences_Review::getUrl()) falls back to http.
     */
    public static function getResourceUrl(string $journalUrl, int $vid, bool $isNewFrontSwitched): string
    {
        $journalUrl = rtrim((string)preg_replace('~^http://~i', 'https://', $journalUrl), '/');

        return $isNewFrontSwitched
            ? sprintf('%s/volumes/%d', $journalUrl, $vid)
            : sprintf('%s/volume/view/id/%d', $journalUrl, $vid);
    }

    /**
     * Title of the proceedings and its language: English when available, the first title otherwise.
     *
     * @param array<string, string>|null $titles titles indexed by language code
     * @return array{title: string, language: string}
     */
    public static function getProceedingsTitle(?array $titles): array
    {
        $titles = array_filter($titles ?? [], static fn($title): bool => trim((string)$title) !== '');

        if ($titles === []) {
            return ['title' => '', 'language' => self::DEFAULT_TITLE_LANGUAGE];
        }

        $language = array_key_exists(self::DEFAULT_TITLE_LANGUAGE, $titles)
            ? self::DEFAULT_TITLE_LANGUAGE
            : (string)array_key_first($titles);

        return ['title' => trim((string)$titles[$language]), 'language' => $language];
    }

    /**
     * Publisher of the journal, the journal name when no publisher is configured.
     *
     * @return array{name: string, place: string}
     */
    public static function getPublisher(Episciences_Review $journal): array
    {
        $name = trim(strip_tags((string)$journal->getSetting(Episciences_Review::SETTING_JOURNAL_PUBLISHER)));
        $place = trim(strip_tags((string)$journal->getSetting(Episciences_Review::SETTING_JOURNAL_PUBLISHER_LOC)));

        return ['name' => $name !== '' ? $name : $journal->getName(), 'place' => $place];
    }

    /**
     * Year of the earliest published paper of the volume, the current year when none is published.
     */
    public function getPublicationYear(Episciences_Volume $volume): string
    {
        try {
            $date = (string)$volume->getEarliestPublicationDateFromVolume();
        } catch (Exception) {
            $date = '';
        }

        // A SQL date (Y-m-d[ H:i:s])
        $timestamp = $date !== '' ? strtotime($date) : false;

        return $timestamp !== false ? date('Y', $timestamp) : ($this->clock)()->format('Y');
    }

    /**
     * Conference dates split as Crossref expects them, null unless both dates are valid and in
     * the years Crossref accepts.
     *
     * @return array<string, string>|null
     */
    public static function getConferenceDate(string $start, string $end): ?array
    {
        $startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $end);

        // createFromFormat() rolls an impossible date over (2026-02-30 becomes 2026-03-02)
        if ($startDate === false || $endDate === false
            || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $end
            || !ProceedingsValidator::isDateInCrossrefRange($startDate->format('Y-m-d'))
            || !ProceedingsValidator::isDateInCrossrefRange($endDate->format('Y-m-d'))) {
            return null;
        }

        return [
            'start_year' => $startDate->format('Y'),
            'start_month' => $startDate->format('m'),
            'start_day' => $startDate->format('d'),
            'end_year' => $endDate->format('Y'),
            'end_month' => $endDate->format('m'),
            'end_day' => $endDate->format('d'),
        ];
    }

    /**
     * Volume editors with a surname, as Crossref contributors (the papers export does the same).
     *
     * @param array<int|string, mixed> $editors
     * @return list<array{given_name: string, surname: string, orcid: string}>
     */
    public static function getContributors(array $editors): array
    {
        $contributors = [];

        foreach ($editors as $editor) {
            if (!$editor instanceof Episciences_Editor || trim((string)$editor->getLastname()) === '') {
                continue;
            }

            $contributors[] = [
                'given_name' => trim((string)$editor->getFirstname()),
                'surname' => trim((string)$editor->getLastname()),
                'orcid' => strtoupper(trim((string)$editor->getOrcid())),
            ];
        }

        return $contributors;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(Episciences_Volume $volume, Episciences_Review $journal, string $doi): array
    {
        $vid = (int)$volume->getVid();
        $timestamp = ($this->clock)()->format('YmdHisv');
        $setting = static fn(string $name): string => trim((string)$volume->getSetting($name));

        return [
            'batchId' => sprintf('%s_volume_%d_%s', DOMAIN, $vid, $timestamp),
            'timestamp' => $timestamp,
            'depositorName' => DOMAIN,
            'contributors' => self::getContributors($volume->getEditors()),
            'conference' => [
                'name' => $setting(Episciences_Volume::VOLUME_CONFERENCE_NAME),
                'theme' => $setting(Episciences_Volume::VOLUME_CONFERENCE_THEME),
                'acronym' => $setting(Episciences_Volume::VOLUME_CONFERENCE_ACRONYM),
                'number' => $setting(Episciences_Volume::VOLUME_CONFERENCE_NUMBER),
                'location' => $setting(Episciences_Volume::VOLUME_CONFERENCE_LOCATION),
                'date' => self::getConferenceDate(
                    $setting(Episciences_Volume::VOLUME_CONFERENCE_START_DATE),
                    $setting(Episciences_Volume::VOLUME_CONFERENCE_END_DATE)
                ),
            ],
            'proceedingsTitle' => self::getProceedingsTitle($volume->getTitles()),
            'publisher' => self::getPublisher($journal),
            'publicationYear' => $this->getPublicationYear($volume),
            'doi' => $doi,
            'resource' => self::getResourceUrl(
                $journal->getUrl(),
                $vid,
                ($this->isNewFrontSwitched)((int)$journal->getRvid())
            ),
        ];
    }

    /**
     * @throws RuntimeException
     */
    private static function formatXml(string $xml): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML(trim($xml));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('The Crossref XML of the volume is not well-formed.');
        }

        $output = $dom->saveXML();
        if ($output === false) {
            throw new RuntimeException('The Crossref XML of the volume could not be serialized.');
        }

        return $output;
    }
}
