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

        $repoId = Episciences_Repositories::getRepoIdByLabel(Episciences_Repositories::HAL_LABEL);

        $settings = ['is' => [
            'repoid' => $repoId,
            'status' => Episciences_Paper::STATUS_PUBLISHED]];
        try {
            $papers = Episciences_PapersManager::getList($settings);
        } catch (Zend_Db_Select_Exception $exception) {
            echo json_encode($output);
            return;
        }

        $journalMappings = Episciences_Review::getHalJournalMappings(true);

        $reviews = [];

        foreach ($papers as $paper) {
            /** @var $paper Episciences_Paper */

            if (!array_key_exists($paper->getRvid(), $reviews)) {
                if (Episciences_Review::exist($paper->getRvid())) {
                    $review = Episciences_ReviewsManager::find($paper->getRvid());
                    $reviews[$paper->getRvid()] = $review;
                } else {
                    continue;
                }
            }

            $documentIdentifier = $paper->getIdentifier();

            if (array_key_exists($paper->getRvid(), $journalMappings)) {
                $journalRef['halJournalId'] = $journalMappings[$paper->getRvid()];
            }

            $journalRef['docJournalId'] = $paper->getRvid();


            $journalRef['docJournalName'] = $reviews[$paper->getRvid()]->getName();


            $journalRef['docVersion'] = $paper->getVersion();

            if ($paper->getDoi() !== '') {
                $journalRef['docDoi'] = $paper->getDoi();
            }


            if ($paper->getVid() !== 0) {
                $journalRef['docVolumeName'] = Episciences_VolumesManager::translateVolumeKey('volume_' . $paper->getVid() . '_title', $locale, false);
            }
            if ($paper->getSid() !== 0) {
                $journalRef['docSectionName'] = Episciences_SectionsManager::translateSectionKey('section_' . $paper->getSid() . '_title', $locale, false);
            }

            $journalRef['docPublicationDate'] = $paper->getPublication_date();
            $journalRef['docLastUpdate'] = $paper->getModification_date();

            $output['publishedDocuments'][$documentIdentifier] = $journalRef;

        }

        echo json_encode($output);


    }

}