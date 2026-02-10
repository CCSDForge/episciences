<?php

class Episciences_Paper_Authors_HalTeiParser
{
    private const IDNO_TYPE_ORCID = 'ORCID';
    private const IDNO_TYPE_ROR = 'ROR';
    private const KEY_AFFILIATIONS = 'affiliations';
    private const KEY_ORCID = 'orcid';
    private const KEY_NAME = 'name';
    private const KEY_ACRONYM = 'acronym';
    private const ROR_URL_PREFIX = 'https://ror.org/';

    /**
     * Extract all authors with their affiliations and ORCIDs from a TEI XML document
     *
     * @param SimpleXMLElement $teiDocument full TEI XML document
     * @return array<int, array{given_name: string, family: string, fullname: string, affiliations?: array, orcid?: string}>
     */
    public static function getAuthorsFromHalTei(SimpleXMLElement $teiDocument): array
    {
        if (!isset($teiDocument->text->body->listBibl->biblFull->titleStmt->author)) {
            return [];
        }

        $authorNodes = $teiDocument->text->body->listBibl->biblFull->titleStmt->author;
        $parsedAuthors = [];

        foreach ($authorNodes as $authorNode) {
            foreach ($authorNode->persName as $personName) {
                $parsedAuthors = self::getAuthorInfoFromXmlTei($personName, $parsedAuthors);
            }

            if (isset($authorNode->affiliation)) {
                $parsedAuthors = self::getAuthorStructureFromXmlTei($authorNode, $parsedAuthors);
            }

            if (isset($authorNode->idno)) {
                $parsedAuthors = self::getOrcidAuthorFromXmlTei($authorNode, $parsedAuthors);
            }
        }

        return $parsedAuthors;
    }

    /**
     * Extract author name information from a TEI persName element
     *
     * @param SimpleXMLElement|null $personName TEI persName element
     * @param array $parsedAuthors accumulated authors array
     * @return array updated authors array with new entry appended
     */
    public static function getAuthorInfoFromXmlTei(?SimpleXMLElement $personName, array $parsedAuthors): array
    {
        $parsedAuthors[] = [
            'given_name' => (string)$personName->forename,
            'family' => (string)$personName->surname,
            'fullname' => rtrim($personName->forename . ' ' . $personName->surname),
        ];

        return $parsedAuthors;
    }

    /**
     * Extract affiliation structure references from a TEI author element
     *
     * @param SimpleXMLElement|null $authorNode TEI author element
     * @param array $parsedAuthors accumulated authors array
     * @return array updated authors array with affiliations added to last entry
     */
    public static function getAuthorStructureFromXmlTei(?SimpleXMLElement $authorNode, array $parsedAuthors): array
    {
        $lastAuthorIndex = array_key_last($parsedAuthors);

        foreach ($authorNode->affiliation as $affiliationNode) {
            $structRef = (string)str_replace('#', '', $affiliationNode->attributes()->ref);
            $parsedAuthors[$lastAuthorIndex][self::KEY_AFFILIATIONS][] = $structRef;
        }

        return $parsedAuthors;
    }

    /**
     * Extract ORCID identifier from a TEI author element
     *
     * @param SimpleXMLElement|null $authorNode TEI author element
     * @param array $parsedAuthors accumulated authors array
     * @return array updated authors array with ORCID added to last entry
     */
    public static function getOrcidAuthorFromXmlTei(?SimpleXMLElement $authorNode, array $parsedAuthors): array
    {
        $lastAuthorIndex = array_key_last($parsedAuthors);

        foreach ($authorNode->idno as $idnoNode) {
            if ((string)$idnoNode->attributes()->type === self::IDNO_TYPE_ORCID) {
                $parsedAuthors[$lastAuthorIndex][self::KEY_ORCID] = Episciences_Paper_AuthorsManager::normalizeOrcid((string)$idnoNode);
            }
        }

        return $parsedAuthors;
    }

