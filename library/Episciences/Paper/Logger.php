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
    public const CODE_AUTHOR_COMMENT_COVER_LETTER =  "author_comment_cover_letter";
    public const CODE_EDITOR_COMMENT=  "editor_comment";
    public const CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR = "paper_comment_form_reviewer_to_contributor";
    public const CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER = "paper_comment_form_contributor_to_reviewer";
    public const CODE_PAPER_UPDATED = "paper_updated";
    // https://github.com/CCSDForge/episciences/issues/50
    public const CODE_ALTER_PUBLICATION_DATE = "paper_alter_publication_date";

    public const CODE_DOI_ASSIGNED = 'doi_assigned';
    public const CODE_DOI_UPDATED = 'doi_updated';

    public const CODE_DOI_CANCELED = 'doi_canceled';

    public const CODE_COI_REPORTED = "coi_reported";
    public const CODE_COI_REVERTED= "coi_reverted";

    public const CODE_ACCEPTED_ASK_AUTHORS_FINAL_VERSION = "accepted_ask_authors_final_version";
    public const CODE_ACCEPTED_ASK_FOR_AUTHOR_VALIDATION = "accepted_ask_for_author_validation";

    public const CODE_VERSION_REPOSITORY_UPDATED = "version_repository_updated";
    public const CODE_NEW_REVIEWING_DEADLINE = 'new_reviewing_deadline';
    public const CODE_INBOX_COAR_NOTIFY_REVIEW = 'coar_notify_review';
    public const CODE_LD_ADDED = 'ld_added';
    public const CODE_LD_CHANGED = 'ld_changed';
    public const CODE_LD_REMOVED = 'ld_remove';
    // alert-
    public const WARNING = 'warning';
    public const INFO = 'info';

    public const VIOLET = 'violet';
    public const SUCCESS = 'success';
    public const DANGER = 'danger';
    public const PRIMARY = 'primary';

    public const CODE_REVISION_DEADLINE_UPDATED = 'revision_deadline_updated';


    // log type css class
    public static array $_css = [
        self::CODE_RESTORATION_OF_STATUS => self::SUCCESS,
        self::CODE_STATUS => self::SUCCESS,
        self::CODE_EDITOR_ASSIGNMENT => self::WARNING,
        self::CODE_EDITOR_UNASSIGNMENT => self::WARNING,
        self::CODE_REVIEWER_INVITATION => self::WARNING,
        self::CODE_REVIEWER_INVITATION_ACCEPTED => self::WARNING,
        self::CODE_REVIEWER_INVITATION_DECLINED => self::WARNING,
        self::CODE_REVIEWER_UNASSIGNMENT => self::WARNING,
        self::CODE_REVIEWER_ASSIGNMENT => self::WARNING,
        self::CODE_REVIEWING_IN_PROGRESS => self::WARNING,
        self::CODE_REVIEWING_COMPLETED => self::WARNING,
        self::CODE_MAIL_SENT => self::INFO,
        self::CODE_REMINDER_SENT => self::INFO,
        self::CODE_VOLUME_SELECTION => self::VIOLET,
        self::CODE_OTHER_VOLUMES_SELECTION => self::VIOLET,
        self::CODE_SECTION_SELECTION => self::VIOLET,
        self::CODE_MINOR_REVISION_REQUEST => self::VIOLET,
        self::CODE_MAJOR_REVISION_REQUEST => self::VIOLET,
        self::CODE_REVISION_REQUEST_ANSWER => self::VIOLET,
        self::CODE_REVISION_REQUEST_NEW_VERSION => self::VIOLET,
        self::CODE_REVISION_REQUEST_TMP_VERSION => self::VIOLET,
        self::CODE_ALTER_REPORT_STATUS => self::WARNING,
        self::CODE_MONITORING_REFUSED => self::DANGER,
        self::CODE_ABANDON_PUBLICATION_PROCESS => self::DANGER,
        self::CODE_CONTINUE_PUBLICATION_PROCESS => self::WARNING,
        self::CODE_COPY_EDITOR_ASSIGNMENT => self::WARNING,
        self::CODE_COPY_EDITOR_UNASSIGNMENT => self::WARNING,
        self::CODE_CE_AUTHOR_SOURCES_REQUEST => self::VIOLET,
        self::CODE_NEW_PAPER_COMMENT => self::VIOLET,
        self::CODE_CE_AUTHOR_SOURCES_DEPOSED => self::VIOLET,
        self::CODE_CE_AUTHOR_FINALE_VERSION_REQUEST => self::VIOLET,
        self::CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED => self::VIOLET,
        self::CODE_CE_REVIEW_FORMATTING_DEPOSED => self::VIOLET,
        self::CODE_CE_READY_TO_PUBLISH => self::VIOLET,
        self::CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED => self::VIOLET,
        self::CODE_AUTHOR_COMMENT_COVER_LETTER => self::PRIMARY,
        self::CODE_EDITOR_COMMENT => self::VIOLET,
        self::CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR => self::VIOLET,
        self::CODE_PAPER_UPDATED => self::WARNING,
        self::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER => self::VIOLET,
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
        self::CODE_LD_CHANGED => self::INFO,
        self::CODE_LD_REMOVED => self::INFO,
        self::CODE_REVISION_DEADLINE_UPDATED => self::WARNING,

    ];

    public static array $_label = [
        self::CODE_STATUS => 'Nouveau statut',
        self::CODE_EDITOR_ASSIGNMENT => "Assignation d'un rédacteur",
        self::CODE_EDITOR_UNASSIGNMENT => "Désassignation d'un rédacteur",
        self::CODE_REVIEWER_INVITATION => "Invitation d'un relecteur",
        self::CODE_REVIEWER_INVITATION_ACCEPTED => "Invitation de relecture acceptée",
        self::CODE_REVIEWER_INVITATION_DECLINED => "Invitation de relecture refusée",
        self::CODE_REVIEWER_UNASSIGNMENT => "Suppression d'un relecteur",
        self::CODE_REVIEWER_ASSIGNMENT => "Assignation d'un relecteur",
        self::CODE_REVIEWING_IN_PROGRESS => "Relecture en cours",
        self::CODE_REVIEWING_COMPLETED => "Relecture terminée",
        self::CODE_MAIL_SENT => "Envoi d'un e-mail",
        self::CODE_REMINDER_SENT => "Envoi d'une relance automatique",
        self::CODE_VOLUME_SELECTION => "Déplacé dans un volume",
        self::CODE_OTHER_VOLUMES_SELECTION => "Sélection des volumes secondaires",
        self::CODE_SECTION_SELECTION => "Déplacé dans une rubrique",
        self::CODE_MINOR_REVISION_REQUEST => 'Demande de modifications mineures',
        self::CODE_MAJOR_REVISION_REQUEST => 'Demande de modifications majeures',
        self::CODE_REVISION_REQUEST_ANSWER => 'Réponse à une demande de modifications',
        self::CODE_REVISION_REQUEST_NEW_VERSION => 'Réponse à une demande de modifications (nouvelle version)',
        self::CODE_REVISION_REQUEST_TMP_VERSION => 'Réponse à une demande de modifications (version temporaire)',
        self::CODE_RESTORATION_OF_STATUS=> "Restauration du statut",
        self::CODE_MONITORING_REFUSED => "Ne plus gérer l'artcile",
        self::CODE_NEW_PAPER_COMMENT => 'Nouveau commentaire',
        self::CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR => "Demande d'éclaircissements (relecteur au contributeur)",
        self::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER => "Réponse à une demande d'éclaircissement (contributeur au relecteur)",
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
        self::CODE_LD_CHANGED => "Changement d'une donnée liée",
        self::CODE_LD_REMOVED => "Suppression d'une donnée liée",
    ];

    /**
     * @param $paperid
     * @param $docid
     * @param $action
     * @param null $uid
     * @param null $detail
     * @param null $date
     * @param null $rvid
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function log($paperid, $docid, $action, $uid = null, $detail = null, $date = null, $rvid = null): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $data = [
            'PAPERID' => $paperid,
            'DOCID' => $docid,
            // if no user is specified, 'episciences' uid is used
            'UID' => ($uid) ?: EPISCIENCES_UID,
            'RVID' => ($rvid) ?: RVID,
            'ACTION' => $action,
            'DETAIL' => $detail,
            'DATE' => ($date) ?: new Zend_DB_Expr('NOW()')
        ];

        if (!$db->insert(T_LOGS, $data)) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    public static function getLogTypes(): array
    {
        $reflect = new ReflectionClass(__CLASS__);
        $constants = $reflect->getConstants();
        $keys = array_filter(array_keys($constants), static function ($k) {
            return strpos($k, "CODE_") === 0;
        });
        return array_intersect_key($constants, array_flip($keys));
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

            if($oldUid === 0 || $newUid === 0){
                return 0;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['UID'] = (int)$newUid;
            $where['UID = ?'] = (int)$oldUid;
            return $db->update(T_LOGS, $data, $where);
        }

}
