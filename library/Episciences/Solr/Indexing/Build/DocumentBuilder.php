<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use Ccsd_Tools;
use Ccsd_Tools_String;
use Episciences\Solr\Indexing\Model\SolrDocument;
use Episciences_Paper;

/**
 * Orchestrator replacing Ccsd_Search_Solr_Indexer_Episciences::addMetadataToDoc().
 * Composes the focused Build/* collaborators instead of one 500-line class, and
 * produces an immutable SolrDocument instead of mutating a shared Solarium
 * Document — each collaborator below is independently unit-testable via
 * constructor injection (no reflection needed, unlike the legacy class's test).
 *
 * Field names/types must stay identical to src/solr/episciences/conf/schema.xml
 * and to Ccsd_Search_Solr_Indexer_Episciences::addMetadataToDoc() so that
 * Comparison\DocumentComparator's field-level diff stays meaningful.
 */
final class DocumentBuilder
{
    public function __construct(
        private readonly ExportFieldsBuilder $exportFieldsBuilder,
        private readonly AuthorFieldsBuilder $authorFieldsBuilder,
        private readonly DateFieldsBuilder $dateFieldsBuilder,
        private readonly LocaleFieldsBuilder $localeFieldsBuilder,
        private readonly VolumeSectionResolver $volumeSectionResolver,
        private readonly KeywordFieldsBuilder $keywordFieldsBuilder,
    ) {
    }

    public function build(Episciences_Paper $paper): SolrDocument
    {
        $docId = (int)$paper->getDocid();

        $document = SolrDocument::empty();
        $document = $this->exportFieldsBuilder->build($document, $paper);

        $journal = $this->volumeSectionResolver->resolveJournal($paper->getRvid());

        $document = $this->authorFieldsBuilder->build($document, $paper->getAuthors());
        $document = $this->keywordFieldsBuilder->build($document, $paper->getMetadata('subjects'));

        $submissionDate = $paper->getSubmission_date();
        $publicationDate = $paper->getPublication_date();
        $dates = $this->dateFieldsBuilder->computeDates(
            $submissionDate ? (string)$submissionDate : null,
            $publicationDate ? (string)$publicationDate : null,
        );

        $esDocUrl = sprintf('https://%s.%s/%s', $journal->getCode(), DOMAIN, $paper->getPaperid());

        $document = $document->withFilteredMap([
            'docid' => $docId,
            'paperid' => $paper->getPaperid(),
            'doi_s' => $paper->getDoi(),
            'language_s' => Ccsd_Tools::xpath($paper->getRecord(), '//dc:language'),
            'identifier_s' => $paper->getIdentifier(),
            'version_td' => $paper->getVersion(),
            'es_submission_date_tdate' => $dates['submission'],
            'es_publication_date_tdate' => $dates['publication'],
            'es_doc_url_s' => $esDocUrl,
            'es_pdf_url_s' => $esDocUrl . '/pdf',
            'publication_date_tdate' => $dates['publication'],
            'publication_date_year_fs' => $dates['year'],
            'publication_date_month_fs' => $dates['month'],
            'publication_date_day_fs' => $dates['day'],
            'revue_id_i' => $paper->getRvid(),
            'revue_code_t' => $journal->getCode(),
            'revue_title_s' => $this->cleanChars($journal->getName()),
        ]);

        $document = $this->localeFieldsBuilder->withTitles($document, $paper->getMetadata('title'));
        $document = $this->localeFieldsBuilder->withAbstracts($document, $paper->getAbstractsCleaned());

        $document = $this->volumeSectionResolver->withVolume($document, $paper->getVid());
        $document = $this->volumeSectionResolver->withSecondaryVolumes($document, $docId);
        $document = $this->volumeSectionResolver->withSection($document, $paper->getSid());

        return $this->dateFieldsBuilder->withIndexingTimestamp($document);
    }

    private function cleanChars(string $inputString): string
    {
        return trim(Ccsd_Tools_String::stripCtrlChars(html_entity_decode($inputString)));
    }
}
