<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger\Handler;

use Episciences\Solr\Indexing\Build\DocumentBuilder;
use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Episciences_PapersManager;
use LogicException;
use Solarium\QueryType\Update\Query\Document;
use Solarium\QueryType\Update\Query\Query;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Builds a Solr document for one paper and sends it, replacing
 * Ccsd_Search_Solr_Indexer_Episciences::addMetadataToDoc() + processArrayOfDocid().
 *
 * Unlike legacy sendSolrQuery() (Ccsd/Search/Solr/Indexer.php:517-544), Solr/
 * network exceptions from Client::update() are NOT caught here — letting them
 * propagate is what lets Messenger's retry strategy and failure transport do
 * their job, instead of legacy's behaviour of logging-and-swallowing the error
 * while indexPaper() unconditionally reports success.
 */
final class IndexPaperMessageHandler
{
    public function __construct(
        private readonly DocumentBuilder $documentBuilder,
        private readonly SolariumClientFactory $clientFactory,
    ) {
    }

    public function __invoke(IndexPaperMessage $message): void
    {
        $paper = Episciences_PapersManager::get($message->docId, false);

        if (!$paper) {
            // A missing paper will never appear by retrying — fail permanently
            // instead of burning through the retry budget (fixes the "no retry
            // at all" legacy bug for real Solr failures without over-retrying
            // failures that are never transient in the first place).
            throw new UnrecoverableMessageHandlingException(
                sprintf('IndexPaperMessage: no paper found for docid %d.', $message->docId)
            );
        }

        try {
            $solrDocument = $this->documentBuilder->build($paper);
        } catch (\RuntimeException $e) {
            // A missing/stale reference (e.g. the paper's journal was deleted
            // or merged after this message was enqueued) will never resolve
            // by retrying — fail permanently, same reasoning as the missing
            // paper case above.
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $client = $this->clientFactory->getIndexingClient();
        $update = $client->createUpdate();
        $update->addDocument($solrDocument->toSolariumDocument($this->createEmptyDocument($update)));
        $update->addCommit();

        $client->update($update);
    }

    /**
     * Query::createDocument() is typed to return the generic DocumentInterface
     * (it's configurable via the 'documentclass' option), but Solarium's
     * default — which this app never overrides — is the concrete Document
     * class, the only one with an addField() method. Legacy
     * Ccsd_Search_Solr_Indexer relies on the same assumption implicitly; this
     * makes it an explicit, checked assertion instead.
     */
    private function createEmptyDocument(Query $update): Document
    {
        $document = $update->createDocument();

        if (!$document instanceof Document) {
            throw new LogicException('Expected Solarium update query to produce a ' . Document::class . ' instance.');
        }

        return $document;
    }
}
