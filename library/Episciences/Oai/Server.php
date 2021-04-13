<?php
/*
 * Configuration du serveur OAI de l'application HAL par rapport à la librairie OAI CCSD
 * + déclaration de l'identité
 * + paramètrage des formats
 * + ajout des collections
 * + Définition de comment retrouve t on les documents
 */

class Episciences_Oai_Server extends Ccsd_Oai_Server
{

    const LIMIT_IDENTIFIERS = 400;
    const LIMIT_RECORDS = 100;
    private $_formats = ['oai_dc' => 'dc', 'tei' => 'tei'];

    protected function getIdentity($url)
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

    /*
     * retourne les formats dispo sur le serveur OAI
     * @return array code=>array('schema'=>, 'ns'=>)
     */

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

    /*
     * retourne les sets dispo sur le serveur OAI
     * @return array code=>name
     */

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
        $cacheName = 'oai-sets.phps';
        if (Episciences_Cache::exist($cacheName, 3600)) {
            $out = unserialize(Episciences_Cache::get($cacheName));
        } else {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $out = [];
            // revues
            $sql = $db->select()
                ->from(T_REVIEW, ['CODE', 'NAME'])
                ->where('RVID != 0')
                ->where('STATUS = 1')
                ->order('CREATION DESC');
            foreach ($db->fetchAll($sql) as $row) {
                $out['journal:' . $row['CODE']] = $row['NAME'];
            }
            if (count($out)) {
                $out = ['journal' => 'All ' . DOMAIN] + $out;
            }
            Episciences_Cache::save($cacheName, serialize($out));
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

    protected function getIds($method, $format, $until, $from, $set, $token)
    {
        if (!in_array($method, ['ListIdentifiers', 'ListRecords'])) {
            return false;
        }
        $queryString = "q=*:*";
        $conf = [];
        if ($token == null) {
            $conf['cursor'] = 0;
            $conf['format'] = $format;
            $query = '';
            if ($until != null || $from != null) {
                $query .= "&fq=publication_date_tdate:" . urlencode('[' . (($from == null) ? "*" : '"' . $from . 'T00:00:00Z"') . " TO " . (($until == null) ? "*" : '"' . $until . 'T23:59:59Z"') . "]");
            }
            if ($set != null) {
                if (substr($set, 0, 8) == 'journal:') {
                    $query .= "&fq=revue_code_t:" . urlencode(substr($set, 8));
                }
            }
            $conf['query'] = $query;
            $queryString .= $query;
            $queryString .= "&cursorMark=*";
        } else {
            if (!Episciences_Cache::exist('oai-token-' . md5($token) . '.phps', 7200)) {
                return 'token';
            }
            $conf = unserialize(Episciences_Cache::get('oai-token-' . md5($token) . '.phps'));
            $format = $conf['format'];
            $queryString .= $conf['query'] . "&cursorMark=" . urlencode($token);
        }
        if (!array_key_exists($format, $this->_formats)) {
            return false;
        }
        // maximum de retour
        $queryString .= "&rows=" . (($method == self::OAI_VERB_LISTIDS) ? Episciences_Oai_Server::LIMIT_IDENTIFIERS : Episciences_Oai_Server::LIMIT_RECORDS);
        // orderby
        $queryString .= "&sort=docid+desc";
        $queryString .= "&fl=docid&wt=phps";

        $result = unserialize(Episciences_Tools::solrCurl($queryString));
        if (isset($result['response']) && is_array($result['response']) && isset($result['response']['numFound'])) {
            if ($result['response']['numFound'] == 0) {
                return 0;
            } else {
                if (isset($result['response']['docs']) && is_array($result['response']['docs']) && isset($result['nextCursorMark'])) {
                    $out = [];
                    foreach ($result['response']['docs'] as $res) {
                        $paper = Episciences_PapersManager::get($res['docid'], false);
                        if (false === $paper) {
                            continue;
                        }
                        if ($method == self::OAI_VERB_LISTIDS) {
                            $out[] = $paper->getOaiHeader();
                        } else {
                            $out[] = ['header' => $paper->getOaiHeader(), 'metadata' => $paper->get($this->_formats[$format])];
                        }
                    }
                    // token
                    if ($result['response']['numFound'] > (($method == self::OAI_VERB_LISTIDS) ? Episciences_Oai_Server::LIMIT_IDENTIFIERS : Episciences_Oai_Server::LIMIT_RECORDS)) {
                        if ($result['nextCursorMark'] == $token) {
                            // c'est la fin
                            if (count($out)) {
                                $out[] = '<resumptionToken completeListSize="' . $result['response']['numFound'] . '" />';
                            } else {
                                return 0;
                            }
                        } else {
                            // attention, Solr nous donne un autre cursor même si la prochaine requete ne remonte plus de résultat
                            if ($result['response']['numFound'] > ($conf['cursor'] + count($out))) {
                                $out[] = '<resumptionToken expirationDate="' . gmdate("Y-m-d\TH:i:s\Z", time() + 7200) . '" completeListSize="' . $result['response']['numFound'] . '" cursor="' . $conf['cursor'] . '">' . $result['nextCursorMark'] . '</resumptionToken>';
                                $conf['cursor'] += (($method == self::OAI_VERB_LISTIDS) ? Episciences_Oai_Server::LIMIT_IDENTIFIERS : Episciences_Oai_Server::LIMIT_RECORDS);
                                $conf['solr'] = $queryString;
                                Episciences_Cache::save('oai-token-' . md5($result['nextCursorMark']) . '.phps', serialize($conf));
                            } else {
                                $out[] = '<resumptionToken completeListSize="' . $result['response']['numFound'] . '" />';
                            }
                        }
                    }
                    return $out;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

}