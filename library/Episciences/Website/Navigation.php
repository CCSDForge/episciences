<?php

class Episciences_Website_Navigation extends Ccsd_Website_Navigation
{
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
    const PAGE_ACCEPTED_PAPERS_LIST = 'acceptedPapersList';
    const PAGE_SEARCH = 'search';
    const PAGE_EDITORIAL_STAFF = 'editorialStaff';
    const PAGE_CREDITS = 'credits';
    const PAGE_PUBLISHING_POLICIES = 'publishingPolicies';
    const PAGE_ETHICAL_CHARTER = 'ethicalCharter';
    const PAGE_EDITORIAL_WORKFLOW = 'EditorialWorkflow';
    const PAGE_PREPARE_SUBMISSION= 'PrepareSubmission';
    const PAGE_FOR_REVIEWERS= 'ForReviewers';
    const PAGE_FOR_CONFERENCE_ORGANISERS= 'ForConferenceOrganisers';
    const PAGE_ABOUT = 'about';
    const PAGE_JOURNAL_INDEXING= 'journalIndexing';
    const PAGE_JOURNAL_ACKNOWLEDGEMENTS = 'journalAcknowledgements';
    const PAGE_EDITORIAL_BOARD = 'editorialBoard';
    const PAGE_TECHNICAL_BOARD = 'technicalBoard';
    const PAGE_SCIENTIFIC_ADVISORY_BOARD = 'scientificAdvisoryBoard';
    const PAGE_FORMER_MEMBERS = 'formerMembers';
    const PAGE_INTRODUCTION_BOARD = 'introductionBoard';
    const PAGE_REVIEWERS_BOARD = 'reviewersBoard';
    const PAGE_OPERATING_CHARTER_BOARD = 'operatingCharterBoard';

    protected $_table = 'WEBSITE_NAVIGATION';
    protected $_primary = 'NAVIGATIONID';
    protected $_sid = 0;

    public function setOptions($options = []): void
    {
        foreach ($options as $option => $value) {
            $option = strtolower($option);
            if ($option == 'sid') {
                $this->_sid = $value;
            } elseif ($option == 'languages') {
                $this->_languages = is_array($value) ? $value : [$value];
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
            if ($row['PARAMS'] != '') {
                $options = array_merge($options, unserialize($row['PARAMS'], ['allowed_classes' => false]));
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

            // Synchronize title with T_PAGES for predefined pages
            $this->syncPageTitleToDatabase($this->_pages[$pageId]);

            $pageIdCounter++;
        }
    }

    /**
     * Synchronize page title with T_PAGES table for predefined pages
     *
     * @param Episciences_Website_Navigation_Page $page
     * @return void
     */
    private function syncPageTitleToDatabase(Episciences_Website_Navigation_Page $page): void
    {
        if (!($page instanceof Episciences_Website_Navigation_Page_Predefined)) {
            return;
        }

        // Get the review code from database using SID (RVID) → REVIEW table → code
        // This ensures we use the same code as in T_PAGES table
        $reviewCode = null;
        try {
            $review = Episciences_ReviewsManager::find($this->_sid);
            if ($review) {
                $reviewCode = $review->getCode();

                // Development environment only: REVIEW.code is 'dev' but T_PAGES uses 'epijinfo'
                // In preprod/production, the codes are consistent so no mapping is needed
                if ($reviewCode === 'dev') {
                    $reviewCode = 'epijinfo';
                }
            }
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        if (empty($reviewCode)) {
            return;
        }

        $pageCode = $page->getPermalien();
        if (empty($pageCode)) {
            return;
        }

        $labels = $page->getLabels();
        if (empty($labels)) {
            return;
        }

        $existingPage = Episciences_Page_Manager::findByCodeAndPageCode($reviewCode, $pageCode);

        if ($existingPage->getId() > 0) {
            // Update existing page
            $existingPage->setCode($reviewCode);
            $existingPage->setPageCode($pageCode);
            $existingPage->setTitle($labels);
            $existingPage->setUid(Episciences_Auth::getUid());
            Episciences_Page_Manager::update($existingPage);
        } else {
            // Create new entry in T_PAGES
            $newPage = new Episciences_Page();
            $newPage->setCode($reviewCode);
            $newPage->setPageCode($pageCode);
            $newPage->setTitle($labels);
            $newPage->setUid(Episciences_Auth::getUid());
            $newPage->setContent([]);
            $newPage->setVisibility(['public']);
            Episciences_Page_Manager::add($newPage);
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


}
