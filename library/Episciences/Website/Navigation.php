<?php

/**
 * Navigation spécifique à l'application Episciences
 * @author yannick
 *
 */
class Episciences_Website_Navigation extends Ccsd_Website_Navigation
{
    /**
     * Liste des pages
     */
    const PAGE_INDEX = 'index';
    const PAGE_CUSTOM = 'custom';
    const PAGE_LINK = 'link';
    const PAGE_FILE = 'file';
    const PAGE_NEWS = 'news';
    const PAGE_RSS = 'rss';

    const PAGE_BROWSE_BY_AUTHOR = 'browseByAuthor';
    const PAGE_BROWSE_BY_DATE = 'browseByDate';
    const PAGE_BROWSE_BY_SECTION = 'browseBySection';
    const PAGE_BROWSE_BY_VOLUME = 'browseByVolume';

    const PAGE_BROWSE_LATEST = 'browseLatest';
    const PAGE_BROWSE_CURRENT_ISSUES = 'browseCurrentIssues';
    const PAGE_BROWSE_SPECIAL_ISSUES = 'browseSpecialIssues';
    const PAGE_BROWSE_REGULAR_ISSUES = 'browseRegularIssues';

    const PAGE_SEARCH = 'search';
    const PAGE_EDITORIAL_STAFF = 'editorialStaff';


    /**
     * Table de stockage de la navigztion d'un site
     * @var string
     */
    protected $_table = 'WEBSITE_NAVIGATION';
    /**
     * Clé primaire
     * @var string
     */
    protected $_primary = 'NAVIGATIONID';
    /**
     * Identifiant de la revue dans Episciences
     * @var int
     */
    protected $_sid = 0;

    /**
     * Initialisation des options de la navigation
     * @see Ccsd_Website_Navigation::setOptions($options)
     */
    public function setOptions($options = [])
    {
        foreach ($options as $option => $value) {
            $option = strtolower($option);
            switch ($option) {
                case 'sid'   :
                    $this->_sid = $value;
                    break;
                case 'languages':
                    $this->_languages = is_array($value) ? $value : [$value];
                    break;
            }

        }
    }

    /**
     * Chargement de la navigation du site
     * @see Ccsd_Website_Navigation::load()
     */
    public function load()
    {
        $sql = $this->_db->select()
            ->from($this->_table)
            ->where('SID = ?', $this->_sid)
            ->order('NAVIGATIONID ASC');

        $this->_pages = [];
        $reader = new Ccsd_Lang_Reader('menu', REVIEW_LANG_PATH, $this->_languages, true);
        foreach ($this->_db->fetchAll($sql) as $row) {
            //Récupération des infos sur la page en base
            $options = ['languages' => $this->_languages];
            foreach ($this->_languages as $lang) {
                $options['labels'][$lang] = $reader->get($row['LABEL'], $lang);
            }
            if ($row['PARAMS'] != '') {
                $options = array_merge($options, unserialize($row['PARAMS'], ['allowed_classes' => false]));
            }
            //Création de la page
            $this->_pages[$row['PAGEID']] = new $row['TYPE_PAGE']($options);
            $this->_pages[$row['PAGEID']]->load();
            //Définition de l'ordre des pages
            if ($row['PAGEID'] > $this->_idx) {
                $this->_idx = $row['PAGEID'];
            }
            if ($row['PARENT_PAGEID'] == 0) {
                $this->_order[$row['PAGEID']] = [];
            } else {
                if (isset($this->_order[$row['PARENT_PAGEID']])) {
                    $this->_order[$row['PARENT_PAGEID']][$row['PAGEID']] = [];
                } else {
                    foreach ($this->_order as $i => $elem) {
                        if (is_array($elem) && isset($this->_order[$i][$row['PARENT_PAGEID']])) {
                            $this->_order[$i][$row['PARENT_PAGEID']][$row['PAGEID']] = [];
                        }
                    }
                }
            }
        }
        if (count($this->_pages) == 0) {
            $this->_pages[0] = new Episciences_Website_Navigation_Page_Index();
            $this->_order[0] = [];
        }
        $this->_idx++;
    }

