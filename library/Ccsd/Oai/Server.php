<?php

/**
 * Class Ccsd_Oai_Server
 */
abstract class Ccsd_Oai_Server
{

    const LIMIT_IDENTIFIERS = 400;
    const LIMIT_RECORDS = 100;

    const OAI_VERB_LISTIDS = 'ListIdentifiers';
    const OAI_VERB_LISTRECS = 'ListRecords';
    const OAI_VERB_IDENTIFY = "Identify";
    const OAI_VERB_LIST_SETS = "ListSets";
    const OAI_VERB_LIST_METADATA_FORMATS = "ListMetadataFormats";
    const OAI_VERB_GET_RECORD = "GetRecord";


    protected $_config = array();
    protected $_params = array();
    protected $_xml = null;
    protected $_oaipmh = null;
    protected $_format;
    /**
     * @var string
     * Use to diffferentiate the change of Oai identifier form
     *
     * We pass from oai:HAL:hal-00000001
     *      to oai:hal.archives-ouvertes.fr:hal-0000001
     */
    protected $version = "v1";

    /**
     * Ccsd_Oai_Server constructor.
     * @param Zend_Controller_Request_Abstract $request
     */
    public function __construct(Zend_Controller_Request_Abstract $request, $version = "v1")
    {

        $this->version = $version;
        /** @var Zend_Controller_Request_Http $request */
        header('Content-Type: text/xml; charset=utf-8');
        $this->_xml = new Ccsd_DOMDocument('1.0', 'utf-8');
        $this->_xml->formatOutput = false;
        $this->_xml->substituteEntities = true;
        $this->_xml->preserveWhiteSpace = false;
        $this->_xml->appendChild($this->_xml->createProcessingInstruction('xml-stylesheet', 'type="text/xsl" href="/oai/xsl"'));
        $this->_oaipmh = $this->_xml->createElement('OAI-PMH');
        $this->_xml->appendChild($this->_oaipmh);
        $this->_oaipmh->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.openarchives.org/OAI/2.0/');
        $this->_oaipmh->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd');
        $this->_oaipmh->appendChild($this->_xml->createElement('responseDate', gmdate('Y-m-d\TH:i:s\Z')));
        $requete = $this->_xml->createElement('request', $request->getScheme() . '://' . $request->getHttpHost() . $request->getPathInfo());
        $this->_oaipmh->appendChild($requete);

        if ($request->isPost()) {
            $this->_params = $request->getPost();
        } else if ($request->isGet()) {
            $this->_params = $request->getQuery();
            // doublon de parametres
            $paramsQuery = array_filter(explode('&', htmlspecialchars_decode($_SERVER['QUERY_STRING'])));
            if (count($this->_params) != count($paramsQuery)) {
                $error = new Ccsd_Oai_Error('sameArgument');
                if ($this->_xml instanceof Ccsd_DOMDocument) {
                    $errorNode = $this->_xml->createElement('error', $error->getMessage());
                    $errorNode->setAttribute('code', $error->getCode());
                    $this->_oaipmh->appendChild($errorNode);
                    echo $this->_xml->saveXML();
                }
                exit;
            }
        } else {
            $error = new Ccsd_Oai_Error('badRequestMethod', $request->getMethod());
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
                echo $this->_xml->saveXML();
            }
            exit;
        }
        if (isset($this->_params['XDEBUG_SESSION_START'])) {
            unset($this->_params['XDEBUG_SESSION_START']);
        }
        $this->_params = array_map('rawurldecode', $this->_params);

        // Handle &amp; in query string that PHP doesn't decode in keys (common mistake when copying from XML/XSL)
        foreach ($this->_params as $key => $val) {
            if (str_starts_with($key, 'amp;')) {
                $newKey = substr($key, 4);
                if (!isset($this->_params[$newKey])) {
                    $this->_params[$newKey] = $val;
                }
                unset($this->_params[$key]);
            }
        }

