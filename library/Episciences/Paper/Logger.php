<?php

class Episciences_Paper_Logger
{
    public const CODE_STATUS = 'status';
    public const CODE_RESTORATION_OF_STATUS = 'restoration_of_status';
    public const CODE_EDITOR_ASSIGNMENT = 'editor_assignment';
    public const CODE_EDITOR_UNASSIGNMENT = 'editor_unassignment';
    public const CODE_REVIEWER_INVITATION = 'reviewer_invitation';
    public const CODE_REVIEWER_INVITATION_ACCEPTED = 'reviewer_invitation_accepted';
    public const CODE_REVIEWER_INVITATION_DECLINED = 'reviewer_invitation_declined';
    public const CODE_REVIEWER_ASSIGNMENT = 'reviewer_assignment';
    public const CODE_REVIEWER_UNASSIGNMENT = 'reviewer_unassignment';
    public const CODE_REVIEWING_IN_PROGRESS = 'reviewing_in_progress';
    public const CODE_REVIEWING_COMPLETED = 'reviewing_completed';
    public const CODE_MAIL_SENT = 'mail_sent';
    public const CODE_REMINDER_SENT = 'reminder_sent';
    // master volume selection
    public const CODE_VOLUME_SELECTION = 'volume_selection';
    // secondary volume selection
    public const CODE_OTHER_VOLUMES_SELECTION = 'other_volumes_selection';
    public const CODE_SECTION_SELECTION = 'section_selection';
    public const CODE_MINOR_REVISION_REQUEST = 'minor_revision_request';
    public const CODE_MAJOR_REVISION_REQUEST = 'major_revision_request';
    // revision request answer : comment
    public const CODE_REVISION_REQUEST_ANSWER = 'revision_request_answer';
    // revision request answer : new version
    public const CODE_REVISION_REQUEST_NEW_VERSION = 'revision_request_new_version';
    // revision request answer : tmp version
    public const CODE_REVISION_REQUEST_TMP_VERSION = 'revision_request_tmp_version';
    // alter report status
    public const CODE_ALTER_REPORT_STATUS = 'alter_report_status';
    public const CODE_MONITORING_REFUSED = 'monitoring_refused';
    // Abandon publication process
    public const CODE_ABANDON_PUBLICATION_PROCESS = 'abandon_publication_process';
    // Continue publication process
    public const CODE_CONTINUE_PUBLICATION_PROCESS = 'continue_publication_process';
    // COPY EDITOR
    public const CODE_COPY_EDITOR_ASSIGNMENT = 'copy_editor_assignment';
    public const CODE_COPY_EDITOR_UNASSIGNMENT = 'copy_editor_unassignment';

    // copy editing author sources request
    public const CODE_CE_AUTHOR_SOURCES_REQUEST = 'copy_editing_author_sources_request';
    public const CODE_CE_AUTHOR_SOURCES_DEPOSED = 'copy_editing_author_sources_deposed';
    public const CODE_CE_AUTHOR_FINALE_VERSION_REQUEST = 'copy_editing_author_finale_version_request';
    public const CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED = 'copy_editing_author_finale_version_deposed';
    public const CODE_CE_READY_TO_PUBLISH = 'copy_editing_ready_to_publish';
    public const CODE_CE_REVIEW_FORMATTING_DEPOSED = 'copy_editing_review_formatting_deposed';
    // new paper comment
    public const CODE_NEW_PAPER_COMMENT = 'new_paper_comment';
    public const CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED = 'copy_editing_author_final_version_submitted';
    public const CODE_AUTHOR_COMMENT_COVER_LETTER = "author_comment_cover_letter";
    public const CODE_EDITOR_COMMENT = "editor_comment";
    public const CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR = "paper_comment_form_reviewer_to_contributor";
    public const CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER = "paper_comment_form_contributor_to_reviewer";
    public const CODE_PAPER_COMMENT_FROM_AUTHOR_TO_EDITOR = "paper_comment_from_author_to_editor";
    public const CODE_PAPER_COMMENT_FROM_EDITOR_TO_AUTHOR = "paper_comment_from_editor_to_author";
    public const CODE_PAPER_UPDATED = "paper_updated";
    // https://github.com/CCSDForge/episciences/issues/50
    public const CODE_ALTER_PUBLICATION_DATE = "paper_alter_publication_date";

