<?php

use Episciences\Repositories\CommonHooksInterface;
use Episciences\Repositories\DataSanitizerInterface;

class Episciences_Repositories_HAL_Hooks implements CommonHooksInterface, DataSanitizerInterface
{
    /**
     * Boilerplate HAL exposes through <dc:description> in its oai_dc records, none of
     * which is an abstract:
     *
     * - "International audience" and "National audience" are how HAL serializes its
     *   `audience` field. Both values occur in production records.
     * - "soumission à Episciences" is what depositors type in the abstract field when
     *   they create a HAL record for the sole purpose of submitting it here.
     *
     * Compared on normalized whitespace, case-insensitively.
     */
    public const NON_ABSTRACT_DESCRIPTIONS = [
        'International audience',
        'National audience',
        'soumission à Episciences',
    ];

    /**
     * Strip HAL's non-abstract <dc:description> nodes from the raw XML before it is
     * persisted into PAPERS.RECORD.
     *
     * RECORD is read by two independent paths - Paper::getMetadata() (PHP) and
     * Paper::getXslt() -> public/xsl/*.xsl (XSLT straight on the DOM) - so filtering
     * these markers in each consumer meant repeating the test everywhere and missing
     * it somewhere (Episciences_Paper_Tei::getAbstracts() leaked "International
     * audience" into the TEI export). Removing the nodes once, at ingestion, keeps
     * both paths correct with no downstream test at all.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function hookCleanXMLRecordInput(array $input): array
    {
        if (empty($input['record']) || !is_string($input['record'])) {
            return $input;
        }

        $input['record'] = Episciences_Repositories_Common::removeDcDescriptionByText(
            $input['record'],
            self::NON_ABSTRACT_DESCRIPTIONS
        );

        return $input;
    }

    // HAL has no dedicated handling for the hooks below: return [] so
    // Episciences_Repositories::callHook() falls back to the same defaults that
    // applied before this class existed (OAI-based retrieval, version required,
    // identifier common to all versions).

    /**
     * @param array<string, mixed> $hookParams
     * @return array<string, mixed>
     */
    public static function hookApiRecords(array $hookParams): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function hookIsRequiredVersion(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function hookIsIdentifierCommonToAllVersions(): array
    {
        return [];
    }
}