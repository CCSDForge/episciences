# Paper Comment Types (Episciences_CommentsManager)

This document describes the various comment and interaction types available in the Episciences platform, as defined in `library/Episciences/CommentsManager.php`.

Comments in Episciences represent more than simple text notes; they are structured lifecycle events exchanged between authors, reviewers, and editors during review and copy-editing phases.

## Type Mapping Table

| Code (Integer) | Constant Name | Description (English) | Description (French) | Typical Actors |
| :--- | :--- | :--- | :--- | :--- |
| **0** | `TYPE_INFO_REQUEST` | Clarification request | Demande d'éclaircissements | Reviewer → Author |
| **1** | `TYPE_INFO_ANSWER` | Response to clarification request | Réponse à une demande d'éclaircissements | Author → Reviewer |
| **2** | `TYPE_REVISION_REQUEST` | Revision request | Demande de modifications | Editor → Author |
| **3** | `TYPE_REVISION_ANSWER_COMMENT` | Revision answer (comment only) | Réponse à une demande de modifications (commentaire) | Author → Editor |
| **4** | `TYPE_AUTHOR_COMMENT` | Author comment / Cover letter | Commentaire de l'auteur / lettre d'accompagnement | Author → Journal |
| **5** | `TYPE_REVISION_CONTACT_COMMENT` | Revision answer without new file upload | Réponse à une demande de modifications (sans dépôt de version) | Author → Editor |
| **6** | `TYPE_REVISION_ANSWER_TMP_VERSION` | Revision answer with temporary version | Réponse à une demande de modifications (version temporaire) | Author → Editor |
| **7** | `TYPE_REVISION_ANSWER_NEW_VERSION` | Revision answer with new version | Réponse à une demande de modifications (nouvelle version) | Author → Editor |
| **8** | `TYPE_SUGGESTION_ACCEPTATION` | Recommendation: Accept submission | Suggestion d'acceptation du papier | Reviewer → Editor |
| **9** | `TYPE_SUGGESTION_REFUS` | Recommendation: Reject submission | Suggestion de refus du papier | Reviewer → Editor |
| **10** | `TYPE_SUGGESTION_NEW_VERSION` | Recommendation: Request revisions | Suggestion de demande de modifications du papier | Reviewer → Editor |
| **11** | `TYPE_CONTRIBUTOR_TO_REVIEWER` | Contributor comment to reviewer | Commentaire du contributeur au relecteur | Author → Reviewer |
| **12** | `TYPE_EDITOR_COMMENT` | Editor comment | Commentaire du rédacteur | Editor → Journal / Internal |
| **13** | `TYPE_EDITOR_MONITORING_REFUSED` | Refusal to manage / supervise submission | Refus d'assurer le suivi | Editor → Board |
| **14** | `TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER` | Copy editing: Author sources deposited | Préparation de copie : sources déposées | Author → Copy Editor |
| **15** | `TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST` | Copy editing: Awaiting author layout/formatting | Préparation de copie : en attente de la mise en forme par l'auteur | Copy Editor → Author |
| **16** | `TYPE_AUTHOR_FORMATTING_ANSWER` | Copy editing: Final formatted version submitted for validation | Préparation de copie : version finale déposée, en attente de validation | Author → Copy Editor |
| **17** | `TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST` | Copy editing: Layout validated, awaiting definitive release | Préparation de copie : version formatée validée, en attente de la version définitive | Copy Editor → Author |
| **18** | `TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST` | Copy editing: Journal layout complete, awaiting final version | Préparation de copie : mise en forme par la revue terminée, en attente de la version finale | Copy Editor → Author |
| **19** | `TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED` | Copy editing: Final version submitted | Préparation de copie : version finale soumise | Author → Copy Editor |
| **20** | `TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST` | Copy editing: Awaiting author sources | Préparation de copie : en attente des sources auteurs | Copy Editor → Author |
| **21** | `TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION` | Accepted: Awaiting author validation | Accepté - en attente de validation par l'auteur | Editor → Author |
| **22** | `TYPE_AUTHOR_TO_EDITOR` | Author message to assigned editors | Message de l'auteur aux rédacteurs assignés | Author → Editor |
| **23** | `TYPE_EDITOR_TO_AUTHOR` | Editor message to author | Message du rédacteur à l'auteur | Editor → Author |

## Functional Categorization

### 1. Peer Review & Clarification
Used during the reviewing stage for questions/answers between reviewers and authors:
- `TYPE_INFO_REQUEST` (0)
- `TYPE_INFO_ANSWER` (1)
- `TYPE_CONTRIBUTOR_TO_REVIEWER` (11)

### 2. Revision Handling
Used when an editor requests revisions and when authors submit modified papers or feedback:
- `TYPE_REVISION_REQUEST` (2)
- `TYPE_REVISION_ANSWER_COMMENT` (3)
- `TYPE_REVISION_CONTACT_COMMENT` (5)
- `TYPE_REVISION_ANSWER_TMP_VERSION` (6)
- `TYPE_REVISION_ANSWER_NEW_VERSION` (7)

### 3. Reviewer Recommendations
Internal suggestions sent by reviewers to editors alongside their evaluation:
- Grouped in `Episciences_CommentsManager::$suggestionTypes`:
  - `TYPE_SUGGESTION_ACCEPTATION` (8)
  - `TYPE_SUGGESTION_REFUS` (9)
  - `TYPE_SUGGESTION_NEW_VERSION` (10)

### 4. Direct Messages & Editorial Tracking
Direct communication and assignment handling:
- `TYPE_AUTHOR_COMMENT` (4): Submission cover letter or general note from author.
- `TYPE_EDITOR_COMMENT` (12): Notes made by editors.
- `TYPE_EDITOR_MONITORING_REFUSED` (13): Editor declines assignment.
- `TYPE_AUTHOR_TO_EDITOR` (22) / `TYPE_EDITOR_TO_AUTHOR` (23): Threaded messaging between author and assigned editors.

### 5. Copy-Editing Workflow (Post-Acceptance)
Tracks source file deposits, journal or author formatting, and final validation:
- **Requests from journal / copy editor** (`$_copyEditingRequestTypes`):
  - `TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST` (20)
  - `TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST` (15)
  - `TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST` (17)
  - `TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST` (18)
- **Responses from author** (`$_copyEditingAnswerTypes`):
  - `TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER` (14)
  - `TYPE_AUTHOR_FORMATTING_ANSWER` (16)
  - `TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED` (19)
- **Validation**:
  - `TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION` (21)

## Technical Details

- **Classes**:
  - `Episciences_CommentsManager` (`library/Episciences/CommentsManager.php`)
  - `Episciences_Comment` (`library/Episciences/Comment.php`)
- **Database Table**: `paper_comments` (`T_PAPER_COMMENTS`)
- **Key Columns**:
  - `PCID`: Primary key of the comment.
  - `DOCID`: Target paper document ID.
  - `PARENTID`: Parent comment ID for threading/replies.
  - `UID`: Author user ID.
  - `TYPE`: Integer corresponding to the comment type code above.
  - `MESSAGE`: Comment body text.
  - `FILE`: Attached file path (if any).
  - `WHEN`: Timestamp of creation.
