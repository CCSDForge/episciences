<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Messenger\Handler;

use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;

/**
 * Deletes one document from Solr, by docid or by raw query — replaces
 * Ccsd_Search_Solr_Indexer::deleteDocids()/deleteDocument(). Exceptions
 * propagate for the same reason as IndexPaperMessageHandler.
 */
final class DeletePaperMessageHandler
{
    public function __construct(private readonly SolariumClientFactory $clientFactory)
    {
    }

    public function __invoke(DeletePaperMessage $message): void
    {
        $client = $this->clientFactory->getIndexingClient();
        $update = $client->createUpdate();
        $update->addDeleteQuery($message->toSolrDeleteQuery());
        $update->addCommit();

        $client->update($update);
    }
}
