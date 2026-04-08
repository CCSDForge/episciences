# Log Event Types (Episciences_Paper_Logger)

This document lists the various log event types used in the Episciences platform to track actions performed on papers, as defined in `library/Episciences/Paper/Logger.php`.

## Log Event Mapping Table

| Code (String) | Constant Name | Description (English) | Description (French) | CSS Class |
| :--- | :--- | :--- | :--- | :--- |
| `status` | `CODE_STATUS` | New status | Nouveau statut | success |
| `restoration_of_status` | `CODE_RESTORATION_OF_STATUS` | Restoration of status | Restauration du statut | success |
| `editor_assignment` | `CODE_EDITOR_ASSIGNMENT` | Editor assignment | Assignation d'un rédacteur | warning |
| `editor_unassignment` | `CODE_EDITOR_UNASSIGNMENT` | Editor unassignment | Désassignation d'un rédacteur | warning |
| `reviewer_invitation` | `CODE_REVIEWER_INVITATION` | Reviewer invitation | Invitation d'un relecteur | warning |
| `reviewer_invitation_accepted` | `CODE_REVIEWER_INVITATION_ACCEPTED` | Accepted invitation | Invitation de relecture acceptée | warning |
| `reviewer_invitation_declined` | `CODE_REVIEWER_INVITATION_DECLINED` | Declined invitation | Invitation de relecture refusée | warning |
| `reviewer_assignment` | `CODE_REVIEWER_ASSIGNMENT` | Reviewer assignment | Assignation d'un relecteur | warning |
| `reviewer_unassignment` | `CODE_REVIEWER_UNASSIGNMENT` | Reviewer unassignment | Suppression d'un relecteur | warning |
| `reviewing_in_progress` | `CODE_REVIEWING_IN_PROGRESS` | Ongoing review | Relecture en cours | warning |
| `reviewing_completed` | `CODE_REVIEWING_COMPLETED` | Review Completed | Relecture terminée | warning |
| `mail_sent` | `CODE_MAIL_SENT` | E-mail sent | Envoi d'un e-mail | info |
| `reminder_sent` | `CODE_REMINDER_SENT` | Reminder sent | Envoi d'une relance automatique | info |
| `volume_selection` | `CODE_VOLUME_SELECTION` | Moved to a volume | Déplacé dans un volume | violet |
| `other_volumes_selection` | `CODE_OTHER_VOLUMES_SELECTION` | Secondary volumes assignment | Sélection des volumes secondaires | violet |
| `section_selection` | `CODE_SECTION_SELECTION` | Moved to a section | Déplacé dans une rubrique | violet |
| `minor_revision_request` | `CODE_MINOR_REVISION_REQUEST` | Minor revision request | Demande de modifications mineures | violet |
| `major_revision_request` | `CODE_MAJOR_REVISION_REQUEST` | Major revision request | Demande de modifications majeures | violet |
| `revision_request_answer` | `CODE_REVISION_REQUEST_ANSWER` | Revision request answer (without any modifications) | Réponse à une demande de modifications | violet |
| `revision_request_new_version` | `CODE_REVISION_REQUEST_NEW_VERSION` | Revision request answer (new version) | Réponse à une demande de modifications (nouvelle version) | violet |
| `revision_request_tmp_version` | `CODE_REVISION_REQUEST_TMP_VERSION` | Revision request answer (temporary version) | Réponse à une demande de modifications (version temporaire) | violet |
| `alter_report_status` | `CODE_ALTER_REPORT_STATUS` | Permission to change the reviewing by | - | warning |
| `monitoring_refused` | `CODE_MONITORING_REFUSED` | Handling of article refused | Ne plus gérer l'article | danger |
| `abandon_publication_process` | `CODE_ABANDON_PUBLICATION_PROCESS` | Abandon publication process | Abandonner le processus de publication | danger |
| `continue_publication_process` | `CODE_CONTINUE_PUBLICATION_PROCESS` | - | Reprendre le processus de publication | warning |
| `copy_editor_assignment` | `CODE_COPY_EDITOR_ASSIGNMENT` | Copy editor assignment | Assignation d'un préparateur de copie | warning |
| `copy_editor_unassignment` | `CODE_COPY_EDITOR_UNASSIGNMENT` | Copy editor unassignment | Désassignation d'un préparateur de copie | warning |
| `copy_editing_author_sources_request` | `CODE_CE_AUTHOR_SOURCES_REQUEST` | Copy editing (waiting for authors sources) | - | violet |
| `copy_editing_author_sources_deposed` | `CODE_CE_AUTHOR_SOURCES_DEPOSED` | Copy editing (sources submitted) | - | violet |
| `copy_editing_author_finale_version_request` | `CODE_CE_AUTHOR_FINALE_VERSION_REQUEST` | Copy ed. : Pending a final author version | - | violet |
| `copy_editing_author_finale_version_deposed` | `CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED` | Copy ed. : final version submitted | - | violet |
| `copy_editing_ready_to_publish` | `CODE_CE_READY_TO_PUBLISH` | Copy ed. : final version validated | - | violet |
| `copy_editing_review_formatting_deposed` | `CODE_CE_REVIEW_FORMATTING_DEPOSED` | Copy ed. formatting review completed | - | violet |
| `new_paper_comment` | `CODE_NEW_PAPER_COMMENT` | New comment | Nouveau commentaire | violet |
| `copy_editing_author_final_version_submitted` | `CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED` | Copy ed. final version submitted (new version) | - | violet |
| `author_comment_cover_letter` | `CODE_AUTHOR_COMMENT_COVER_LETTER` | Author comment / Cover letter | - | primary |
| `editor_comment` | `CODE_EDITOR_COMMENT` | Editor comment | - | violet |
| `paper_comment_form_reviewer_to_contributor` | `CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR` | Clarification request (reviewer to contributor) | Demande d'éclaircissements (relecteur au contributeur) | violet |
| `paper_comment_form_contributor_to_reviewer` | `CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER` | Clarification answer (contributor to reviewer) | Réponse à une demande d'éclaircissement | violet |
| `paper_updated` | `CODE_PAPER_UPDATED` | Update | - | warning |
| `paper_alter_publication_date` | `CODE_ALTER_PUBLICATION_DATE` | New publication date | - | warning |
| `doi_assigned` | `CODE_DOI_ASSIGNED` | DOI assignment | DOI assigné | info |
| `doi_updated` | `CODE_DOI_UPDATED` | DOI Updated | - | info |
| `doi_canceled` | `CODE_DOI_CANCELED` | DOI canceled | DOI Annulé | info |
| `coi_reported` | `CODE_COI_REPORTED` | Conflict Of Interest (COI) | Conflit d'intérêts (CI) | danger |
| `coi_reverted` | `CODE_COI_REVERTED` | Conflict Of Interest (COI): cancelled | Conflit d'intérêts (CI) : annulé | success |
| `accepted_ask_authors_final_version` | `CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION` | Accepted, ask author's final version | Accepté, demande de la version finale à l'auteur | violet |
| `accepted_ask_for_author_validation` | `CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION` | Accepted, waiting for authors validation | Accepté, en attente de validation par l'auteur | violet |
| `version_repository_updated` | `CODE_VERSION_REPOSITORY_UPDATED` | Version number updated | Numéro de version mis à jour | info |
| `new_reviewing_deadline` | `CODE_NEW_REVIEWING_DEADLINE` | New deadline for review | Nouvelle date limite de rendu de relecture | warning |
| `coar_notify_review` | `CODE_INBOX_COAR_NOTIFY_REVIEW` | New submission: automatically transferred from | Nouvelle soumission : transférée automatiquement depuis | info |
| `ld_added` | `CODE_LD_ADDED` | Related work added | Ajout d'une donnée liée | info |
| `ld_changed` | `CODE_LD_CHANGED` | Related work changed | Changement d'une donnée liée | info |
| `ld_remove` | `CODE_LD_REMOVED` | Related work removed | Suppression d'une donnée liée | info |
| `paper_imported` | `CODE_DOCUMENT_IMPORTED` | The document has been imported | Le document a été importé | info |
| `dd_uploaded` | `CODE_DD_UPLOADED` | Data descriptor uploaded | Descripteur de données chargé | info |
| `swd_uploaded` | `CODE_SWD_UPLOADED` | Software descriptor uploaded | Descripteur de logiciel chargé | info |
| `revision_deadline_updated` | `CODE_REVISION_DEADLINE_UPDATED` | New revision deadline | - | warning |

## Technical Details

- **Class**: `Episciences_Paper_Logger`
- **File**: `library/Episciences/Paper/Logger.php`
- **Database Table**: `logs` (column `ACTION`)

### Log Detail
Some actions include additional information in the `DETAIL` column (JSON encoded).

### CSS Classes
The CSS classes are used in the management interface to colorize the log entries:
- `success`: Green
- `warning`: Orange
- `info`: Blue
- `violet`: Purple
- `danger`: Red
- `primary`: Dark Blue