    public const CODE_DOI_ASSIGNED = 'doi_assigned';
    public const CODE_DOI_UPDATED = 'doi_updated';

    public const CODE_DOI_CANCELED = 'doi_canceled';

    public const CODE_COI_REPORTED = "coi_reported";
    public const CODE_COI_REVERTED = "coi_reverted";

    public const CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION = "accepted_ask_authors_final_version";
    public const CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION = "accepted_ask_for_author_validation";

    public const CODE_VERSION_REPOSITORY_UPDATED = "version_repository_updated";
    public const CODE_NEW_REVIEWING_DEADLINE = 'new_reviewing_deadline';
    public const CODE_INBOX_COAR_NOTIFY_REVIEW = 'coar_notify_review';
    public const CODE_LD_ADDED = 'ld_added';
    public const CODE_DOCUMENT_IMPORTED = 'paper_imported';
    public const CODE_LD_CHANGED = 'ld_changed';
    public const CODE_LD_REMOVED = 'ld_remove'; // legacy DB value: stored as 'ld_remove' (trailing 'd' missing)
    // alert-
    public const WARNING = 'warning';
    public const INFO = 'info';

    public const VIOLET = 'violet';
    public const SUCCESS = 'success';
    public const DANGER = 'danger';
    public const PRIMARY = 'primary';
    public const CODE_DD_UPLOADED = 'dd_uploaded';
    public const CODE_SWD_UPLOADED = 'swd_uploaded';


    public const CODE_REVISION_DEADLINE_UPDATED = 'revision_deadline_updated';


