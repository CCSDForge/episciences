<?php

declare(strict_types=1);

namespace Episciences\Hal;

use Episciences_Paper;
use Episciences_Repositories;

/**
 * One version of a HAL document, and where its TEI is read: the OAI-PMH server of the repository
 * (GetRecord, metadataPrefix=xml-tei), configured per repository (REPOSITORIES.BASEURL and the OAI
 * identifier template), so that each HAL instance (production, preprod) is asked for its own documents.
 *
 * Not the HAL search API: its index only holds the latest version of a document. The OAI-PMH server
 * serves every online version, and the latest one for an identifier without version.
 */
final class HalTeiSource
{
    public const OAI_METADATA_PREFIX = 'xml-tei';

    /**
     * @param int $repoId repository of the document
     * @param string $identifier HAL document identifier, without version
     * @param int $version document version (0 = latest)
     */
    public function __construct(
        public readonly int $repoId,
        public readonly string $identifier,
        public readonly int $version = 0,
    ) {
    }

    /**
     * Document of the HAL main repository (HAL_REPO_ID)
     */
    public static function hal(string $identifier, int $version = 0): self
    {
        return new self((int)Episciences_Repositories::HAL_REPO_ID, $identifier, $version);
    }

    /**
     * Version of the document the paper was submitted from; the identifier of a refused paper
     * loses its suffix, so that the HAL identifier is requested
     */
    public static function ofPaper(Episciences_Paper $paper): self
    {
        return new self(
            (int)$paper->getRepoid(),
            Episciences_Paper::stripRefusedSuffix((string)$paper->getIdentifier()),
            (int)$paper->getVersion()
        );
    }

    /**
     * OAI-PMH GetRecord URL of the TEI
     *
     * @return string|null null when the repository has no HTTP(S) OAI-PMH server or no OAI identifier
     */
    public function getRecordUrl(): ?string
    {
        $baseUrl = (string)Episciences_Repositories::getBaseUrl($this->repoId);
        $oaiIdentifier = Episciences_Repositories::getIdentifier(
            $this->repoId,
            $this->identifier,
            $this->version !== 0 ? $this->version : null
        );

        if ($oaiIdentifier === null || $oaiIdentifier === '' || !self::isHttpUrl($baseUrl)) {
            return null;
        }

        return $baseUrl . '?' . http_build_query([
            'verb' => 'GetRecord',
            'metadataPrefix' => self::OAI_METADATA_PREFIX,
            'identifier' => $oaiIdentifier,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Readable description, for the logs
     */
    public function describe(): string
    {
        return sprintf('%s (version %d, repository %d)', $this->identifier, $this->version, $this->repoId);
    }

    private static function isHttpUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
