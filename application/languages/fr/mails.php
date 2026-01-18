<?php
return [
    // UTILISATEUR ********************************************************************************************************************************
    "user_tpl_group" => "Compte utilisateur",

    // Création de compte : envoi du lien de confirmation
    "user_registration_tpl_name" => "Validation de compte",
    "user_registration_mail_subject" => "%%REVIEW_CODE%% - Validation de votre compte",

    // Identifiant oublié
    "user_lost_login_tpl_name" => "Identifiants perdus",
    "user_lost_login_mail_subject" => "%%REVIEW_CODE%% - Liste de vos noms d'utilisateurs",

    // Mot de passe oublié
    "user_lost_password_tpl_name" => "Mot de passe perdu",
    "user_lost_password_mail_subject" => "%%REVIEW_CODE%% - Changement du mot de passe de votre compte",


    // PAPER - SOUMISSION D'UN ARTICLE ***********************************************************************************************************
    "paper_submission_tpl_group" => "Article - soumission",

    // Confirmation de la soumission d'un article – copie destinée aux rédacteurs
    "paper_submission_editor_copy_tpl_name" => "Nouvel article - Copie à destination des rédacteurs",
    "paper_submission_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un nouvel article vient d'être proposé",

    // Confirmation de la soumission d'un article – copie destinée à l'auteur
    "paper_submission_author_copy_tpl_name" => "Nouvel article - Copie à destination de l'auteur",
    "paper_submission_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a bien été soumis",

    // Confirmation de nouvelle version d'un article pour les auteurs et co auteur si existant
    "paper_new_version_submission_author_tpl_name" => "Nouvelle version du papier à destination de l'auteur et du co-auteur",
    "paper_new_version_submission_author_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Nouvelle version du papier",

    // Confirmation de nouvelle version temporaire d'un article pour les auteurs et co auteur si existant
    "paper_new_version_temporary_submission_author_tpl_name" => "Nouvelle version temporaire du papier à destination de l'auteur et du co-auteur",
    "paper_new_version_temporary_submission_author_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Nouvelle version temporaire du papier",

    // Suppression d'un article par son auteur - copie destinée à l'auteur
    "paper_deleted_author_copy_tpl_name" => "Article supprimé - Copie à destination de l'auteur",
    "paper_deleted_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a été supprimé",

    // Suppression d'un article par son auteur - copie destinée aux rédacteurs
    "paper_deleted_editor_copy_tpl_name" => "Article supprimé - Copie à destination des rédacteurs",
    "paper_deleted_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article a été supprimé",

    // Suppression d'un article par son auteur - copie destinée aux relecteurs
    "paper_deleted_reviewer_copy_tpl_name" => "Article supprimé - Copie à destination des relecteurs",
    "paper_deleted_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article a été supprimé",

    // Confirmation de la soumission automatique d'un article depuis le serveur de preprint – copie destinée à l'auteur
    "inbox_paper_submission_author_copy_tpl_name" => "Votre soumission effectuée via le serveur de preprint - Copie à destination de l'auteur",
    "inbox_paper_submission_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a bien été soumis - merci de compléter votre soumission",

    // PAPER - ASSIGNATION DE REDACTEURS ***********************************************************************************************************
    "paper_editor_assign_tpl_group" => "Article - assignation de rédacteurs",

    // Assignation de rédacteur
    "paper_editor_assign_tpl_name" => "Assignation d'un rédacteur",
    "paper_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article vous a été assigné pour édition",

    "paper_section_editor_assign_tpl_name" => "assignation d'un rédacteur -  choix de la rubrique par l’auteur",
    "paper_section_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    "paper_volume_editor_assign_tpl_name" => "assignation d'un rédacteur - choix du volume par l’auteur",
    "paper_volume_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    "paper_suggested_editor_assign_tpl_name" => "assignation d'un rédacteur - suggéré par l'auteur",
    "paper_suggested_editor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - You've been chosen to manage an article",

    // Désassignation de rédacteur
    "paper_editor_unassign_tpl_name" => "Suppression de l'assignation d'un rédacteur",
    "paper_editor_unassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - L'édition d'un article vous a été retirée",


    // PAPER - RELECTURE ****************************************************************************************************************************
    "paper_review_tpl_group" => "Article - relecture",

    // Date limite de rendu de la relecture modifié (rating deadline)
    "paper_updated_rating_deadline_tpl_name" => "Modification de la date limite de rendu de la relecture",
    "paper_updated_rating_deadline_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date limite de rendu de relecture a été modifiée",

    // Suppression de relecteur
    "paper_reviewer_removal_tpl_name" => "Suppression d'un relecteur",
    "paper_reviewer_removal_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre invitation de relecture a été annulée",

    // Invitation de relecteur
    "paper_reviewer_invitation1_tpl_name" => "Inviter un utilisateur à relire un article - relecteur connu par le système",
    "paper_reviewer_invitation1_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Vous avez été invité à relire un article",

    "paper_reviewer_invitation2_tpl_name" => "Inviter un utilisateur à relire un article - utilisateur ayant déjà un compte",
    "paper_reviewer_invitation2_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Vous avez été invité à relire un article",

    "paper_reviewer_invitation3_tpl_name" => "Inviter un utilisateur à relire un article - utilisateur n'ayant pas encore de compte",
    "paper_reviewer_invitation3_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Vous avez été invité à relire un article",

    // Réponse à une invitation de relecture
    "paper_reviewer_acceptation_reviewer_copy_tpl_name" => "Invitation de relecture acceptée (copie à destination du relecteur)",
    "paper_reviewer_acceptation_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Vous venez d'accepter une invitation de relecture",

    "paper_reviewer_acceptation_editor_copy_tpl_name" => "Invitation de relecture acceptée (copie à destination des rédacteurs)",
    "paper_reviewer_acceptation_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un relecteur vient d'accepter de relire cet article",

    "paper_reviewer_refusal_reviewer_copy_tpl_name" => "Invitation de relecture refusée (copie à destination du relecteur)",
    "paper_reviewer_refusal_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Vous venez de refuser une invitation de relecture",

    "paper_reviewer_refusal_editor_copy_tpl_name" => "Invitation de relecture refusée (copie à destination des rédacteurs)",
    "paper_reviewer_refusal_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un relecteur vient de refuser de relire cet article",

    // Relecture terminée - copie destinée au relecteur
    "paper_reviewed_reviewer_copy_tpl_name" => "Relecture terminée (copie à destination du relecteur)",
    "paper_reviewed_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Relecture terminée",

    // Relecture terminée - copie destinée aux rédacteurs (à intégrer)
    "paper_reviewed_editor_copy_tpl_name" => "Relecture terminée (copie à destination des rédacteurs)",
    "paper_reviewed_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Relecture terminée d'un article dont vous êtes responsable",

    // Notification de réassignation à une nouvelle version d'un article (relecteurs)
    "paper_new_version_reviewer_reassign_tpl_name" => "Réassignation d'un relecteur à la nouvelle version d'un article",
    "paper_new_version_reviewer_reassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réassignation de relecture à la nouvelle version d'un article",

    // Notification de réassignation à la version temporaire d'un article (relecteurs)
    "paper_tmp_version_reviewer_reassign_tpl_name" => "Réassignation d'un relecteur à une version temporaire",
    "paper_tmp_version_reviewer_reassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réassignation de relecture à une version temporaire",


    // Notification de réinvitation à une nouvelle version d'un article (relecteurs)
    "paper_new_version_reviewer_reinvitation_tpl_name" => "Ré-invitation d'un relecteur à la nouvelle version d'un article",
    "paper_new_version_reviewer_reinvitation_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Ré-invitation de relecture à la nouvelle version d'un article",

    // Notification de réinvitation à la version temporaire d'un article (relecteurs)
    "paper_tmp_version_reviewer_reinvitation_tpl_name" => "Ré-invitation d'un relecteur à une version temporaire",
    "paper_tmp_version_reviewer_reinvitation_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Ré-invitation de relecture à une version temporaire",

    // Papier accetpté : notifier les relecteurs qui n'ont pas encore achevé leurs relectures.
    "paper_reviewer_paper_accepted_stop_pending_reviewing_tpl_name" => "Version finale acceptée : Inutile de poursuivre le travail de relecture",
    "paper_reviewer_paper_accepted_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Inutile de poursuivre votre travail de relecture",

    // Papier en demande de modifications : notifier les relecteurs qui n'ont pas encore achevé leurs relectures.
    "paper_reviewer_paper_revision_request_stop_pending_reviewing_tpl_name" => "Demande de révision : Inutile de poursuivre le travail de relecture",
    "paper_reviewer_paper_revision_request_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Inutile de poursuivre votre travail de relecture",

    // refus de l'artcile : notifier les relecteurs qui n'ont pas encore achevé leurs relectures.
    "paper_reviewer_paper_refused_stop_pending_reviewing_tpl_name" => "Article refusé : Inutile de poursuivre le travail de relecture",
    "paper_reviewer_paper_refused_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Inutile de poursuivre votre travail de relecture",

    // PAPER - RELANCES AUTOMATIQUES ****************************************************************************************************************************
    "paper_review_reminder_tpl_group" => "Relances automatiques",

    // Relance suite à une invitation de relecteur restée sans réponse - copie destinée au relecteur
    "reminder_unanswered_reviewer_invitation_reviewer_version_tpl_name" => "Invitation de relecteur sans réponse (copie destinée au relecteur)",
    "reminder_unanswered_reviewer_invitation_reviewer_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Invitation de relecture en attente",

    // Relance suite à une invitation de relecteur restée sans réponse - copie destinée au rédacteur
    "reminder_unanswered_reviewer_invitation_editor_version_tpl_name" => "Invitation de relecteur sans réponse (copie destinée au rédacteur)",
    "reminder_unanswered_reviewer_invitation_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Invitation de relecture en attente",


    // Rappel avant date de livraison de relecture - copie destinée au relecteur
    "reminder_before_deadline_reviewer_version_tpl_name" => "Rappel avant date de livraison de relecture (copie destinée au relecteur)",
    "reminder_before_deadline_reviewer_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date de livraison de votre relecture approche !",

    // Rappel avant date de livraison de relecture - copie destinée au rédacteur
    "reminder_before_deadline_editor_version_tpl_name" => "Rappel avant date de livraison de relecture (copie destinée au rédacteur)",
    "reminder_before_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date de livraison de relecture approche !",

    // Relance après date de livraison de relecture - copie destinée au rédacteur
    "reminder_after_deadline_reviewer_version_tpl_name" => "Relance après date de livraison de relecture (copie destinée au relecteur)",
    "reminder_after_deadline_reviewer_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date de livraison de votre relecture est dépassée !",

    // Relance après date de livraison de relecture - copie destinée au relecteur
    "reminder_after_deadline_editor_version_tpl_name" => "Relance après date de livraison de relecture (copie destinée au rédacteur)",
    "reminder_after_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date de livraison de relecture est dépassée !",


    // Rappel avant date limite de modification - copie destinée à l'auteur
    "reminder_before_revision_deadline_author_version_tpl_name" => "Rappel avant date limite de modification (copie destinée à l'auteur)",
    "reminder_before_revision_deadline_author_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date limite de modification de votre article approche !",

    // Rappel avant date limite de modification - copie destinée au rédacteur
    "reminder_before_revision_deadline_editor_version_tpl_name" => "Rappel avant date limite de modification (copie destinée au rédacteur)",
    "reminder_before_revision_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date limite de modification de cet article approche !",

    // Relance après date limite de modification - copie destinée à l'auteur
    "reminder_after_revision_deadline_author_version_tpl_name" => "Relance après date limite de modification (copie destinée à l'auteur)",
    "reminder_after_revision_deadline_author_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date limite de modification de votre article est dépassée !",

    // Relance après date limite de modification - copie destinée au rédacteur
    "reminder_after_revision_deadline_editor_version_tpl_name" => "Relance après date limite de modification (copie destinée au rédacteur)",
    "reminder_after_revision_deadline_editor_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La date limite de modification de cet article est dépassée !",

    // Pas assez de relecteurs
    "reminder_not_enough_reviewers_tpl_name" => "Nombre de relecteurs insuffisant",
    "reminder_not_enough_reviewers_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Nombre de relecteurs insuffisant",

    // article bloqué à l'état accepté
    'reminder_article_blocked_in_accepted_state_editor_version_tpl_name' => "Article bloqué à l'état accepté (copie destinée au rédacteur)",
    'reminder_article_blocked_in_accepted_state_editor_version_mail_subject' => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Article bloqué à l'état accepté !",


    // PAPER - COMMENTAIRES ****************************************************************************************************************************
    "paper_comment_tpl_group" => "Article - commentaires",

    // Commentaire d'un relecteur sur un article – copie destinée à l'auteur
    "paper_comment_author_copy_tpl_name" => "Commentaire d'un relecteur sur un article (copie destinée à l'auteur)",
    "paper_comment_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un relecteur a posté un commentaire concernant votre article",

    // Commentaire d'un relecteur sur un article – copie destinée aux rédacteurs
    "paper_comment_editor_copy_tpl_name" => "Commentaire d'un relecteur sur un article (copie destinée aux rédacteurs)",
    "paper_comment_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un relecteur a posté un commentaire concernant un article dont vous êtes responsable",

    // Réponse de l'auteur à un commentaire d'un relecteur – copie destinée au relecteur
    "paper_comment_answer_reviewer_copy_tpl_name" => "Réponse de l'auteur au commentaire d'un relecteur (copie destinée au relecteur)",
    "paper_comment_answer_reviewer_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réponse de l'auteur à votre commentaire",

    // Réponse de l'auteur à un commentaire d'un relecteur – copie destinée aux rédacteurs
    "paper_comment_answer_editor_copy_tpl_name" => "Réponse de l'auteur au commentaire d'un relecteur (copie destinée aux rédacteurs)",
    "paper_comment_answer_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réponse de l'auteur à un commentaire sur un article dont vous êtes responsable",

    // Commentaires des rédacteurs
    "paper_comment_by_editor_editor_copy_tpl_name" => "Commentaire d'un rédacteur sur un article (copie destinée aux rédacteurs)",
    "paper_comment_by_editor_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un rédacteur a posté un commentaire concernant un article dont vous êtes responsable",

    // Communication auteur vers éditeurs assignés
    "paper_comment_from_author_to_editor_editor_copy_tpl_name" => "Message de l'auteur aux rédacteurs assignés (copie destinée aux rédacteurs)",
    "paper_comment_from_author_to_editor_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - nouveau message concernant %%ARTICLE_RELATIONSHIP%%",

    // Réponse d'un éditeur à un message de l'auteur
    "paper_editor_response_to_author_author_copy_tpl_name" => "Réponse d'un rédacteur à votre message (copie destinée à l'auteur)",
    "paper_editor_response_to_author_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un rédacteur a répondu à votre message",

    // Commentaire de l'auteur / lettre d'accompagnement
    'paper_author_comment_editor_copy_tpl_name' => "Commentaire de l'auteur / lettre d'accompagnement",
    'paper_author_comment_editor_copy_mail_subject' => "Commentaire de l'auteur / lettre d'accompagnement",


    // PAPER - SUGGESTIONS D'UN REDACTEUR ***************************************************************************************************************
    "paper_editor_suggestion_tpl_group" => "Article - suggestions d'un rédacteur",

    // Un rédacteur suggère d'accepter l'article
    "paper_suggest_acceptation_tpl_name" => "Suivi d'un article : suggestion d'acceptation",
    "paper_suggest_acceptation_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un rédacteur suggère l'acceptation d'un article",

    // Un rédacteur suggère de refuser l'article
    "paper_suggest_refusal_tpl_name" => "Suivi d'un article : suggestion de refus",
    "paper_suggest_refusal_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un rédacteur suggère le refus d'un article",

    // Un rédacteur suggère de demander une nouvelle version de l'article
    "paper_suggest_new_version_tpl_name" => "Suivi d'un article : suggestion de demande de modifications",
    "paper_suggest_new_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un rédacteur suggère la demande de modification d'un article",


    // PAPER - DEMANDES DE MODIFICATIONS *****************************************************************************************************************
    "paper_revision_tpl_group" => "Article - demandes de modifications",

    // Un rédacteur en chef demande des modifications sur l'article
    "paper_revision_request_tpl_name" => "Demande de modifications d'un article",
    "paper_revision_request_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Demande de modifications de votre article",

    // Un rédacteur en chef demande des modifications mineures sur l'article
    "paper_minor_revision_request_tpl_name" => "Demande de modifications mineures d'un article",
    "paper_minor_revision_request_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Demande de modifications mineures de votre article",

    // Un rédacteur en chef demande des modifications majeures sur l'article
    "paper_major_revision_request_tpl_name" => "Demande de modifications majeures d'un article",
    "paper_major_revision_request_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Demande de modifications majeures de votre article",

    // Réponse de l'auteur à une demande de modifications : pas de modifications
    "paper_revision_answer_tpl_name" => "Réponse à une demande de modifications : commentaire",
    "paper_revision_answer_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réponse à une demande de modifications : commentaire",

    // Réponse de l'auteur à une demande de modifications : version temporaire de l'article
    "paper_tmp_version_submitted_tpl_name" => "Réponse à une demande de modifications : version temporaire",
    "paper_tmp_version_submitted_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réponse à une demande de modifications : version temporaire",

    // Réponse de l'auteur à une demande de modifications : nouvelle version de l'article
    "paper_new_version_submitted_tpl_name" => "Réponse à une demande de modifications : nouvelle version",
    "paper_new_version_submitted_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Réponse à une demande de modifications : nouvelle version",


    // PAPER - DECISION FINALE *****************************************************************************************************************************
    "paper_final_decision_tpl_group" => "Article - décision finale",

    //accepted ask authors final version
    "paper_accepted_ask_authors_final_version_tpl_name" => "Accepté, demande de la version finale à l'auteur",
    "paper_accepted_ask_authors_final_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Article déjà accepté, demande de la version finale",

    // accept paper
    "paper_accepted_tpl_name" => "Article accepté - Copie à destination de l'auteur",
    "paper_accepted_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a été accepté",

    // accept tmp paper
    "paper_accepted_tmp_version_tpl_name" => "Article accepté dans sa version temporaire",
    "paper_accepted_tmp_version_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La version temporaire de votre article a été acceptée",

    // accept tmp paper - managers copy
    "paper_accepted_tmp_version_managers_copy_tpl_name" => "Article accepté dans sa version temporaire (copie destinée aux managers de l'article)",
    "paper_accepted_tmp_version_managers_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La version temporaire d'un article a été acceptée",

    'paper_formatted_by_journal_waiting_author_validation_tpl_name' => "Dépôt de la mise en forme par la revue, en attente de validation par l'auteur",
    'paper_formatted_by_journal_waiting_author_validation_mail_subject' => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - mise en forme en attente de validation",

    // reject paper
    "paper_refused_tpl_name" => "Article refusé - Copie à destination de l'auteur",
    "paper_refused_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a été refusé",

    "paper_refused_editors_copy_tpl_name" => "Article refusé - Copie à destination des rédacteurs",
    "paper_refused_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article vient d'être refusé",

    // ask other editors
    "paper_ask_other_editors_tpl_name" => "Demander l'avis d'autres rédacteurs",
    "paper_ask_other_editors_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre avis ?",

    // publish paper - author copy
    "paper_published_author_copy_tpl_name" => "Article publié - Copie à destination de l'auteur",
    "paper_published_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a été publié",

    // publish paper - editor copy
    "paper_published_editor_copy_tpl_name" => "Article publié - Copie à destination des rédacteurs",
    "paper_published_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article vient d'être publié",

    // Papier accepté : notifier les relecteurs qui n'ont pas encore achevé leurs relectures.
    "paper_reviewer_paper_published_stop_pending_reviewing_tpl_name" => "Article publié : Inutile de poursuivre le travail de relecture",
    "paper_reviewer_paper_published_stop_pending_reviewing_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Inutile de poursuivre votre travail de relecture",

    // Le rédacteur souhaite de ne plus gérer l'article
    "paper_editor_refused_monitoring_tpl_name" => "Arrêt de la supervision de l'article par le rédacteur",
    "paper_editor_refused_monitoring_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un rédacteur souhaite ne plus gérer l'article",

    // Accept paper - editor copy
    "paper_accepted_editors_copy_tpl_name" => "Article accepté - Copie à destination des rédacteurs",
    "paper_accepted_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Nouvel article accepté",

    // PAPER - Abandon publication process
    "abandon_publication_process_tpl_group" => "Article - Abandonner le processus de publication",

    // Abandonner le processus de publication d'un article  – copie destinée à l'auteur
    "paper_abandon_publication_author_copy_tpl_name" => "Abandonner le processus de publication (copie destinée à l'auteur)",
    "paper_abandon_publication_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Le processus de publication de votre article a été stoppé",

    // Abandonner le processus de publication d'un article, cas où le rédacteur a déjà été assigné - copie destinée aux rédacteurs
    "paper_abandon_publication_editor_copy_tpl_name" => "Abandonner le processus de publication (copie à destination des rédacteurs)",
    "paper_abandon_publication_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Le processus de publication de l'article a été stoppé",

    // Abandonner le processus de publication d'un article par l'auteur lui-même  – copie destinée à l'auteur
    "paper_abandon_publication_by_author_author_copy_tpl_name" => "Abandonner le processus de publication par l'auteur lui-même (copie destinée à l'auteur)",
    "paper_abandon_publication_by_author_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Le processus de publication de votre article a été stoppé",

    // Abandonner le processus de publication d'un article, cas où le redacteur n'a pas encore été assigné – copie destinée aux rédacteurs en chef, administrateurs, secrétaires de rédaction
    "paper_abandon_publication_no_assigned_editors_tpl_name" => "Abandonner le processus de publication (cas où le rédacteur n'a pas encore été assigné)",
    "paper_abandon_publication_no_assigned_editors_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Le processus de publication de l'article a été stoppé",

    // PAPER - Continue publication process
    "continue_publication_process_tpl_group" => "Article - Reprendre le processus de publication",

    // Reprendre le processus de publication d'un article  – copie destinée à l'auteur
    "paper_continue_publication_author_copy_tpl_name" => "Reprendre le processus de publication (copie destinée à l'auteur)",
    "paper_continue_publication_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Le processus de publication de votre article a été repris",

    // Reprendre le processus de publication d'un article - copie destinée aux rédacteurs
    "paper_continue_publication_editor_copy_tpl_name" => "Reprendre le processus de publication (copie à destination des rédacteurs)",
    "paper_continue_publication_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Le processus de publication de l'article a été repris",
    // Suppression d'un relecteur suite à l'arrêt du processus de publication
    "paper_abandon_publication_reviewer_removal_tpl_name" => "Abandonner le processus de publication - Suppression d'un relecteur",
    "paper_abandon_publication_reviewer_removal_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre invitation de relecture a été annulée",

    // PAPER - Assignation de préparateur de copie
    "paper_copy_editing_tpl_group" => "Article - préparation de copie",

    // Assignation de préparateur de copie - copie destinée au préparateur de copie
    "paper_copyeditor_assign_tpl_name" => "Assignation d'un préparateur de copie (copie destinée au préparateur de copie)",
    "paper_copyeditor_assign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article vous a été assigné pour la préparation de copie",

    // Assignation de préparateurs de copie - copie destinée aux rédacteurs
    "paper_copyeditor_assign_editor_copy_tpl_name" => "Assignation d'un préparateur de copie (copie destinée aux rédacteurs)",
    "paper_copyeditor_assign_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Nouvel article assigné pour la préparation de copie",

    // Assignation de préparateurs de copie - copie destinée à l'auteur
    "paper_copyeditor_assign_author_copy_tpl_name" => "Assignation d'un préparateur de copie (copie destinée à l'auteur)",
    "paper_copyeditor_assign_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a été assigné pour la préparation de copie",

    // Désassignation de rédacteur - copie destinée au préparateur de copie
    "paper_copyeditor_unassign_tpl_name" => "Suppression de l'assignation d'un préparateur de copie (copie destinée au préparateur de copie)",
    "paper_copyeditor_unassign_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - La préparation de copie d'un article vous a été retirée",

    // Copy editing : demande de sources - copie rédacteur
    "paper_ce_waiting_for_author_sources_editor_copy_tpl_name" => "Demande de sources auteur (copie destinée aux rédacteurs)",
    "paper_ce_waiting_for_author_sources_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - En attente des sources auteur",

    // Copy editing : demande de sources - copie auteur
    "paper_ce_waiting_for_author_sources_author_copy_tpl_name" => "Demande de sources auteur (copie destinée à l'auteur)",
    "paper_ce_waiting_for_author_sources_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Demande de sources auteur",

    // Copy_editing : sources auteurs déposées - préparateur de copie et rédacteurs
    "paper_ce_author_sources_deposed_response_copyeditors_and_editors_copy_tpl_name" => "Sources auteurs déposées (copie destinée aux préparateur de copies et aux rédacteurs)",
    "paper_ce_author_sources_deposed_response_copyeditors_and_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Sources auteurs déposées",

    //Copy editing : sources auteurs déposées - copy auteur
    "paper_ce_author_sources_deposed_response_author_copy_tpl_name" => "Sources auteurs déposées (copie destinée à l'auteur) ",
    "paper_ce_author_sources_deposed_response_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Sources déposées ",

    // Copy editing : en attente de la mise en forme par l'auteur - copy rédacteurs  & copy editors
    "paper_ce_waiting_for_author_formatting_editor_and_copyeditor_copy_tpl_name" => "Demande de mise en forme par l'auteur (copie destinée aux préparateurs de copie et aux rédacteurs)",
    "paper_ce_waiting_for_author_formatting_editor_and_copyeditor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - en attente de version finale auteur",

    // Copy editing : en attente de la mise en forme par l'auteur - copie auteur
    "paper_ce_waiting_for_author_formatting_author_copy_tpl_name" => "Demande de mise en forme par l'auteur (copie destinée à l'auteur)",
    "paper_ce_waiting_for_author_formatting_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - en attente de la mise en forme",

    //Copy editing : réponse à une demande de la version finale - copie destinée aux rédacteurs & préparateurs de copie
    "paper_ce_author_vesrion_finale_deposed_editor_and_copyeditor_copy_tpl_name" => "Réponse à une demande de la version finale (copie destinée au rédacteur & préparateur de copie)",
    "paper_ce_author_vesrion_finale_deposed_editor_and_copyeditor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Version finale déposée par l'auteur",

    //Copy editing : réponse à une demande de la version finale - copie destinée à l'auteur
    "paper_ce_author_vesrion_finale_deposed_author_copy_tpl_name" => "Réponse à une demande de la version finale(copie destinée à l'auteur)",
    "paper_ce_author_vesrion_finale_deposed_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - votre réponse à bien été enregistrée",

    // Copy editing: prêt à publier
    "paper_ce_accepted_final_version_copyeditor_and_editor_copy_tpl_name" => "Validation mise en fome (copie destinée au rédacteur & préparateur de copie",
    "paper_ce_accepted_final_version_copyeditor_and_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - version finale validée",

    "paper_ce_accepted_final_version_author_copy_tpl_name" => "Validation de la mise en forme (copie destinée à l'auteur)",
    "paper_ce_accepted_final_version_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - version finale validée",

    "paper_ce_review_formatting_deposed_editor_and_copyeditor_copy_tpl_name" => "Dépôt de la mise en forme par la revue (copie destinée au rédacteur & préparateur de copie)",
    "paper_ce_review_formatting_deposed_editor_and_copyeditor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - la mise en forme par la revue vient d'ête déposée",

    "paper_ce_review_formatting_deposed_author_copy_tpl_name" => "Dépôt de la mise en forme par la revue (copié déstinée à l'auteur)",
    "paper_ce_review_formatting_deposed_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - votre article vient d'être mis en forme",

    // Confirmation de la mise à jour d'un article – copie destinée aux rédacteurs
    "paper_submission_updated_editor_copy_tpl_name" => "Mise à jour d'un article - Copie à destination des rédacteurs",
    "paper_submission_updated_editor_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un article vient d'être mis à jour",

    // Confirmation de la mise à jour d'un article – copie destinée à l'auteur
    "paper_submission_updated_author_copy_tpl_name" => "Mise à jour d'un article - Copie à destination de l'auteur",
    "paper_submission_updated_author_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Votre article a bien été mis à jour",

    // git #230
    // Notification de la soumission d'un article – copie destinée aux rédacteurs en chef, administrateurs et secrétaires de rédaction (selon les paramètres de notification de la revue),
    "paper_submission_other_recipient_copy_tpl_name" => "Nouvel article - Copie à destination des rédacteurs en chefs, administrateurs et secrétaires de rédaction ",
    "paper_submission_other_recipient_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Un nouvel article vient d'être proposé",

    //gitHub #513
    "reminder_reviewed_article_editors_copy_tpl_name" => "Article bloqué à l'état relu (copie à destination des rédacteurs | rédacteurs en chef)",
    "reminder_reviewed_article_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Article relu, decision requise",
    "reminder_submitted_article_editors_copy_tpl_name" => "Article bloqué à l'état initial [soumis] - (copie à destination des rédacteurs | rédacteurs en chef)",
    "reminder_submitted_article_editors_copy_mail_subject" => "%%REVIEW_CODE%% #%%ARTICLE_ID%% - Nouvelle soumission, decision requise",


];
