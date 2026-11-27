# Linked Data Relationships

The linked data of a paper (related datasets, software, publications) are stored in `paper_datasets`, each row with
the relation between the paper and the linked resource in the `relationship` column.

- **Stored vocabulary**: Crossref `relationship-type` values, in `camelCase` (`isSupplementedBy`, `hasReview`, ...),
  defined in `Episciences\Paper\Relationship`.
- **Sources and exports** use other vocabularies: HAL TEI, the DataCite shaped repositories (Zenodo, ARCHE, BAOBAB,
  DSpace) and Scholexplorer (Scholix) give DataCite `relationType` values in `PascalCase` (`IsSupplementedBy`,
  `IsReviewedBy`, ...); the OpenAIRE and TEI exports expect DataCite values too.
- **`Episciences\Paper\RelationshipMapper`** converts between the two, in both directions. Every write path
  normalizes the relation before storing it; every export converts it to its own vocabulary.

## Stored Vocabulary

"Form" tells whether the relation is offered in the linked data form (`Relationship::$hiddenRelations` lists the
others, still valid in the exports). "Crossref element" is the element used by the Crossref export.

| Group | Relation | Form | Crossref element |
| :--- | :--- | :--- | :--- |
| Basis | `isBasedOn` | yes | `inter_work_relation` |
| Basis | `isBasisFor` | yes | `inter_work_relation` |
| Basis | `basedOnData` | yes | `inter_work_relation` |
| Basis | `isDataBasisFor` | yes | `inter_work_relation` |
| Comment | `isCommentOn` | yes | `inter_work_relation` |
| Comment | `hasComment` | yes | `inter_work_relation` |
| Continuation | `isContinuedBy` | yes | `inter_work_relation` |
| Continuation | `continues` | yes | `inter_work_relation` |
| Derivation | `isDerivedFrom` | yes | `inter_work_relation` |
| Derivation | `hasDerivation` | yes | `inter_work_relation` |
| Documentation | `isDocumentedBy` | yes | `inter_work_relation` |
| Documentation | `documents` | yes | `inter_work_relation` |
| Funding | `isFinancedBy` | yes | `inter_work_relation` |
| Part | `isPartOf` | yes | `inter_work_relation` |
| Part | `hasPart` | yes | `inter_work_relation` |
| Peer review | `isReviewOf` | yes | `inter_work_relation` |
| Peer review | `hasReview` | yes | `inter_work_relation` |
| References | `references` | yes | `inter_work_relation` |
| References | `isReferencedBy` | yes | `inter_work_relation` |
| Related material | `hasRelatedMaterial` | yes | `inter_work_relation` |
| Related material | `isRelatedMaterial` | yes | `inter_work_relation` |
| Reply | `isReplyTo` | yes | `inter_work_relation` |
| Reply | `hasReply` | yes | `inter_work_relation` |
| Requirement | `requires` | yes | `inter_work_relation` |
| Requirement | `isRequiredBy` | yes | `inter_work_relation` |
| Software compilation | `isCompiledBy` | yes | `inter_work_relation` |
| Software compilation | `compiles` | yes | `inter_work_relation` |
| Supplement | `isSupplementTo` | yes | `inter_work_relation` |
| Supplement | `isSupplementedBy` | yes | `inter_work_relation` |
| Translation | `isTranslationOf` | yes | `intra_work_relation` |
| Translation | `hasTranslation` | yes | `intra_work_relation` |
| Preprint | `isPreprintOf` | no | `intra_work_relation` |
| Preprint | `hasPreprint` | no | `intra_work_relation` |
| Manuscript | `isManuscriptOf` | no | `intra_work_relation` |
| Manuscript | `hasManuscript` | no | `intra_work_relation` |
| Expression | `isExpressionOf` | no | `intra_work_relation` |
| Expression | `hasExpression` | no | `intra_work_relation` |
| Manifestation | `isManifestationOf` | no | `intra_work_relation` |
| Manifestation | `hasManifestation` | no | `intra_work_relation` |
| Replacement | `isReplacedBy` | yes | `intra_work_relation` |
| Replacement | `replaces` | yes | `intra_work_relation` |
| Same as | `isSameAs` | yes | `intra_work_relation` |
| Identical | `isIdenticalTo` | no | `intra_work_relation` |
| Variant form | `isVariantFormOf` | no | `intra_work_relation` |
| Variant form | `isOriginalFormOf` | no | `intra_work_relation` |
| Version | `isVersionOf` | no | `intra_work_relation` |
| Version | `hasVersion` | no | `intra_work_relation` |
| Format | `isFormatOf` | no | `intra_work_relation` |
| Format | `hasFormat` | no | `intra_work_relation` |

