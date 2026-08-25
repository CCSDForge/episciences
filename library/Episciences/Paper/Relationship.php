<?php

declare(strict_types=1);

namespace Episciences\Paper;

class Relationship
{
    /**
     * @var array<string, array<int, string>>
     */
    protected static array $supportedRelationShips = [
        "Basis" => [
            "isBasedOn",
            "isBasisFor",
            "basedOnData",
            "isDataBasisFor"
        ],
        "Comment" => [
            "isCommentOn",
            "hasComment"
        ],
        "Continuation" => [
            "isContinuedBy",
            "continues"
        ],
        "Derivation" => [
            "isDerivedFrom",
            "hasDerivation"
        ],
        "Documentation" => [
            "isDocumentedBy",
            "documents"
        ],
        "Funding" => [
            "isFinancedBy"
        ],
        "Part" => [
            "isPartOf",
            "hasPart"
        ],
        "Peer review" => [
            "isReviewOf",
            "hasReview"
        ],
        "References" => [
            "references",
            "isReferencedBy"
        ],
        "Related material" => [
            "hasRelatedMaterial",
            "isRelatedMaterial"
        ],
        "Reply" => [
            "isReplyTo",
            "hasReply"
        ],
        "Requirement" => [
            "requires",
            "isRequiredBy"
        ],
        "Software compilation" => [
            "isCompiledBy",
            "compiles"
        ],
        "Supplement" => [
            "isSupplementTo",
            "isSupplementedBy"
        ]
    ];

    /**
     * @var array<string, array<int, string>>
     */
    protected static array $supportedRelationShips_intra_work_relation = [
        "Translation" => [
            "isTranslationOf",
            "hasTranslation"
        ],
        "Preprint" => [
            "isPreprintOf",
            "hasPreprint"
        ],
        "Manuscript" => [
            "isManuscriptOf",
            "hasManuscript"
        ],
        "Expression" => [
            "isExpressionOf",
            "hasExpression"
        ],
        "Manifestation" => [
            "isManifestationOf",
            "hasManifestation"
        ],
        "Replacement" => [
            "isReplacedBy",
            "replaces"
        ],
        "Same as" => [
            "isSameAs"
        ],
        "Identical" => [
            "isIdenticalTo"
        ],
        "Variant form" => [
            "isVariantFormOf",
            "isOriginalFormOf"
        ],
        "Version" => [
            "isVersionOf",
            "hasVersion"
        ],
        "Format" => [
            "isFormatOf",
            "hasFormat"
        ]
    ];

    /**
     * Relations excluded from the UI form dropdown but still valid in CrossRef XML exports.
     * Use getFlattenedRelationshipsIntraWorkRelation() (full list) for export classification,
     * and getDisplayedRelationShipsIntraWorkRelation() (filtered list) for form rendering.
     *
     * @var array<int, string>
     */
    protected static array $hiddenRelations = [
        "isPreprintOf", "hasPreprint",
        "isManuscriptOf", "hasManuscript",
        "isExpressionOf", "hasExpression",
        "isManifestationOf", "hasManifestation",
        "isIdenticalTo",
        "isVariantFormOf", "isOriginalFormOf",
        "isVersionOf", "hasVersion",
        "isFormatOf", "hasFormat"
    ];

    /**
     * @param array<string|int, array<int, string>> $inputArray
     * @return array<int, string>
     */
    public static function removeFirstLevel(array $inputArray): array
    {
        if (empty($inputArray)) {
            return [];
        }
        return array_merge(...array_values($inputArray));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function getSupportedRelationShips(): array
    {
        return self::$supportedRelationShips;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function getSupportedRelationShipsIntraWorkRelation(): array
    {
        return self::$supportedRelationShips_intra_work_relation;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function getDisplayedRelationShipsIntraWorkRelation(): array
    {
        $displayed = [];
        foreach (self::$supportedRelationShips_intra_work_relation as $group => $relations) {
            $filteredRelations = array_filter($relations, function(string $relation) {
                return !in_array($relation, self::$hiddenRelations, true);
            });
            if (!empty($filteredRelations)) {
                $displayed[$group] = array_values($filteredRelations);
            }
        }
        return $displayed;
    }

    /**
     * @return array<int, string>
     */
    public static function getFlattenedRelationships(): array
    {
        return self::removeFirstLevel(self::$supportedRelationShips);
    }

    /**
     * @return array<int, string>
     */
    public static function getFlattenedRelationshipsIntraWorkRelation(): array
    {
        return self::removeFirstLevel(self::$supportedRelationShips_intra_work_relation);
    }
}
