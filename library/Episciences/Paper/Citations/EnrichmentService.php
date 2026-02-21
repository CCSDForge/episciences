<?php
declare(strict_types=1);

use Psr\Cache\CacheItemInterface;

/**
 * Orchestrates the citation data enrichment pipeline.
 *
 * Flow: OpenCitations DOI list → OpenAlex metadata → Crossref location → DB upsert
 * All logging and CLI output is delegated to Episciences_Paper_Citations_Logger.
 */
class Episciences_Paper_Citations_EnrichmentService
{
    private const JSON_ENCODE_FLAGS = JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE;
    private const JSON_DECODE_FLAGS = JSON_THROW_ON_ERROR;
    private const JSON_MAX_DEPTH = 512;

    /**
     * Fetch citing DOIs from the OpenCitations API cache, enrich each via OpenAlex + Crossref,
     * and upsert the resulting metadata into the database.
     *
     * @param array $apiCallCitationCache decoded JSON array from OpenCitations API cache
     * @param int $docId paper document identifier
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function extractAndStore(array $apiCallCitationCache, int $docId): void
    {
        $globalArrayCiteDOI = Episciences_OpencitationsTools::cleanDoisCitingFound($apiCallCitationCache);
        $globalInfoMetadata = [];
        $i = 0;

        foreach ($globalArrayCiteDOI as $doiWhoCite) {
            if ($doiWhoCite !== '') {
                $globalInfoMetadata = self::processCitationsByDoiCited($doiWhoCite, $globalInfoMetadata, $i);
                $i++;
            }
        }

        self::persist($globalInfoMetadata, $docId);
    }

    /**
     * Retrieve OpenAlex metadata for a citing DOI and merge it into the metadata accumulator.
     *
     * @param string $doiWhoCite DOI of the citing paper
     * @param array $globalInfoMetadata accumulated metadata array (indexed by position)
     * @param int $i current position index
     * @return array updated metadata accumulator
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function processCitationsByDoiCited(string $doiWhoCite, array $globalInfoMetadata, int $i): array
    {
        /** @var CacheItemInterface $setsMetadata */
        $setsMetadata = Episciences_OpenalexTools::getMetadataOpenAlexByDoi($doiWhoCite);

        Episciences_Paper_Citations_Logger::log('METADATA FOUND IN CACHE ' . $doiWhoCite);

        /** @var array $metadataInfoCitation */
        $metadataInfoCitation = json_decode(
            (string) $setsMetadata->get(),
            true,
            self::JSON_MAX_DEPTH,
            self::JSON_DECODE_FLAGS
        );

        if (reset($metadataInfoCitation) !== '') {
            $globalInfoMetadata = self::getAllCitationInfoAndFormat(
                $metadataInfoCitation,
                $globalInfoMetadata,
                $i,
                $doiWhoCite
            );
        }

        return $globalInfoMetadata;
    }

    /**
     * Format metadata from OpenAlex + Crossref into the standard citation array entry.
     *
     * @param array $metadataInfoCitation decoded OpenAlex metadata for the citing paper
     * @param array $globalInfoMetadata accumulated metadata array
     * @param int $i current index into $globalInfoMetadata
     * @param string $doiWhoCite DOI of the citing paper
     * @return array updated accumulator
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public static function getAllCitationInfoAndFormat(
        array $metadataInfoCitation,
        array $globalInfoMetadata,
        int $i,
        string $doiWhoCite
    ): array {
        $globalInfoMetadata[$i]['type'] = $metadataInfoCitation['type_crossref'];
        $globalInfoMetadata[$i]['author'] = Episciences_OpenalexTools::getAuthors($metadataInfoCitation['authorships']);
        $globalInfoMetadata[$i]['year'] = $metadataInfoCitation['publication_year'];
        $globalInfoMetadata[$i]['title'] = $metadataInfoCitation['title'];

        /** @var array|string $getBestOpenAccessInfo */
        $getBestOpenAccessInfo = Episciences_OpenalexTools::getBestOaInfo(
            $metadataInfoCitation['primary_location'],
            $metadataInfoCitation['locations'],
            $metadataInfoCitation['best_oa_location']
        );

        $getLocationFromCr = Episciences_CrossrefTools::getLocationFromCrossref($getBestOpenAccessInfo, $doiWhoCite);
        $globalInfoMetadata = Episciences_CrossrefTools::addLocationEvent(
            $metadataInfoCitation['type_crossref'],
            $doiWhoCite,
            $globalInfoMetadata,
            $i
        );

        if ($getLocationFromCr === '' && $getBestOpenAccessInfo === '') {
            $globalInfoMetadata[$i]['source_title'] = '';
        } else {
            $globalInfoMetadata[$i]['source_title'] = ($getLocationFromCr === '')
                ? $getBestOpenAccessInfo['source_title']
                : $getLocationFromCr;
        }

        $globalInfoMetadata[$i]['volume'] = $metadataInfoCitation['biblio']['volume'] ?? '';
        $globalInfoMetadata[$i]['issue'] = $metadataInfoCitation['biblio']['issue'] ?? '';
        $globalInfoMetadata[$i]['page'] = Episciences_OpenalexTools::getPages(
            $metadataInfoCitation['biblio']['first_page'],
            $metadataInfoCitation['biblio']['last_page']
        );
        $globalInfoMetadata[$i]['doi'] = $doiWhoCite;

        if ($getLocationFromCr === '' && $getBestOpenAccessInfo === '') {
            $globalInfoMetadata[$i]['oa_link'] = '';
        } else {
            $globalInfoMetadata[$i]['oa_link'] = ($getLocationFromCr === '' && !is_null($getBestOpenAccessInfo['oa_link']))
                ? $getBestOpenAccessInfo['oa_link']
                : '';
        }

        $globalInfoMetadata[$i]['source_title'] = Episciences_OpenalexTools::removeHalStringFromLocation(
            $globalInfoMetadata[$i]['source_title']
        );

        return $globalInfoMetadata;
    }

    /**
     * JSON-encode the metadata accumulator and upsert it into the database for the given document.
     *
     * @param array $globalInfoMetadata citation metadata to persist
     * @param int $docId paper document identifier
     * @throws JsonException
     */
    public static function persist(array $globalInfoMetadata, int $docId): void
    {
        if ($docId <= 0 || $globalInfoMetadata === []) {
            return;
        }

        $globalInfoMetaAsJson = json_encode($globalInfoMetadata, self::JSON_ENCODE_FLAGS);

        Episciences_Paper_Citations_Logger::log($globalInfoMetaAsJson);

        $citationObject = (new Episciences_Paper_Citations())
            ->setCitation($globalInfoMetaAsJson)
            ->setDocId($docId)
            ->setSourceId((int) Episciences_Repositories::OPENCITATIONS_ID);

        if (Episciences_Paper_Citations_Repository::insert([$citationObject]) >= 1) {
            Episciences_Paper_Citations_Logger::log('CITATION INSERTED FOR ' . $docId);
        } else {
            Episciences_Paper_Citations_Logger::log('NO CHANGING CITATIONS FOR ' . $docId);
        }
    }
}