    // log type css class — sorted alphabetically by key (constant value)
    public static array $_css = [
        self::CODE_ABANDON_PUBLICATION_PROCESS          => self::DANGER,
        self::CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION   => self::VIOLET,
        self::CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION   => self::VIOLET,
        self::CODE_ALTER_REPORT_STATUS                  => self::WARNING,
        self::CODE_AUTHOR_COMMENT_COVER_LETTER          => self::PRIMARY,
        self::CODE_INBOX_COAR_NOTIFY_REVIEW             => self::INFO,
        self::CODE_COI_REPORTED                         => self::DANGER,
        self::CODE_COI_REVERTED                         => self::SUCCESS,
        self::CODE_CONTINUE_PUBLICATION_PROCESS         => self::WARNING,
        self::CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED    => self::VIOLET,
        self::CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED     => self::VIOLET,
        self::CODE_CE_AUTHOR_FINALE_VERSION_REQUEST     => self::VIOLET,
        self::CODE_CE_AUTHOR_SOURCES_DEPOSED            => self::VIOLET,
        self::CODE_CE_AUTHOR_SOURCES_REQUEST            => self::VIOLET,
        self::CODE_CE_READY_TO_PUBLISH                  => self::VIOLET,
        self::CODE_CE_REVIEW_FORMATTING_DEPOSED         => self::VIOLET,
        self::CODE_COPY_EDITOR_ASSIGNMENT               => self::WARNING,
        self::CODE_COPY_EDITOR_UNASSIGNMENT             => self::WARNING,
        self::CODE_DD_UPLOADED                          => self::INFO,
        self::CODE_DOI_ASSIGNED                         => self::INFO,
        self::CODE_DOI_CANCELED                         => self::INFO,
        self::CODE_DOI_UPDATED                          => self::INFO,
        self::CODE_EDITOR_ASSIGNMENT                    => self::WARNING,
        self::CODE_EDITOR_COMMENT                       => self::VIOLET,
        self::CODE_EDITOR_UNASSIGNMENT                  => self::WARNING,
        self::CODE_LD_ADDED                             => self::INFO,
        self::CODE_LD_CHANGED                           => self::INFO,
        self::CODE_LD_REMOVED                           => self::INFO,
        self::CODE_MAIL_SENT                            => self::INFO,
        self::CODE_MAJOR_REVISION_REQUEST               => self::VIOLET,
        self::CODE_MINOR_REVISION_REQUEST               => self::VIOLET,
        self::CODE_MONITORING_REFUSED                   => self::DANGER,
        self::CODE_NEW_PAPER_COMMENT                    => self::VIOLET,
        self::CODE_NEW_REVIEWING_DEADLINE               => self::WARNING,
        self::CODE_OTHER_VOLUMES_SELECTION              => self::VIOLET,
        self::CODE_ALTER_PUBLICATION_DATE               => self::WARNING,
        self::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER => self::VIOLET,
        self::CODE_PAPER_COMMENT_FROM_AUTHOR_TO_EDITOR => self::VIOLET,
        self::CODE_PAPER_COMMENT_FROM_EDITOR_TO_AUTHOR => self::VIOLET,
        self::CODE_DOI_ASSIGNED => self::INFO,
        self::CODE_DOI_UPDATED => self::INFO,
        self::CODE_DOI_CANCELED => self::INFO,
        self::CODE_ALTER_PUBLICATION_DATE => self::WARNING,
        self::CODE_COI_REPORTED => self::DANGER,
        self::CODE_COI_REVERTED => self::SUCCESS,
        self::CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION => self::VIOLET,
        self::CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION => self::VIOLET,
        self::CODE_VERSION_REPOSITORY_UPDATED => self::INFO,
        self::CODE_NEW_REVIEWING_DEADLINE => self::WARNING,
        self::CODE_INBOX_COAR_NOTIFY_REVIEW => self::INFO,
        self::CODE_LD_ADDED => self::INFO,
        self::CODE_LD_REMOVED => self::INFO,
        self::CODE_REVISION_DEADLINE_UPDATED => self::WARNING,
        self::CODE_DOCUMENT_IMPORTED => self::INFO,
        self::CODE_LD_CHANGED => self::INFO,
        self::CODE_DD_UPLOADED => self::INFO,
        self::CODE_SWD_UPLOADED => self::INFO,

        self::CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR => self::VIOLET,
        self::CODE_DOCUMENT_IMPORTED                    => self::INFO,
        self::CODE_PAPER_UPDATED                        => self::WARNING,
        self::CODE_REMINDER_SENT                        => self::INFO,
        self::CODE_RESTORATION_OF_STATUS               => self::SUCCESS,
        self::CODE_REVISION_DEADLINE_UPDATED            => self::WARNING,
        self::CODE_REVISION_REQUEST_ANSWER              => self::VIOLET,
        self::CODE_REVISION_REQUEST_NEW_VERSION         => self::VIOLET,
        self::CODE_REVISION_REQUEST_TMP_VERSION         => self::VIOLET,
        self::CODE_REVIEWER_ASSIGNMENT                  => self::WARNING,
        self::CODE_REVIEWER_INVITATION                  => self::WARNING,
        self::CODE_REVIEWER_INVITATION_ACCEPTED         => self::WARNING,
        self::CODE_REVIEWER_INVITATION_DECLINED         => self::WARNING,
        self::CODE_REVIEWER_UNASSIGNMENT                => self::WARNING,
        self::CODE_REVIEWING_COMPLETED                  => self::WARNING,
        self::CODE_REVIEWING_IN_PROGRESS                => self::WARNING,
        self::CODE_SECTION_SELECTION                    => self::VIOLET,
        self::CODE_STATUS                               => self::SUCCESS,
        self::CODE_SWD_UPLOADED                         => self::INFO,
        self::CODE_VERSION_REPOSITORY_UPDATED           => self::INFO,
        self::CODE_VOLUME_SELECTION                     => self::VIOLET,
    ];