    /**
     * Extract affiliation details (name, ROR, acronym) from the TEI back/listOrg section
     *
     * @param SimpleXMLElement $teiDocument full TEI XML document
     * @return array<string, array{name: string, ROR?: string, acronym?: string}> keyed by structure ID
     */
    public static function getAffiFromHalTei(SimpleXMLElement $teiDocument): array
    {
        $backSection = $teiDocument->text->back;
        $organizationsByStructId = [];

        if (!isset($backSection->listOrg)) {
            return $organizationsByStructId;
        }

        foreach ($backSection->listOrg->org as $orgNode) {
            $structId = (string)$orgNode->attributes('xml', true)[0];
            $organizationsByStructId[$structId][self::KEY_NAME] = trim((string)$orgNode->orgName);
            $hasRor = self::extractRorFromOrg($orgNode, $organizationsByStructId, $structId);

            if ($hasRor) {
                self::extractAcronymFromOrg($orgNode, $organizationsByStructId, $structId);
            }
        }

        return $organizationsByStructId;
    }

    /**
     * @param SimpleXMLElement $orgNode
     * @param array &$organizationsByStructId
     * @param string $structId
     * @return bool true if ROR was found
     */
    private static function extractRorFromOrg(SimpleXMLElement $orgNode, array &$organizationsByStructId, string $structId): bool
    {
        if (!$orgNode->idno) {
            return false;
        }

        foreach ($orgNode->idno as $idnoNode) {
            if ((string)$idnoNode->attributes()->type === self::IDNO_TYPE_ROR) {
                $rorId = str_replace(self::ROR_URL_PREFIX, '', (string)$idnoNode);
                $organizationsByStructId[$structId][self::IDNO_TYPE_ROR] = trim(self::ROR_URL_PREFIX . $rorId);
                return true;
            }
        }

        return false;
    }

    /**
     * @param SimpleXMLElement $orgNode
     * @param array &$organizationsByStructId
     * @param string $structId
     */
    private static function extractAcronymFromOrg(SimpleXMLElement $orgNode, array &$organizationsByStructId, string $structId): void
    {
        foreach ($orgNode->orgName as $orgNameNode) {
            if ((string)$orgNameNode->attributes()->type === self::KEY_ACRONYM) {
                $organizationsByStructId[$structId][self::KEY_ACRONYM] = trim((string)$orgNameNode);
                return;
            }
        }
    }

    /**
     * Replace affiliation structure references (e.g. "struct-1234") with their resolved details
     *
     * @param array $authorsWithStructRefs authors with raw structure references
     * @param array $affiliationsByStructId affiliation details keyed by structure ID
     * @return array authors with resolved affiliation details
     */
    public static function mergeAuthorInfoAndAffiTei(array $authorsWithStructRefs, array $affiliationsByStructId): array
    {
        foreach ($authorsWithStructRefs as $authorIndex => $author) {
            if (!isset($author[self::KEY_AFFILIATIONS])) {
                continue;
            }

            foreach ($author[self::KEY_AFFILIATIONS] as $affiIndex => $structRef) {
                $resolvedAffiliation = [self::KEY_NAME => $affiliationsByStructId[$structRef][self::KEY_NAME]];

                if (array_key_exists(self::IDNO_TYPE_ROR, $affiliationsByStructId[$structRef])) {
                    $resolvedAffiliation[self::IDNO_TYPE_ROR] = $affiliationsByStructId[$structRef][self::IDNO_TYPE_ROR];
                }

                if (array_key_exists(self::KEY_ACRONYM, $affiliationsByStructId[$structRef])) {
                    $resolvedAffiliation[self::KEY_ACRONYM] = $affiliationsByStructId[$structRef][self::KEY_ACRONYM];
                }

                $authorsWithStructRefs[$authorIndex][self::KEY_AFFILIATIONS][$affiIndex] = $resolvedAffiliation;
            }
        }

        return $authorsWithStructRefs;
    }
}
