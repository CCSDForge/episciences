<?php

use Psr\Log\LoggerInterface;

class Ccsd_Oai_Client {

	/**
	 * Maximum duration in seconds of an OAI-PMH request: a submission waits for the answer,
	 * so an unresponsive repository must fail fast instead of blocking the author
	 */
	public const REQUEST_TIMEOUT = 30;
	public const CONNECT_TIMEOUT = 10;

	/** cURL errors worth another attempt: no answer, no connection, connection cut */
	public const TRANSIENT_CURL_ERRORS = [
		CURLE_COULDNT_RESOLVE_HOST,
		CURLE_COULDNT_CONNECT,
		CURLE_OPERATION_TIMEDOUT,
		CURLE_SSL_CONNECT_ERROR,
		CURLE_GOT_NOTHING,
		CURLE_RECV_ERROR,
	];
	/** Pause in seconds before another attempt */
	public const RETRY_DELAY = 1;
	/** Upper limit of the pause requested by a Retry-After header: an author is waiting for the answer */
	public const MAX_RETRY_DELAY = 3;

	/** Number of attempts of a request; 1 = no retry */
	protected int $maxAttempts = 1;
	protected int $requestTimeout = self::REQUEST_TIMEOUT;
	protected int $connectTimeout = self::CONNECT_TIMEOUT;

	/** Retry-After header of the last response, in seconds */
	protected ?int $lastRetryAfter = null;

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

	/**
	 * Number of attempts of a request when the repository does not answer or answers with an HTTP 5xx status
	 */
	public function setMaxAttempts(int $maxAttempts): void
	{
		$this->maxAttempts = max(1, $maxAttempts);
	}

	public function setRequestTimeout(int $seconds): void
	{
		$this->requestTimeout = $seconds;
	}

	public function setConnectTimeout(int $seconds): void
	{
		$this->connectTimeout = $seconds;
	}

	/**
	 * Whether the failure may not happen again: the repository did not answer, could not be reached
	 * or answered with an HTTP 5xx status
	 */
	public static function isTransientFailure(Ccsd_Error $e): bool
	{
		if (
			$e instanceof Ccsd_Oai_ServerErrorException
			|| in_array($e->getMessage(), [Ccsd_Error::REPOSITORY_TIMEOUT_CODE, Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE], true)
		) {
			return true;
		}

		return $e->getType() === Ccsd_Error::CURL_ERROR_TYPE
			&& in_array($e->getCode(), self::TRANSIENT_CURL_ERRORS, true);
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
            $options[CURLOPT_CONNECTTIMEOUT] = $this->connectTimeout;
            $options[CURLOPT_TIMEOUT] = $this->requestTimeout;
			$url = $this->baseUrl.'?verb=GetRecord&identifier='.$identifier.'&metadataPrefix='.$format;

			$curlResponse = $this->requestWithRetries($url, $options);

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
     * Requests the OAI-PMH server, again after a transient failure, up to $maxAttempts times
     * @param string $url
     * @param array $options cURL options
     * @return string response body
     * @throws Ccsd_Error the failure of the last attempt; a timeout is reported as REPOSITORY_TIMEOUT_CODE,
     *  another transient cURL error (DNS, connection, TLS, empty answer) as REPOSITORY_UNAVAILABLE_CODE
     */
    protected function requestWithRetries(string $url, array $options): string
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->requestOnce($url, $options);
            } catch (Ccsd_Error $e) {
                $this->logRequestFailure($url, $e, $attempt);

                if ($attempt >= $this->maxAttempts || !self::isTransientFailure($e)) {
                    if ($e->getType() === Ccsd_Error::CURL_ERROR_TYPE && $e->getCode() === CURLE_OPERATION_TIMEDOUT) {
                        throw new Ccsd_Error(Ccsd_Error::REPOSITORY_TIMEOUT_CODE, CURLE_OPERATION_TIMEDOUT);
                    }
                    // Shown to the author as a temporary unavailability instead of the raw cURL error (logged above)
                    if ($e->getType() === Ccsd_Error::CURL_ERROR_TYPE && in_array($e->getCode(), self::TRANSIENT_CURL_ERRORS, true)) {
                        throw new Ccsd_Error(Ccsd_Error::REPOSITORY_UNAVAILABLE_CODE, $e->getCode());
                    }
                    throw $e;
                }

                $this->pause($this->retryDelay($e));
            }
        }
    }

    /**
     * One request; an HTTP 5xx answer is an error, not a body to parse
     * @throws Ccsd_Error
     */
    protected function requestOnce(string $url, array $options): string
    {
        $body = $this->fetchResponse($url, $options);
        $httpStatus = (int)($this->lastTransferInfo['http_code'] ?? 0);

        if ($httpStatus >= 500 && $httpStatus <= 599) {
            throw new Ccsd_Oai_ServerErrorException($httpStatus, $url, $this->lastRetryAfter);
        }

        return $body;
    }

    /**
     * Seconds to wait before the next attempt: the Retry-After of an HTTP 5xx answer, capped
     */
    protected function retryDelay(Ccsd_Error $e): int
    {
        if ($e instanceof Ccsd_Oai_ServerErrorException && $e->getRetryAfter() !== null) {
            return min(max(0, $e->getRetryAfter()), static::MAX_RETRY_DELAY);
        }

        return static::RETRY_DELAY;
    }

    /**
     * Waits before the next attempt (overridable so that tests do not sleep)
     */
    protected function pause(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
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
        $this->lastRetryAfter = null;
        // Keeps the Retry-After header of the final response (headers of redirections are discarded)
        $options[CURLOPT_HEADERFUNCTION] = function ($curl, string $header): int {
            if (preg_match('~^HTTP/~i', $header)) {
                $this->lastRetryAfter = null;
            } elseif (preg_match('~^Retry-After:\s*(\d+)\s*$~i', $header, $matches)) {
                $this->lastRetryAfter = (int)$matches[1];
            }
            return strlen($header);
        };
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
     * @param int $attempt number of the failed attempt
     */
    protected function logRequestFailure(string $url, Ccsd_Error $e, int $attempt = 1): void
    {
        if ($this->logger === null) {
            return;
        }

        $info = $this->lastTransferInfo;

        $this->logger->warning('OAI-PMH request failed', [
            'url' => $url,
            'curl_errno' => $e instanceof Ccsd_Oai_ServerErrorException ? CURLE_OK : $e->getCode(),
            'error' => $e->getMessage(),
            'http_code' => $info['http_code'] ?? null,
            'primary_ip' => $info['primary_ip'] ?? null,
            'connect_time' => $info['connect_time'] ?? null,
            'total_time' => $info['total_time'] ?? null,
            'attempt' => $attempt,
            'max_attempts' => $this->maxAttempts,
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

