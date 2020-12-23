<?php

/**
 * Class Ccsd_Export
 */
class Ccsd_Export
{
    const DEFAULT_EXPORT_FILENAME = 'export';

    /** @var string    */
    protected $_filename;

    /** @var string    */
    protected $_data = "";

    /**
     * @param string $data
     * @param string $filename
     */
    public function __construct ($data, $filename)
    {
        $this->setData($data)->setFilename($filename);
    }

    public function exportXLS ()
    {
        Ccsd_Export_Excel::export($this->_data, $this->_filename);
    }

    public function exportCSV ()
    {
        Ccsd_Export_Csv::export($this->_data, $this->_filename);
    }

    /**
     * @return string $_filename
     */
    public function getFilename ()
    {
        return $this->_filename;
    }

    /**
     * @param string $_filename
     * @return Ccsd_Export
     */
    public function setFilename ($_filename = null)
    {
        if ($_filename == null) {
            $this->_filename = self::DEFAULT_EXPORT_FILENAME;
        } else {
            $this->_filename = $_filename;
        }
        return $this;
    }

    /**
     * @return string $_data
     */
    public function getData ()
    {
        return $this->_data;
    }

    /**
     * @param string $_data
     * @return Ccsd_Export
     */
    public function setData ($_data)
    {
        $this->_data = $_data;
        return $this;
    }
}