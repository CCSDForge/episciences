<?php

declare(strict_types=1);

namespace Episciences\Repositories\HAL;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Episciences\Paper\Relationship;
use Episciences_Paper_DatasetsManager;

/**
 * Reads the linked data of a HAL document from its TEI (OAI-PMH server of its repository, metadataPrefix=xml-tei):
 *
 *  - <relatedItem target="..." type="IsSupplementedBy" subtype="http://purl.org/coar/resource_type/c_ddb1"/>:
 *    the related works declared in HAL, with their DataCite relation type and their COAR resource type;
 *  - <idno type="swhid">: the Software Heritage identifier of the document.
 *
 * Returns entries in the shape read by Episciences_Submit::processDatasets()
 * (identifier, relation, resource_type), the relation in the vocabulary of the linked data
 * (Episciences\Paper\Relationship).
 */
final class TeiLinkedDataParser
{
    public const RESOURCE_TYPE_DATASET = 'dataset';
    public const RESOURCE_TYPE_SOFTWARE = 'software';
    public const RESOURCE_TYPE_PUBLICATION = 'publication';

    private const TEI_NS = 'http://www.tei-c.org/ns/1.0';
    private const COAR_PREFIX = 'http://purl.org/coar/resource_type/';

    /**
     * COAR resource types that are not publications
     */
    private const COAR_RESOURCE_TYPES = [
        'c_ddb1' => self::RESOURCE_TYPE_DATASET,
        'c_5ce6' => self::RESOURCE_TYPE_SOFTWARE,
    ];

    /**
     * DataCite relation types whose lcfirst() is not in the linked data vocabulary
     */
    private const RELATION_TYPES = [
        'IsReviewedBy' => 'hasReview',
        'Reviews' => 'isReviewOf',
        'Cites' => 'references',
        'IsCitedBy' => 'isReferencedBy',
    ];

    /**
     * @return list<array{identifier: string, relation: string, resource_type: string}>|null null when the TEI
     *         does not hold the HAL document (unreadable TEI or empty search result)
     */
    public static function parse(string $tei): ?array
    {
        $xpath = self::loadXpath($tei);
        $document = $xpath?->query('//tei:biblFull')?->item(0);

        if ($xpath === null || !$document instanceof DOMElement) {
            return null;
        }

        $entries = [];

        foreach (self::elements($xpath, './/tei:relatedItem[@target]', $document) as $relatedItem) {
            $entries[] = [
                'identifier' => trim($relatedItem->getAttribute('target')),
                'relation' => self::relationship($relatedItem->getAttribute('type')),
                'resource_type' => self::resourceType($relatedItem->getAttribute('subtype')),
            ];
        }

        foreach (self::elements($xpath, './/tei:idno[@type="swhid"]', $document) as $swhid) {
            $entries[] = [
                'identifier' => trim($swhid->textContent),
                'relation' => Episciences_Paper_DatasetsManager::RELATION_TYPE_SOFTWARE,
                'resource_type' => self::RESOURCE_TYPE_SOFTWARE,
            ];
        }

        return self::deduplicate($entries);
    }

    /**
     * DataCite relation type (TEI @type) => relationship of the linked data; "references" when unknown
     */
    public static function relationship(string $relationType): string
    {
        $relationType = trim($relationType);

        if (isset(self::RELATION_TYPES[$relationType])) {
            return self::RELATION_TYPES[$relationType];
        }

        $candidate = lcfirst($relationType);
        $known = array_merge(
            Relationship::getFlattenedRelationships(),
            Relationship::getFlattenedRelationshipsIntraWorkRelation()
        );

        return in_array($candidate, $known, true) ? $candidate : Episciences_Paper_DatasetsManager::RELATION_TYPE_SOFTWARE;
    }

    /**
     * COAR resource type URI (TEI @subtype) => dataset, software or publication
     */
    public static function resourceType(string $coarUri): string
    {
        $code = str_starts_with($coarUri, self::COAR_PREFIX) ? substr($coarUri, strlen(self::COAR_PREFIX)) : '';

        return self::COAR_RESOURCE_TYPES[$code] ?? self::RESOURCE_TYPE_PUBLICATION;
    }

    /**
     * @param list<array{identifier: string, relation: string, resource_type: string}> $entries
     * @return list<array{identifier: string, relation: string, resource_type: string}> without empty identifiers,
     *         the first entry kept for an identifier listed twice
     */
    private static function deduplicate(array $entries): array
    {
        $unique = [];

        foreach ($entries as $entry) {
            $key = strtolower($entry['identifier']);
            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $entry;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<DOMElement>
     */
    private static function elements(DOMXPath $xpath, string $query, DOMElement $context): array
    {
        $nodes = $xpath->query($query, $context);
        $elements = [];

        foreach ($nodes ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
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
