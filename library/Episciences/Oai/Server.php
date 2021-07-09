<?php

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

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
    private $_formats = ['oai_dc' => 'dc', 'tei' => 'tei'];


    /**
     * @param string $url
     * @return array
     */
    protected function getIdentity($url): array
    {
        return [
            'repositoryName' => ucfirst(DOMAIN),
            'baseURL' => $url,
            'protocolVersion' => '2.0',
            'adminEmail' => 'contact@' . DOMAIN,
            'earliestDatestamp' => '2000-01-01',
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

    protected function existId($identifier)
    {
        // identifier format -> oai:episciences.org:jdmdh:1
        $identifier = (int)substr(strrchr($identifier, ":"), 1);
        $paper = Episciences_PapersManager::get($identifier);
        if (false === $paper) {
            return false;
        }
        return $paper->getStatus() == Episciences_Paper::STATUS_PUBLISHED;
    }

    protected function existFormat($format)
    {
        return array_key_exists($format, $this->getFormats());
    }

    protected function getFormats()
    {
        return [
            'oai_dc' => ['schema' => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd', 'ns' => 'http://www.openarchives.org/OAI/2.0/oai_dc/'],
            'tei' => ['schema' => 'https://api.archives-ouvertes.fr/documents/aofr.xsd', 'ns' => 'https://hal.archives-ouvertes.fr/'],
            //  'datacite' => ['schema' => 'http://schema.datacite.org/meta/kernel-4.3/metadata.xsd', 'ns' => 'http://datacite.org/schema/kernel-4']
        ];
    }

    protected function existSet($set)
    {
        return array_key_exists($set, $this->getSets());
    }

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

    protected function checkDateFormat($date)
    {
        return (new Zend_Validate_Date(['format' => 'yyyy-MM-dd']))->isValid($date);
    }

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
        return ['header' => $paper->getOaiHeader(), 'metadata' => $paper->get($this->_formats[$format])];
    }

    /**
     * @param string $method
     * @param string $format
     * @param string $until
     * @param string $from
     * @param string $set
     * @param string $token
     * @return array|false|int|string
     * @throws Zend_Db_Statement_Exception
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
            if ($until != null || $from != null) {
                $query .= "&fq=publication_date_tdate:" . urlencode('[' . (($from == null) ? "*" : '"' . $from . 'T00:00:00Z"') . " TO " . (($until == null) ? "*" : '"' . $until . 'T23:59:59Z"') . "]");
            }
            if ((($set !== null) || ($set !== self::SET_DRIVER) || ($set !== self::SET_OPENAIRE)) && strpos($set, self::SET_JOURNAL_PREFIX) === 0) {
                $query .= "&fq=revue_code_t:" . urlencode(substr($set, 8));
            }
            $conf['query'] = $query;
            $queryString .= $query;
            $queryString .= "&cursorMark=*";
        } else {
            if (!Episciences_Cache::exist('oai-token-' . md5($token) . '.phps', self::OAI_TOKEN_EXPIRATION_TIME)) {
                return 'token';
            }
            $conf = unserialize(Episciences_Cache::get('oai-token-' . md5($token) . '.phps'), ['allowed_classes' => false]);
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

        $result = unserialize(Episciences_Tools::solrCurl($queryString), ['allowed_classes' => false]);
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
                        $out[] = ['header' => $oaiHeader, 'metadata' => $paper->get($this->_formats[$format])];
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
                    } else if ($result['response']['numFound'] > ($conf['cursor'] + count($out))) {
                        $out[] = '<resumptionToken expirationDate="' . gmdate("Y-m-d\TH:i:s\Z", time() + self::OAI_TOKEN_EXPIRATION_TIME) . '" completeListSize="' . $result['response']['numFound'] . '" cursor="' . $conf['cursor'] . '">' . $result['nextCursorMark'] . '</resumptionToken>';
                        $conf['cursor'] += (($method === self::OAI_VERB_LISTIDS) ? self::LIMIT_IDENTIFIERS : self::LIMIT_RECORDS);
                        $conf['solr'] = $queryString;
                        Episciences_Cache::save('oai-token-' . md5($result['nextCursorMark']) . '.phps', serialize($conf));
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