        if (array_key_exists('verb', $this->_params) && is_string($this->_params['verb'])) {
            foreach ($this->_params as $key => $param) {
                if (is_string($param) && !empty($param)) {
                    $requete->setAttribute($key, $param);
                }
            }
            $verb = $this->_params['verb'];
            unset($this->_params['verb']);
            switch ($verb) {
                case self::OAI_VERB_IDENTIFY:
                    $this->identify($request->getScheme() . '://' . $request->getHttpHost() . $request->getPathInfo());
                    break;
                case self::OAI_VERB_LIST_SETS:
                    $this->listSets();
                    break;
                case self::OAI_VERB_LIST_METADATA_FORMATS:
                    $this->listMetadataFormats();
                    break;
                case self::OAI_VERB_GET_RECORD:
                    if (!array_key_exists("metadataPrefix", $this->_params)) {
                        $error = new Ccsd_Oai_Error('missingArgument', 'metadataPrefix');
                        if ($this->_xml instanceof Ccsd_DOMDocument) {
                            $errorNode = $this->_xml->createElement('error', $error->getMessage());
                            $errorNode->setAttribute('code', $error->getCode());
                            $this->_oaipmh->appendChild($errorNode);
                            echo $this->_xml->saveXML();
                        }
                        exit;
                    }
                    if (!array_key_exists("identifier", $this->_params)) {
                        $error = new Ccsd_Oai_Error('missingArgument', 'identifier');
                        if ($this->_xml instanceof Ccsd_DOMDocument) {
                            $errorNode = $this->_xml->createElement('error', $error->getMessage());
                            $errorNode->setAttribute('code', $error->getCode());
                            $this->_oaipmh->appendChild($errorNode);
                            echo $this->_xml->saveXML();
                        }
                        exit;
                    }
                    $this->getRecord();
                    break;
                case self::OAI_VERB_LISTIDS:
                    if (!array_key_exists("metadataPrefix", $this->_params) && !array_key_exists("resumptionToken", $this->_params)) {
                        $error = new Ccsd_Oai_Error('missingArgument', 'metadataPrefix');
                        if ($this->_xml instanceof Ccsd_DOMDocument) {
                            $errorNode = $this->_xml->createElement('error', $error->getMessage());
                            $errorNode->setAttribute('code', $error->getCode());
                            $this->_oaipmh->appendChild($errorNode);
                            echo $this->_xml->saveXML();
                        }
                        exit;
                    }
                    $this->listIdentifiers();
                    break;
                case self::OAI_VERB_LISTRECS:
                    if (!array_key_exists("metadataPrefix", $this->_params) && !array_key_exists("resumptionToken", $this->_params)) {
                        $error = new Ccsd_Oai_Error('missingArgument', 'metadataPrefix');
                        if ($this->_xml instanceof Ccsd_DOMDocument) {
                            $errorNode = $this->_xml->createElement('error', $error->getMessage());
                            $errorNode->setAttribute('code', $error->getCode());
                            $this->_oaipmh->appendChild($errorNode);
                            echo $this->_xml->saveXML();
                        }
                        exit;
                    }
                    $this->listRecords();
                    break;
                default:
                    $error = new Ccsd_Oai_Error('badVerb', $verb);
                    if ($this->_xml instanceof Ccsd_DOMDocument) {
                        $errorNode = $this->_xml->createElement('error', $error->getMessage());
                        $errorNode->setAttribute('code', $error->getCode());
                        $this->_oaipmh->appendChild($errorNode);
                        echo $this->_xml->saveXML();
                    }
                    exit;
            }

            if ($this->_xml instanceof Ccsd_DOMDocument) {
                echo $this->_xml->saveXML();
            }
            exit;
        }

