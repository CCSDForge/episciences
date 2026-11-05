<?php

declare(strict_types=1);

namespace Episciences\Paper\Funding;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reads the fundings of a HAL document from its TEI (HAL search API, wt=xml-tei),
 * the same TEI the author enrichment already fetches and caches.
 *
 *  - <funder ref="#projanr-…"/> / <funder ref="#projeurop-…"/> point to the projects
 *    of <back><listOrg type="projects">: ANR (anrProject) or European (europeanProject);
 *  - <funder>free text</funder> is the funding typed by the depositor.
 *
 * Returns entries in the paper_projects.funding format read by FundingReferenceFactory.
 */
final class HalTeiFundingParser
{
    public const ANR_FUNDER_NAME = 'French National Research Agency (ANR)';
    public const EUROPEAN_FUNDER_NAME = 'European Commission';

    private const TEI_NS = 'http://www.tei-c.org/ns/1.0';
    private const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Reads the TEI once: the fundings of the document, and the references of <funder ref="#..."/>
     * that point to no project it can read (project missing from <listOrg type="projects">, of an unknown
     * type or without xml:id), left out of the fundings, which are then not the complete list.
     *
     * @return array{fundings: list<array<string, string>>, unresolved: list<string>}|null null when the TEI
     *         does not hold the HAL document (unreadable TEI or empty search result): then no funding read
     *         means nothing about the fundings of the document
     */
    public static function read(string $tei): ?array
    {
        $xpath = self::loadXpath($tei);
        $documents = $xpath?->query('//tei:biblFull');

        if ($xpath === null || !$documents instanceof \DOMNodeList || $documents->length === 0) {
            return null;
        }

        $projectsById = self::indexProjects($xpath);
        $fundings = [];
        $unresolved = [];

        foreach (self::funders($xpath) as $funder) {
            $ref = self::reference($funder);

            if ($ref !== '') {
                if (isset($projectsById[$ref])) {
                    $fundings[] = $projectsById[$ref];
                } else {
                    $unresolved[] = $ref;
                }
                continue;
            }

            $statement = self::normalizeSpace($funder->textContent);
            if ($statement !== '') {
                $fundings[] = ['fundingStatement' => $statement];
            }
        }

        return ['fundings' => $fundings, 'unresolved' => $unresolved];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function parse(string $tei): array
    {
        return self::read($tei)['fundings'] ?? [];
    }

    /**
     * @return list<string> see read()
     */
    public static function unresolvedReferences(string $tei): array
    {
        return self::read($tei)['unresolved'] ?? [];
    }

    /**
     * @return list<DOMElement> the <funder> elements of the document, in document order
     */
    private static function funders(DOMXPath $xpath): array
    {
        $funders = [];

        foreach ($xpath->query('//tei:biblFull/tei:titleStmt/tei:funder') ?: [] as $funder) {
            if ($funder instanceof DOMElement) {
                $funders[] = $funder;
            }
        }

        return $funders;
    }

    /**
     * xml:id of the project a <funder> points to, '' for a free-text funding
     */
    private static function reference(DOMElement $funder): string
    {
        return ltrim(trim($funder->getAttribute('ref')), '#');
    }

    /**
     * Whether the TEI holds the HAL document itself: only then does an empty parse() result
     * mean that the document has no funding (and not an unreadable TEI or an empty search result).
     */
    public static function hasDocument(string $tei): bool
    {
        return self::read($tei) !== null;
    }

    /**
     * @return array<string, array<string, string>> project entries keyed by their xml:id
     */
    private static function indexProjects(DOMXPath $xpath): array
    {
        $projects = [];

        foreach ($xpath->query('//tei:back/tei:listOrg[@type="projects"]/tei:org') ?: [] as $org) {
            if (!$org instanceof DOMElement) {
                continue;
            }

            $id = $org->getAttributeNS(self::XML_NS, 'id');
            $project = match ($org->getAttribute('type')) {
                'anrProject' => self::anrProject($xpath, $org),
                'europeanProject' => self::europeanProject($xpath, $org),
                default => null,
            };

            if ($id !== '' && $project !== null) {
                $projects[$id] = $project;
            }
        }

        return $projects;
    }

    /**
     * @return array<string, string>
     */
    private static function anrProject(DOMXPath $xpath, DOMElement $org): array
    {
        return self::withoutEmpty([
            'projectTitle' => self::value($xpath, 'tei:desc', $org),
            'acronym' => self::value($xpath, 'tei:orgName', $org),
            'funderName' => self::ANR_FUNDER_NAME,
            'code' => self::value($xpath, 'tei:idno[@type="anr"]', $org),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function europeanProject(DOMXPath $xpath, DOMElement $org): array
    {
        return self::withoutEmpty([
            'projectTitle' => self::value($xpath, 'tei:desc', $org),
            'acronym' => self::value($xpath, 'tei:orgName', $org),
            'funderName' => self::EUROPEAN_FUNDER_NAME,
            'code' => self::value($xpath, 'tei:idno[@type="number"]', $org),
            'callId' => self::value($xpath, 'tei:idno[@type="call"]', $org),
            'projectFinancing' => self::value($xpath, 'tei:idno[@type="program"]', $org),
        ]);
    }

    private static function value(DOMXPath $xpath, string $query, DOMElement $context): string
    {
        $node = $xpath->query($query, $context)->item(0);

        return $node !== null ? self::normalizeSpace($node->textContent) : '';
    }

    /**
     * @param array<string, string> $entry
     * @return array<string, string>
     */
    private static function withoutEmpty(array $entry): array
    {
        return array_filter($entry, static fn(string $value): bool => $value !== '');
    }

    private static function normalizeSpace(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private static function loadXpath(string $tei): ?DOMXPath
    {
        if (trim($tei) === '') {
            return null;
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($tei, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('tei', self::TEI_NS);

        return $xpath;
    }
}
