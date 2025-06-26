<?php

class Episciences_Website_Navigation extends Ccsd_Website_Navigation
{
    public const PAGE_INDEX = 'index';
    public const PAGE_CUSTOM = 'custom';
    public const PAGE_LINK = 'link';
    public const PAGE_FILE = 'file';
    public const PAGE_NEWS = 'news';
    public const PAGE_RSS = 'rss';
    public const PAGE_BROWSE_BY_AUTHOR = 'browseByAuthor';
    public const PAGE_BROWSE_BY_DATE = 'browseByDate';
    public const PAGE_BROWSE_BY_SECTION = 'browseBySection';
    public const PAGE_BROWSE_BY_VOLUME = 'browseByVolume';

    public const PAGE_BROWSE_LATEST = 'browseLatest';
    public const PAGE_BROWSE_CURRENT_ISSUES = 'browseCurrentIssues';
    public const PAGE_BROWSE_SPECIAL_ISSUES = 'browseSpecialIssues';
    public const PAGE_BROWSE_REGULAR_ISSUES = 'browseRegularIssues';
    public const PAGE_ACCEPTED_PAPERS_LIST = 'acceptedPapersList';
    public const PAGE_SEARCH = 'search';
    public const PAGE_EDITORIAL_STAFF = 'editorialStaff';
    public const PAGE_CREDITS = 'credits';
    public const PAGE_PUBLISHING_POLICIES = 'publishingPolicies';
    public const PAGE_ETHICAL_CHARTER = 'ethicalCharter';
    public const PAGE_EDITORIAL_WORKFLOW = 'EditorialWorkflow';
    public const PAGE_PREPARE_SUBMISSION= 'PrepareSubmission';
    public  const PAGE_ABOUT = 'about';
    public const PAGE_JOURNAL_INDEXING= 'journalIndexing';
    public const PAGE_EDITORIAL_BOARD = 'editorialBoard';
    public const PAGE_TECHNICAL_BOARD = 'technicalBoard';
    public const PAGE_SCIENTIFIC_ADVISORY_BOARD = 'scientificAdvisoryBoard';
    public const PAGE_FORMER_MEMBERS = 'formerMembers';

    protected $_table = 'WEBSITE_NAVIGATION';
    protected $_primary = 'NAVIGATIONID';
    protected $_sid = 0;

    public static array $groupedPages = [
        'Home (backend)' => [self::PAGE_INDEX],
        'About' => [
            self::PAGE_ABOUT,
            self::PAGE_JOURNAL_INDEXING

        ],

        'Boards' => [
            self::PAGE_EDITORIAL_BOARD,
            self::PAGE_TECHNICAL_BOARD,
            self::PAGE_SCIENTIFIC_ADVISORY_BOARD,
            self::PAGE_FORMER_MEMBERS
        ],

        'For authors' => [
            self::PAGE_EDITORIAL_WORKFLOW,
            self::PAGE_ETHICAL_CHARTER,
            self::PAGE_PREPARE_SUBMISSION,
        ],

        'Other' => [
            self::PAGE_CREDITS,
            self::PAGE_CUSTOM,
            self::PAGE_NEWS,
            self::PAGE_FILE,
            self::PAGE_LINK,
        ],
    ];

    /** You can now find them on the new sites */

    public static array $ignoredPageTypes = [
        self::PAGE_RSS,
        self::PAGE_BROWSE_BY_AUTHOR,
        self::PAGE_BROWSE_BY_DATE,
        self::PAGE_BROWSE_BY_SECTION,
        self::PAGE_BROWSE_BY_VOLUME,
        self::PAGE_BROWSE_LATEST,
        self::PAGE_BROWSE_CURRENT_ISSUES,
        self::PAGE_BROWSE_SPECIAL_ISSUES,
        self::PAGE_BROWSE_REGULAR_ISSUES,
        self::PAGE_ACCEPTED_PAPERS_LIST,
        self::PAGE_PUBLISHING_POLICIES,
        self::PAGE_SEARCH,
        self::PAGE_EDITORIAL_STAFF,
    ];

    public function setOptions($options = []): void
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
            $options = array_merge(['languages' => $this->_languages], $row);
            foreach ($this->_languages as $lang) {
                $options['labels'][$lang] = $reader->get($row['LABEL'], $lang);
            }
            if ($row['PARAMS'] !== '') {
                $options = array_merge($options, unserialize($row['PARAMS'], ['allowed_classes' => false]));
            }

