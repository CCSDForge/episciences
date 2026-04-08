# Document Statuses (Episciences_Paper)

This document lists the various statuses a paper can have in the Episciences platform, as defined in `library/Episciences/Paper.php`.

## Status Mapping Table

| Code (Integer) | Constant Name | Status Text (English) | Description (English) | Description (French) |
| :--- | :--- | :--- | :--- | :--- |
| **0** | `STATUS_SUBMITTED` | submitted | submitted | soumis |
| **1** | `STATUS_OK_FOR_REVIEWING` | waitingForReviewing | waiting for review | en attente de relecture |
| **2** | `STATUS_BEING_REVIEWED` | underReview | under review | en cours de relecture |
| **3** | `STATUS_REVIEWED` | reviewed pending editorial decision | reviewed pending editorial decision | évalué - en attente de décision éditoriale |
| **4** | `STATUS_ACCEPTED` | accepted | accepted | accepté |
| **5** | `STATUS_REFUSED` | refused | refused | refusé |
| **6** | `STATUS_OBSOLETE` | obsolete | obsolete | obsolète |
| **7** | `STATUS_WAITING_FOR_MINOR_REVISION` | pendingMinorRevision | pending minor revision | en attente de modifications mineures |
| **8** | `STATUS_WAITING_FOR_COMMENTS` | pendingClarification | pending clarification | en attente d'éclaircissements |
| **9** | `STATUS_TMP_VERSION` | temporaryVersion | temporary version | version temporaire |
| **10** | `STATUS_NO_REVISION` | revisionRequestAnswerWithoutAnyModifications | revision request answer: without any modifications | réponse à une demande de modifications : pas de modifications |
| **11** | `STATUS_NEW_VERSION` | answerToRevisionRequestNewVersion | revision request answer (new version) | réponse à une demande de modifications: nouvelle version |
| **12** | `STATUS_DELETED` | deleted | deleted | supprimé |
| **13** | `STATUS_REMOVED` | deletedByTheJournal | deleted by the Journal | supprimé par la revue |
| **14** | `STATUS_REVIEWERS_INVITED` | - | reviewers have been invited, but no one has accepted yet | relecteurs invités, aucun n'a encore accepté |
| **15** | `STATUS_WAITING_FOR_MAJOR_REVISION` | pendingMajorRevision | pending major revision | en attente de modifications majeures |
| **16** | `STATUS_PUBLISHED` | published | published | publié |
| **17** | `STATUS_ABANDONED` | abandoned | abandoned | abandonné |
| **18** | `STATUS_CE_WAITING_FOR_AUTHOR_SOURCES` | waitingForAuthorsSources | Copy ed.: waiting for author's sources | copy ed : en attente des sources auteurs |
| **19** | `STATUS_CE_AUTHOR_SOURCES_DEPOSED` | waitingForFormattingByTheJournal | Copy ed.: waiting for formatting by the journal | copy ed. : en attente de la mise en forme par la revue |
| **20** | `STATUS_CE_REVIEW_FORMATTING_DEPOSED` | formattingByJournalCompletedWaitingForAFinalVersion | Copy ed.: formatting by journal completed, waiting for a final version | copy ed : mise en forme par la revue terminée, en attente de la version finale |
| **21** | `STATUS_CE_WAITING_AUTHOR_FINAL_VERSION` | waitingForAuthorsFinalVersion | Copy ed.: waiting for author's final version | copy ed : en attente de la version finale auteur |
| **22** | `STATUS_CE_AUTHOR_FINAL_VERSION_DEPOSED` | finalVersionSubmittedWaitingForValidation | Copy ed.: final version submitted, waiting for validation | copy ed : version finale déposée en attente de validation |
| **23** | `STATUS_CE_READY_TO_PUBLISH` | readyToPublish | Copy ed.: ready to publish | copy ed : prêt à publier |
| **24** | `STATUS_CE_AUTHOR_FORMATTING_DEPOSED` | formattingByAuthorCompletedWaitingForFinalVersion | Copy ed.: formatting by author completed, waiting for final version | copy ed : mise en forme par l'auteur terminée, en attente de la version finale |
| **25** | `STATUS_TMP_VERSION_ACCEPTED` | acceptedTemporaryVersionWaitingForAuthorsFinalVersion | accepted temporary version, waiting for author's final version | version temporaire acceptée, en attente de la version finale |
| **26** | `STATUS_ACCEPTED_WAITING_FOR_AUTHOR_FINAL_VERSION` | acceptedWaitingForAuthorsFinalVersion | accepted - waiting for author's final version | accepté - en attente de la version finale de l'auteur |
| **27** | `STATUS_ACCEPTED_WAITING_FOR_MAJOR_REVISION` | acceptedWaitingForMajorRevision | accepted, waiting for major revision | accepté, en attente de modifications majeures |
| **28** | `STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING` | acceptedFinalVersionSubmittedWaitingForFormattingByCopyEditors | Accepted - final version submitted, waiting for formatting by copy editors | Accepté - version finale soumise, en attente de la mise en forme par la revue |
| **29** | `STATUS_TMP_VERSION_ACCEPTED_AFTER_AUTHOR_MODIFICATION` | acceptedTemporaryVersionAfterAuthorsModifications | accepted temporary version after author's modifications | version temporaire acceptée après modification de l'auteur |
| **30** | `STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MINOR_REVISION` | acceptedTemporaryVersionWaitingForMinorRevision | accepted temporary version, waiting for minor revision | version temporaire acceptée, en attente des modifications mineures |
| **31** | `STATUS_TMP_VERSION_ACCEPTED_WAITING_FOR_MAJOR_REVISION` | acceptedTemporaryVersionWaitingForMajorRevision | accepted temporary version, waiting for major revision | version temporaire acceptée, en attente des modifications majeures |
| **32** | `STATUS_ACCEPTED_WAITING_FOR_AUTHOR_VALIDATION` | AcceptedWaitingForAuthorsValidation | Accepted - waiting for author's validation | accepté - en attente de validation par l'auteur |
| **33** | `STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION` | AcceptedWaitingForFinalPublication | Accepted - waiting for final publication | approuvé - en attente de publication |

## Technical Details

- **Class**: `Episciences_Paper`
- **File**: `library/Episciences/Paper.php`
- **Database Table**: `papers` (column `STATUS`)

### Related Constants
- `STATUS_CODES`: List of statuses used in search filters.
- `OTHER_STATUS_CODE`: Statuses not present in search filters (Obsolete, Tmp, Deleted, etc.).
- `EDITABLE_VERSION_STATUS`: Statuses where the version is considered editable.
- `ACCEPTED_SUBMISSIONS`: Statuses that imply the submission has been accepted (including copy-editing phases).