Each relation has a translation key of the same name in `application/languages/{en,fr}/views.php`, used to
display the linked data on the paper page. `Episciences_Paper::getDatasetsFromEnrichment()` groups them by
`toCrossref()` of the stored value, so that a legacy row holding a DataCite type joins the group of its stored
relation; a row without relation, or with one without equivalent, is shown under "Other"
(`Episciences_Paper::LINKED_DATA_OTHER_RELATION`, translation key `linked_data_other_relation`).

## Inbound: DataCite / Scholix to Stored

`RelationshipMapper::toCrossref()` returns the stored relation, or `null` when there is none;
`RelationshipMapper::fromDataCite()` returns a fallback instead of `null`. Surrounding spaces are ignored, and the
case of the first letter does not matter (`IsReviewedBy` and `isReviewedBy` give the same result).

1. **Dedicated mappings** (`DATACITE_TO_CROSSREF`), for the DataCite types whose `lcfirst()` is not a stored relation
   or means something else:

   | DataCite / Scholix | Stored | Note |
   | :--- | :--- | :--- |
   | `IsReviewedBy` | `hasReview` | |
   | `Reviews` | `isReviewOf` | |
   | `Cites` | `references` | lossy: exported back as `References` |
   | `IsCitedBy` | `isReferencedBy` | lossy: exported back as `IsReferencedBy` |
   | `IsSourceOf` | `hasDerivation` | |
   | `IsNewVersionOf` | `isVersionOf` | lossy: exported back as `IsVersionOf` |
   | `IsPreviousVersionOf` | `hasVersion` | lossy: exported back as `HasVersion` |
   | `IsRelatedTo` | `isRelatedMaterial` | Scholix only, not a DataCite 4.x type |
   | `IsDescribedBy` | `isDocumentedBy` | lossy: exported back as `IsDocumentedBy` |
   | `Describes` | `documents` | lossy: exported back as `Documents` |
   | `IsObsoletedBy` | `isReplacedBy` | DataCite 4.2+ |
   | `Obsoletes` | `replaces` | DataCite 4.2+ |

2. **Generic rule**: `lcfirst()` of the DataCite type when it is a stored relation
   (`IsSupplementedBy` gives `isSupplementedBy`, `IsTranslationOf` gives `isTranslationOf`, ...).

3. **No equivalent**: `HasMetadata`, `IsMetadataFor`, `IsCollectedBy`, `Collects`, `IsPublishedIn` and any unknown value.
   `fromDataCite()` then stores its fallback:

   | Source | Fallback | Why |
   | :--- | :--- | :--- |
   | HAL TEI, DataCite shaped repositories | `references` (default) | historical behaviour |
   | Scholexplorer | `isRelatedMaterial` (`RelationshipMapper::FALLBACK_RELATED_MATERIAL`) | `references` would state a citation Scholix does not give |

## Outbound: Stored to Exports

`RelationshipMapper::toDataCite($relation, $profile)` returns the DataCite type accepted by the profile, or `null`.
It first looks up `CROSSREF_TO_DATACITE`, for the stored relations whose `ucfirst()` is not a DataCite type:

| Stored | DataCite | Minimal DataCite version |
| :--- | :--- | :--- |
| `hasReview` | `IsReviewedBy` | 4.1 |
| `isReviewOf` | `Reviews` | 4.1 |
| `hasDerivation` | `IsSourceOf` | 4.1 |
| `isBasedOn` | `IsDerivedFrom` | 4.1 |
| `basedOnData` | `IsDerivedFrom` | 4.1 |
| `isBasisFor` | `IsSourceOf` | 4.1 |
| `isDataBasisFor` | `IsSourceOf` | 4.1 |
| `isReplacedBy` | `IsObsoletedBy` | 4.2 |
| `replaces` | `Obsoletes` | 4.2 |

Otherwise it uses `ucfirst()`. The result is kept only when the profile accepts it.