            $currentPageKey = lcfirst(str_replace('Episciences_Website_Navigation_Page_', '', $row['TYPE_PAGE']));

            if(in_array($currentPageKey, self::$ignoredPageTypes, true)){
                continue;
            }

            //Création de la page
            $this->_pages[$row['PAGEID']] = new $row['TYPE_PAGE']($options);
            /** @var Episciences_Website_Navigation_Page $currentPage */
            $currentPage = $this->_pages[$row['PAGEID']];
            $currentPage->load();
            //Définition de l'ordre des pages
            if ($row['PAGEID'] > $this->_idx) {
                $this->_idx = $row['PAGEID'];
            }
            if ($row['PARENT_PAGEID'] == 0) {
                $this->_order[$row['PAGEID']] = [];
            } else if (isset($this->_order[$row['PARENT_PAGEID']])) {
                $this->_order[$row['PARENT_PAGEID']][$row['PAGEID']] = [];
            } else {
                foreach ($this->_order as $i => $elem) {
                    if (is_array($elem) && isset($this->_order[$i][$row['PARENT_PAGEID']])) {
                        $this->_order[$i][$row['PARENT_PAGEID']][$row['PAGEID']] = [];
                    }
                }
            }
        }

        if (count($this->_pages) === 0) {
            $this->_pages[0] = new Episciences_Website_Navigation_Page_Index();
            $this->_order[0] = [];
        }
        $this->_idx++;
    }


    public function save()
    {
        // Suppression de l'ancien menu
        $this->_db->delete($this->_table, 'SID = ' . $this->_sid);

        $lang = [];
        $pageIdCounter = 1;

        // Enregistrement des nouvelles données
        foreach ($this->_order as $pageId => $subPageIds) {
            $this->processPage($pageId, null, $lang, $pageIdCounter);

            if (is_array($subPageIds) && count($subPageIds) > 0) {
                foreach ($subPageIds as $subPageId => $subSubPageIds) {
                    $this->processPage($subPageId, $pageId, $lang, $pageIdCounter);

                    if (is_array($subSubPageIds) && count($subSubPageIds) > 0) {
                        foreach (array_keys($subSubPageIds) as $subSubPageId) {
                            $this->processPage($subSubPageId, $subPageId, $lang, $pageIdCounter);
                        }
                    }
                }
            }
        }

        // Enregistrement des traductions dans des fichiers
        $writer = new Ccsd_Lang_Writer($lang);
        $writer->write(REVIEW_LANG_PATH, 'menu');
    }

    private function processPage($pageId, $parentId, &$lang, &$pageIdCounter)
    {
        if (isset($this->_pages[$pageId])) {
            $this->_pages[$pageId]->setPageId($pageIdCounter);
            if ($parentId !== null) {
                $this->_pages[$pageId]->setPageParentId($this->_pages[$parentId]->getPageId());
            }
            $this->savePage($this->_pages[$pageId]);

            $key = $this->_pages[$pageId]->getLabelKey();
            $lang[$key] = $this->_pages[$pageId]->getLabels();
            $pageIdCounter++;
        }
    }

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
     */
    public function toArray(): array
    {
        $result = [];
        $id = 0;

        foreach ($this->_order as $pageId => $subPageIds) {
            if (isset($this->_pages[$pageId])) {
                $result[$id] = $this->pageToArray($pageId, $subPageIds);
            }
            $id++;
        }

        return $result;
    }

    private function pageToArray(int $pageId, $subPageIds): array
    {
        $pageArray = $this->_pages[$pageId]->toArray();

        if (is_array($subPageIds) && count($subPageIds) > 0) {
            $pageArray['pages'] = $this->subPagesToArray($subPageIds);
        }

        return $pageArray;
    }

    private function subPagesToArray(array $subPageIds): array
    {
        $subPagesArray = [];
        $subId = 0;

        foreach ($subPageIds as $subPageId => $subSubPageIds) {
            if (isset($this->_pages[$subPageId])) {
                $subPagesArray[$subId] = $this->pageToArray($subPageId, $subSubPageIds);
            }
            $subId++;
        }

        return $subPagesArray;
    }

    public function getPageTypes($reload = false): array
    {
        $typePage = parent::getPageTypes($reload);

        foreach ($typePage as $pageKey => $page) {

            if(in_array(lcfirst($pageKey), self::$ignoredPageTypes, true)) {
                unset($typePage[$pageKey]);
            }
        }
        return $typePage;

    }


}
