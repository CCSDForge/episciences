<?php
/**
 * Created by PhpStorm.
 * User: yannick
 * Date: 19/05/15
 * Time: 11:20
 */

class Ccsd_Navigation extends Zend_Navigation
{

    public function __construct($pages = null)
    {
        try {
            $this->addPages($pages);
        } catch (Exception $e) {
            //Erreur dans la navigation
            if ($pages instanceof Zend_Config) {
                $pages = $pages->toArray();
            }
            if (is_array($pages)) {
                $this->addPages($this->cleanPages($pages));
            }
        }
    }


    public function cleanPages($pages)
    {
        $cleanedPages = [];
        foreach($pages as $page) {
            if (isset($page['pages']) && is_array($page['pages'])) {
                $page['pages'] = $this->cleanPages($page['pages']);
            }

            if (isset($page['action']) && isset($page['controller'])) {
                $cleanedPages[] = $page;
            }
        }
        return $cleanedPages;
    }

}