<?php

use Psr\Log\LoggerInterface;

class Ccsd_Oai_Client {

	/**
	 * Maximum duration in seconds of an OAI-PMH request: a submission waits for the answer,
	 * so an unresponsive repository must fail fast instead of blocking the author
	 */
	public const REQUEST_TIMEOUT = 30;
	public const CONNECT_TIMEOUT = 10;

	protected $baseUrl = null;

	/** Failed requests are logged here when set */
	protected ?LoggerInterface $logger = null;

	/** curl_getinfo() of the last request: timings, HTTP code, remote address */
	protected array $lastTransferInfo = [];
	protected $_outputFormat = null; // format de sortie des verbes OAI : array or xml

	public function __construct( $url = null, $format = 'array')
	{
		if ( null != $url ) {
			$this->baseUrl = $url;
		}
		$this->_outputFormat = $format;
	}
	
	public function setLogger(?LoggerInterface $logger): void
	{
		$this->logger = $logger;
	}

	public function getBaseUrl()
	{
		return $this->baseUrl;
	}
	
	public function setBaseUrl( $url = null)
	{
		if ( null != $url ) {
			$this->baseUrl = $url;
		}
	}
	
	public function getOutputFormat()
	{
		return $this->_outputFormat;
	}
	
	public function setOutputFormat( $format = null)
	{
		if ( null != $format ) {
			$this->_outputFormat = $format;
		}
	}
	
	public function identify()
	{
		if ( null == $this->baseUrl ) {
			throw new Exception("URL du serveur OAI-PMH non définie", 0);
		}
		try {
			$dom = new DOMDocument();
			$dom->loadXML((string) file_get_contents($this->baseUrl.'?verb=Identify'));
			$xpath = new DOMXPath($dom);
			foreach (Ccsd_Tools::getNamespaces($dom->documentElement) as $id => $ns) {
				$xpath->registerNamespace($id, $ns);
			}
			$error = self::isOaiError($xpath->query('/xmlns:OAI-PMH/xmlns:error'));
			if ( $error ) {
				throw new Exception($error, 1);
			}
			$record = $xpath->query('//xmlns:Identify');
			if ( $record->length ) {
				return ( $this->_outputFormat == 'array') ? Ccsd_Tools::dom2array($record->item(0)) : $dom->saveXML($record->item(0));
			} else {
				throw new Exception("XML non valide", 0);
			}
		} catch ( Exception $e ) {
			throw new Exception($e->getMessage(), 0);
		}
	}

    /**
     * @param $identifier
     * @param string $format
     * @return array|string
     * @throws Exception
     */
	public function getRecord($identifier, $format='oai_dc')
	{
		if ( null == $this->baseUrl ) {
			throw new Exception("URL du serveur OAI-PMH non définie", "0");
		}

        $error = false;

		try {
			$dom = new DOMDocument();
            $userAgent = method_exists($this, 'getUserAgent') ? $this->getUserAgent() : 'CcsdToolsCurl';
            $options[CURLOPT_USERAGENT] = $userAgent;
            $options[CURLOPT_CONNECTTIMEOUT] = static::CONNECT_TIMEOUT;
            $options[CURLOPT_TIMEOUT] = static::REQUEST_TIMEOUT;
			$url = $this->baseUrl.'?verb=GetRecord&identifier='.$identifier.'&metadataPrefix='.$format;

			try {
				$curlResponse = $this->fetchResponse($url, $options);
			} catch (Ccsd_Error $e) {
				$this->logRequestFailure($url, $e);
				if ($e->getCode() === CURLE_OPERATION_TIMEDOUT) {
					throw new Ccsd_Error(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, CURLE_OPERATION_TIMEDOUT);
				}
				throw $e;
			}

            if(empty($curlResponse)) {
                throw new Ccsd_Error("No body returned");
            }

			// Non-XML body (e.g. HTML error page): fail as a Ccsd_Error instead of a TypeError below
			if (!@$dom->loadXML($curlResponse) || $dom->documentElement === null) {
				throw new Ccsd_Error("Invalid OAI-PMH response");
			}
			$xpath = new DOMXPath($dom);
			foreach (Ccsd_Tools::getNamespaces($dom->documentElement) as $id => $ns) {
				$xpath->registerNamespace($id, $ns);
			}


            if ($xpath->query('/xmlns:OAI-PMH/xmlns:error') !== false) {
                $error = self::isOaiError($xpath->query('/xmlns:OAI-PMH/xmlns:error'));
            }


			if ( $error ) {
				throw new Ccsd_Error($error, 1);
			}

			$record = $xpath->query('//xmlns:GetRecord/xmlns:record');
			if ( $record->length ) {
				return ( $this->_outputFormat === 'array') ? Ccsd_Tools::dom2array($record->item(0)) : $dom->saveXML($record->item(0));
			}

            throw new Exception("XML non valide", 0);
        } catch (Ccsd_Error $e){
		    throw $e;

        } catch ( Exception $e ) {
			throw new Exception($e->getMessage(), "0");
		}
    }

    /**
     * HTTP call of the OAI-PMH server (overridable to test response handling without network)
     * @param string $url
     * @param array $options cURL options
     * @return string response body
     * @throws Ccsd_Error on cURL error
     */
    protected function fetchResponse(string $url, array $options): string
    {
        $this->lastTransferInfo = [];
        try {
            return (string)Ccsd_Tools::curlSourceGetContents($url, $options, $transferInfo);
        } finally {
            $this->lastTransferInfo = $transferInfo ?? [];
        }
    }

    /**
     * Logs a failed HTTP call of the OAI-PMH server, with what is needed to tell a network problem
     * (no connection, IPv6 route) from an unresponsive repository
     * @param string $url
     * @param Ccsd_Error $e
     */
    protected function logRequestFailure(string $url, Ccsd_Error $e): void
    {
        if ($this->logger === null) {
            return;
        }

        $info = $this->lastTransferInfo;

        $this->logger->warning('OAI-PMH request failed', [
            'url' => $url,
            'curl_errno' => $e->getCode(),
            'error' => $e->getMessage(),
            'http_code' => $info['http_code'] ?? null,
            'primary_ip' => $info['primary_ip'] ?? null,
            'connect_time' => $info['connect_time'] ?? null,
            'total_time' => $info['total_time'] ?? null,
        ]);
    }

	protected static function isOaiError( DOMNodeList $nodelist )
	{
		if ( $nodelist->length ) {
			return $nodelist->item(0)->getAttribute('code').': '.$nodelist->item(0)->nodeValue;
		}
		return false;
	}
}