| Stored | Crossref XML | OpenAIRE 4.0 (`export/datacite.phtml`) | HAL TEI (`Episciences_Paper_Tei`) |
| :--- | :--- | :--- | :--- |
| `isBasedOn` | `isBasedOn` | `IsDerivedFrom` | `IsDerivedFrom` |
| `isBasisFor` | `isBasisFor` | `IsSourceOf` | `IsSourceOf` |
| `basedOnData` | `basedOnData` | `IsDerivedFrom` | `IsDerivedFrom` |
| `isDataBasisFor` | `isDataBasisFor` | `IsSourceOf` | `IsSourceOf` |
| `isCommentOn` | `isCommentOn` | *omitted* | *no `type`* |
| `hasComment` | `hasComment` | *omitted* | *no `type`* |
| `isContinuedBy` | `isContinuedBy` | `IsContinuedBy` | `IsContinuedBy` |
| `continues` | `continues` | `Continues` | `Continues` |
| `isDerivedFrom` | `isDerivedFrom` | `IsDerivedFrom` | `IsDerivedFrom` |
| `hasDerivation` | `hasDerivation` | `IsSourceOf` | `IsSourceOf` |
| `isDocumentedBy` | `isDocumentedBy` | `IsDocumentedBy` | `IsDocumentedBy` |
| `documents` | `documents` | `Documents` | `Documents` |
| `isFinancedBy` | `isFinancedBy` | *omitted* | *no `type`* |
| `isPartOf` | `isPartOf` | `IsPartOf` | `IsPartOf` |
| `hasPart` | `hasPart` | `HasPart` | `HasPart` |
| `isReviewOf` | `isReviewOf` | `Reviews` | `Reviews` |
| `hasReview` | `hasReview` | `IsReviewedBy` | `IsReviewedBy` |
| `references` | `references` | `References` | `References` |
| `isReferencedBy` | `isReferencedBy` | `IsReferencedBy` | `IsReferencedBy` |
| `hasRelatedMaterial` | `hasRelatedMaterial` | *omitted* | *no `type`* |
| `isRelatedMaterial` | `isRelatedMaterial` | *omitted* | *no `type`* |
| `isReplyTo` | `isReplyTo` | *omitted* | *no `type`* |
| `hasReply` | `hasReply` | *omitted* | *no `type`* |
| `requires` | `requires` | `Requires` | `Requires` |
| `isRequiredBy` | `isRequiredBy` | `IsRequiredBy` | `IsRequiredBy` |
| `isCompiledBy` | `isCompiledBy` | `IsCompiledBy` | `IsCompiledBy` |
| `compiles` | `compiles` | `Compiles` | `Compiles` |
| `isSupplementTo` | `isSupplementTo` | `IsSupplementTo` | `IsSupplementTo` |
| `isSupplementedBy` | `isSupplementedBy` | `IsSupplementedBy` | `IsSupplementedBy` |
| `isTranslationOf` | `isTranslationOf` | *omitted* | `IsTranslationOf` |
| `hasTranslation` | `hasTranslation` | *omitted* | `HasTranslation` |
| `isPreprintOf` | `isPreprintOf` | *omitted* | *no `type`* |
| `hasPreprint` | `hasPreprint` | *omitted* | *no `type`* |
| `isManuscriptOf` | `isManuscriptOf` | *omitted* | *no `type`* |
| `hasManuscript` | `hasManuscript` | *omitted* | *no `type`* |
| `isExpressionOf` | `isExpressionOf` | *omitted* | *no `type`* |
| `hasExpression` | `hasExpression` | *omitted* | *no `type`* |
| `isManifestationOf` | `isManifestationOf` | *omitted* | *no `type`* |
| `hasManifestation` | `hasManifestation` | *omitted* | *no `type`* |
| `isReplacedBy` | `isReplacedBy` | *omitted* | `IsObsoletedBy` |
| `replaces` | `replaces` | *omitted* | `Obsoletes` |
| `isSameAs` | `isSameAs` | *omitted* | *no `type`* |
| `isIdenticalTo` | `isIdenticalTo` | `IsIdenticalTo` | `IsIdenticalTo` |
| `isVariantFormOf` | `isVariantFormOf` | `IsVariantFormOf` | `IsVariantFormOf` |
| `isOriginalFormOf` | `isOriginalFormOf` | `IsOriginalFormOf` | `IsOriginalFormOf` |
| `isVersionOf` | `isVersionOf` | `IsVersionOf` | `IsVersionOf` |
| `hasVersion` | `hasVersion` | `HasVersion` | `HasVersion` |
| `isFormatOf` | `isFormatOf` | *omitted* | *no `type`* |
| `hasFormat` | `hasFormat` | *omitted* | *no `type`* |

