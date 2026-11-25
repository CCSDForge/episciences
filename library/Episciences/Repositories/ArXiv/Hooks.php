<?php

use Episciences\Repositories\CommonHooksInterface;

class Episciences_Repositories_ArXiv_Hooks implements CommonHooksInterface
{
    /**
     * Filter extracted metadata specifically for arXiv records (#793).
     * Keeps only the first dc:description element as the paper abstract.
     *
     * @param array<string, mixed> $hookParams
     * @return array{metadata: array<string, mixed>}
     */
    public static function hookFilterMetadata(array $hookParams): array
    {
        $metadata = $hookParams['metadata'] ?? [];
        $xml = $hookParams['xml'] ?? '';

        if (isset($metadata['description']) && is_array($metadata['description']) && $xml !== '') {
            // Episciences_Tools::xpath($xml, ..., true) overwrites entries sharing the same
            // xml:lang in place, so array_slice(0, 1) on that result could silently keep a
            // later node's text instead of the true first <dc:description>. Re-query without
            // lang-collapsing to get the real document order.
            $orderedDescriptions = Episciences_Tools::xpath($xml, '//dc:description', true, false);
            if (is_array($orderedDescriptions) && $orderedDescriptions !== []) {
                $firstDescription = reset($orderedDescriptions);
                $metadata['description'] = is_array($firstDescription) ? $firstDescription : [$firstDescription];
            } else {
                $metadata['description'] = [];
            }
        }

        return ['metadata' => $metadata];
    }

    /**
     * arXiv's OAI-PMH oai_dc record carries the "Comments" field (e.g. "to be
     * published in JFP") as a second, separate <dc:description> sibling after the
     * real abstract. hookFilterMetadata() above only filters the already-parsed
     * $metadata array, so it never affected PAPERS.RECORD itself - the paper view
     * page (Paper::getXslt() -> public/xsl/full_paper.xsl) renders every
     * <dc:description> node directly from the stored RECORD XML and still showed
     * the surplus node. Strip it here, from the raw XML, before it's ever
     * persisted, so both the parsed-metadata path and the XSLT path stay in sync.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function hookCleanXMLRecordInput(array $input): array
    {
        if (empty($input['record'])) {
            return $input;
        }

        $dom = new DOMDocument();

        try {
            set_error_handler('\Ccsd\Xml\Exception::HandleXmlError');
            $loaded = $dom->loadXML($input['record']);
        } catch (\Ccsd\Xml\Exception $e) {
            $loaded = false;
        } finally {
            restore_error_handler();
        }

        if (!$loaded || !$dom->documentElement) {
            return $input;
        }

        $xpath = new DOMXPath($dom);
        foreach (Ccsd_Tools::getNamespaces($dom->documentElement) as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        $descriptions = $xpath->query('//dc:description');

        if ($descriptions === false || $descriptions->length <= 1) {
            return $input;
        }

        for ($i = $descriptions->length - 1; $i > 0; $i--) {
            $node = $descriptions->item($i);
            $node->parentNode->removeChild($node);
        }

        $input['record'] = $dom->saveXML($dom->documentElement);

        return $input;
    }

    // arXiv has no dedicated handling for these hooks: return [] so
    // Episciences_Repositories::callHook() falls back to the same defaults
    // used before this class existed (OAI-based retrieval, version required,
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
