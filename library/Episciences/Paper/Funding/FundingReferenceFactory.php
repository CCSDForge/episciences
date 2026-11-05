<?php

declare(strict_types=1);

namespace Episciences\Paper\Funding;

/**
 * Builds FundingReference objects from the funding entries stored in paper_projects.funding.
 *
 * Stored keys (all optional, any value may be the "unidentified" placeholder):
 *  - projectTitle, acronym, funderName, code: every source
 *  - callId, projectFinancing: HAL European projects
 *  - openaireId, grantDoi: OpenAIRE Graph projects
 *  - url: Dataverse projects
 *  - fundingStatement: HAL free-text funding (funding_s)
 */
final class FundingReferenceFactory
{
    /** Values standing for "unknown": the placeholder written by the enrichment, plus empty ones */
    private const UNKNOWN_VALUES = ['', \Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED, 'null'];

    /** ANR grant codes, e.g. ANR-22-CE45-0024, ANR-11-IDEX-0004-02 */
    private const ANR_CODE = 'ANR-\d{2}-[A-Z0-9]+(?:-[A-Z0-9]+){1,3}';
    private const ANR_CODE_PATTERN = '/\b' . self::ANR_CODE . '\b/i';

    private const CORDIS_PROJECT_URL = 'https://cordis.europa.eu/project/id/';
    private const DOI_URL_PREFIX = 'https://doi.org/';

    private const EC_FRAMEWORK_PROGRAMMES = [
        'Horizon Europe Framework Programme',
        'Horizon 2020 Framework Programme',
        'Seventh Framework Programme',
        'Sixth Framework Programme',
        'Fifth Framework Programme',
        'Fourth Framework Programme',
        'Third Framework Programme',
        'Second Framework Programme',
        'First Framework Programme',
    ];

    /** European Commission funding streams, from the OpenAIRE project identifier prefix */
    private const OPENAIRE_PREFIX_STREAMS = [
        'corda_______' => 'Seventh Framework Programme',
        'corda__h2020' => 'Horizon 2020 Framework Programme',
        'corda_____he' => 'Horizon Europe Framework Programme',
    ];

    /** European Commission funding streams, from the HAL "financing" value (e.g. "H2020-MSCA-IF-2014", "EC:FP7:ERC") */
    private const HAL_FINANCING_STREAMS = [
        '/^HORIZON\b/i' => 'Horizon Europe Framework Programme',
        '/^H2020\b/i' => 'Horizon 2020 Framework Programme',
        '/^(?:EC:)?FP7\b/i' => 'Seventh Framework Programme',
        '/^(?:EC:)?FP6\b/i' => 'Sixth Framework Programme',
        '/^(?:EC:)?FP5\b/i' => 'Fifth Framework Programme',
        '/^(?:EC:)?FP4\b/i' => 'Fourth Framework Programme',
        '/^(?:EC:)?FP3\b/i' => 'Third Framework Programme',
        '/^(?:EC:)?FP2\b/i' => 'Second Framework Programme',
        '/^(?:EC:)?FP1\b/i' => 'First Framework Programme',
    ];

    /** ERC grants in the HAL "financing" value: "ERC-2014-STG" (call year) or "EC:FP7:ERC" */
    private const HAL_ERC_CALL_PATTERN = '/^ERC-(\d{4})\b/i';
    private const HAL_ERC_FP7_PATTERN = '/^(?:EC:)?FP7:ERC\b/i';

    /** First ERC call year of each framework programme, latest first */
    private const ERC_CALL_YEAR_STREAMS = [
        2021 => 'Horizon Europe Framework Programme',
        2014 => 'Horizon 2020 Framework Programme',
        2007 => 'Seventh Framework Programme',
    ];

