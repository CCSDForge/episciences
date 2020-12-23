<?php

class Episciences_News extends Ccsd_News
{
	
	public function __construct()
	{
		$this->_db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$this->_sid = RVID;
		$this->_languages = Zend_Registry::get('languages');
		$this->_dirLangFiles = REVIEW_PATH . 'languages/';
		$this->_sidField = 'RVID';
	}
    /**
     * Récupération de la liste des actualités d'un site
     * @param boolean $online retourne uniquement les actus en ligne
     * @param int $newsid retourne uniquement une actu
     * @param int $limit retourne un certain nombre de news
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function getListNews($online = true, $newsid = 0, $limit = 0)
    {
        $sql = $this->_db->select()
            ->from($this->_table, array(T_USERS . '.SCREEN_NAME', $this->_table . '.*', new Zend_Db_Expr('CONCAT_WS("", "title_", ' .$this->_primary. ') as TITLE'), new Zend_Db_Expr('CONCAT_WS("", "content_", ' .$this->_primary. ') as CONTENT')))
            ->joinInner([T_USERS], T_USERS . '.UID =  ' . $this->_table . '.UID', [])
            ->where($this->_sidField . ' = ?', $this->_sid)
            ->order('DATE_POST DESC');
        if ($online) {
            $sql->where('ONLINE = 1');
        }
        if ($limit > 0) {
            $sql->limit($limit);
        }

        if ($newsid != 0) {
            $sql->where($this->_primary . ' = ?', $newsid);
            return $this->_db->fetchRow($sql);
        } else {
            return $this->_db->fetchAll($sql);
        }
    }



}