    // log type labels — sorted alphabetically by key (constant value)
    public static array $_label = [
        self::CODE_ABANDON_PUBLICATION_PROCESS          => "Abandon du processus de publication",
        self::CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION   => "Accepté, demande de la version finale à l'auteur",
        self::CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION   => "Accepté, en attente de validation par l'auteur",
        self::CODE_ALTER_REPORT_STATUS                  => "Modification du statut du rapport",
        self::CODE_AUTHOR_COMMENT_COVER_LETTER          => "Lettre de présentation de l'auteur",
        self::CODE_INBOX_COAR_NOTIFY_REVIEW             => "Nouvelle soumission : transférée automatiquement depuis",
        self::CODE_COI_REPORTED                         => "Conflit d'intérêts (CI)",
        self::CODE_COI_REVERTED                         => "Conflit d'intérêts (CI) : annulé",
        self::CODE_CONTINUE_PUBLICATION_PROCESS         => "Reprise du processus de publication",
        self::CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED    => "Version finale soumise par l'auteur (édition)",
        self::CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED     => "Version finale de l'auteur déposée (édition)",
        self::CODE_CE_AUTHOR_FINALE_VERSION_REQUEST     => "Demande de la version finale à l'auteur (édition)",
        self::CODE_CE_AUTHOR_SOURCES_DEPOSED            => "Sources de l'auteur déposées (édition)",
        self::CODE_CE_AUTHOR_SOURCES_REQUEST            => "Demande des sources à l'auteur (édition)",
        self::CODE_CE_READY_TO_PUBLISH                  => "Prêt pour la publication (édition)",
        self::CODE_CE_REVIEW_FORMATTING_DEPOSED         => "Mise en forme déposée par la revue (édition)",
        self::CODE_COPY_EDITOR_ASSIGNMENT               => "Assignation d'un rédacteur copiste",
        self::CODE_COPY_EDITOR_UNASSIGNMENT             => "Désassignation d'un rédacteur copiste",
        self::CODE_DD_UPLOADED                          => 'Descripteur de données chargé',
        self::CODE_DOI_ASSIGNED                         => 'DOI assigné',
        self::CODE_DOI_CANCELED                         => 'DOI Annulé',
        self::CODE_DOI_UPDATED                          => 'DOI mis à jour',
        self::CODE_EDITOR_ASSIGNMENT                    => "Assignation d'un rédacteur",
        self::CODE_EDITOR_COMMENT                       => "Commentaire du rédacteur",
        self::CODE_EDITOR_UNASSIGNMENT                  => "Désassignation d'un rédacteur",
        self::CODE_LD_ADDED                             => "Ajout d'une donnée liée",
        self::CODE_LD_CHANGED                           => "Changement d'une donnée liée",
        self::CODE_LD_REMOVED                           => "Suppression d'une donnée liée",
        self::CODE_MAIL_SENT                            => "Envoi d'un e-mail",
        self::CODE_MAJOR_REVISION_REQUEST               => 'Demande de modifications majeures',
        self::CODE_MINOR_REVISION_REQUEST               => 'Demande de modifications mineures',
        self::CODE_MONITORING_REFUSED                   => "Ne plus gérer l'article",
        self::CODE_NEW_PAPER_COMMENT                    => 'Nouveau commentaire',
        self::CODE_NEW_REVIEWING_DEADLINE               => 'Nouvelle date limite de rendu de relecture',
        self::CODE_OTHER_VOLUMES_SELECTION              => "Sélection des volumes secondaires",
        self::CODE_ALTER_PUBLICATION_DATE               => "Modification de la date de publication",
        self::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER => "Réponse à une demande d'éclaircissement (contributeur au relecteur)",
        self::CODE_PAPER_COMMENT_FROM_AUTHOR_TO_EDITOR => "Message de l'auteur aux rédacteurs assignés",
        self::CODE_PAPER_COMMENT_FROM_EDITOR_TO_AUTHOR => "Réponse du rédacteur à l'auteur",
        self::CODE_DOI_ASSIGNED => 'DOI assigné',
        self::CODE_DOI_CANCELED => 'DOI Annulé',
        self::CODE_COI_REPORTED => "Conflit d'intérêts (CI)",
        self::CODE_COI_REVERTED => "Conflit d'intérêts (CI) : annulé",
        self::CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION => "Accepté, demande de la version finale à l'auteur",
        self::CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION => "Accepté, en attente de validation par l'auteur",
        self::CODE_VERSION_REPOSITORY_UPDATED => 'Numéro de version mis à jour',
        self::CODE_NEW_REVIEWING_DEADLINE => 'Nouvelle date limite de rendu de relecture',
        self::CODE_INBOX_COAR_NOTIFY_REVIEW => "Nouvelle soumission : transférée automatiquement depuis",
        self::CODE_LD_ADDED => "Ajout d'une donnée liée",
        self::CODE_LD_REMOVED => "Suppression d'une donnée liée",
        self::CODE_DOCUMENT_IMPORTED => "Le document a été importé",
        self::CODE_LD_CHANGED => "Changement d'une donnée liée",
        self::CODE_DD_UPLOADED => 'Descripteur de données chargé',
        self::CODE_SWD_UPLOADED=> 'Descripteur de logiciel chargé',
        self::CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR => "Demande d'éclaircissements (relecteur au contributeur)",
        self::CODE_DOCUMENT_IMPORTED                    => "Le document a été importé",
        self::CODE_PAPER_UPDATED                        => "Article mis à jour",
        self::CODE_REMINDER_SENT                        => "Envoi d'une relance automatique",
        self::CODE_RESTORATION_OF_STATUS               => "Restauration du statut",
        self::CODE_REVISION_DEADLINE_UPDATED            => "Date limite de révision mise à jour",
        self::CODE_REVISION_REQUEST_ANSWER              => 'Réponse à une demande de modifications',
        self::CODE_REVISION_REQUEST_NEW_VERSION         => 'Réponse à une demande de modifications (nouvelle version)',
        self::CODE_REVISION_REQUEST_TMP_VERSION         => 'Réponse à une demande de modifications (version temporaire)',
        self::CODE_REVIEWER_ASSIGNMENT                  => "Assignation d'un relecteur",
        self::CODE_REVIEWER_INVITATION                  => "Invitation d'un relecteur",
        self::CODE_REVIEWER_INVITATION_ACCEPTED         => "Invitation de relecture acceptée",
        self::CODE_REVIEWER_INVITATION_DECLINED         => "Invitation de relecture refusée",
        self::CODE_REVIEWER_UNASSIGNMENT                => "Suppression d'un relecteur",
        self::CODE_REVIEWING_COMPLETED                  => "Relecture terminée",
        self::CODE_REVIEWING_IN_PROGRESS                => "Relecture en cours",
        self::CODE_SECTION_SELECTION                    => "Déplacé dans une rubrique",
        self::CODE_STATUS                               => 'Nouveau statut',
        self::CODE_SWD_UPLOADED                         => 'Descripteur de logiciel chargé',
        self::CODE_VERSION_REPOSITORY_UPDATED           => 'Numéro de version mis à jour',
        self::CODE_VOLUME_SELECTION                     => "Déplacé dans un volume",
    ];

