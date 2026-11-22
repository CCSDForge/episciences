<?php

declare(strict_types=1);

namespace Episciences\Paper;

use Episciences_Paper_DatasetsManager;

/**
 * Converts the relations of the linked data between the stored vocabulary (Crossref relationship-type,
 * camelCase, see Relationship) and the DataCite relationType vocabulary (PascalCase) used by HAL TEI,
 * the DataCite shaped repositories (Zenodo, ...), Scholexplorer and the OpenAIRE / TEI exports.
 *
 * See docs/linked-data-relationships.md for the full mapping.
 */
final class RelationshipMapper
{
    public const string FALLBACK_RELATED_MATERIAL = 'isRelatedMaterial';

    /**
     * DataCite / Scholix relation types whose lcfirst() is not in the stored vocabulary, or means something else
     */
    private const array DATACITE_TO_CROSSREF = [
        'IsReviewedBy' => 'hasReview',
        'Reviews' => 'isReviewOf',
        'Cites' => 'references',
        'IsCitedBy' => 'isReferencedBy',
        'IsSourceOf' => 'hasDerivation',
        'IsNewVersionOf' => 'isVersionOf',
        'IsPreviousVersionOf' => 'hasVersion',
        'IsRelatedTo' => 'isRelatedMaterial',
        'IsDescribedBy' => 'isDocumentedBy',
        'Describes' => 'documents',
        'IsObsoletedBy' => 'isReplacedBy',
        'Obsoletes' => 'replaces',
    ];

    /**
     * Stored relations whose ucfirst() is not a DataCite relationType; the result is then filtered by the profile
     */
    private const array CROSSREF_TO_DATACITE = [
        'hasReview' => 'IsReviewedBy',
        'isReviewOf' => 'Reviews',
        'hasDerivation' => 'IsSourceOf',
        'isBasedOn' => 'IsDerivedFrom',
        'basedOnData' => 'IsDerivedFrom',
        'isBasisFor' => 'IsSourceOf',
        'isDataBasisFor' => 'IsSourceOf',
        'isReplacedBy' => 'IsObsoletedBy',
        'replaces' => 'Obsoletes',
    ];

    /**
     * @var array<string, true>|null stored vocabulary (inter-work and intra-work relations)
     */
    private static ?array $vocabulary = null;

    /**
     * DataCite / Scholix relation type => stored relation, $fallback when it has no equivalent
     */
    public static function fromDataCite(
        string $relationType,
        string $fallback = Episciences_Paper_DatasetsManager::RELATION_TYPE_SOFTWARE
    ): string {
        return self::toCrossref($relationType) ?? $fallback;
    }

    /**
     * Any relation (DataCite or stored, whatever the case of its first letter) => stored relation,
     * null when it has no equivalent
     */
    public static function toCrossref(string $relationship): ?string
    {
        $relationship = trim($relationship);

        if ($relationship === '') {
            return null;
        }

        $candidate = self::DATACITE_TO_CROSSREF[ucfirst($relationship)] ?? lcfirst($relationship);

        return self::isStoredRelation($candidate) ? $candidate : null;
    }

    /**
     * Stored relation => DataCite relationType accepted by $profile, null when it has no equivalent
     */
    public static function toDataCite(string $relationship, RelationshipProfile $profile = RelationshipProfile::DataCite): ?string
    {
        $relationship = lcfirst(trim($relationship));

        if ($relationship === '') {
            return null;
        }

        $candidate = self::CROSSREF_TO_DATACITE[$relationship] ?? ucfirst($relationship);

        return $profile->allows($candidate) ? $candidate : null;
    }

    public static function isStoredRelation(string $relationship): bool
    {
        self::$vocabulary ??= array_fill_keys(
            [...Relationship::getFlattenedRelationships(), ...Relationship::getFlattenedRelationshipsIntraWorkRelation()],
            true
        );

        return isset(self::$vocabulary[$relationship]);
    }
}
