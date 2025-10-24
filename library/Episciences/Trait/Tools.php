<?php

namespace Episciences\Trait;

use Ccsd_Search_Solr_Indexer;
use Ccsd_Search_Solr_Indexer_Episciences;
use Episciences_Notify_Hal;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Review;
use Episciences_ReviewsManager;
use Episciences_View_Helper_Log;
use Exception;
use Psr\Log\LogLevel;

trait Tools
{
    public function COARNotify(Episciences_Paper $paper, Episciences_Review|bool $journal = false, bool $checkEnv = true): void
    {

        if (
            $checkEnv &&
            isset($_ENV['APP_ENV']) &&
            $_ENV['APP_ENV'] === 'development'
        ) {
            return;
        }


        if (!$paper->isPublished()) {
            return;
        }

        // if HAL, send coar notify message
        if (Episciences_Repositories::isFromHalRepository($paper->getRepoid())) {

            if (!$journal) {
                $journal = Episciences_ReviewsManager::find(RVID);
            }

            if ($journal) {
                $notification = new Episciences_Notify_Hal($paper, $journal);
                try {
                    $notification->announceEndorsement();
                } catch (Exception $exception) {
                    Episciences_View_Helper_Log::log(sprintf("Announcing publication to HAL failed: %s", $exception->getMessage()), LogLevel::CRITICAL);
                }
            }
        }

    }


    final public function index(Episciences_Paper $paper): void
    {
        if (!$paper->isPublished()) {
            return;
        }

        $resOfIndexing = $paper->indexUpdatePaper();

        if (!$resOfIndexing) {
            try {
                Ccsd_Search_Solr_Indexer::addToIndexQueue([$paper->getDocid()], RVCODE, Ccsd_Search_Solr_Indexer::O_UPDATE, Ccsd_Search_Solr_Indexer_Episciences::$coreName);
            } catch (Exception $e) {
                Episciences_View_Helper_Log::log($e->getMessage(), LogLevel::CRITICAL);
            }
        }
    }

}