    /**
     * @param $paperid
     * @param $docid
     * @param $action
     * @throws Zend_Db_Adapter_Exception
     */
    public static function log($paperid, $docid, $action, $uid = null, $detail = null, $date = null, $rvid = null): bool
    {
        if (!array_key_exists($action, self::$_css)) {
            throw new InvalidArgumentException(sprintf('Unknown log action: "%s"', $action));
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $data = [
            'PAPERID' => $paperid,
            'DOCID' => $docid,
            // ?? instead of ?: so that $uid = 0 is not silently replaced by EPISCIENCES_UID
            'UID'  => $uid  ?? EPISCIENCES_UID,
            'RVID' => $rvid ?? RVID,
            'ACTION' => $action,
            'DETAIL' => $detail,
            'DATE' => $date ?? new Zend_Db_Expr('NOW()')
        ];
        return (bool)$db->insert(T_LOGS, $data);
    }

    public static function getLogTypes(): array
    {
        static $cache = null;
        if ($cache === null) {
            $reflect = new ReflectionClass(self::class);
            $constants = $reflect->getConstants();
            $keys = array_filter(array_keys($constants), static fn(string $k) => str_starts_with($k, 'CODE_'));
            $cache = array_intersect_key($constants, array_flip($keys));
        }
        return $cache;
    }

    /**
     * Met à jour l'UID de l'utilisateur
     * @param int $oldUid : l'UID à supprimer
     * @param int $newUid : Nouvel UID
     * @return int : le nombre de lignes affectées
     * @throws Zend_Db_Adapter_Exception
     */
    public static function updateUid(int $oldUid = 0, int $newUid = 0): int
    {

        if ($oldUid === 0 || $newUid === 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $data['UID'] = (int)$newUid;
        $where['UID = ?'] = (int)$oldUid;
        return $db->update(T_LOGS, $data, $where);
    }

}