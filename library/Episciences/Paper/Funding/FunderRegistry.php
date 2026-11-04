<?php

declare(strict_types=1);

namespace Episciences\Paper\Funding;

/**
 * Static table of well-known funders, used to attach persistent identifiers
 * (Crossref Funder ID, ROR) to the funder names stored in paper_projects,
 * which never carry any identifier themselves.
 *
 * A funder is found either from the OpenAIRE project identifier prefix
 * (e.g. "anr_________::..."), which is unambiguous, or from its name
 * (case and spacing insensitive), which covers HAL, Dataverse and legacy rows.
 *
 * Identifiers were checked against the ROR registry (ROR "fundref" external
 * identifiers, preferred value). Unknown funders are exported by name only.
 */
final class FunderRegistry
{
    private const CROSSREF_FUNDER_DOI_PREFIX = 'https://doi.org/10.13039/';
    private const ROR_URL_PREFIX = 'https://ror.org/';

    /**
     * @var array<string, array{crossref: string, ror: string, names: list<string>, openairePrefixes: list<string>}>
     */
    private const FUNDERS = [
        'EC' => [
            'crossref' => '501100000780',
            'ror' => '00k4n6c32',
            'names' => ['European Commission', 'Commission européenne', 'EC'],
            'openairePrefixes' => ['corda_______', 'corda__h2020', 'corda_____he'],
        ],
        'ERC' => [
            'crossref' => '501100000781',
            'ror' => '0472cxd90',
            'names' => ['European Research Council', 'ERC'],
            'openairePrefixes' => [],
        ],
        'ANR' => [
            'crossref' => '501100001665',
            'ror' => '00rbzpz17',
            'names' => [
                'French National Research Agency (ANR)',
                'French National Research Agency',
                'Agence Nationale de la Recherche',
                'Agence Nationale de la Recherche (ANR)',
                'ANR',
            ],
            'openairePrefixes' => ['anr_________'],
        ],
        'INCA' => [
            'crossref' => '501100006364',
            'ror' => '03m8vkq32',
            'names' => ['Institut National du Cancer', 'French National Cancer Institute'],
            'openairePrefixes' => ['inca________'],
        ],
        'NSF' => [
            'crossref' => '100000001',
            'ror' => '021nxhr62',
            'names' => ['National Science Foundation', 'U.S. National Science Foundation', 'NSF'],
            'openairePrefixes' => ['nsf_________'],
        ],
        'NIH' => [
            'crossref' => '100000002',
            'ror' => '01cwqze88',
            'names' => ['National Institutes of Health', 'NIH'],
            'openairePrefixes' => ['nih_________'],
        ],
        'WT' => [
            'crossref' => '100010269',
            'ror' => '029chgv08',
            'names' => ['Wellcome Trust', 'Wellcome'],
            'openairePrefixes' => ['wt__________'],
        ],
        'SNSF' => [
            'crossref' => '501100001711',
            'ror' => '00yjd3n13',
            'names' => [
                'Swiss National Science Foundation',
                'Fonds National Suisse de la Recherche Scientifique',
                'SNSF',
                'SNF',
            ],
            'openairePrefixes' => ['snsf________'],
        ],
        'FCT' => [
            'crossref' => '501100001871',
            'ror' => '00snfqn58',
            'names' => [
                'Fundação para a Ciência e a Tecnologia',
                'Fundação para a Ciência e Tecnologia',
                'Fundação para a Ciência e a Tecnologia, I.P.',
                'FCT',
            ],
            'openairePrefixes' => ['fct_________'],
        ],
        'NWO' => [
            'crossref' => '501100003246',
            'ror' => '04jsz6e67',
            'names' => [
                'Dutch Research Council',
                'Netherlands Organisation for Scientific Research',
                'Netherlands Organisation for Scientific Research (NWO)',
                'NWO',
            ],
            'openairePrefixes' => ['nwo_________'],
        ],
        'UKRI' => [
            'crossref' => '100014013',
            'ror' => '001aqnf71',
            'names' => ['UK Research and Innovation', 'UKRI'],
            'openairePrefixes' => ['ukri________'],
        ],
        'FWF' => [
            'crossref' => '501100002428',
            'ror' => '013tf3c58',
            'names' => ['Austrian Science Fund', 'Austrian Science Fund (FWF)', 'FWF Austrian Science Fund', 'FWF'],
            'openairePrefixes' => ['fwf_________'],
        ],
        'DFG' => [
            'crossref' => '501100001659',
            'ror' => '018mejw64',
            'names' => ['Deutsche Forschungsgemeinschaft', 'German Research Foundation', 'DFG'],
            'openairePrefixes' => ['dfgf________'],
        ],
        'ARC' => [
            'crossref' => '501100000923',
            'ror' => '05mmh0f86',
            'names' => ['Australian Research Council', 'Australian Research Council (ARC)'],
            'openairePrefixes' => ['arc_________'],
        ],
        'NHMRC' => [
            'crossref' => '501100000925',
            'ror' => '011kf5r70',
            'names' => [
                'National Health and Medical Research Council',
                'National Health and Medical Research Council (NHMRC)',
                'NHMRC',
            ],
            'openairePrefixes' => ['nhmrc_______'],
        ],
        'AKA' => [
            'crossref' => '501100002341',
            'ror' => '05k73zm37',
            'names' => ['Research Council of Finland', 'Academy of Finland'],
            'openairePrefixes' => ['aka_________'],
        ],
        'VR' => [
            'crossref' => '501100004359',
            'ror' => '03zttf063',
            'names' => ['Swedish Research Council', 'Vetenskapsrådet'],
            'openairePrefixes' => ['vr__________'],
        ],
    ];

