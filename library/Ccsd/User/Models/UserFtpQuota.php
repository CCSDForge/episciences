<?php

/**
 * Class Ccsd_User_Models_UserFtpQuota
 */
class Ccsd_User_Models_UserFtpQuota
{
    /**
     * Quota FTP
     * @const int
     */
    const CCSD_FTP_QUOTA = 5368709120;

    /**
     * Quota nombre de fichiers FTP
     * @const int
     */
    const CCSD_FTP_QUOTA_FILES = 5000;

    /**
     *
     * @var int
     */
    protected $_Id;

    /**
     *
     * @var string
     */
    protected $_username;

    /**
     *
     * @var string
     */
    protected $_quota_type = 'user';

    /**
     *
     * @var boolean
     */
    protected $_par_session = 'false';

    /**
     *
     * @var int
     */
    protected $_limit_type = 'soft';

    /**
     *
     * @var int
     */
    protected $_bytes_up_limit;

    /**
     *
     * @var int
     */
    protected $_bytes_down_limit;

    /**
     *
     * @var int
     */
    protected $_bytes_transfer_limit;

    /**
     *
     * @var int
     */
    protected $_files_up_limit;

    /**
     *
     * @var int
     */
    protected $_files_down_limit;

    /**
     *
     * @var int
     */
    protected $_files_transfer_limit;

    /**
     * ------------------------------------------------------------
     * Limites quotas
     */

    /**
     *
     * @var int
     */
    protected $_bytes_up_total;

    /**
     *
     * @var int
     */
    protected $_bytes_down_total;

    /**
     *
     * @var int
     */
    protected $_bytes_transfer_total;

    /**
     *
     * @var int
     */
    protected $_files_up_total;

    /**
     *
     * @var int
     */
    protected $_files_down_total;

    /**
     *
     * @var int
     */
    protected $_files_transfer_total;

    /**
     * ------------------------------------------------------------
     * Limites quotas //
     */
    public function __construct(array $options = null)
    {
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    public function setOptions(array $options)
    {
        $methods = get_class_methods($this);
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (in_array($method, $methods)) {
                $this->$method($value);
            }
        }
        return $this;
    }

    public function save()
    {
        $UserFtpQuotaMapper = new Ccsd_User_Models_UserFtpQuotaMapper();
        $this->setQuota_type('user')
            ->setPar_session('false')
            ->setLimit_type('soft')
            ->setBytes_up_limit()
            ->setBytes_down_limit()
            ->setBytes_transfer_limit()
            ->setFiles_up_limit()
            ->setFiles_down_limit()
            ->setFiles_transfer_limit();

        return $UserFtpQuotaMapper->save($this);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_Id;
    }

    /**
     * @param int $_Id
     * @return $this
     */
    public function setId($_Id)
    {
        $this->_Id = $_Id;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->_username;
    }

