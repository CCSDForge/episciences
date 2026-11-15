<?php
declare(strict_types=1);

namespace Episciences\Volume;

use Episciences_Volume;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * Server-side rules of the proceedings part of the volume form. public/js/volume/confproceeding.js
 * mirrors them, this class stays the source of truth.
 */
final class ProceedingsValidator
{
    /** Crossref-safe characters, without delimiters: also used as the HTML "pattern" attribute */
    public const DOI_SUFFIX_PATTERN = '[A-Za-z0-9._\-]{1,20}';
    public const DOI_SUFFIX_MAX_LENGTH = 20;
    private const DOI_INFIX = '.proceedings.';

    /** Years accepted by Crossref in conference_date (xrefYear) */
    public const MIN_YEAR = 1400;
    public const MAX_YEAR = 2200;
    public const MIN_DATE = '1400-01-01';
    public const MAX_DATE = '2200-12-31';

    public const MSG_REQUIRED = 'Ce champ est obligatoire pour des actes de conférence.';
    public const MSG_DATE_OUT_OF_RANGE = 'L\'année doit être comprise entre 1400 et 2200.';
    public const MSG_END_BEFORE_START = 'La date de fin de la conférence doit être identique ou postérieure à la date de début.';
    public const MSG_DOI_SUFFIX_FORMAT = 'Le suffixe du DOI accepte uniquement les lettres sans accent, les chiffres, le point, le tiret et le tiret bas (20 caractères au maximum).';
    public const MSG_DOI_ALREADY_USED = 'Ce DOI est déjà utilisé par un autre volume de la revue.';
    public const MSG_DOI_LOCKED_PROCEEDINGS = 'Le DOI de ces actes a été transmis à Crossref : le volume doit rester un volume d\'actes de conférence.';

    private const REQUIRED_FIELDS = [
        Episciences_Volume::VOLUME_CONFERENCE_NAME,
        Episciences_Volume::VOLUME_CONFERENCE_START_DATE,
        Episciences_Volume::VOLUME_CONFERENCE_END_DATE,
    ];

    private Zend_Db_Adapter_Abstract $db;

    public function __construct(?Zend_Db_Adapter_Abstract $db = null)
    {
        $this->db = $db ?? Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    /**
     * @param array<string, mixed> $values posted values
     * @param string $doiPrefix DOI prefix of the journal, empty when none is configured
     * @param bool $doiLocked the DOI is requested or registered: the posted suffix is ignored
     * @return array<string, string> untranslated error message by field name, empty when valid
     */
    public function validate(array $values, string $doiPrefix, string $rvcode, int $rvid, ?int $vid, bool $doiLocked): array
    {
        if ((string)($values[Episciences_Volume::VOLUME_IS_PROCEEDING] ?? '0') !== '1') {
            return $doiLocked ? [Episciences_Volume::VOLUME_IS_PROCEEDING => self::MSG_DOI_LOCKED_PROCEEDINGS] : [];
        }

        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (self::value($values, $field) === '') {
                $errors[$field] = self::MSG_REQUIRED;
            }
        }

        $errors += $this->validateDates(
            self::value($values, Episciences_Volume::VOLUME_CONFERENCE_START_DATE),
            self::value($values, Episciences_Volume::VOLUME_CONFERENCE_END_DATE)
        );

        $suffix = self::value($values, Episciences_Volume::VOLUME_CONFERENCE_DOI);
        if (!$doiLocked && $doiPrefix !== '' && $suffix !== '') {
            $doiError = $this->validateDoiSuffix($suffix, self::buildDoi($doiPrefix, $rvcode, $suffix), $rvid, $vid);
            if ($doiError !== null) {
                $errors[Episciences_Volume::VOLUME_CONFERENCE_DOI] = $doiError;
            }
        }

        return $errors;
    }

    /**
     * Whether a Y-m-d date has a year Crossref accepts.
     */
    public static function isDateInCrossrefRange(string $date): bool
    {
        return $date >= self::MIN_DATE && $date <= self::MAX_DATE;
    }

    public static function isDoiSuffixValid(string $suffix): bool
    {
        return preg_match('/^' . self::DOI_SUFFIX_PATTERN . '$/D', $suffix) === 1;
    }

    /**
     * Prefix shown before the suffix input: {prefix}/{RVCODE}.proceedings.
     */
    public static function buildDoiStart(string $doiPrefix, string $rvcode): string
    {
        return $doiPrefix . '/' . $rvcode . self::DOI_INFIX;
    }

    public static function buildDoi(string $doiPrefix, string $rvcode, string $suffix): string
    {
        return self::buildDoiStart($doiPrefix, $rvcode) . $suffix;
    }

    /**
     * Suffix of a stored proceedings DOI (it may contain dots), empty when the DOI is not one.
     */
    public static function extractDoiSuffix(string $doi): string
    {
        $position = strrpos($doi, self::DOI_INFIX);

        return $position === false ? '' : substr($doi, $position + strlen(self::DOI_INFIX));
    }

    /**
     * Range accepted by Crossref, end not before start. Empty dates are reported as required.
     *
     * @return array<string, string>
     */
    private function validateDates(string $start, string $end): array
    {
        $errors = [];
        foreach ([Episciences_Volume::VOLUME_CONFERENCE_START_DATE => $start, Episciences_Volume::VOLUME_CONFERENCE_END_DATE => $end] as $field => $date) {
            if ($date !== '' && !self::isDateInCrossrefRange($date)) {
                $errors[$field] = self::MSG_DATE_OUT_OF_RANGE;
            }
        }

        if ($errors === [] && $start !== '' && $end !== '' && $end < $start) {
            $errors[Episciences_Volume::VOLUME_CONFERENCE_END_DATE] = self::MSG_END_BEFORE_START;
        }

        return $errors;
    }

    private function validateDoiSuffix(string $suffix, string $doi, int $rvid, ?int $vid): ?string
    {
        if (!self::isDoiSuffixValid($suffix)) {
            return self::MSG_DOI_SUFFIX_FORMAT;
        }

        return $this->isDoiUsedByAnotherVolume($doi, $rvid, $vid) ? self::MSG_DOI_ALREADY_USED : null;
    }

    private function isDoiUsedByAnotherVolume(string $doi, int $rvid, ?int $vid): bool
    {
        $select = $this->db->select()
            ->from(['S' => T_VOLUME_SETTINGS], [new Zend_Db_Expr('COUNT(*)')])
            ->join(['V' => T_VOLUMES], 'V.VID = S.VID', [])
            ->where('V.RVID = ?', $rvid)
            ->where('S.SETTING = ?', Episciences_Volume::VOLUME_CONFERENCE_DOI)
            ->where('S.VALUE = ?', $doi);

        if ($vid !== null) {
            $select->where('S.VID != ?', $vid);
        }

        return (int)$this->db->fetchOne($select) > 0;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function value(array $values, string $field): string
    {
        $value = $values[$field] ?? '';

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