        $error = new Ccsd_Oai_Error('noVerb');
        if ($this->_xml instanceof Ccsd_DOMDocument) {
            $errorNode = $this->_xml->createElement('error', $error->getMessage());
            $errorNode->setAttribute('code', $error->getCode());
            $this->_oaipmh->appendChild($errorNode);
            echo $this->_xml->saveXML();
        }

    }

    /**
     * @param string $url
     * @return bool
     */
    private function identify($url)
    {
        if (count($this->_params) > 0) {
            foreach ($this->_params as $key => $val) {
                $error = new Ccsd_Oai_Error('badArgument', $key, $val);
                if ($this->_xml instanceof Ccsd_DOMDocument) {
                    $errorNode = $this->_xml->createElement('error', $error->getMessage());
                    $errorNode->setAttribute('code', $error->getCode());
                    $this->_oaipmh->appendChild($errorNode);
                }
            }
            return false;
        }
        $identify = $this->_xml->createElement(self::OAI_VERB_IDENTIFY);
        foreach ($this->getIdentity($url) as $config => $value) {
            if (is_array($value)) {
                foreach ($value as $node => $val) {
                    $descs = $this->_xml->createElement($config);
                    if (isset($val['attributes'], $val['nodes']) && is_array($val) && is_array($val['attributes']) && is_array($val['nodes'])) {
                        $desc = $this->_xml->createElement($node);
                        foreach ($val['attributes'] as $ns => $attribute) {
                            $desc->setAttribute($ns, $attribute);
                        }
                        foreach ($val['nodes'] as $entry => $nodeChild) {
                            if (is_array($nodeChild)) {
                                $n = $this->_xml->createElement($entry);
                                foreach ($nodeChild as $k => $v) {
                                    $n->appendChild($this->_xml->createElement($k, $v));
                                }
                                $desc->appendChild($n);
                            } else {
                                $desc->appendChild($this->_xml->createElement($entry, $nodeChild));
                            }
                        }
                        $descs->appendChild($desc);
                    }
                    $identify->appendChild($descs);
                }
            } else {
                $identify->appendChild($this->_xml->createElement($config, $value));
            }
        }
        $this->_oaipmh->appendChild($identify);
        return true;
    }

    /**
     * @return bool  (true on success)
     */
    private function listSets()
    {
        if (count($this->_params) > 0) {
            foreach ($this->_params as $key => $val) {
                if ($key === 'resumptionToken') {
                    $error = new Ccsd_Oai_Error("badResumptionToken", $key, $val);
                    if ($this->_xml instanceof Ccsd_DOMDocument) {
                        $errorNode = $this->_xml->createElement('error', $error->getMessage());
                        $errorNode->setAttribute('code', $error->getCode());
                        $this->_oaipmh->appendChild($errorNode);
                    }
                } else {
                    $error = new Ccsd_Oai_Error('badArgument', $key, $val);
                    if ($this->_xml instanceof Ccsd_DOMDocument) {
                        $errorNode = $this->_xml->createElement('error', $error->getMessage());
                        $errorNode->setAttribute('code', $error->getCode());
                        $this->_oaipmh->appendChild($errorNode);
                    }
                }
            }
            return false;
        }
        $sets = $this->_xml->createElement(self::OAI_VERB_LIST_SETS);
        foreach ($this->getSets() as $code => $name) {
            $set = $this->_xml->createElement('set');
            $set->appendChild($this->_xml->createElement('setSpec', $code));
            $set->appendChild($this->_xml->createElement('setName', $name));
            $sets->appendChild($set);
        }
        $this->_oaipmh->appendChild($sets);
        return true;
    }

    /**
     * @return bool (true on success)
     */
    private function listMetadataFormats()
    {
        $identifier = $error = null;
        if (count($this->_params) > 0) {
            foreach ($this->_params as $key => $val) {
                if ($key === 'identifier') {
                    $identifier = $val;
                } else {
                    $error = new Ccsd_Oai_Error('badArgument', $key, $val);
                    if ($this->_xml instanceof Ccsd_DOMDocument) {
                        $errorNode = $this->_xml->createElement('error', $error->getMessage());
                        $errorNode->setAttribute('code', $error->getCode());
                        $this->_oaipmh->appendChild($errorNode);
                    }
                }
            }
            if (null != $error) {
                return false;
            }
        }
        if ((null !== $identifier) && !$this->existId($identifier)) {
            $error = new Ccsd_Oai_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE, 'identifier', $identifier);
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
            }
            return false;
        }
        $formats = $this->_xml->createElement(self::OAI_VERB_LIST_METADATA_FORMATS);
        foreach ($this->getFormats() as $code => $metas) {
            $format = $this->_xml->createElement('metadataFormat');
            $format->appendChild($this->_xml->createElement('metadataPrefix', $code));
            if (isset($metas['schema'])) {
                $format->appendChild($this->_xml->createElement('schema', $metas['schema']));
            }
            if (isset($metas['ns'])) {
                $format->appendChild($this->_xml->createElement('metadataNamespace', $metas['ns']));
            }
            $formats->appendChild($format);
        }
        $this->_oaipmh->appendChild($formats);
        return true;
    }

    /**
     * @return bool
     */
    private function getRecord()
    {
        $identifier = $format = $error = null;
        if (count($this->_params) > 0) {
            foreach ($this->_params as $key => $val) {
                switch ($key) {
                    case 'metadataPrefix':
                        if (!$this->existFormat($val)) {
                            $error = new Ccsd_Oai_Error('cannotDisseminateFormat', 'metadataPrefix', $val);
                            if ($this->_xml instanceof Ccsd_DOMDocument) {
                                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                                $errorNode->setAttribute('code', $error->getCode());
                                $this->_oaipmh->appendChild($errorNode);
                            }
                        } else {
                            $format = $val;
                        }
                        break;
                    case 'identifier':
                        if (!$this->existId($val)) {
                            $error = new Ccsd_Oai_Error(Ccsd_Error::ID_DOES_NOT_EXIST_CODE, 'identifier', $val);
                            if ($this->_xml instanceof Ccsd_DOMDocument) {
                                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                                $errorNode->setAttribute('code', $error->getCode());
                                $this->_oaipmh->appendChild($errorNode);
                            }
                        } else {
                            $identifier = $val;
                        }
                        break;
                    default:
                        $error = new Ccsd_Oai_Error('badArgument', $key, $val);
                        if ($this->_xml instanceof Ccsd_DOMDocument) {
                            $errorNode = $this->_xml->createElement('error', $error->getMessage());
                            $errorNode->setAttribute('code', $error->getCode());
                            $this->_oaipmh->appendChild($errorNode);
                        }
                }
            }
            if (null != $error) {
                return false;
            }
        }
        if (null == $identifier) {
            $error = new Ccsd_Oai_Error('missingArgument', 'identifier');
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
            }
        }
        if (null == $format) {
            $error = new Ccsd_Oai_Error('missingArgument', 'metadataPrefix');
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
            }
        }
        if (null != $error) {
            return false;
        }
        $rec = $this->getId($identifier, $format);
        if (isset($rec['header'], $rec['metadata']) && is_array($rec)) {
            try {
                $gr = $this->_xml->createElement(self::OAI_VERB_GET_RECORD);
                $record = $this->_xml->createElement('record');
                $gr->appendChild($record);
                $header = $this->_xml->createDocumentFragment();
                $header->appendXML($rec['header']);
                $record->appendChild($header);
                $metadata = $this->_xml->createElement('metadata');
                if ($format === 'xml-tei' || $format === 'tei') {
                    $metadata->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tei', 'http://www.tei-c.org/ns/1.0');
                }
                if ($format === 'oai_openaire') {
                    $metadata->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:datacite', 'http://datacite.org/schema/kernel-4');
                }
                $data = $this->_xml->createDocumentFragment();
                $data->appendXML($rec['metadata']);
                $metadata->appendChild($data);
                $record->appendChild($metadata);
                $this->_oaipmh->appendChild($gr);
            } catch (Exception $e) {
                return false;
            }
        } else {
            $error = new Ccsd_Oai_Error('missingArgument', 'identifier');
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
            }
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    private function listIdentifiers()
    {
        return $this->listIds('ListIdentifiers');
    }

    /**
     * @return bool
     */
    private function listRecords()
    {
        return $this->listIds('ListRecords');
    }

    /**
     * @param $method
     * @return bool
     */
    private function listIds($method)
    {
        $format = $until = $from = $set = $token = $error = null;
        if (count($this->_params) > 0) {
            if (isset($this->_params['resumptionToken']) && count($this->_params) > 1) {
                $error = new Ccsd_Oai_Error('exclusiveArgument');
                if ($this->_xml instanceof Ccsd_DOMDocument) {
                    $errorNode = $this->_xml->createElement('error', $error->getMessage());
                    $errorNode->setAttribute('code', $error->getCode());
                    $this->_oaipmh->appendChild($errorNode);
                }
                return false;
            }
            foreach ($this->_params as $key => $val) {
                switch ($key) {
                    case 'metadataPrefix':
                        if (!$this->existFormat($val)) {
                            $error = new Ccsd_Oai_Error('cannotDisseminateFormat', 'metadataPrefix', $val);
                            if ($this->_xml instanceof Ccsd_DOMDocument) {
                                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                                $errorNode->setAttribute('code', $error->getCode());
                                $this->_oaipmh->appendChild($errorNode);
                            }
                        } else {
                            $format = $val;
                        }
                        break;
                    case 'until':
                        if (!$this->checkDateFormat($val)) {
                            $error = new Ccsd_Oai_Error('badGranularity', 'until', $val);
                            if ($this->_xml instanceof Ccsd_DOMDocument) {
                                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                                $errorNode->setAttribute('code', $error->getCode());
                                $this->_oaipmh->appendChild($errorNode);
                            }
                        } else {
                            $until = $val;
                        }
                        break;
                    case 'from':
                        if (!$this->checkDateFormat($val)) {
                            $error = new Ccsd_Oai_Error('badGranularity', 'from', $val);
                            if ($this->_xml instanceof Ccsd_DOMDocument) {
                                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                                $errorNode->setAttribute('code', $error->getCode());
                                $this->_oaipmh->appendChild($errorNode);
                            }
                        } else {
                            $from = $val;
                        }
                        break;
                    case 'set':
                        if (!$this->existSet($val)) {
                            $error = new Ccsd_Oai_Error('badArgument', $key, $val);
                            if ($this->_xml instanceof Ccsd_DOMDocument) {
                                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                                $errorNode->setAttribute('code', $error->getCode());
                                $this->_oaipmh->appendChild($errorNode);
                            }
                        } else {
                            $set = $val;
                        }
                        break;
                    case 'resumptionToken':
                        // handle cursorMarks with '+' (plus) like AoFf4+EF
                        $token = str_replace(' ', '+', $val);
                        break;
                    default:
                        $error = new Ccsd_Oai_Error('badArgument', $key, $val);
                        if ($this->_xml instanceof Ccsd_DOMDocument) {
                            $errorNode = $this->_xml->createElement('error', $error->getMessage());
                            $errorNode->setAttribute('code', $error->getCode());
                            $this->_oaipmh->appendChild($errorNode);
                        }
                }
            }
            if (null != $error) {
                return false;
            }
        }
        // pas de format spécifié ni de token dans lequel le format est stocké
        if (null == $format && null == $token) {
            $error = new Ccsd_Oai_Error('missingArgument', 'metadataPrefix');
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
            }
            return false;
        }
        // retourne les docid de documents
        $docids = $this->getIds($method, $format, $until, $from, $set, $token);
        if (is_array($docids) && count($docids)) {
            try {
                // TODO faire plus propre c'est juste un hotfix parce que l'on perd le format quand on utilise un resumptionToken
                if (($format == null) && (array_key_exists('metadataPrefix', $docids))) {
                    $format = $docids['metadataPrefix'];
                }

                if (array_key_exists('metadataPrefix', $docids)) {
                    unset($docids['metadataPrefix']);
                }
                // fin du TO DO

                $resp = $this->_xml->createElement($method);
                $this->_oaipmh->appendChild($resp);
                foreach ($docids as $document) {
                    if (isset($document['header'], $document['metadata']) && is_array($document) && $document['header'] && $document['metadata']) {
                        $record = $this->_xml->createElement('record');
                        $resp->appendChild($record);
                        $header = $this->_xml->createDocumentFragment();
                        $header->appendXML($document['header']);
                        $record->appendChild($header);
                        $metadata = $this->_xml->createElement('metadata');
                        if ($format === 'xml-tei' || $format === 'tei') {
                            $metadata->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:tei', 'http://www.tei-c.org/ns/1.0');
                        }
                        if ($format === 'oai_openaire') {
                            $metadata->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:datacite', 'http://datacite.org/schema/kernel-4');
                        }
                        $data = $this->_xml->createDocumentFragment();
                        $data->appendXML($document['metadata']);
                        $metadata->appendChild($data);
                        $record->appendChild($metadata);
                    } else if (is_string($document)) {
                        // Juste une chaine XML (resumptionToken) ou XML deja serialise
                        $str = $this->_xml->createDocumentFragment();
                        $str->appendXML($document);
                        $resp->appendChild($str);
                    }
                }
            } catch (Exception $e) {
                return false;
            }
        } else {
            if ($docids === 0) {
                $error = new Ccsd_Oai_Error('noRecordsMatch');
            } else if ($docids === 'token') {
                $error = new Ccsd_Oai_Error('badResumptionToken', $token);
            } else {
                $error = new Ccsd_Oai_Error('100', 'Solr', 'Error');
            }
            if ($this->_xml instanceof Ccsd_DOMDocument) {
                $errorNode = $this->_xml->createElement('error', $error->getMessage());
                $errorNode->setAttribute('code', $error->getCode());
                $this->_oaipmh->appendChild($errorNode);
            }
            return false;
        }
        return true;
    }

    /**
     * @param string $url
     */
    abstract protected function getIdentity($url);

    /**  */
    abstract protected function getFormats();

    /**  */
    abstract protected function getSets();

    /**
     * @param int $id
     */
    abstract protected function existId($id);

    /**
     * @param string $format
     */
    abstract protected function existFormat($format);

    /**
     * @param string $set
     */
    abstract protected function existSet($set);

    /**
     * @param string $date
     */
    abstract protected function checkDateFormat($date);

    /**
     * @param int $identifier
     * @param string $format
     */
    abstract protected function getId($identifier, $format);

    /**
     * @param string $method
     * @param string $format
     * @param string $until
     * @param string $from
     * @param string $set
     * @param string $token
     */
    abstract protected function getIds($method, $format, $until, $from, $set, $token);

    /**
     * feuille de style pour un affichage sympa des flux OAI
     * @throws Exception
     */
    public static function getXsl()
    {
        if (is_file(__DIR__ . '/oai2.xsl')) {
            return file_get_contents(__DIR__ . '/oai2.xsl');
        }
        throw new Exception("File " . __DIR__ . '/oai2.xsl non exists');
    }
}