    /**
     * @param string $_username
     * @return $this
     */
    public function setUsername($_username)
    {
        $this->_username = $_username;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuota_type()
    {
        return $this->_quota_type;
    }

    /**
     * @param string $_quota_type
     * @return $this
     */
    public function setQuota_type($_quota_type)
    {
        $this->_quota_type = $_quota_type;
        return $this;
    }

    /**
     * @return bool
     */
    public function getPar_session()
    {
        return $this->_par_session;
    }

    /**
     * @param bool $_par_session
     * @return $this
     */
    public function setPar_session($_par_session)
    {
        $this->_par_session = $_par_session;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit_type()
    {
        return $this->_limit_type;
    }

    /**
     * @param int $_limit_type
     * @return $this
     */
    public function setLimit_type($_limit_type)
    {
        $this->_limit_type = $_limit_type;
        return $this;
    }

    /**
     * @return int
     */
    public function getBytes_up_limit()
    {
        return $this->_bytes_up_limit;
    }

    /**
     * @param int|null $_bytes_up_limit
     * @return $this
     * @throws Exception
     */
    public function setBytes_up_limit($_bytes_up_limit = null)
    {
        if ($_bytes_up_limit == null) {

            $_bytes_up_limit = self::CCSD_FTP_QUOTA;

        }

        $_bytes_up_limit = intval(filter_var($_bytes_up_limit, FILTER_SANITIZE_NUMBER_INT));

        $this->_bytes_up_limit = $_bytes_up_limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getBytes_down_limit()
    {
        return $this->_bytes_down_limit;
    }

    /**
     * @param int|null $_bytes_down_limit
     * @return $this
     * @throws Exception
     */
    public function setBytes_down_limit($_bytes_down_limit = null)
    {
        if ($_bytes_down_limit == null) {
            $_bytes_down_limit = self::CCSD_FTP_QUOTA;
        }

        $_bytes_down_limit = intval(filter_var($_bytes_down_limit, FILTER_SANITIZE_NUMBER_INT));

        $this->_bytes_down_limit = $_bytes_down_limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getBytes_transfer_limit()
    {
        return $this->_bytes_transfer_limit;
    }

    /**
     * @param int|null $_bytes_transfer_limit
     * @return $this
     * @throws Exception
     */
    public function setBytes_transfer_limit($_bytes_transfer_limit = null)
    {
        if ($_bytes_transfer_limit == null) {
            $_bytes_transfer_limit = self::CCSD_FTP_QUOTA;
        }

        $_bytes_transfer_limit = intval(filter_var($_bytes_transfer_limit, FILTER_SANITIZE_NUMBER_INT));

        $this->_bytes_transfer_limit = $_bytes_transfer_limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getFiles_up_limit()
    {
        return $this->_files_up_limit;
    }

    /**
     * @param int|null $_files_up_limit
     * @return $this
     * @throws Exception
     */
    public function setFiles_up_limit($_files_up_limit = null)
    {
        if ($_files_up_limit == null) {
            $_files_up_limit = self::CCSD_FTP_QUOTA_FILES;
        }

        $_files_up_limit = intval(filter_var($_files_up_limit, FILTER_SANITIZE_NUMBER_INT));

        $this->_files_up_limit = $_files_up_limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getFiles_down_limit()
    {
        return $this->_files_down_limit;
    }

    /**
     * @param int|null $_files_down_limit
     * @return $this
     * @throws Exception
     */
    public function setFiles_down_limit($_files_down_limit = null)
    {
        if ($_files_down_limit == null) {
            $_files_down_limit = self::CCSD_FTP_QUOTA_FILES;
        }

        $_files_down_limit = intval(filter_var($_files_down_limit, FILTER_SANITIZE_NUMBER_INT));

        $this->_files_down_limit = $_files_down_limit;
        return $this;
    }

    /**
     * @return int
     */
    public function getFiles_transfer_limit()
    {
        return $this->_files_transfer_limit;
    }

    /**
     * @param int|null $_files_transfer_limit
     * @return $this
     * @throws Exception
     */
    public function setFiles_transfer_limit($_files_transfer_limit = null)
    {
        if ($_files_transfer_limit == null) {
            $_files_transfer_limit = self::CCSD_FTP_QUOTA_FILES;
        }

        $_files_transfer_limit = intval(filter_var($_files_transfer_limit, FILTER_SANITIZE_NUMBER_INT));

        $this->_files_transfer_limit = $_files_transfer_limit;
        return $this;
    }

    /**
     * ------------------------------------------------------------
     * Limites quotas
     */

    /**
     *
     * @return int $_bytes_up_total
     */
    public function getBytes_up_total()
    {
        return $this->_bytes_up_total;
    }

    /**
     *
     * @param number $_bytes_up_total
     * @return $this
     */
    public function setBytes_up_total($_bytes_up_total)
    {
        $this->_bytes_up_total = $_bytes_up_total;
        return $this;
    }

    /**
     *
     * @return int $_bytes_down_total
     */
    public function getBytes_down_total()
    {
        return $this->_bytes_down_total;
    }

    /**
     *
     * @param number $_bytes_down_total
     * @return $this
     */
    public function setBytes_down_total($_bytes_down_total)
    {
        $this->_bytes_down_total = $_bytes_down_total;
        return $this;
    }

    /**
     *
     * @return int $_bytes_transfer_total
     */
    public function getBytes_transfer_total()
    {
        return $this->_bytes_transfer_total;
    }

    /**
     *
     * @param number $_bytes_transfer_total
     * @return $this
     */
    public function setBytes_transfer_total($_bytes_transfer_total)
    {
        $this->_bytes_transfer_total = $_bytes_transfer_total;
        return $this;
    }

    /**
     *
     * @return int $_files_up_total
     */
    public function getFiles_up_total()
    {
        return $this->_files_up_total;
    }

    /**
     *
     * @param number $_files_up_total
     * @return $this
     */
    public function setFiles_up_total($_files_up_total)
    {
        $this->_files_up_total = $_files_up_total;
        return $this;
    }

    /**
     *
     * @return int $_files_down_total
     */
    public function getFiles_down_total()
    {
        return $this->_files_down_total;
    }

    /**
     *
     * @param number $_files_down_total
     * @return $this
     */
    public function setFiles_down_total($_files_down_total)
    {
        $this->_files_down_total = $_files_down_total;
        return $this;
    }

    /**
     *
     * @return int $_files_transfer_total
     */
    public function getFiles_transfer_total()
    {
        return $this->_files_transfer_total;
    }

    /**
     *
     * @param int $_files_transfer_total
     * @return $this
     */
    public function setFiles_transfer_total($_files_transfer_total)
    {
        $this->_files_transfer_total = $_files_transfer_total;
        return $this;
    }
    /**
     * ------------------------------------------------------------
     * Limites quotas //
     */
}


