    /** @var array<string, string>|null normalized name => funder key */
    private static ?array $nameIndex = null;

    /** @var array<string, string>|null OpenAIRE prefix => funder key */
    private static ?array $prefixIndex = null;

    /**
     * Find the registry key of a funder, from its OpenAIRE project identifier first, then from its name.
     */
    public static function findKey(?string $funderName, ?string $openaireProjectId = null): ?string
    {
        $prefix = self::openairePrefix($openaireProjectId);

        if ($prefix !== null && isset(self::prefixIndex()[$prefix])) {
            return self::prefixIndex()[$prefix];
        }

        if ($funderName === null) {
            return null;
        }

        return self::nameIndex()[self::normalizeName($funderName)] ?? null;
    }

    /**
     * Crossref Funder ID as a DOI URL, e.g. https://doi.org/10.13039/501100001665
     */
    public static function crossrefFunderId(?string $key): ?string
    {
        return $key !== null && isset(self::FUNDERS[$key])
            ? self::CROSSREF_FUNDER_DOI_PREFIX . self::FUNDERS[$key]['crossref']
            : null;
    }

    /**
     * ROR identifier as a URL, e.g. https://ror.org/00rbzpz17
     */
    public static function ror(?string $key): ?string
    {
        return $key !== null && isset(self::FUNDERS[$key])
            ? self::ROR_URL_PREFIX . self::FUNDERS[$key]['ror']
            : null;
    }

    /**
     * Display name of a registered funder (the first name of its entry).
     */
    public static function displayName(string $key): ?string
    {
        return self::FUNDERS[$key]['names'][0] ?? null;
    }

    /**
     * The part of an OpenAIRE project identifier before "::", e.g. "corda__h2020".
     */
    public static function openairePrefix(?string $openaireProjectId): ?string
    {
        if ($openaireProjectId === null || !str_contains($openaireProjectId, '::')) {
            return null;
        }

        return strtolower(strstr($openaireProjectId, '::', true));
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtolower((string)preg_replace('/\s+/u', ' ', trim($name)));
    }

    /**
     * @return array<string, string>
     */
    private static function nameIndex(): array
    {
        if (self::$nameIndex === null) {
            self::$nameIndex = [];
            foreach (self::FUNDERS as $key => $funder) {
                foreach ($funder['names'] as $name) {
                    self::$nameIndex[self::normalizeName($name)] = $key;
                }
            }
        }

        return self::$nameIndex;
    }

    /**
     * @return array<string, string>
     */
    private static function prefixIndex(): array
    {
        if (self::$prefixIndex === null) {
            self::$prefixIndex = [];
            foreach (self::FUNDERS as $key => $funder) {
                foreach ($funder['openairePrefixes'] as $prefix) {
                    self::$prefixIndex[$prefix] = $key;
                }
            }
        }

        return self::$prefixIndex;
    }
}