    /**
     * Normalize every stored funding entry of a paper, merging the same funding reported by several sources.
     *
     * @param array<int|string, mixed> $storedFundings entries as returned by Episciences_Paper::getFundings()
     * @return list<FundingReference>
     */
    public static function fromStoredList(array $storedFundings): array
    {
        $merged = [];

        foreach ($storedFundings as $stored) {
            if (!is_array($stored)) {
                continue;
            }

            foreach (self::fromStored($stored) as $reference) {
                $key = $reference->mergeKey()
                    ?? 'other:' . md5(serialize($reference->toArray()));

                $merged[$key] = isset($merged[$key]) ? $merged[$key]->mergeWith($reference) : $reference;
            }
        }

        return array_values($merged);
    }

    /**
     * Fundings that name their funder: the only ones Crossref (fundref) and OpenAIRE (fundingReference)
     * accept, and the only ones the citation meta tags describe.
     *
     * @param list<FundingReference> $references
     * @return list<FundingReference>
     */
    public static function withFunder(array $references): array
    {
        return array_values(array_filter($references, static fn(FundingReference $reference): bool => $reference->hasFunder()));
    }

    /**
     * One stored entry usually gives one reference; a free-text statement may give
     * one reference per ANR code it quotes, plus the statement itself.
     *
     * @param array<string, mixed> $stored
     * @return list<FundingReference>
     */
    public static function fromStored(array $stored): array
    {
        $statement = self::clean($stored['fundingStatement'] ?? null);

        if ($statement !== null && self::hasNoStructuredData($stored)) {
            return self::fromStatement($statement);
        }

        $reference = self::fromStructured($stored);

        return $reference !== null ? [$reference] : [];
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function fromStructured(array $stored): ?FundingReference
    {
        $funderName = self::clean($stored['funderName'] ?? null);
        $awardNumber = self::clean($stored['code'] ?? null);
        $openaireId = self::clean($stored['openaireId'] ?? null);

        $funderKey = FunderRegistry::findKey($funderName, $openaireId);

        // an ANR code identifies its funder, even when the source did not name it
        if ($funderKey === null && $funderName === null && $awardNumber !== null && self::isAnrCode($awardNumber)) {
            $funderKey = 'ANR';
        }

        // HAL files ERC grants under the European Commission; the financing value tells them apart
        if ($funderKey === 'EC' && self::isErcFinancing(self::clean($stored['projectFinancing'] ?? null))) {
            $funderKey = 'ERC';
            $funderName = FunderRegistry::displayName('ERC');
        }

        if ($funderName === null && $funderKey !== null) {
            $funderName = FunderRegistry::displayName($funderKey);
        }

        $grantDoi = self::normalizeDoi(self::clean($stored['grantDoi'] ?? null));
        $fundingStream = self::resolveFundingStream($stored, $funderKey, $openaireId);

        $reference = new FundingReference(
            funderName: $funderName,
            funderCrossrefId: FunderRegistry::crossrefFunderId($funderKey),
            funderRor: FunderRegistry::ror($funderKey),
            fundingStream: $fundingStream,
            awardNumber: $awardNumber,
            awardTitle: self::clean($stored['projectTitle'] ?? null),
            awardAcronym: self::clean($stored['acronym'] ?? null),
            awardUri: self::resolveAwardUri($stored, $funderKey, $fundingStream, $awardNumber, $grantDoi),
            grantDoi: $grantDoi,
            callId: self::clean($stored['callId'] ?? null),
            openaireProjectId: $openaireId,
            statement: self::clean($stored['fundingStatement'] ?? null),
            funderKey: $funderKey,
        );

        return self::isEmpty($reference) ? null : $reference;
    }

    /**
     * @return list<FundingReference>
     */
    private static function fromStatement(string $statement): array
    {
        $references = [];
        preg_match_all(self::ANR_CODE_PATTERN, $statement, $matches);

        foreach (array_unique(array_map('strtoupper', $matches[0])) as $anrCode) {
            $references[] = new FundingReference(
                funderName: FunderRegistry::displayName('ANR'),
                funderCrossrefId: FunderRegistry::crossrefFunderId('ANR'),
                funderRor: FunderRegistry::ror('ANR'),
                awardNumber: $anrCode,
                funderKey: 'ANR',
            );
        }

        // keep the text itself unless it is nothing more than the codes
        $rest = trim((string)preg_replace(self::ANR_CODE_PATTERN, '', $statement), " \t\n\r\0\x0B,;.-");
        if ($rest !== '') {
            $references[] = new FundingReference(statement: $statement);
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function resolveFundingStream(array $stored, ?string $funderKey, ?string $openaireId): ?string
    {
        $prefix = FunderRegistry::openairePrefix($openaireId);
        if ($prefix !== null && isset(self::OPENAIRE_PREFIX_STREAMS[$prefix])) {
            return self::OPENAIRE_PREFIX_STREAMS[$prefix];
        }

        $financing = self::clean($stored['projectFinancing'] ?? null);
        if ($financing === null || $funderKey === null || FunderRegistry::family($funderKey) !== 'EC') {
            return null;
        }

        if (preg_match(self::HAL_ERC_CALL_PATTERN, $financing, $matches)) {
            return self::ercCallYearStream((int)$matches[1]);
        }

        foreach (self::HAL_FINANCING_STREAMS as $pattern => $streamName) {
            if (preg_match($pattern, $financing)) {
                return $streamName;
            }
        }

        return null;
    }

    /**
     * Landing page of the award: grant DOI first, then the URL given by the source,
     * then the CORDIS page of European Commission framework programme projects (CORDIS ids are the
     * grant agreement numbers). Some sources file other awards under the European Commission, so the
     * framework programme must be known before pointing to CORDIS.
     *
     * @param array<string, mixed> $stored
     */
    private static function resolveAwardUri(array $stored, ?string $funderKey, ?string $fundingStream, ?string $awardNumber, ?string $grantDoi): ?string
    {
        if ($grantDoi !== null) {
            return self::DOI_URL_PREFIX . $grantDoi;
        }

        $url = self::clean($stored['url'] ?? null);
        if ($url !== null && \Episciences_Tools::isHttpUrl($url)) {
            return $url;
        }

        if (
            $funderKey !== null
            && FunderRegistry::family($funderKey) === 'EC'
            && in_array($fundingStream, self::EC_FRAMEWORK_PROGRAMMES, true)
            && $awardNumber !== null
            && ctype_digit($awardNumber)
        ) {
            return self::CORDIS_PROJECT_URL . $awardNumber;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $stored
     */
    private static function hasNoStructuredData(array $stored): bool
    {
        foreach (['funderName', 'code', 'projectTitle', 'openaireId', 'grantDoi'] as $key) {
            if (self::clean($stored[$key] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    private static function isErcFinancing(?string $financing): bool
    {
        return $financing !== null
            && (preg_match(self::HAL_ERC_CALL_PATTERN, $financing) === 1 || preg_match(self::HAL_ERC_FP7_PATTERN, $financing) === 1);
    }

    private static function ercCallYearStream(int $year): ?string
    {
        foreach (self::ERC_CALL_YEAR_STREAMS as $firstYear => $streamName) {
            if ($year >= $firstYear) {
                return $streamName;
            }
        }

        return null;
    }

    private static function isAnrCode(string $code): bool
    {
        return (bool)preg_match('/^' . self::ANR_CODE . '$/i', $code);
    }

    private static function isEmpty(FundingReference $reference): bool
    {
        return $reference->funderName === null
            && $reference->awardNumber === null
            && $reference->awardTitle === null
            && $reference->grantDoi === null
            && $reference->statement === null;
    }

    private static function normalizeDoi(?string $doi): ?string
    {
        if ($doi === null) {
            return null;
        }

        $doi = (string)preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:)#i', '', $doi);

        return str_starts_with($doi, '10.') ? $doi : null;
    }

    private static function clean(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string)$value);

        return in_array(mb_strtolower($value), self::UNKNOWN_VALUES, true) ? null : $value;
    }
}
