<?php

return [

    // UTILISATEUR ********************************************************************************************************************************
    "user_tpl_group" => "User account",

    // Création de compte : envoi du lien de confirmation
    "user_registration_tpl_name" => "Registration validation",
    "user_registration_mail_subject" => "%%REVIEW_CODE%% - Registration validation",

    // Identifiant oublié
    "user_lost_login_tpl_name" => "Lost login",
    "user_lost_login_mail_subject" => "%%REVIEW_CODE%% - Logins list",

    // Mot de passe oublié
    "user_lost_password_tpl_name" => "Lost password",
    "user_lost_password_mail_subject" => "%%REVIEW_CODE%% - Password reset request",


    // PAPER - SOUMISSION D'UN ARTICLE ***********************************************************************************************************
    "paper_submission_tpl_group" => "Paper - submission",

    // Confirmation de la soumission d'un article – copie destinée aux rédacteurs
    "paper_submission_editor_copy_tpl_name" => "Submitted article - Editors copy",
    "paper_submission_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - A new article has been submitted",

    // Confirmation de la soumission d'un article – copie destinée à l'auteur
    "paper_submission_author_copy_tpl_name" => "Submitted article - Author copy",
    "paper_submission_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been submitted",

    // Confirmation de nouvelle version d'un article pour les auteurs et co auteur si existant
    "paper_new_version_submission_author_tpl_name" => "New version of the paper for the author and co-author",
    "paper_new_version_submission_author_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New version has been submitted",

    // Confirmation de nouvelle version temporaire d'un article pour les auteurs et co auteur si existant
    "paper_new_version_temporary_submission_author_tpl_name" => "New temporary version of your paper for the author and co-author",
    "paper_new_version_temporary_submission_author_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New temporary version has been submitted",

    // Suppression d'un article par son auteur - copie destinée à l'auteur
    "paper_deleted_author_copy_tpl_name" => "Deleted submission - Author copy",
    "paper_deleted_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been deleted",

    // Suppression d'un article par son auteur - copie destinée aux rédacteurs
    "paper_deleted_editor_copy_tpl_name" => "Deleted submission - Editors copy",
    "paper_deleted_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - A submission has been deleted",

    // Suppression d'un article par son auteur - copie destinée aux relecteurs
    "paper_deleted_reviewer_copy_tpl_name" => "Deleted submission - Reviewers copy",
    "paper_deleted_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - A submission has been deleted",


    // PAPER - ASSIGNATION DE REDACTEURS ***********************************************************************************************************
    "paper_editor_assign_tpl_group" => "Paper - editors assignment",

    // Assignation de rédacteur
    "paper_editor_assign_tpl_name" => "Assign an editor",
    "paper_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    "paper_section_editor_assign_tpl_name" => "Assign an editor - choice of section by author",
    "paper_section_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    "paper_volume_editor_assign_tpl_name" => "Assign an editor - choice of volume by author",
    "paper_volume_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    "paper_suggested_editor_assign_tpl_name" => "Assign an editor - suggested by author",
    "paper_suggested_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    // Désassignation de rédacteur
    "paper_editor_unassign_tpl_name" => "Unassign an editor",
    "paper_editor_unassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been unassigned from managing an article",


    // PAPER - RELECTURE ****************************************************************************************************************************
    "paper_review_tpl_group" => "Paper - reviewing",

    // Date limite de rendu de la relecture modifié (rating deadline)
    "paper_updated_rating_deadline_tpl_name" => "Updated rating deadline",
    "paper_updated_rating_deadline_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The rating deadline has been updated",

    // Suppression de relecteur
    "paper_reviewer_removal_tpl_name" => "Reviewer removal",
    "paper_reviewer_removal_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your rating invitation has been cancelled",

    // Invitation de relecteur
    "paper_reviewer_invitation1_tpl_name" => "Invite a user to review an article - existing reviewer",
    "paper_reviewer_invitation1_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You are invited to review an article",

    "paper_reviewer_invitation2_tpl_name" => "Invite a user to review an article - existing user",
    "paper_reviewer_invitation2_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You are invited to review an article",

    "paper_reviewer_invitation3_tpl_name" => "Invite a user to review an article - user does not have an account yet",
    "paper_reviewer_invitation3_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You are invited to review an article",

    // Réponse à une invitation de relecture
    "paper_reviewer_acceptation_reviewer_copy_tpl_name" => "Accepted reviewer invitation (reviewer copy)",
    "paper_reviewer_acceptation_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've accepted a reviewing invitation",

    "paper_reviewer_acceptation_editor_copy_tpl_name" => "Accepted reviewer invitation (editors copy)",
    "paper_reviewer_acceptation_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - A reviewer has accepted to review this article",

    "paper_reviewer_refusal_reviewer_copy_tpl_name" => "Refused reviewer invitation (reviewer copy)",
    "paper_reviewer_refusal_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've refused a reviewing invitation",

    "paper_reviewer_refusal_editor_copy_tpl_name" => "Refused reviewer invitation (editors copy)",
    "paper_reviewer_refusal_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - A reviewer has refused to review this article",

    // Relecture terminée - copie destinée au relecteur
    "paper_reviewed_reviewer_copy_tpl_name" => "Completed rating (reviewer copy)",
    "paper_reviewed_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Completed rating",

    // Relecture terminée - copie destinée aux rédacteurs (à intégrer)
    "paper_reviewed_editor_copy_tpl_name" => "Completed rating (editors copy)",
    "paper_reviewed_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Completed rating on an article you're managing",

    // Notification de réassignation à la nouvelle version d'un article (relecteurs)
    "paper_new_version_reviewer_reassign_tpl_name" => "Reviewer assignment to a new version of an article",
    "paper_new_version_reviewer_reassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewer assignment to a new version of an article",

    // Notification de réassignation à la version temporaire d'un article (relecteurs)
    "paper_tmp_version_reviewer_reassign_tpl_name" => "Reviewer assignment to a temporary version",
    "paper_tmp_version_reviewer_reassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewer assignment to a temporary version",

    // Papier accetpté : notifier les relecteurs qui n'ont pas encore achevé leur relecture.
    "paper_reviewer_paper_accepted_stop_pending_reviewing_tpl_name" => "Final version accepted: reviewing is no longer needed",
    "paper_reviewer_paper_accepted_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - reviewing is no longer needed",

    // Papier en demande de modifications : notifier les relecteurs qui n'ont pas encore achevé leurs relectures.
    "paper_reviewer_paper_revision_request_stop_pending_reviewing_tpl_name" => "Revision request: reviewing is no longer needed",
    "paper_reviewer_paper_revision_request_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - reviewing is no longer needed",

    // refus de l'artcile : notifier les relecteurs qui n'ont pas encore achevé leurs relectures.
    "paper_reviewer_paper_refused_stop_pending_reviewing_tpl_name" => "Article refused: reviewing is no longer needed",
    "paper_reviewer_paper_refused_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - reviewing is no longer needed",

    // Notification de réinvitation à la nouvelle version d'un article (relecteurs)
    "paper_new_version_reviewer_reinvitation_tpl_name" => "Reviewer invitation to a new version of an article",
    "paper_new_version_reviewer_reinvitation_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewer assignment to a new version of an article",

    // Notification de réinvitation à la version temporaire d'un article (relecteurs)
    "paper_tmp_version_reviewer_reinvitation_tpl_name" => "Reviewer invitation to a temporary version",
    "paper_tmp_version_reviewer_reinvitation_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewer assignment to a temporary version",


    // PAPER - RELANCES AUTOMATIQUES ****************************************************************************************************************************
    "paper_review_reminder_tpl_group" => "Automatic reminders",


    // Relance suite à une invitation de relecteur restée sans réponse - copie destinée au relecteur
    "reminder_unanswered_reviewer_invitation_reviewer_version_tpl_name" => "Unanswered reviewer invitation (reviewer copy)",
    "reminder_unanswered_reviewer_invitation_reviewer_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Unanswered reviewer invitation",

    // Relance suite à une invitation de relecteur restée sans réponse - copie destinée au rédacteur
    "reminder_unanswered_reviewer_invitation_editor_version_tpl_name" => "Unanswered reviewer invitation (editor copy)",
    "reminder_unanswered_reviewer_invitation_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Unanswered reviewer invitation",


    // Rappel avant date de livraison de relecture - copie destinée au relecteur
    "reminder_before_deadline_reviewer_version_tpl_name" => "Reminder before reviewing deadline (reviewer copy)",
    "reminder_before_deadline_reviewer_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewing deadline is coming close!",

    // Rappel avant date de livraison de relecture - copie destinée au rédacteur
    "reminder_before_deadline_editor_version_tpl_name" => "Reminder before reviewing deadline (editor copy)",
    "reminder_before_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewing deadline is coming close!",

    // Relance après date de livraison de relecture - copie destinée au relecteur
    "reminder_after_deadline_reviewer_version_tpl_name" => "Reminder after reviewing deadline (reviewer copy)",
    "reminder_after_deadline_reviewer_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewing deadline is over!",

    // Relance après date de livraison de relecture - copie destinée au rédacteur
    "reminder_after_deadline_editor_version_tpl_name" => "Reminder after reviewing deadline (editor copy)",
    "reminder_after_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewing deadline is over!",


    // Rappel avant date limite de modification - copie destinée à l'auteur
    "reminder_before_revision_deadline_author_version_tpl_name" => "Reminder before revision deadline (author copy)",
    "reminder_before_revision_deadline_author_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Revision deadline is coming close!",

    // Rappel avant date limite de modification - copie destinée au rédacteur
    "reminder_before_revision_deadline_editor_version_tpl_name" => "Reminder before revision deadline (editor copy)",
    "reminder_before_revision_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Revision deadline is coming close!",

    // Relance après date limite de modification - copie destinée à l'auteur
    "reminder_after_revision_deadline_author_version_tpl_name" => "Reminder after revision deadline (author copy)",
    "reminder_after_revision_deadline_author_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Revision deadline is over!",

    // Relance après date limite de modification - copie destinée au rédacteur
    "reminder_after_revision_deadline_editor_version_tpl_name" => "Reminder after revision deadline (editor copy)",
    "reminder_after_revision_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Revision deadline is over!",

    // Pas assez de relecteurs
    "reminder_not_enough_reviewers_tpl_name" => "Not enough reviewers",
    "reminder_not_enough_reviewers_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Not enough reviewers",

    // article bloqué à l'état accepté
    'reminder_article_blocked_in_accepted_state_editor_version_tpl_name' => 'Article blocked in accepted state (editor copy)',
    'reminder_article_blocked_in_accepted_state_editor_version_mail_subject' => '%%REVIEW_CODE%% #%%ARTICLE_ID%% - Article blocked at accepted state!',


    // PAPER - COMMENTAIRES ****************************************************************************************************************************
    "paper_comment_tpl_group" => "Paper - comments",

    // Commentaire d'un relecteur sur un article – copie destinée à l'auteur
    "paper_comment_author_copy_tpl_name" => "Reviewer's comment about a paper (author copy)",
    "paper_comment_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Comment request about your article",

    // Commentaire d'un relecteur sur un article – copie destinée aux rédacteurs
    "paper_comment_editor_copy_tpl_name" => "Reviewer's comment about a paper (editors copy)",
    "paper_comment_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Comment request about an article you're managing",

    // Réponse de l'auteur à un commentaire d'un relecteur – copie destinée au relecteur
    "paper_comment_answer_reviewer_copy_tpl_name" => "Author's answer to a reviewer's comment (reviewer copy)",
    "paper_comment_answer_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New answer from an author to your comment",

    // Réponse de l'auteur à un commentaire d'un relecteur – copie destinée aux rédacteurs
    "paper_comment_answer_editor_copy_tpl_name" => "Author's answer to a reviewer's comment (editors copy)",
    "paper_comment_answer_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New answer from an author to a comment on an article you're managing",

    // Commentaires des rédacteurs
    "paper_comment_by_editor_editor_copy_tpl_name" => "Editor's comment about a paper (editors copy)",
    "paper_comment_by_editor_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - editor comment about an article you're managing",

    // Communication from author to assigned editors
    "paper_comment_from_author_to_editor_editor_copy_tpl_name" => "Message from author to assigned editors (editors copy)",
    "paper_comment_from_author_to_editor_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - new message about %%ARTICLE_RELATIONSHIP%%",

    // Editor response to author message
    "paper_editor_response_to_author_author_copy_tpl_name" => "Editor response to your message (author copy)",
    "paper_editor_response_to_author_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - an editor responded to your message",

    // Commentaire de l'auteur / lettre d'accompagnement
    'paper_author_comment_editor_copy_tpl_name' => "Author's comments / Cover letter",
    'paper_author_comment_editor_copy_mail_subject' => "Author's comments / Cover letter",


    // PAPER - SUGGESTIONS D'UN REDACTEUR ***************************************************************************************************************
    "paper_editor_suggestion_tpl_group" => "Paper - editor suggestions",

    // Un rédacteur suggère d'accepter l'article
    "paper_suggest_acceptation_tpl_name" => "Article monitoring: acceptance proposal",
    "paper_suggest_acceptation_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - An editor suggested an article acceptance",

    // Un rédacteur suggère de refuser l'article
    "paper_suggest_refusal_tpl_name" => "Article monitoring: refusal proposal",
    "paper_suggest_refusal_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - An editor suggested an article refusal",

    // Un rédacteur suggère de demander une nouvelle version de l'article
    "paper_suggest_new_version_tpl_name" => "Article monitoring: revision request proposal",
    "paper_suggest_new_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - An editor suggests an article revision request",


    // PAPER - DEMANDES DE MODIFICATIONS *****************************************************************************************************************
    "paper_revision_tpl_group" => "Paper - revision requests",

    // Un rédacteur en chef demande des modifications sur l'article
    "paper_revision_request_tpl_name" => "New version request",
    "paper_revision_request_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Modification request",

    // Un rédacteur en chef demande des modifications mineures sur l'article
    "paper_minor_revision_request_tpl_name" => "Minor revision request",
    "paper_minor_revision_request_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Minor revision request",

    // Un rédacteur en chef demande des modifications majeures sur l'article
    "paper_major_revision_request_tpl_name" => "Major revision request",
    "paper_major_revision_request_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Major revision request",

    // Réponse de l'auteur à une demande de modifications : pas de modifications
    "paper_revision_answer_tpl_name" => "New answer to your revision request: comment",
    "paper_revision_answer_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New answer to your revision request: comment",

    // Réponse de l'auteur à une demande de modifications : version temporaire de l'article
    "paper_tmp_version_submitted_tpl_name" => "New answer to your revision request: temporary version",
    "paper_tmp_version_submitted_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New answer to your revision request: temporary version",

    // Réponse de l'auteur à une demande de modifications : nouvelle version de l'article
    "paper_new_version_submitted_tpl_name" => "New answer to your revision request: new version",
    "paper_new_version_submitted_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New answer to your revision request: new version",


    // PAPER - DECISION FINALE *****************************************************************************************************************************
    "paper_final_decision_tpl_group" => "Paper - final decision",

    //accepted ask authors final version
    "paper_accepted_ask_authors_final_version_tpl_name" => "Accepted, ask author's final version",
    "paper_accepted_ask_authors_final_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Article already accepted, ask author's final version",

    // Article accepté
    "paper_accepted_tpl_name" => "Accepted article - Author copy",
    "paper_accepted_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been accepted",

    // Article accepté dans sa version temporaire
    "paper_accepted_tmp_version_tpl_name" => "Accepted temporary version",
    "paper_accepted_tmp_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The temporary version of your article has been approved",

    // accept tmp paper - managers copy
    "paper_accepted_tmp_version_managers_copy_tpl_name" => "Accepted temporary version (managers copy)",
    "paper_accepted_tmp_version_managers_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The temporary version of an article has been approved",

    // Article refusé
    "paper_refused_tpl_name" => "Refused article - author copy",
    "paper_refused_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been refused",

    "paper_refused_editors_copy_tpl_name" => "Refused article - editor copy",
    "paper_refused_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - An article has just been refused",

    // ask other editors
    "paper_ask_other_editors_tpl_name" => "Ask other editors for their opinion",
    "paper_ask_other_editors_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your opinion?",

    // published paper - author copy
    "paper_published_author_copy_tpl_name" => "Published article - Author copy",
    "paper_published_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been published",

    // published paper - editor copy
    "paper_published_editor_copy_tpl_name" => "Published article - Editors copy",
    "paper_published_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New published article",

    // Papier publié : notifier les relecteurs qui n'ont pas encore achevé leur relecture.
    "paper_reviewer_paper_published_stop_pending_reviewing_tpl_name" => "Published article: reviewing is no longer needed",
    "paper_reviewer_paper_published_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewing is no longer needed",

    // Le rédacteur souhaite de ne plus gérer l'article
    "paper_editor_refused_monitoring_tpl_name" => "End of article supervision by the editor",
    "paper_editor_refused_monitoring_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - An editor doesn't want to manage the article",

    // accepted paper - editor copy
    "paper_accepted_editors_copy_tpl_name" => "Accepted article - Editors copy",
    "paper_accepted_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New accepted article",

    // ARTICLE - Abandon publication process
    "abandon_publication_process_tpl_group" => "Paper - Abandon publication process",

    // Abandonner le processus de publication d'un article  – copie destinée à l'auteur
    "paper_abandon_publication_author_copy_tpl_name" => "Abandon publication process (author copy) ",
    "paper_abandon_publication_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The publication process of your article has been stopped",

    // Abandonner le processus de publication d'un article, cas où le rédacteur a déjà été assigné - copie destinée à l'éditeur
    "paper_abandon_publication_editor_copy_tpl_name" => "Abandon publication process (editors copy)",
    "paper_abandon_publication_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The publication process of this article has been stopped",

    // Abandonner le processus de publication d'un article par l'auteur lui-même  – copie destinée à l'auteur
    "paper_abandon_publication_by_author_author_copy_tpl_name" => "Abandon publication process by author (author copy) ",
    "paper_abandon_publication_by_author_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The publication process of your article has been stopped",

    // Abandonner le processus de publication d'un article, cas où le redacteur n'a pas encore été assigné – copie destinée aux rédcateurs en chef, administrateurs, secrétaires de rédaction
    "paper_abandon_publication_no_assigned_editors_tpl_name" => "Abandon publication process (case where editor was not assigned)",
    "paper_abandon_publication_no_assigned_editors_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The publication process of this article has been stopped",

    // PAPER - Continue publication process
    "continue_publication_process_tpl_group" => "Paper - Continue publication process",

    // Reprendre le processus de publication d'un article  – copie destinée à l'auteur
    "paper_continue_publication_author_copy_tpl_name" => "Continue publication process (author copy)",
    "paper_continue_publication_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The publication process of your article has been resumed",

    // Abandonner le processus de publication d'un article - copie destinée à l'éditeur
    "paper_continue_publication_editor_copy_tpl_name" => "Continue publication process (editors copy)",
    "paper_continue_publication_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - The publication process of this article has been resumed",
    // Supprimer un relecture suite à l'arrêt du processus de prublication
    "paper_abandon_publication_reviewer_removal_tpl_name" => "Abandon publication process - Reviewer removal",
    "paper_abandon_publication_reviewer_removal_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your rating invitation has been cancelled",

    //PAPER - ASSIGANTION DE COPY EDITORS
    "paper_copy_editing_tpl_group" => "Paper - copy editing",

    // Assigantion de Copy Editor - copy editor copy
    "paper_copyeditor_assign_tpl_name" => "Assign a copy editor (copy editor copy)",
    "paper_copyeditor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage copy editing of an article",

    // Assignation de préparateurs de copie - copie destinée aux rédacteurs
    "paper_copyeditor_assign_editor_copy_tpl_name" => "Assign a copy editor (editors copy)",
    "paper_copyeditor_assign_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New article assigned to copy editing",

    // Assignation de préparateurs de copie - copie destinée à l'auteur
    "paper_copyeditor_assign_author_copy_tpl_name" => "Assign a copy editor (author copy)",
    "paper_copyeditor_assign_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been assigned to copy editing",

    // Désassignation de préparateurs de copie
    "paper_copyeditor_unassign_tpl_name" => "Unassign a copy editor (copy editor copy)",
    "paper_copyeditor_unassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been unassigned from the copy editing of an article",

    // Copy editing : demande de sources - copie  rédacteur
    "paper_ce_waiting_for_author_sources_editor_copy_tpl_name" => "Request for author's sources (editors copy)",
    "paper_ce_waiting_for_author_sources_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Waiting for author's sources",

    // Copy editing : demande de sources - copie auteur
    "paper_ce_waiting_for_author_sources_author_copy_tpl_name" => "Request for author's sources (author copy)",
    "paper_ce_waiting_for_author_sources_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Request for author's sources",

    // Copy_editing : sources auteurs déposées - copie préparateurs de copie et rédacteurs
    "paper_ce_author_sources_deposed_response_copyeditors_and_editors_copy_tpl_name" => "Author's sources have been submitted (editors and copy editors copy)",
    "paper_ce_author_sources_deposed_response_copyeditors_and_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Author sources have been submitted",

    //Copy editing : sources auteurs déposées - copy auteur
    "paper_ce_author_sources_deposed_response_author_copy_tpl_name" => "Author's sources have been submitted (author copy) ",
    "paper_ce_author_sources_deposed_response_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Author sources have been submitted",

    // Copy editing : en attente de la mise en forme par l'auteur - copie préparateurs de copie et rédacteurs
    "paper_ce_waiting_for_author_formatting_editor_and_copyeditor_copy_tpl_name" => "Author formatting request (copy editors and editors copy)",
    "paper_ce_waiting_for_author_formatting_editor_and_copyeditor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - waiting for author's final version",

    // Copy editing : en attente de la mise en forme par l'auteur - copie auteur
    "paper_ce_waiting_for_author_formatting_author_copy_tpl_name" => "Author formatting request (author copy)",
    "paper_ce_waiting_for_author_formatting_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - waiting for your final version",

    //Copy editing : réponse à une demande de la version finale - copie destinée aux rédacteurs & préparateurs de copie
    "paper_ce_author_vesrion_finale_deposed_editor_and_copyeditor_copy_tpl_name" => "Final version answer (copy editor and editor copy)",
    "paper_ce_author_vesrion_finale_deposed_editor_and_copyeditor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - author's final version submitted",

    //Copy editing : réponse à une demande de la version finale - copie destinée à l'auteur
    "paper_ce_author_vesrion_finale_deposed_author_copy_tpl_name" => "Final version submitted answer (author copy)",
    "paper_ce_author_vesrion_finale_deposed_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - your response has been saved",

    // Copy editing: prêt à publier
    "paper_ce_accepted_final_version_copyeditor_and_editor_copy_tpl_name" => "Formatting validation (copy editor and editor copy)",
    "paper_ce_accepted_final_version_copyeditor_and_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - the final version has been validated ",

    "paper_ce_accepted_final_version_author_copy_tpl_name" => "Formatting validation (author copy)",
    "paper_ce_accepted_final_version_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - the final version has been validated",

    "paper_ce_review_formatting_deposed_editor_and_copyeditor_copy_tpl_name" => "Journal formatting submitted (copy editor and editor copy)",
    "paper_ce_review_formatting_deposed_editor_and_copyeditor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - journal formatting has been submitted",

    "paper_ce_review_formatting_deposed_author_copy_tpl_name" => "Journal formatting submitted (author copy)",
    "paper_ce_review_formatting_deposed_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - journal formatting has been submitted",

    'paper_formatted_by_journal_waiting_author_validation_tpl_name' => "Journal formatting submitted, ask author's validation",
    'paper_formatted_by_journal_waiting_author_validation_mail_subject' => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - formatting awaiting validation",

    // Confirmation de la mise à jour d'un article – copie destinée aux rédacteurs
    "paper_submission_updated_editor_copy_tpl_name" => "Updated article - Editors copy",
    "paper_submission_updated_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - An article has been updated",

    // Confirmation de la mise à jour d'un article – copie destinée à l'auteur
    "paper_submission_updated_author_copy_tpl_name" => "Updated article - Author copy",
    "paper_submission_updated_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been updated",

    // Confirmation de la soumission automatique d'un article depuis le serveur de preprint – copie destinée à l'auteur
    "inbox_paper_submission_author_copy_tpl_name" => "Your submission made via a preprint server - Author copy",
    "inbox_paper_submission_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Your article has been submitted - please complete your submission",

    //templates
    "vous aviez envoyé" => "you have sent",
    "a envoyé" => "has sent",
    'Le template par défaut a été restauré' => 'The default template has been restored',
    'La suppression du template personnalisé a échoué' => 'Deleting the custom template failed',
    'Rétablir' => 'Revert',

    // git #230
    // Notification de la soumission d'un article – copie destinée aux rédacteurs en chef, administrateurs et secrétaires de rédaction (selon les paramètres de notification de la revue),
    "paper_submission_other_recipient_copy_tpl_name" => "Submitted article - chief editors, administrators and secretaries copy",
    "paper_submission_other_recipient_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - A new article has been submitted",
    //gitHub #513
    "reminder_reviewed_article_editors_copy_tpl_name" => "Article blocked in reviewed state (editors in chief | editors copy)",
    "reminder_reviewed_article_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Reviewed article, decision required",
    "reminder_submitted_article_editors_copy_tpl_name" => "Article blocked in initial state [submitted] - (editors in chief | editors copy)",
    "reminder_submitted_article_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - New submission, decision required",
];