- **Crossref XML** (`export/crossref.phtml`): `RelationshipMapper::toCrossrefOrDefault()` of the stored value, so that
  a legacy row holding a DataCite type is still exported; `references` when the relation is absent or unknown.
- **OpenAIRE 4.0**: the `<datacite:relatedIdentifier>` is omitted when there is no accepted type.
- **HAL TEI**: the `<relatedItem>` is kept, without its `type` attribute, rather than with a value of another
  vocabulary. `Episciences\Repositories\HAL\TeiLinkedDataParser` reads it back with its fallback (`references`).

### Export Profiles

`Episciences\Paper\RelationshipProfile` lists the DataCite types each output accepts:

| Profile | Used by | Vocabulary |
| :--- | :--- | :--- |
| `RelationshipProfile::OpenAire` | `export/datacite.phtml` (OpenAIRE Guidelines for Literature Repositories 4.0, `openaire.xsd`) | DataCite 4.1 (31 types) |
| `RelationshipProfile::DataCite` (default) | `Episciences_Paper_Tei` (HAL TEI) | DataCite 4.6 |

The types added after DataCite 4.1 (`IsObsoletedBy`, `Obsoletes`, `IsCollectedBy`, `Collects`, `IsPublishedIn`,
`IsTranslationOf`, `HasTranslation`) are not in `openaire.xsd`: exporting them would make the OpenAIRE record invalid.
`IsRelatedTo` is in neither profile.

## Write Paths

| Source | Entry point | Conversion |
| :--- | :--- | :--- |
| HAL (TEI of the search API) | `TeiLinkedDataParser::relationship()`, called by `TeiLinkedDataParser::parse()` | `fromDataCite()` |
| DataCite shaped repositories (Zenodo, ARCHE, BAOBAB, DSpace), HAL TEI entries | `Episciences_Submit::processDatasets()` (also `forceAddingDatasets()` for the DOIs whose metadata lookup failed) | `fromDataCite()` |
| Scholexplorer (`enrichment:links`) | `GetLinkDataCommand::insertLink()`; `ScholexplorerApiClient::RELATIONSHIP_INVERSES` inverts the Scholix type first, when the paper is the link target | `fromDataCite()` with the `isRelatedMaterial` fallback |
| zbMATH Open (`enrichment:zb-reviews`) | `GetZbReviewsCommand` | always `hasReview` |
| Manual entry | `AdministratelinkeddataController::addldAction()` / `setnewinfoldAction()` | `toCrossref()`; the request is rejected when it gives `null` |

## Adding or Changing a Relation

- [ ] Add it to `Episciences\Paper\Relationship` (and to `$hiddenRelations` if it must not be offered in the form).
- [ ] Add the translation key (same name) to `application/languages/en/views.php` and `application/languages/fr/views.php`.
- [ ] If a DataCite type maps to it under another name, add it to `RelationshipMapper::DATACITE_TO_CROSSREF`;
      if its `ucfirst()` is not a DataCite type but an equivalent exists, add it to `CROSSREF_TO_DATACITE`.
- [ ] Check the profile lists of `RelationshipProfile` if a new DataCite version is adopted by an export.
- [ ] Update `tests/unit/library/Episciences/paper/RelationshipMapperTest.php` and the tables of this document.
- [ ] Write a migration in `src/mysql/` when existing rows must change (see below).

## Legacy Data

Rows stored before the normalization may hold DataCite types. `src/mysql/2026-11-22-normalize-paper-datasets-relationships.sql`
converts them with the same mapping (case-sensitive comparisons, `utf8mb4_bin`, so that running it twice changes
nothing) and lists the affected DOCIDs. For these DOCIDs, then:

1. rebuild `PAPERS.DOCUMENT`, built from the Crossref export:
   `php scripts/console.php papers:update-document --sqlwhere "DOCID IN (...)"`;
2. reindex them in Solr: `php scripts/solr/solrJob.php -e <env> -c episciences --sqlwhere "DOCID IN (...)"`.

The cached exports of these papers (`Episciences_Paper::get()`: Crossref, OpenAIRE, TEI) are rebuilt when their cache
expires (31 days for a published paper).

Values without equivalent (`HasMetadata`, `IsCollectedBy`, ...) are left unchanged: the Crossref export gives them as
`references` and the OpenAIRE export leaves them out.
