-- Register the alternative-pipeline mail templates so that the editor/copy-editor
-- transition modals (state 4 → 34, 35 → 36, 36 → 37, 39 → publish, etc.) are
-- pre-filled with the matching subject/body coming from
-- application/languages/{en,fr}/emails/paper_alt_*.phtml.
INSERT INTO `MAIL_TEMPLATE` (`ID`, `PARENTID`, `RVID`, `RVCODE`, `KEY`, `TYPE`, `POSITION`) VALUES
(NULL, NULL, NULL, NULL, 'paper_alt_request_final_version_author_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_final_version_deposit_author_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_final_version_deposit_editor_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_start_layout_editing_copyeditor_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_incorrect_password_author_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_incorrect_latex_author_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_send_proof_to_author_author_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_return_to_layout_editing_copyeditor_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_author_approved_proof_editor_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_author_rejected_proof_editor_copy', 'paper_copy_editing', NULL),
(NULL, NULL, NULL, NULL, 'paper_alt_approve_for_publication_author_copy', 'paper_copy_editing', NULL);
