<?php

class Episciences_Paper_Logger
{
    const CODE_STATUS = 'status';
    const CODE_RESTORATION_OF_STATUS = 'restoration_of_status';
    const CODE_EDITOR_ASSIGNMENT = 'editor_assignment';
    const CODE_EDITOR_UNASSIGNMENT = 'editor_unassignment';
    const CODE_REVIEWER_INVITATION = 'reviewer_invitation';
    const CODE_REVIEWER_INVITATION_ACCEPTED = 'reviewer_invitation_accepted';
    const CODE_REVIEWER_INVITATION_DECLINED = 'reviewer_invitation_declined';
    const CODE_REVIEWER_ASSIGNMENT = 'reviewer_assignment';
    const CODE_REVIEWER_UNASSIGNMENT = 'reviewer_unassignment';
    const CODE_REVIEWING_IN_PROGRESS = 'reviewing_in_progress';
    const CODE_REVIEWING_COMPLETED = 'reviewing_completed';
    const CODE_MAIL_SENT = 'mail_sent';
    const CODE_REMINDER_SENT = 'reminder_sent';
    // master volume selection
    const CODE_VOLUME_SELECTION = 'volume_selection';
    // secondary volume selection
    const CODE_OTHER_VOLUMES_SELECTION = 'other_volumes_selection';
    const CODE_SECTION_SELECTION = 'section_selection';
    const CODE_MINOR_REVISION_REQUEST = 'minor_revision_request';
    const CODE_MAJOR_REVISION_REQUEST = 'major_revision_request';
    // revision request answer : comment
    const CODE_REVISION_REQUEST_ANSWER = 'revision_request_answer';
    // revision request answer : new version
    const CODE_REVISION_REQUEST_NEW_VERSION = 'revision_request_new_version';
    // revision request answer : tmp version
    const CODE_REVISION_REQUEST_TMP_VERSION = 'revision_request_tmp_version';
    // alter report status
    const CODE_ALTER_REPORT_STATUS = 'alter_report_status';
    const CODE_MONITORING_REFUSED = 'monitoring_refused';
    // Abandon publication process
    const CODE_ABANDON_PUBLICATION_PROCESS = 'abandon_publication_process';
    // Continue publication process
    const CODE_CONTINUE_PUBLICATION_PROCESS = 'continue_publication_process';
    // COPY EDITOR
    const CODE_COPY_EDITOR_ASSIGNMENT = 'copy_editor_assignment';
    const CODE_COPY_EDITOR_UNASSIGNMENT = 'copy_editor_unassignment';

    // copy editing author sources request
    const CODE_CE_AUTHOR_SOURCES_REQUEST = 'copy_editing_author_sources_request';
    const CODE_CE_AUTHOR_SOURCES_DEPOSED = 'copy_editing_author_sources_deposed';
    const CODE_CE_AUTHOR_FINALE_VERSION_REQUEST = 'copy_editing_author_finale_version_request';
    const CODE_CE_AUTHOR_FINALE_VERSION_DEPOSED = 'copy_editing_author_finale_version_deposed';
    const CODE_CE_READY_TO_PUBLISH = 'copy_editing_ready_to_publish';
    const CODE_CE_REVIEW_FORMATTING_DEPOSED = 'copy_editing_review_formatting_deposed';
    // new paper comment
    const CODE_NEW_PAPER_COMMENT = 'new_paper_comment';
    const CODE_CE_AUTHOR_FINAL_VERSION_SUBMITTED = 'copy_editing_author_final_version_submitted';
    const CODE_AUTHOR_COMMENT_COVER_LETTER =  "author_comment_cover_letter";
    const CODE_EDITOR_COMMENT=  "editor_comment";
    const CODE_PAPER_COMMENT_FROM_REVIEWER_TO_CONTRIBUTOR = "paper_comment_form_reviewer_to_contributor";
    const CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER = "paper_comment_form_contributor_to_reviewer";
    const CODE_PAPER_UPDATED = "paper_updated";
    // https://github.com/CCSDForge/episciences/issues/50
    public const CODE_ALTER_PUBLICATION_DATE = "paper_alter_publication_date";

    const CODE_DOI_ASSIGNED = 'doi_assigned';

    public const CODE_COI_REPORTED = "coi_reported";

    // alert-
    const WARNING = 'warning';
    const INFO = 'info';
    const VIOLET = 'violet';
    const SUCCESS = 'success';
    const DANGER = 'danger';
    const PRIMARY = 'primary';

    // log type css class
    public static $_css = [
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
        self::CODE_ALTER_PUBLICATION_DATE => self::WARNING,
        self::CODE_COI_REPORTED => self::DANGER
    ];

    public static $_label = [
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
        self::CODE_PAPER_COMMENT_FROM_CONTRIBUTOR_TO_REVIEWER => "Réponse à une demande d'eclaicissement (contributeur au relecteur) ",
        self::CODE_DOI_ASSIGNED => 'DOI assigné',
        self::CODE_COI_REPORTED => "Conflit d'intérêts (CI)"
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
    public static function log($paperid, $docid, $action, $uid = null, $detail = null, $date = null, $rvid = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $data = [
            'PAPERID' => $paperid,
            'DOCID' => $docid,
            // if no user is specified, 'episciences' uid is used
            'UID' => ($uid) ? $uid : EPISCIENCES_UID,
            'RVID' => ($rvid) ? $rvid : RVID,
            'ACTION' => $action,
            'DETAIL' => $detail,
            'DATE' => ($date) ? $date : new Zend_DB_Expr('NOW()')
        ];

        if (!$db->insert(T_LOGS, $data)) {
            return false;
        }

        return true;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public static function getLogTypes()
    {
        $reflect = new ReflectionClass(get_class());
        $constants = $reflect->getConstants();
        $keys = array_filter(array_keys($constants), function ($k) {
            return substr($k, 0, 5) === "CODE_";
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
    public static function updateUid(int $oldUid = 0, int $newUid = 0)
    {

            if($oldUid == 0 || $newUid == 0){
                return 0;
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $data['UID'] = (int)$newUid;
            $where['UID = ?'] = (int)$oldUid;
            return $db->update(T_LOGS, $data, $where);
        }

}
