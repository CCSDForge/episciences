<?php

use Episciences\Paper\Export;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Episciences_Oai_Server extends Ccsd_Oai_Server
{

    public const CACHE_CLASS_NAMESPACE = 'oai';
    public const LIMIT_IDENTIFIERS = 400;
    public const LIMIT_RECORDS = 100;
    public const SET_DRIVER = 'driver';
    public const SET_OPENAIRE = 'openaire';
    public const SET_JOURNAL = 'journal';
    public const SET_JOURNAL_PREFIX = 'journal:';
    public const OAI_TOKEN_EXPIRATION_TIME = 7200;
    private $_formats = ['oai_dc' => 'dc', 'tei' => 'tei', 'oai_openaire' => 'datacite', 'crossref' => 'crossref'];


    /**
     * @param string $url
     * @return array
     */
    protected function getIdentity($url): array
    {
        $earliestPublicationDate = Episciences_Paper::getEarliestPublicationDate();

        return [
            'repositoryName' => ucfirst(DOMAIN),
            'baseURL' => $url,
            'protocolVersion' => '2.0',
            'adminEmail' => 'contact@' . DOMAIN,
            'earliestDatestamp' => $earliestPublicationDate,
            'deletedRecord' => 'no',
            'granularity' => 'YYYY-MM-DD',
            'description' => [
                'oai-identifier' => [
                    'attributes' => [
                        'xmlns' => "http://www.openarchives.org/OAI/2.0/oai-identifier", 'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance", 'xsi:schemaLocation' => "http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd"],
                    'nodes' => ['scheme' => 'oai', 'repositoryIdentifier' => DOMAIN, 'delimiter' => ':', 'sampleIdentifier' => 'oai:' . DOMAIN . ':jdmdh:1']],
                'eprints' => [
                    'attributes' => [
                        'xmlns' => "http://www.openarchives.org/OAI/1.1/eprints", 'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance", 'xsi:schemaLocation' => "http://www.openarchives.org/OAI/1.1/eprints http://www.openarchives.org/OAI/1.1/eprints.xsd"],
                    'nodes' => ['content' => ['text' => 'Episciences is an overlay journal platform'], 'metadataPolicy' => ['text' => '1) CC0: https://creativecommons.org/publicdomain/zero/1.0/'], 'dataPolicy' => ['text' => '']]]
            ]
        ];
    }

    /**
     * @param int $identifier
     * @return bool
     */
    protected function existId($identifier)
    {
        // identifier format -> oai:episciences.org:jdmdh:1
        $identifier = (int)substr(strrchr($identifier, ":"), 1);

        try {
            $paper = Episciences_PapersManager::get($identifier);
        } catch (Zend_Db_Statement_Exception $exception) {
            $paper = false;
        }

        if (false === $paper) {
            return false;
        }
        return $paper->getStatus() === Episciences_Paper::STATUS_PUBLISHED;
    }

    /**
     * @param string $format
     * @return bool
     */
    protected function existFormat($format): bool
    {
        return array_key_exists($format, $this->getFormats());
    }

    /**
     * @return string[][]
     */
    protected function getFormats()
    {
        return [
            'oai_dc' => ['schema' => 'https://www.openarchives.org/OAI/2.0/oai_dc.xsd', 'ns' => 'http://www.openarchives.org/OAI/2.0/oai_dc/'],
            'tei' => ['schema' => 'https://api.archives-ouvertes.fr/documents/aofr.xsd', 'ns' => 'https://hal.archives-ouvertes.fr/'],
            'oai_openaire' => ['schema' => 'https://www.openaire.eu/schema/repo-lit/4.0/openaire.xsd', 'ns' => 'http://namespace.openaire.eu/schema/oaire/'],
            'crossref' => ['schema' => 'https://www.crossref.org/schemas/crossref5.3.1.xsd', 'ns' => 'http://www.crossref.org/schema/5.3.1']
        ];
    }

    /**
     * @param string $set
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function existSet($set)
    {
        return array_key_exists($set, $this->getSets());
    }

    /**
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getSets()
    {
        $cache = new FilesystemAdapter(self::CACHE_CLASS_NAMESPACE, 0, CACHE_PATH_METADATA);
        $sets = $cache->getItem(__FUNCTION__);
        $sets->expiresAfter(3600 * 24);

        if (!$sets->isHit()) {

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $out = [];
            // revues
            $sql = $db->select()
                ->from(T_REVIEW, ['CODE', 'NAME'])
                ->where('RVID != 0')
                ->where('STATUS = 1')
                ->order('CREATION DESC');
            foreach ($db->fetchAll($sql) as $row) {
                $out[self::SET_JOURNAL_PREFIX . $row['CODE']] = $row['NAME'];
            }
            if (count($out)) {
                $out[self::SET_OPENAIRE] = 'OpenAIRE';
                $out[self::SET_DRIVER] = 'Open Access DRIVERset';
                $out = [self::SET_JOURNAL => 'All ' . DOMAIN] + $out;
            }

            $sets->set($out);
            $cache->save($sets);
        } else {
            $out = $sets->get();
        }
        return $out;
    }

    /**
     * @param string $date
     * @return bool
     */
    protected function checkDateFormat($date): bool
    {
        return (new Zend_Validate_Date(['format' => 'yyyy-MM-dd']))->isValid($date);
    }

    /**
     * @param int $identifier
     * @param string $format
     * @return array|false
     * @throws Zend_Db_Statement_Exception
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function getId($identifier, $format)
    {
        // identifier format -> oai:episciences.org:jdmdh:1
        $identifier = (int)substr(strrchr($identifier, ":"), 1);
        $paper = Episciences_PapersManager::get($identifier);
        if (false === $paper) {
            return false;
        }
        if (!array_key_exists($format, $this->_formats)) {
            return false;
        }
        return ['header' => $paper->getOaiHeader(), 'metadata' => $this->getOaiMetadata($paper, $this->_formats[$format])];
    }

    /**
     * Returns the metadata string for a paper in the given internal format.
     * For the crossref format, the personal depositor email is replaced with a
     * generic address since this output is publicly harvested via OAI-PMH.
     */
    private function getOaiMetadata(Episciences_Paper $paper, string $internalFormat): string|false
    {
        if ($internalFormat === 'crossref') {
            return Export::getCrossref($paper, true);
        }
        return $paper->get($internalFormat);
    }

    /**
     * Returns the cache pool used to store OAI resumption tokens.
     *
     * Extracted as a protected method to allow injection of a test double (e.g. ArrayAdapter)
     * in unit tests without touching the filesystem.
     */
    protected function getTokenCachePool(): CacheItemPoolInterface
    {
        return new FilesystemAdapter('oai-token', 0, CACHE_PATH_METADATA);
    }

    /**
     * Executes a Solr query and returns the raw serialized response.
     *
     * Extracted as a protected method to allow mocking in unit tests.
     */
    protected function executeSolrQuery(string $queryString): string|false
    {
        return Episciences_Tools::solrCurl($queryString);
    }

    /**
     * @param string $method
     * @param string $format
     * @param string $until
     * @param string $from
     * @param string $set
     * @param string $token
     * @return array|false|int|string
     * @throws Zend_Db_Statement_Exception|\Psr\Cache\InvalidArgumentException
     */
    protected function getIds($method, $format, $until, $from, $set, $token)
    {
        if (!in_array($method, ['ListIdentifiers', 'ListRecords'])) {
            return false;
        }
        $queryString = "q=*:*";
        $conf = [];
        if ($token === null) {
            $conf['cursor'] = 0;
            $conf['format'] = $format;
            $query = '';
            if ($until !== null || $from !== null) {
                $query .= "&fq=publication_date_tdate:" . urlencode('[' . (($from == null) ? "*" : '"' . $from . 'T00:00:00Z"') . " TO " . (($until == null) ? "*" : '"' . $until . 'T23:59:59Z"') . "]");
            }
            if ($set !== null && str_starts_with($set, self::SET_JOURNAL_PREFIX)) {
                $query .= "&fq=revue_code_t:" . urlencode(substr($set, 8));
            }
            $conf['query'] = $query;
            $queryString .= $query;
            $queryString .= "&cursorMark=*";
        } else {
            $tokenPool = $this->getTokenCachePool();
            $cacheItem = $tokenPool->getItem('oai-token-' . md5($token));
            if (!$cacheItem->isHit()) {
                return 'token';
            }
            $conf = $cacheItem->get();
            $format = $conf['format'];
            $queryString .= $conf['query'] . "&cursorMark=" . urlencode($token);
        }

        if (!array_key_exists($format, $this->_formats)) {
            return false;
        }
        // maximum de retour
        $queryString .= "&rows=" . (($method === self::OAI_VERB_LISTIDS) ? self::LIMIT_IDENTIFIERS : self::LIMIT_RECORDS);
        // orderby
        $queryString .= "&sort=docid+desc";
        $queryString .= "&fl=docid&wt=phps";

        $solrResponse = $this->executeSolrQuery($queryString);
        $result = $solrResponse !== false ? unserialize($solrResponse, ['allowed_classes' => false]) : false;
        if (isset($result['response'], $result['response']['numFound']) && is_array($result['response'])) {
            if ($result['response']['numFound'] == 0) {
                return 0;
            }

            if (isset($result['response']['docs'], $result['nextCursorMark']) && is_array($result['response']['docs'])) {
                $out = [];
                foreach ($result['response']['docs'] as $res) {
                    $paper = Episciences_PapersManager::get($res['docid'], false);
                    if (false === $paper) {
                        continue;
                    }
                    $oaiHeader = $paper->getOaiHeader();
                    if ($method === self::OAI_VERB_LISTIDS) {
                        $out[] = $oaiHeader;
                    } else {
                        $out[] = ['header' => $oaiHeader, 'metadata' => $this->getOaiMetadata($paper, $this->_formats[$format])];
                    }
                }
                // token
                if ($result['response']['numFound'] > (($method === self::OAI_VERB_LISTIDS) ? self::LIMIT_IDENTIFIERS : self::LIMIT_RECORDS)) {
                    if ($result['nextCursorMark'] === $token) {
                        // c'est la fin
                        if (count($out)) {
                            $out[] = '<resumptionToken completeListSize="' . $result['response']['numFound'] . '" />';
                        } else {
                            return 0;
                        }
                    } elseif ($result['response']['numFound'] > ($conf['cursor'] + count($out))) {
                        $out[] = '<resumptionToken expirationDate="' . gmdate("Y-m-d\TH:i:s\Z", time() + self::OAI_TOKEN_EXPIRATION_TIME) . '" completeListSize="' . $result['response']['numFound'] . '" cursor="' . $conf['cursor'] . '">' . $result['nextCursorMark'] . '</resumptionToken>';
                        $conf['cursor'] += (($method === self::OAI_VERB_LISTIDS) ? self::LIMIT_IDENTIFIERS : self::LIMIT_RECORDS);
                        $conf['solr'] = $queryString;

                        $tokenPool = $this->getTokenCachePool();
                        $newTokenItem = $tokenPool->getItem('oai-token-' . md5($result['nextCursorMark']));
                        $newTokenItem->set($conf)->expiresAfter(self::OAI_TOKEN_EXPIRATION_TIME);
                        $tokenPool->save($newTokenItem);
                    } else {
                        $out[] = '<resumptionToken completeListSize="' . $result['response']['numFound'] . '" />';
                    }
                }
                return $out;
            }

            return false;
        }

        return false;
    }

}