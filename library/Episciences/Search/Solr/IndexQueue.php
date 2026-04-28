<?php

class Episciences_Search_Solr_IndexQueue extends Ccsd_Search_Solr_Models_IndexQueueMapper
{

    /**
     *
     * @var object
     */
    private static $_instance;

    /**
     *
     * @var array
     */
    private $_applicationDefaults = array(
            'APPLICATION' => 'episciences',
            'ORIGIN' => 'update',
            'CORE' => 'episciences',
            'PRIORITY' => 10
    );

    private function __construct ()
    {}

    private function __clone ()
    {}

    public static function getInstance ()
    {
        if (! (self::$_instance instanceof self))
            self::$_instance = new self();

        return self::$_instance;
    }

    /**
     *
     * @param array $arrayOfDataToIndex
     */
    public function addToIndexQueue (array $arrayOfDataToIndex)
    {
        foreach ($arrayOfDataToIndex as $dataToIndex) {
            $arrayOfObjectsToIndex[] = new Ccsd_Search_Solr_Models_IndexQueue(array_merge($this->getApplicationDefaults(), $dataToIndex));
        }
        return parent::saveArray($arrayOfObjectsToIndex);
    }

    /**
     *
     * @return the $_applicationDefaults
     */
    public function getApplicationDefaults ()
    {
        return $this->_applicationDefaults;
    }

    /**
     *
     * @param
     *            array number $_applicationDefaults
     */
    public function setApplicationDefaults ($_applicationDefaults)
    {
        $this->_applicationDefaults = $_applicationDefaults;
        return $this;
    }
}