    /**
     * Enregistrement de la nouvelle navigation
     * @see Ccsd_Website_Navigation::save()
     */
    public function save()
    {
        //Suppression de l'ancien menu
        $this->_db->delete($this->_table, 'SID = ' . $this->_sid);

        $lang = [];
        //Enregistrement des nouvelles données
        $i = 1;

        foreach ($this->_order as $pageid => $spageids) {

            if (isset($this->_pages[$pageid])) {
                //Initialisation de la pageid
                $this->_pages[$pageid]->setPageId($i);
                $this->savePage($this->_pages[$pageid]);
                $key = $this->_pages[$pageid]->getLabelKey();
                $lang[$key] = $this->_pages[$pageid]->getLabels();
                $i++;

                if (is_array($spageids) && count($spageids) > 0) {
                    foreach ($spageids as $spageid => $sspageids) {
                        if (isset($this->_pages[$spageid])) {
                            $this->_pages[$spageid]->setPageId($i);
                            $this->_pages[$spageid]->setPageParentId($this->_pages[$pageid]->getPageId());
                            $this->savePage($this->_pages[$spageid]);
                            $key = $this->_pages[$spageid]->getLabelKey();
                            $lang[$key] = $this->_pages[$spageid]->getLabels();
                            $i++;

                            if (is_array($sspageids) && count($sspageids) > 0) {
                                foreach (array_keys($sspageids) as $sspageid) {
                                    if (isset($this->_pages[$sspageid])) {
                                        $this->_pages[$sspageid]->setPageId($i);
                                        $this->_pages[$sspageid]->setPageParentId($this->_pages[$spageid]->getPageId());
                                        $this->savePage($this->_pages[$sspageid]);
                                        $key = $this->_pages[$sspageid]->getLabelKey();
                                        $lang[$key] = $this->_pages[$sspageid]->getLabels();
                                        $i++;
                                    }
                                }
                            }
                        }

                    }
                }
            }
        }

        //Enregistrement des traductions dans des fichiers
        $writer = new Ccsd_Lang_Writer($lang);
        $writer->write(REVIEW_LANG_PATH, 'menu');
    }

    /**
     * Enregistrement de la page en base
     * @param Ccsd_Website_Navigation_Page $page page à enregistrer
     */
    public function savePage($page)
    {
        //Cas particulier des pages personnalisable
        if ($page->isCustom()) {
            $page->setPermalien($this->getUniqPermalien($page));
        } else if ($page->isFile()) {
            $page->saveFile();
        }

        //Enregistrement en base
        $bind = [
            'SID' => $this->_sid,
            'PAGEID' => $page->getPageId(),
            'TYPE_PAGE' => $page->getPageClass(),
            'CONTROLLER' => $page->getController(),
            'ACTION' => $page->getAction(),
            'LABEL' => $page->getLabelKey(),
            'PARENT_PAGEID' => $page->getPageParentId(),
            'PARAMS' => $page->getSuppParams()
        ];


        $this->_db->insert($this->_table, $bind);
    }

    /**
     * Vérification de l'unicité du lien permanent
     * @param $page
     * @return string|string[]|null
     */
    protected function getUniqPermalien($page)
    {
        $permalien = $page->getPermalien();
        //Liste des permaliens
        $permaliens = [];
        foreach ($this->_pages as $p) {
            if ($p->isCustom() && $p != $page) {
                $permaliens[] = $p->getPermalien();
            }
        }

        while (in_array($permalien, $permaliens)) {
            $newPermalien = preg_replace_callback('#([-_]?)(\d*)$#', function ($matches) {
                if ($matches[0] != '') {
                    return ($matches[1] . ($matches[2] + 1));
                }
            }, $permalien);
            $permalien = ($permalien == $newPermalien) ? $permalien . '1' : $newPermalien;
        }
        return $permalien;
    }

    /**
     * Création de la navigation pour le site
     * @param string $filename nom du fichier de navigation
     */
    public function createNavigation($filename)
    {
        $dir = substr($filename, 0, strrpos($filename, '/'));
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }
        file_put_contents($filename, Zend_Json::encode($this->toArray()));
    }

    /**
     * Transformation de la navigation en tableau PHP (compatible avec la navigation Zend_Navigation)
     * @return array
     */
    public function toArray()
    {
        $res = [];
        $id = 0;
        foreach ($this->_order as $pageid => $spageids) {
            if (isset($this->_pages[$pageid])) {
                $res[$id] = $this->_pages[$pageid]->toArray();
                if (is_array($spageids) && count($spageids) > 0) {
                    $id2 = 0;
                    foreach ($spageids as $spageid => $sspageids) {
                        if (isset($this->_pages[$spageid])) {
                            $res[$id]['pages'][$id2] = $this->_pages[$spageid]->toArray();

                            if (is_array($sspageids) && count($sspageids) > 0) {
                                foreach (array_keys($sspageids) as $sspageid) {
                                    if (isset($this->_pages[$sspageid])) {
                                        $res[$id]['pages'][$id2]['pages'][] = $this->_pages[$sspageid]->toArray();
                                    }

                                }
                            }
                        }
                        $id2++;
                    }
                }
            }
            $id++;
        }

        return $res;
    }

}
