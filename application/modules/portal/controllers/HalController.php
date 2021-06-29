<?php

class HalController extends Zend_Controller_Action
{

    public function bibfeedAction()
    {
        $locale = 'en';
        $output = [];

        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        header('Content-Type: text/json');

        $repoId = Episciences_Repositories::getRepoIdByLabel('Hal');
        $translator = Zend_Registry::get('Zend_Translate');

        $settings = ['is' => [
            'repoid' => $repoId,
            'status' => Episciences_Paper::STATUS_PUBLISHED]];
        try {
            $papers = Episciences_PapersManager::getList($settings);
        } catch (Zend_Db_Select_Exception $exception) {
            echo json_encode($output);
            return;
        }

        $reviews = [];

        foreach ($papers as $paper) {
            /** @var $paper Episciences_Paper */

            if (!array_key_exists($paper->getRvid(), $reviews)) {
                if (Episciences_Review::exist($paper->getRvid())) {
                    $review = Episciences_ReviewsManager::find($paper->getRvid());
                    $reviews[$paper->getRvid()] = $review;
                    $translator->addTranslation(APPLICATION_PATH . '/../data/' . $review->getCode() . '/languages/');
                } else {
                    continue;
                }
            }

            $preprintId = $paper->getIdentifier();

            $journalRef['preprintVersion'] = $paper->getVersion();

            if ($paper->getDoi() !== '') {
                $journalRef['doi'] = $paper->getDoi();
            }

            $journalRef['journalName'] = $reviews[$paper->getRvid()]->getName();
            if ($paper->getVid() !== 0) {
                $journalRef['volumeName'] = ($translator->isTranslated('volume_' . $paper->getVid() . '_title', false, $locale)) ? $translator->translate('volume_' . $paper->getVid() . '_title', $locale) : '';
            }
            if ($paper->getSid() !== 0) {
                $journalRef['sectionName'] = ($translator->isTranslated('section_' . $paper->getSid() . '_title', false, $locale)) ? $translator->translate('section_' . $paper->getSid() . '_title', $locale) : '';
            }

            $journalRef['publicationDate'] = $paper->getPublication_date();
            $journalRef['lastUpdate'] = $paper->getModification_date();

            $output['publishedPapers'][$preprintId] = $journalRef;

        }

        echo json_encode($output);


    }

}