<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Build;

use Episciences\Paper\Export;
use Episciences\Solr\Indexing\Model\SolrDocument;
use Episciences_Paper;

/**
 * Port of the export-format fields from Ccsd_Search_Solr_Indexer_Episciences::
 * addMetadataToDoc() (doc_tei/doc_dc/doc_openaire/doc_crossref/doc_zbjats/
 * doc_doaj/doc_bibtex/doc_csl/doc_type_fs). Delegates the actual format
 * conversion to Episciences\Paper\Export, unchanged — this class only wires the
 * results into a SolrDocument.
 */
final class ExportFieldsBuilder
{
    public function build(SolrDocument $document, Episciences_Paper $paper): SolrDocument
    {
        $document = $document->withRawField('doc_tei', Export::getTei($paper));
        $document = $document->withRawField('doc_dc', Export::getDc($paper));
        $document = $document->withRawField('doc_openaire', Export::getOpenaire($paper));
        $document = $document->withRawField('doc_crossref', Export::getCrossref($paper));
        $document = $document->withRawField('doc_zbjats', Export::getZbjats($paper));
        $document = $document->withRawField('doc_doaj', Export::getDoaj($paper));
        $document = $document->withRawField('doc_bibtex', Export::getBibtex($paper));
        $document = $document->withRawField('doc_csl', Export::getCsl($paper->getDocid()));

        return $document->withRawField('doc_type_fs', $paper->getTypeWithKey());
    }
}
