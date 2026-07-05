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
    const PAGE_FOR_EDITORS = 'ForEditors';
    const PAGE_PROPOSING_SPECIAL_ISSUES= 'ProposingSpecialIssues';
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
        $this->_order = [];
        $this->_idx = 0;

        // Ensure we see the latest changes on disk
        clearstatcache();

        $reader = new Ccsd_Lang_Reader('menu', REVIEW_LANG_PATH, $this->_languages, true);
        $rows = $this->_db->fetchAll($sql);

        if (count($rows) === 0) {
            $this->_pages[0] = new Episciences_Website_Navigation_Page_Index();
            $this->_order[0] = [];
            $this->_idx = 1;
            return;
        }

        // Pre-load all page visibilities in one query to avoid N+1 queries
        $preloadedPages = [];
        if (defined('RVCODE') && RVCODE !== '') {
            $preloadedPages = Episciences_Page_Manager::findAllByCode(RVCODE);
        }

        // Pass 1: Create all page objects
        $parentMap = [];
        foreach ($rows as $row) {
            $pageId = (int)$row['PAGEID'];
            $parentId = (int)$row['PARENT_PAGEID'];

            // Collect info for the page
            $options = array_merge(['languages' => $this->_languages], $row);
            foreach ($this->_languages as $lang) {
                $options['labels'][$lang] = $reader->get($row['LABEL'], $lang);
            }
            if ($row['PARAMS'] != '') {
                $options = array_merge($options, unserialize($row['PARAMS'], ['allowed_classes' => false]));
            }

            // Pass preloaded page data to avoid N+1 queries in Custom::load()
            if (isset($options['permalien']) && isset($preloadedPages[$options['permalien']])) {
                $options['preloadedPage'] = $preloadedPages[$options['permalien']];
            }

            // Create page instance
            $this->_pages[$pageId] = new $row['TYPE_PAGE']($options);
            /** @var Episciences_Website_Navigation_Page $currentPage */
            $currentPage = $this->_pages[$pageId];
            $currentPage->load();

            if ($pageId > $this->_idx) {
                $this->_idx = $pageId;
            }

            $parentMap[$pageId] = $parentId;
        }

        // Pass 2: Rebuild the hierarchy
        foreach ($parentMap as $pageId => $parentId) {
            if ($parentId == 0) {
                // Level 1: Root pages
                if (!isset($this->_order[$pageId])) {
                    $this->_order[$pageId] = [];
                }
            } else {
                // Level 2: Children
                if (isset($this->_order[$parentId])) {
                    $this->_order[$parentId][$pageId] = [];
                } else {
                    // Level 3: Grandchildren
                    $found = false;
                    foreach ($this->_order as $rootId => $children) {
                        if (isset($children[$parentId])) {
                            $this->_order[$rootId][$parentId][$pageId] = [];
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        // If parent not yet found in the tree (should not happen with NAVIGATIONID ASC),
                        // create a placeholder in _order to keep the structure.
                        if (!isset($this->_order[$parentId])) {
                            $this->_order[$parentId] = [];
                        }
                        $this->_order[$parentId][$pageId] = [];
                    }
                }
            }
        }

        $this->_idx++;
    }


    /**
     * @var string|null Preloaded review code for current save operation (bulk loading)
     */
    private ?string $_preloadedReviewCode = null;

    /**
     * @var array<string, Episciences_Page> Preloaded pages indexed by page_code for current save operation (bulk loading)
     */
    private array $_preloadedPages = [];

    public function save()
    {
        // Suppression de l'ancien menu
        $this->_db->delete($this->_table, 'SID = ' . $this->_sid);

        // Préchargement du code de la revue pour éviter les requêtes N+1
        $this->_preloadedReviewCode = null;
        try {
            $review = Episciences_ReviewsManager::find($this->_sid);
            if ($review) {
                $this->_preloadedReviewCode = $review->getCode();
            }
        } catch (Exception $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        // Préchargement de toutes les pages existantes pour éviter les requêtes N+1
        $this->_preloadedPages = [];
        if (!empty($this->_preloadedReviewCode)) {
            $this->_preloadedPages = Episciences_Page_Manager::findAllByCode($this->_preloadedReviewCode);
        }

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

        // Nettoyage des données préchargées après la sauvegarde
        $this->_preloadedReviewCode = null;
        $this->_preloadedPages = [];

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

            // Synchronize title with pages table for predefined pages
            $this->syncPageTitleToDatabase($this->_pages[$pageId]);

            $pageIdCounter++;
        }
    }

    /**
     * Synchronize page title with pages table
     *
     * @param Episciences_Website_Navigation_Page $page
     * @return void
     */
    private function syncPageTitleToDatabase(Episciences_Website_Navigation_Page $page): void
    {
        // Only pages with a permalien need title synchronization with pages table
        if (!method_exists($page, 'getPermalien') || empty($page->getPermalien())) {
            return;
        }

        // Use preloaded review code (bulk loaded in save())
        if (empty($this->_preloadedReviewCode)) {
            return;
        }

        $pageCode = $page->getPermalien();

        $labels = $page->getLabels();
        if (empty($labels)) {
            return;
        }

        if ($page->isCustom()) {
            // Custom pages can have any visibility
            $acl = $page->getAcl();
            $visibility = !empty($acl) ? $acl : ['public'];
            $this->syncCustomPageToDatabase($page, $this->_preloadedReviewCode, $pageCode, $labels, $visibility);
        } else {
            // Predefined pages are always public
            $this->syncPredefinedPageToDatabase($this->_preloadedReviewCode, $pageCode, $labels, ['public']);
        }
    }

    /**
     * Sync a custom page with pages table
     */
    private function syncCustomPageToDatabase(
        Episciences_Website_Navigation_Page $page,
        string $reviewCode,
        string $pageCode,
        array $labels,
        array $visibility
    ): void {
        // Check if the permalien has changed
        $previousPermalien = null;
        if (method_exists($page, 'getPreviousPermalien')) {
            $previousPermalien = $page->getPreviousPermalien();
        }

        if (!empty($previousPermalien)) {
            // The permalien has changed - find the old entry to update it (use preloaded data)
            $oldEntry = $this->_preloadedPages[$previousPermalien] ?? null;

            if ($oldEntry && $oldEntry->getId() > 0) {
                // Update existing entry with the new page_code (preserves the ID)
                $oldEntry->setPageCode($pageCode);
                $oldEntry->setTitle($labels);
                $oldEntry->setVisibility($visibility);
                $oldEntry->setUid(Episciences_Auth::getUid());
                Episciences_Page_Manager::updateWithNewPageCode($oldEntry, $previousPermalien);

                // Update preloaded data: remove old key, add new key
                unset($this->_preloadedPages[$previousPermalien]);
                $this->_preloadedPages[$pageCode] = $oldEntry;
            } else {
                // Old entry does not exist, create a new one
                $newPage = new Episciences_Page();
                $newPage->setCode($reviewCode);
                $newPage->setPageCode($pageCode);
                $newPage->setTitle($labels);
                $newPage->setVisibility($visibility);
                $newPage->setUid(Episciences_Auth::getUid());
                $newPage->setContent([]);
                Episciences_Page_Manager::add($newPage);

                // Add to preloaded data
                $this->_preloadedPages[$pageCode] = $newPage;
            }
        } else {
            // Permalink has not changed - find existing entry for this custom page (use preloaded data)
            $existingEntry = $this->_preloadedPages[$pageCode] ?? null;

            if ($existingEntry && $existingEntry->getId() > 0) {
                // Update existing entry
                $existingEntry->setTitle($labels);
                $existingEntry->setVisibility($visibility);
                $existingEntry->setUid(Episciences_Auth::getUid());
                Episciences_Page_Manager::update($existingEntry);
            } else {
                // Create a new entry
                $newPage = new Episciences_Page();
                $newPage->setCode($reviewCode);
                $newPage->setPageCode($pageCode);
                $newPage->setTitle($labels);
                $newPage->setVisibility($visibility);
                $newPage->setUid(Episciences_Auth::getUid());
                $newPage->setContent([]);
                Episciences_Page_Manager::add($newPage);

                // Add to preloaded data
                $this->_preloadedPages[$pageCode] = $newPage;
            }
        }
    }

    /**
     * Sync a predefined page with pages table
     */
    private function syncPredefinedPageToDatabase(
        string $reviewCode,
        string $pageCode,
        array $labels,
        array $visibility
    ): void {
        // Use preloaded pages (bulk loaded in save())
        $existingPage = $this->_preloadedPages[$pageCode] ?? null;

        if ($existingPage && $existingPage->getId() > 0) {
            // Update existing entry
            $existingPage->setTitle($labels);
            $existingPage->setVisibility($visibility);
            $existingPage->setUid(Episciences_Auth::getUid());
            Episciences_Page_Manager::update($existingPage);
        } else {
            // Create a new entry
            $newPage = new Episciences_Page();
            $newPage->setCode($reviewCode);
            $newPage->setPageCode($pageCode);
            $newPage->setTitle($labels);
            $newPage->setVisibility($visibility);
            $newPage->setUid(Episciences_Auth::getUid());
            $newPage->setContent([]);
            Episciences_Page_Manager::add($newPage);

            // Add to preloaded data
            $this->_preloadedPages[$pageCode] = $newPage;
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
