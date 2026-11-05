<?php

declare(strict_types=1);

namespace Episciences\Paper\Funding;

/**
 * One normalized funding of a paper, whatever its source (OpenAIRE Graph, HAL, Dataverse, ...).
 *
 * Every property is null when unknown: the "unidentified" placeholder stored in
 * paper_projects never reaches this object. Shared by the Crossref and OpenAIRE
 * exports and by the DOCUMENT JSON, so the three always describe the same fundings.
 */
final class FundingReference
{
    public function __construct(
        public readonly ?string $funderName = null,
        public readonly ?string $funderCrossrefId = null,
        public readonly ?string $funderRor = null,
        public readonly ?string $fundingStream = null,
        public readonly ?string $awardNumber = null,
        public readonly ?string $awardTitle = null,
        public readonly ?string $awardAcronym = null,
        public readonly ?string $awardUri = null,
        public readonly ?string $grantDoi = null,
        public readonly ?string $callId = null,
        public readonly ?string $openaireProjectId = null,
        public readonly ?string $statement = null,
        /** FunderRegistry key of a well-known funder (e.g. "ANR", "EC", "ERC") */
        public readonly ?string $funderKey = null,
    ) {
    }

    /**
     * Crossref (fundref) and OpenAIRE (fundingReference) both require the funder name.
     */
    public function hasFunder(): bool
    {
        return $this->funderName !== null;
    }

    /**
     * A free-text funding acknowledgement that could not be turned into a funder or an award.
     */
    public function isStatementOnly(): bool
    {
        return $this->statement !== null
            && $this->funderName === null
            && $this->awardNumber === null
            && $this->awardTitle === null;
    }

    /**
     * Key identifying the same funding coming from several sources.
     * Null when there is not enough information to tell two fundings apart.
     */
    public function mergeKey(): ?string
    {
        if ($this->awardNumber !== null) {
            $funder = $this->funderKey !== null ? FunderRegistry::family($this->funderKey) : ($this->funderName ?? '');

            return 'award:' . mb_strtolower($funder) . '|' . mb_strtolower($this->awardNumber);
        }

        if ($this->grantDoi !== null) {
            return 'doi:' . mb_strtolower($this->grantDoi);
        }

        return null;
    }

    /**
     * Merge two descriptions of the same funding: values already known win, missing ones are filled.
     * The more specific funder wins (ERC over the European Commission it belongs to).
     */
    public function mergeWith(self $other): self
    {
        $funder = FunderRegistry::isPartOf($other->funderKey, $this->funderKey) ? $other : $this;
        $fallback = $funder === $this ? $other : $this;

        return new self(
            funderName: $funder->funderName ?? $fallback->funderName,
            funderCrossrefId: $funder->funderCrossrefId ?? $fallback->funderCrossrefId,
            funderRor: $funder->funderRor ?? $fallback->funderRor,
            fundingStream: $this->fundingStream ?? $other->fundingStream,
            awardNumber: $this->awardNumber ?? $other->awardNumber,
            awardTitle: $this->awardTitle ?? $other->awardTitle,
            awardAcronym: $this->awardAcronym ?? $other->awardAcronym,
            awardUri: $this->awardUri ?? $other->awardUri,
            grantDoi: $this->grantDoi ?? $other->grantDoi,
            callId: $this->callId ?? $other->callId,
            openaireProjectId: $this->openaireProjectId ?? $other->openaireProjectId,
            statement: $this->statement ?? $other->statement,
            funderKey: $funder->funderKey ?? $fallback->funderKey,
        );
    }

    /**
     * Content of the citation_funder_source meta tag of the paper page,
     * e.g. "citation_funder=European Commission; citation_grant_number=101017452;".
     * Null when the funder is unknown.
     */
    public function toCitationFunderSource(): ?string
    {
        if ($this->funderName === null) {
            return null;
        }

        $content = 'citation_funder=' . $this->funderName . ';';

        if ($this->awardNumber !== null) {
            $content .= ' citation_grant_number=' . $this->awardNumber . ';';
        }

        return $content;
    }

    /**
     * Public representation, exposed in PAPERS.DOCUMENT under database.current.fundings.
     *
     * @return array{
     *     funder: array{name: ?string, crossref_funder_id: ?string, ror: ?string},
     *     funding_stream: ?string,
     *     award: array{number: ?string, title: ?string, acronym: ?string, uri: ?string, grant_doi: ?string, call_id: ?string, openaire_id: ?string},
     *     statement: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'funder' => [
                'name' => $this->funderName,
                'crossref_funder_id' => $this->funderCrossrefId,
                'ror' => $this->funderRor,
            ],
            'funding_stream' => $this->fundingStream,
            'award' => [
                'number' => $this->awardNumber,
                'title' => $this->awardTitle,
                'acronym' => $this->awardAcronym,
                'uri' => $this->awardUri,
                'grant_doi' => $this->grantDoi,
                'call_id' => $this->callId,
                'openaire_id' => $this->openaireProjectId,
            ],
            'statement' => $this->statement,
        ];
    }
}
