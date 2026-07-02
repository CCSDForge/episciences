<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use Ccsd_Search_Solr;
use Episciences\AppRegistry;
use Episciences\Solr\Indexing\Model\SolrDocument;
use Episciences_Review;
use Episciences_SectionsManager;
use Episciences_Volume;
use Episciences_Volume_PapersManager;
use Episciences_VolumesManager;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Port of indexVolume()/indexSecondaryVolumes()/indexSection()/
 * getVolumeFromDbOrCache()/getJournalMetadata() from
 * Ccsd_Search_Solr_Indexer_Episciences.
 *
 * Unlike the legacy class (a single ArrayAdapter(0, false) held for the whole
 * process, so a long-running bulk/cron run can serve a stale journal/volume/
 * section snapshot if a row changes mid-run), the cache here is injected by the
 * caller. DocumentBuilder callers should pass a cache scoped to a single message
 * — a per-run process-wide cache is an opt-in choice, not the default, so the
 * new pipeline doesn't reproduce that staleness window.
 */
final class VolumeSectionResolver
{
    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function resolveJournal(int $rvid): Episciences_Review
    {
        $item = $this->cache->getItem('rvid.' . $rvid);

        if ($item->isHit()) {
            return $item->get();
        }

        $journal = new Episciences_Review(Episciences_Review::getData($rvid));
        $this->cache->save($item->set($journal));

        return $journal;
    }

    public function withVolume(SolrDocument $document, int $vid): SolrDocument
    {
        if ($vid === 0) {
            return $document;
        }

        $volume = $this->findVolume($vid);

        if (!$volume) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf("Update doc : le volume (%s) de cet article n'existe pas/plus.", $vid)
            );

            return $document;
        }

        $document = $document->withRawField('volume_id_i', $vid);
        $document = $document->withRawField('volume_status_i', $volume->getStatus());

        $titles = $volume->getTitles();
        if (!is_array($titles) || $titles === []) {
            return $document;
        }

        // We take the first language found because the field is not multivalued
        $firstLanguageFound = array_key_exists('en', $titles) ? 'en' : array_key_first($titles);
        $document = $document->withRawField(
            'volume_fs',
            $vid . Ccsd_Search_Solr::SOLR_FACET_SEPARATOR . $titles[$firstLanguageFound]
        );

        foreach ($titles as $lang => $translation) {
            $document = $document->withRawField($lang . '_volume_title_t', $translation);
            $document = $document->withRawField(
                'volume_title_fs',
                $vid . Ccsd_Search_Solr::SOLR_FACET_SEPARATOR . $lang . '_' . $translation
            );
        }

        return $document;
    }

    public function withSecondaryVolumes(SolrDocument $document, int $docId): SolrDocument
    {
        foreach (Episciences_Volume_PapersManager::findPaperVolumes($docId) as $secondaryVolume) {
            $vid = $secondaryVolume->getVid();
            $volume = $this->findVolume($vid);

            if (!$volume) {
                AppRegistry::getMonoLogger()?->warning(
                    sprintf("Update doc %s : le volume secondaire (%s) de cet article n'existe pas/plus.", $docId, $vid)
                );
                continue;
            }

            $document = $document->withRawField('secondary_volume_id_i', $vid);

            $titles = $volume->getTitles();
            if (!is_array($titles)) {
                continue;
            }

            foreach ($titles as $lang => $translation) {
                $document = $document->withRawField($lang . '_secondary_volume_title_t', $translation);
                $document = $document->withRawField(
                    'secondary_volume_fs',
                    $vid . Ccsd_Search_Solr::SOLR_FACET_SEPARATOR . $translation
                );
            }
        }

        return $document;
    }

    public function withSection(SolrDocument $document, int $sectionId): SolrDocument
    {
        if ($sectionId === 0) {
            return $document;
        }

        $item = $this->cache->getItem('section.' . $sectionId);

        if ($item->isHit()) {
            $section = $item->get();
        } else {
            $section = Episciences_SectionsManager::find($sectionId);
            if ($section) {
                $this->cache->save($item->set($section));
            }
        }

        if (!$section) {
            AppRegistry::getMonoLogger()?->warning(
                sprintf("Update doc : la section (%s) de cet article n'existe pas/plus.", $sectionId)
            );

            return $document;
        }

        $document = $document->withRawField('section_id_i', $sectionId);

        $titles = $section->getTitles();
        if (!is_array($titles) || $titles === []) {
            return $document;
        }

        // We take the first language found because the field is not multivalued
        $firstLanguageFound = array_key_exists('en', $titles) ? 'en' : array_key_first($titles);
        $document = $document->withRawField(
            'section_fs',
            $sectionId . Ccsd_Search_Solr::SOLR_FACET_SEPARATOR . $titles[$firstLanguageFound]
        );

        foreach ($titles as $lang => $translation) {
            $document = $document->withRawField($lang . '_section_title_t', $translation);
            $document = $document->withRawField(
                'section_title_fs',
                $sectionId . Ccsd_Search_Solr::SOLR_FACET_SEPARATOR . $lang . '_' . $translation
            );
        }

        return $document;
    }

    private function findVolume(int $vid): Episciences_Volume|false
    {
        $item = $this->cache->getItem('volume.' . $vid);

        if ($item->isHit()) {
            return $item->get();
        }

        $volume = Episciences_VolumesManager::find($vid);
        if ($volume) {
            $this->cache->save($item->set($volume));
        }

        return $volume;
    }
}
