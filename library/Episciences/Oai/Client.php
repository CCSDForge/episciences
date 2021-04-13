<?php

/**
 * Class Episciences_Oai_Client
 */
class Episciences_Oai_Client extends Ccsd_Oai_Client
{
    /**
     * @var string
     */
    protected $_userAgent;

    /**
     * Episciences_Oai_Client constructor.
     * @param String $url
     * @param string $format
     * @param string $userAgent
     */
    public function __construct(string $url , string $format = 'array', string $userAgent = DOMAIN)
    {
        $this->_userAgent = $userAgent;
        parent::__construct($url, $format);
    }

    /**
     * @return mixed
     */
    public function getUserAgent() : string
    {
        return $this->_userAgent;
    }

    /**
     * @param mixed $userAgent
     */
    public function setUserAgent(string $userAgent)
    {
        $this->_userAgent = $userAgent;
    }

    /**
     * @param $identifier
     * @return array|string
     * @throws Exception
     */
    public function getArXivRawRecord($identifier){
        $this->setOutputFormat('array');
        return $this->getRecord($identifier, 'arXivRaw');
    }
}