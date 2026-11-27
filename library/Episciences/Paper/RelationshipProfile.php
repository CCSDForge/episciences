<?php

declare(strict_types=1);

namespace Episciences\Paper;

/**
 * Output formats of the linked data relations, each with the DataCite relationType values its schema accepts.
 *
 *  - OpenAire: OpenAIRE Guidelines for Literature Repositories 4.0 (export/datacite.phtml, openaire.xsd),
 *    which validates relationType against the DataCite 4.1 vocabulary;
 *  - DataCite: DataCite Metadata Schema 4.6 (HAL TEI, Episciences\Paper\Tei).
 */
enum RelationshipProfile: string
{
    case OpenAire = 'openaire';
    case DataCite = 'datacite';

    /**
     * DataCite 4.1 relationType vocabulary (openaire.xsd, repo-lit 4.0)
     */
    private const array DATACITE_4_1 = [
        'IsCitedBy', 'Cites',
        'IsSupplementTo', 'IsSupplementedBy',
        'IsContinuedBy', 'Continues',
        'IsDescribedBy', 'Describes',
        'HasMetadata', 'IsMetadataFor',
        'HasVersion', 'IsVersionOf', 'IsNewVersionOf', 'IsPreviousVersionOf',
        'IsPartOf', 'HasPart',
        'IsReferencedBy', 'References',
        'IsDocumentedBy', 'Documents',
        'IsCompiledBy', 'Compiles',
        'IsVariantFormOf', 'IsOriginalFormOf', 'IsIdenticalTo',
        'IsReviewedBy', 'Reviews',
        'IsDerivedFrom', 'IsSourceOf',
        'IsRequiredBy', 'Requires',
    ];

    /**
     * relationType values added after DataCite 4.1: Obsoletes pair (4.2), IsPublishedIn (4.4),
     * Collects pair (4.5), translation pair (4.6)
     */
    private const array DATACITE_4_2_TO_4_6_ADDITIONS = [
        'IsObsoletedBy', 'Obsoletes',
        'IsCollectedBy', 'Collects',
        'IsPublishedIn',
        'IsTranslationOf', 'HasTranslation',
    ];

    /**
     * @return list<string>
     */
    public function relationTypes(): array
    {
        return match ($this) {
            self::OpenAire => self::DATACITE_4_1,
            self::DataCite => [...self::DATACITE_4_1, ...self::DATACITE_4_2_TO_4_6_ADDITIONS],
        };
    }

    public function allows(string $relationType): bool
    {
        return in_array($relationType, $this->relationTypes(), true);
    }
}
