# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!--
### Added
### Changed
### Deprecated
### Fixed
### Removed
### Security
### Performances
-->

## Archives

- [2025](changelogs/CHANGELOG-2025.md)
- [2024](changelogs/CHANGELOG-2024.md)
- [2023](changelogs/CHANGELOG-2023.md)
- [2022](changelogs/CHANGELOG-2022.md)
- [2021](changelogs/CHANGELOG-2021.md)

## Unreleased
### Added

- [#1388](https://github.com/CCSDForge/episciences/issues/1388), [#1390](https://github.com/CCSDForge/episciences/pull/1390): Console command `papers:delete` deletes paper versions of a journal (`--rvcode`), with their files and every related row; it asks to type a confirmation phrase, so it never deletes in non-interactive mode, and `--dry-run` only checks the papers. Deletions and failures, with their cause, are logged to `papers_delete-YYYY-MM-DD.log`. Replaces `scripts/DeletePaper.php`.
- [#1362](https://github.com/CCSDForge/episciences/pull/1362): Console commands `user:purge-expired-tokens` (daily cron) and `user:import-legacy-tokens` (one-off, right after creating the `user_token` table, see `src/mysql/2026-10-23-create-user-token-table.sql`).
- [#1361](https://github.com/CCSDForge/episciences/pull/1361): Security log of denied accesses: a daily JSON file (`logs/security-YYYY-MM-DD.log`, kept 180 days) records access refusals, CSRF failures, invalid tokens, unsafe file paths and failed logins, as well as existing pages missing from the access rules.
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): BAOBAB submissions now store the licence, the ROR of affiliations (including an organisational author's own ROR) and the translated abstracts filed as other descriptions.
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): Links to HAL found in a repository's related identifiers are now stored as linked publications, or as linked software for a HAL software deposit.
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): BAOBAB submissions accept an ARK resolver URL (e.g. `https://pidslink.ren.africa/ark:/50962/...`) as well as a bare ARK or a record URL.
- [#303](https://github.com/CCSDForge/episciences/issues/303), [#1314](https://github.com/CCSDForge/episciences/pull/1314): HAL papers: authors are now built from the HAL TEI (middle names, ORCID, affiliations with their ROR) when it lists the same authors as the OAI-DC record; existing authors are completed with their HAL middle names. Initials are always written with a period (`Eva C. Smith`), whether HAL returns `C` or `C.`.
- [#1314](https://github.com/CCSDForge/episciences/pull/1314): `enrichment:creators -v` lists the author corrections made from HAL TEI; with `--dry-run` it lists them without writing anything.
- [#1285](https://github.com/CCSDForge/episciences/pull/1285) Read-only maintenance mode (`EPISCIENCES.READ_ONLY_MODE` in `pwd.json`, optional `READ_ONLY_MODE_START` / `READ_ONLY_MODE_END` planned schedule): public consultation stays available while the web application sends no write query to the database. Only public guest pages that do not write are served (`GET`/`HEAD`, whoever is logged in, OAI-PMH unfiltered); everything else answers HTTP 503 with `Retry-After`; logging out stays possible; paper visits are not recorded. A single maintenance banner (journals and portal) shows the maintenance in progress, announces an upcoming one from its planned start, or the free `RENDER_ALERT_BANNER` message (now Bootstrap-styled and optional). See `docs/read-only-mode.md`.
- [#314](https://github.com/CCSDForge/episciences/issues/314), [#1279](https://github.com/CCSDForge/episciences/pull/1279): Add a "Switch back to my account" action (`/user/unsu`) to directly restore the original user session when impersonating another account; secure switch-user sessions with read-only restrictions on account mutations, and record events in a local audit log (`user_su_log`).
- [#841](https://github.com/CCSDForge/episciences/issues/841), [#893](https://github.com/CCSDForge/episciences/issues/893), [#1008](https://github.com/CCSDForge/episciences/pull/1008): Allow users to select and normalize the licence of a paper using SPDX identifiers.
- [#974](https://github.com/CCSDForge/episciences/issues/974) The unanswered reviewer invitation reminder and the reminders before and after the reviewing deadline can now be sent to chief editors and editorial secretaries.
- [#1216](https://github.com/CCSDForge/episciences/pull/1216) Paper illustration (graphical abstract): required text alternative (WCAG 1.1.1) and optional free-text license, stored as `graphical_abstract_alt` and `graphical_abstract_license` next to `graphical_abstract_file` in `PAPERS.DOCUMENT` and exposed in the JSON v2 export; the license is shown as the image caption.
- [#865](https://github.com/CCSDForge/episciences/issues/865), [#1208](https://github.com/CCSDForge/episciences/pull/1208) Allow administrators and chief editors to change the contributor (owner) of a paper. A modal dialog lets users search for and select a new contributor, with an option to add the former contributor as co-author (checked by default) so they continue receiving notifications. Both the new and former contributors receive email notifications. The action is logged with full details (old/new contributor, co-author status) and displayed in the paper history timeline.
- [#1168](https://github.com/CCSDForge/episciences/pull/1168) Add `journal:create` CLI command (`scripts/CreateJournalCommand.php`, `make create-journal`) to create a new journal — inserting its `REVIEW` row, cloning settings/menu/pages/appearance from a template journal (excluding editorial identity, outgoing notifications and secrets), creating its `data/<rvcode>/` directory tree, and granting an existing user the administrator role. Business logic lives in `Episciences\Journal\Provisioning\{JournalSpec,ReviewRowWriter,SettingsCloner,DataDirectoryProvisioner,WebsiteCloner,ClonableWebsiteStyle,ClonableWebsiteHeader,PagesCloner,AdminRoleAssigner,Report,JournalCreator}`. See `docs/journal-provisioning.md`.
- [#1168](https://github.com/CCSDForge/episciences/pull/1168) Add `journal:seed-demo` CLI command (`scripts/SeedJournalDemoCommand.php`, `make seed-journal-demo`) to seed a journal with a small set of real, fixed HAL/arXiv/Zenodo/BAOBAB submissions (`scripts/importSamples/demo-papers.csv`) across 3 volumes/sections in submitted/accepted/published status, for sandbox testing. Reuses the `import:papers` machinery via `Episciences\Journal\Demo\{DemoCsvRewriter,DemoSeeder}`.
- [#1164](https://github.com/CCSDForge/episciences/pull/1164), [#1167](https://github.com/CCSDForge/episciences/pull/1167) Add BAOBAB (WACREN InvenioRDM instance) as a submission repository (`Episciences_Repositories_BAOBAB_Hooks`): files, authors, access status and the concept identifier are fetched over the REST API, and the Dublin Core body is compiled from the DataCite serializer, fetched over OAI-PMH now that BAOBAB's endpoint has been fixed (content negotiation on the REST API was used as a workaround while it was broken).
- [#1152](https://github.com/CCSDForge/episciences/pull/1152) Add `export:papers` CLI command (`scripts/ExportPapersCommand.php`, `make export-papers`) to export papers to a CSV file, in the same format `import:papers` reads — for a journal, optionally filtered by volume, section, publication year, docid(s), archive identifier+version, status, repoid, uid, or a raw SQL `WHERE` clause.
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Add `import:papers` CLI command (`scripts/ImportPapersCommand.php`, `make import-papers`) to import new papers or update existing ones from a CSV file.
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Add `num` and `year` CSV column support to `import:volumes` (`scripts/ImportVolumesCommand.php`).
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Add `--rvcode` option, `--rvid` integer validation, and batch processing support via `--config` (`journals.ini`) in `ZbjatsZipperCommand` (`zbjats:zip`).
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Cache PDF and XML responses with Symfony Cache in `ZbjatsTools` and support new-front URLs.
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Allow anonymous access to public review report attachments for open peer review.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Add `CheckDoajJournalsCommand` CLI command to check journal presence in DOAJ, track published article count, and enforce API rate limits.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Add constraint description helper text to the volume year field in volume management.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Add TomSelect component to paper filters on `/paper/submitted` and `/paper/ratings`.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Configure local SonarQube support (`make sonar`) and XML-based PHPUnit coverage report generation.
- [#1140](https://github.com/CCSDForge/episciences/issues/1140), [#1141](https://github.com/CCSDForge/episciences/pull/1141) Export secondary volumes under `database.current.secondary_volumes` in the JSON v2 paper export.
- [#1089](https://github.com/CCSDForge/episciences/pull/1089) Asynchronous Solr indexing pipeline powered by Symfony Messenger and Doctrine DBAL transport, replacing the legacy polling queue with automatic retries, bounded failure handling, and `episciences:worker`/`episciences:queue` CLI commands.
- [#1089](https://github.com/CCSDForge/episciences/pull/1089) Automatic Solr reindexing on primary volume or section updates, volume/section metadata changes, and paper enrichments (authors, funding, citations, datasets).
- [#1149](https://github.com/CCSDForge/episciences/pull/1149) On-demand Next.js cache revalidation for frontend pages, articles, volumes, sections, news, and editorial board member updates, with the `next:revalidate-cache` CLI command.
- [#1149](https://github.com/CCSDForge/episciences/pull/1149): Add Docker Compose worker services (`solr-worker`, `worker-next-revalidation`) and systemd service units for background queue processing.
- [#1154](https://github.com/CCSDForge/episciences/pull/1154) Migrate OpenAIRE integration to OpenAIRE Graph API v3 with AAI authentication (client-credentials token flow) and rate-limit handling (`OpenAireApiClient`).
- [#1163](https://github.com/CCSDForge/episciences/pull/1163) Migrate Scholexplorer dataset link enrichment to API v3 (`ScholexplorerApiClient`, `enrichment:links`).
- [#1156](https://github.com/CCSDForge/episciences/pull/1156) Migrate legacy COAR Notify inbox processing script (`scripts/process_inbox_notifications.php`) to the Symfony Console command `notify:process-inbox` (`ProcessInboxNotificationsCommand`).
- [#1150](https://github.com/CCSDForge/episciences/pull/1150) Allow customizing the review code shown in automatic email subjects via the journal settings (`Episciences_Review`), capped to 100 characters.
- [#1163](https://github.com/CCSDForge/episciences/pull/1163): Add `title_translations` to the `PAPERS.DOCUMENT` JSON column.
- [#1163](https://github.com/CCSDForge/episciences/pull/1163): Display bibliographic references count in `papers:update-document` with `-vv`.
- [#1214](https://github.com/CCSDForge/episciences/pull/1214) Add volume editors to CrossRef XML export. When a paper belongs to a volume with assigned editors, their names and ORCID identifiers are now included in the `<journal_issue><contributors>` block.

### Fixed

- Logging in: the page displayed after logging in only keeps the parameters of its address, not the content of the login form.
- [#1403](https://github.com/CCSDForge/episciences/pull/1403): Inviting a reviewer: the number of invitations of each user counts each invitation once; an answer or a cancellation was counted as another invitation.
- [#1402](https://github.com/CCSDForge/episciences/pull/1402): Accepting a paper cancels the reviewer invitations without answer as removing a reviewer does: the invitation itself is now cancelled, not only the assignment, so that it is no longer seen as pending, then expired (e.g. by the statistics of the API).
- [#1385](https://github.com/CCSDForge/episciences/pull/1385): Temporary version: a file whose upload failed is no longer silently dropped; nothing is recorded and the author is asked to attach the files again. Failed uploads are now always displayed in the file upload widget. Answering without any file is still possible.
- [#1386](https://github.com/CCSDForge/episciences/pull/1386): Tighten the checks of the attachment upload and removal endpoints. Copy-editing answer: like a temporary version, it is no longer saved without a file the author attached; a new file never takes the name of a file already sent. **Deployment**: set `UPLOADED_FILE.HMAC_SECRET` in `config/pwd.json` (at least 64 characters, e.g. `openssl rand -hex 32`); without it, attachment uploads are refused.
- [#1388](https://github.com/CCSDForge/episciences/issues/1388), [#1390](https://github.com/CCSDForge/episciences/pull/1390): Deleting a paper version also deletes its authors, declared files, data descriptor, DOI queue entry, assignments with their invitations and answers, reviewers without an account no longer invited to any paper, repository files, linked datasets with their metadata, citations, classifications, SPDX licence code and sent e-mails log. The deletion is all or nothing: if one row cannot be deleted, nothing is. Rows shared by every version of a paper (authors, DOI queue, volume position, projects, conflicts of interest) are deleted with the last version only: deleting one version no longer removes the volume position and projects of the others. A cited paper can now be deleted.
- [#1397](https://github.com/CCSDForge/episciences/pull/1397): Paper history: a chief editor assigned automatically to a new paper is shown with the reason of the assignment ("Chief editor"), like section, volume and suggested editors.
- [#1398](https://github.com/CCSDForge/episciences/pull/1398): Inbox (COAR Notify): a new version answers the latest revision request of the paper, no longer its latest comment of any type. The reviewers are reinvited and the paper kept accepted only when this request says so, and the editors of the paper are notified even when a more recent comment, such as a cover letter, follows the request.
- [#1394](https://github.com/CCSDForge/episciences/pull/1394): A paper submitted through the inbox (COAR Notify) is assigned to the chief editors when the journal assigns them automatically, as on the web; they receive the editor e-mail, not the copy sent to the other recipients. On the web and through the inbox, an editor whose assignment failed is no longer notified as assigned nor recorded in the paper history. A new paper is again notified to the chief editors, secretaries and administrators when the journal checks none of these roles in its notification settings, even with editors assigned automatically. Co-authors of a new version submitted through the inbox are reassigned to the right journal.
- [#1393](https://github.com/CCSDForge/episciences/pull/1393): Papers management and other tables loaded page by page (my papers, ratings, mail history): the header stays aligned with the columns when the paper status icons load after the table.
- [#1393](https://github.com/CCSDForge/episciences/pull/1393): Tables with a horizontal scrollbar (papers management, my papers, ratings, mail history): the header stays aligned with the columns whatever the rules of the journal stylesheet for div elements.
- [#1343](https://github.com/CCSDForge/episciences/pull/1343): the "-REFUSED" suffix of a refused paper submitted again is no longer shown, whatever the status of the paper (e.g. its previous versions made obsolete), nor sent to the repositories (link to the document, HAL enrichment scripts); it is no longer removed from the database when the paper is saved, and it is removed again when the new submission is not saved; the COAR Notify debug and dry-run modes no longer add it (`Episciences_Paper::getIdentifier()`, `Episciences_Paper::restoreRefusedIdentifier()`).
- [#1341](https://github.com/CCSDForge/episciences/pull/1341): submitting a refused paper again only renames ("-REFUSED" suffix) the versions of that paper in its journal, never another paper sharing its identifier, e.g. the same document accepted in another journal (`Episciences_PapersManager::renameIdentifier()`).

### Removed

- [#1388](https://github.com/CCSDForge/episciences/issues/1388), [#1390](https://github.com/CCSDForge/episciences/pull/1390): Remove the legacy `scripts/DeletePaper.php` script (replaced by the `papers:delete` console command), which reported a deletion as successful even when it had failed.

## v1.0.58.1 - 2026-10-29

### Changed
- [#1396](https://github.com/CCSDForge/episciences/pull/1396): volume management: increase volume number max length to 20 characters and sanitize input while preserving "0".
- Update Composer dependencies.

### Fixed
- [#1383](https://github.com/CCSDForge/episciences/pull/1383): search: ignore invalid, unknown or out-of-range filter parameters.

## v1.0.58 - 2026-10-25

### Changed

- [#1362](https://github.com/CCSDForge/episciences/pull/1362): Account activation and password links are now stored in the application database, can be used only once and expire; lifetimes are configurable in `pwd.json` (`USER_TOKEN` section, see `docs/console-commands.md`).
- [#1362](https://github.com/CCSDForge/episciences/pull/1362): Lost password on an Episciences account not activated yet sends a new activation link.
- [#1362](https://github.com/CCSDForge/episciences/pull/1362): Changing the password or the e-mail address of an account revokes its pending account links.
- [#1362](https://github.com/CCSDForge/episciences/pull/1362): Updated the messages of the lost password and lost username pages.
- [#1252](https://github.com/CCSDForge/episciences/pull/1252): OpenCitations client migrated to API v2 (DOI sent with its `doi:` scheme, throttling and retry on HTTP 429).
  **Deployment**: set `OPENCITATIONS.APIURL` to `https://api.opencitations.net/index/v2/citations/` in
  `config/pwd.json` (a warning is logged while the v1 URL is still configured).
- [#1004](https://github.com/CCSDForge/episciences/issues/1004), [#1212](https://github.com/CCSDForge/episciences/pull/1212): The language of abstracts and keywords on the paper pages is shown as a badge with the language name, in the interface language, instead of an ISO code in brackets.
- [#1319](https://github.com/CCSDForge/episciences/pull/1319): Reviewer invitation links now carry a per-invitation key; links of invitations sent before this change keep working.
  Linking an invitation to another account is recorded in the paper history.
  **Deployment**, before deploying the code on every environment: apply `src/mysql/2026-10-16-user_invitation_token.sql`
  and set `REVIEWER_INVITATION.HMAC_SECRET` in `config/pwd.json` (e.g. `openssl rand -hex 32`); without it, new
  invitations are sent without a key (former links) and a warning is logged.
- [#1339](https://github.com/CCSDForge/episciences/pull/1339): the application now requires PHP 8.3: the Composer constraint, the Docker image and the continuous
  integration use PHP 8.3, the properties that were created dynamically (deprecated since PHP 8.2)
  are now declared, the deprecated `utf8_encode()` is no longer used, and the PHP 8.2 and 8.3 polyfills are no longer direct dependencies.
- [#1336](https://github.com/CCSDForge/episciences/pull/1336): Symfony components upgraded from 6.4 to 7.4 (LTS): console commands declare their name and
  description with the `#[AsCommand]` attribute instead of the `$defaultName` property, which
  Symfony 7 no longer reads, and are registered with `Application::addCommand()`. The new global
  `--silent` option of the console commands also stops the log lines written to the standard output,
  like `--quiet`. Messenger now randomises each retry delay of the `solr_index` and
  `next_revalidation` queues by ±10%.
- [#1216](https://github.com/CCSDForge/episciences/pull/1216) "Graphical Abstract" is renamed "Illustration or Graphical Abstract" and uses FilePond (self-hosted webpack entry imported from #1108) instead of a bare file input; the maximum size goes from 100 KB to 500 KB. Validation reuses `Zend_Validate_File_Size`/`Zend_Validate_File_Extension` and `Episciences_Form_Validate_MimeType` (`Episciences\Paper\GraphicalAbstract\GraphicalAbstractValidator`), storage moves to `Episciences\Paper\GraphicalAbstract\GraphicalAbstractRepository`.
- [#1216](https://github.com/CCSDForge/episciences/pull/1216) The illustration of a paper can only be changed on the version in progress, by secretaries, assigned editors and guest editors, and the author and co-authors (`Episciences\Paper\GraphicalAbstract\GraphicalAbstractPolicy`): never on a refused, obsolete or non-latest version, and on a published version only by a chief editor or an administrator. The image URL carries its modification time, and a failed save no longer leaves the previous image deleted.
- [#1215](https://github.com/CCSDForge/episciences/pull/1215) Move the volume DOAJ export button from the public volume page to the volume edit page (next to "Review Grids") and add a button to download the complete volume as a single PDF. Buttons are disabled until the files are generated (`doaj:export-volumes`, `volume:merge-pdf`); URLs are prefixed with the journal code when the journal has switched to the new front-end.
- [#1212](https://github.com/CCSDForge/episciences/pull/1212): Language names now come from CLDR through Symfony Intl (`Ccsd_Locale::getLanguageName()`) instead of the `lang_*` keys of the Zend dictionaries (`application/languages/{en,fr}/Zend.php`), which are removed. Used by the BibTeX export, `Ccsd_Form_Validate_RequiredLang` and `Ccsd_Form_Trait_Populate`. Nine rare English labels change in the BibTeX `language` field: `bn` Bangla, `ff` Fula, `ht` Haitian Creole, `ky` Kyrgyz, `mi` Māori, `or` Odia, `rm` Romansh, `to` Tongan, `ug` Uyghur.
- Update dependencies.
- [#1205](https://github.com/CCSDForge/episciences/pull/1205) Store the board role labels (`editorial_board`, `technical_board`, ..., `former_member`) as plain text in `views.php`, like in `js.php`; the tag icon is now added by `Episciences_Acl::getRoleLabelHtml()` in the role badges (user lists, contacts, mailing lists, permissions, role editing), which also escapes the label. The users-by-role chart on the stats page no longer strips HTML from the labels.

### Fixed

- [#1377](https://github.com/CCSDForge/episciences/pull/1377): Journal settings: `Episciences_Review::getSettings()` loads the settings from the database when none have been loaded yet.
- [#1374](https://github.com/CCSDForge/episciences/pull/1374): The repository checks of the submission page and the linked data form answer with an empty response instead of a server error when they are requested directly.
- [#1373](https://github.com/CCSDForge/episciences/pull/1373): The journal robots.txt outside production and the portal robots.txt close the whole site with a valid `Disallow: /` rule, and are served with a valid `Content-Type` header.
- [#1357](https://github.com/CCSDForge/episciences/pull/1357): Paper titles, versions, volume and section names containing special characters are displayed as they are in the reviewer ratings list, the author paper list, the editor paper lists, the reviewer invitation page and the invitation mail.
- [#1356](https://github.com/CCSDForge/episciences/pull/1356), [#1358](https://github.com/CCSDForge/episciences/pull/1358): The site settings (languages, style, header, pages, public resources and news) check the role of the user on each page and dialog; refused accesses are recorded in the security log.
- [#1354](https://github.com/CCSDForge/episciences/pull/1354): A section that still has papers can no longer be deleted: the delete button lists the papers to move first.
  Deleted and removed papers, which can no longer be moved, leave the section with it.
  Moving a paper to another section now moves all its versions, so older versions no longer keep it in its former section.
  Moving a paper to another section follows the same rules as the paper management page.
  **Deployment**: apply `src/mysql/2026-10-24-papers_section_of_all_versions.sql`, which gives every version the section of
  the latest one.
- [#1360](https://github.com/CCSDForge/episciences/pull/1360): The access denied page answers with a 403 status instead of a "page not found" error.
- [#1379](https://github.com/CCSDForge/episciences/pull/1379): harden file, attachment and volume metadata access checks; resolve ACL resources with dispatcher-normalized names.
- [#1352](https://github.com/CCSDForge/episciences/pull/1352): declare the paper revision deadline property.
- [#1310](https://github.com/CCSDForge/episciences/pull/1310): co-author form and co-author removal are now consistent with the paper management page.
- [#1344](https://github.com/CCSDForge/episciences/pull/1344): graphical abstract and linked data forms are now consistent with the paper context.
- [#1349](https://github.com/CCSDForge/episciences/pull/1349): profile edition form opened from the user list is submitted for the selected account.
- [#1345](https://github.com/CCSDForge/episciences/issues/1345): authors can edit the relationship of an existing related work. Authors can add, edit or remove linked data until the paper is published; editorial roles still can afterwards.
- [#1332](https://github.com/CCSDForge/episciences/pull/1332): journal settings: the statistics start date is now strictly validated (YYYY-MM-DD); a malformed stored value is ignored by the statistics page instead of making it fail.
- [#1339](https://github.com/CCSDForge/episciences/pull/1339): the `Import` and `setNumVol` scripts no longer create dynamic properties (deprecated since PHP 8.2).
- [#1355](https://github.com/CCSDForge/episciences/pull/1355): The news feed (`/rss/news`) uses the same selection of news as the news page, and the number of entries of the feeds is bounded. Each news of the feed now links to its own anchor of the news page (feed readers no longer merge them into one), its content is shown as plain text like on the news page, and the feed link and description of the news feed are no longer those of the papers feed. An invalid number of entries requested falls back to the journal setting, 20 by default.
- [#1298](https://github.com/CCSDForge/episciences/pull/1298): new versions received through the inbox (COAR Notify) of an imported paper are no longer saved as imported either (`ProcessInboxNotificationsCommand::saveNewVersion()`).
- [#1336](https://github.com/CCSDForge/episciences/pull/1336): `episciences:queue --retry` shows the error of the message handler again when the retried message fails, instead of the generic Messenger message.
- [#1340](https://github.com/CCSDForge/episciences/pull/1340): The "Add a file (Foreword, Erratum, Addendum, ...)" dialog of the volume creation and edition pages is no longer empty.
- [#1340](https://github.com/CCSDForge/episciences/pull/1340): The dialogs of the paper page and of the mail templates page are displayed at half the page width again.
- [#1338](https://github.com/CCSDForge/episciences/pull/1338): Multi-value, date, referential and thesaurus form fields no longer carry the internal `pathDir` and `relPublicDirPath` properties as HTML attributes.
- [#1337](https://github.com/CCSDForge/episciences/pull/1337): Multi-value and date form fields no longer raise PHP deprecations when rendered: their decorators now declare the JavaScript function names and indices they receive ("Creation of dynamic property", PHP 8.2+), and a date field without CSS class no longer passes null to `strpos()`.
- [#1337](https://github.com/CCSDForge/episciences/pull/1337): Decoding the subject of a mail without subject no longer raises a PHP deprecation.
- [#1337](https://github.com/CCSDForge/episciences/pull/1337): Multi-value form fields now stop validating at a validator set to break the chain on failure, instead of always running every validator, including on required and multilingual fields; a validator that succeeds no longer stops the chain because an earlier one failed.
- [#1337](https://github.com/CCSDForge/episciences/pull/1337): Multi-value form fields now validate each value again after a first failure (they used to validate the whole list at once, reporting a wrong "invalid type" error, until the end of the request), keep the messages of every failing validator of a language, no longer report the errors of a previous validation, and no longer fail with a TypeError or show "Array" when they have custom error messages; an empty required field is now validated as empty by every validator.
- [#1335](https://github.com/CCSDForge/episciences/pull/1335): A user who submitted a paper, became a reviewer or edited their own roles in one journal lost their roles in the other journals of the same host (e.g. the manager) until they logged in again: the roles kept in the session are now updated for that journal only, and a journal without any role means member (`Episciences_User::addRole()`, `Episciences_User::saveUserRoles()`, `Episciences_User::getAllRoles()`). A user who edits their own roles no longer loses in the session the roles they may not edit (e.g. author), and no longer keeps the roles they removed: the session is now updated from the roles saved in the database.
- [#1314](https://github.com/CCSDForge/episciences/pull/1314): HAL affiliations no longer keep the ROR of another affiliation of the same author, a defect left in the data by a former enrichment bug: `enrichment:creators` now realigns such a ROR on the HAL TEI (or removes it when the HAL structure has none), while a ROR set manually is kept.
- [#1314](https://github.com/CCSDForge/episciences/pull/1314): `enrichment:creators --dry-run` no longer creates missing author records.
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): A BAOBAB submission by the ARK of a version other than the latest one no longer fails with "identifier does not exist": the ARK is now searched across all the versions of the records.
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): A paper whose repository lists related works is now reindexed when one of them is added, even when another one could not be resolved.
- [#1322](https://github.com/CCSDForge/episciences/pull/1322): Fix identifier extraction from URLs pasted on the submission form: HAL pages with a path suffix (`/document`, `/file/...`), arXiv `/pdf/`, `/html/`, `www.` and old-style (`hep-th/9704188`) links, bioRxiv/medRxiv `/content/` links, Zenodo `/record/` and `/doi/` links and IACR ePrint `.pdf` links now fill the identifier field with the repository identifier and the version field with the version given by the URL, as a number. A version typed by the author is no longer cleared or replaced when the identifier or the URL has no version suffix, and `v0` is no longer read as a version (`public/js/submit/identifier.js`).
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): BAOBAB submissions no longer lose their abstract, keywords and dates when OAI-PMH is not configured or fails: the DataCite record is then fetched from the REST API.
- [#1308](https://github.com/CCSDForge/episciences/pull/1308): BAOBAB links from a record to its own deposit page are no longer considered as related works.
- [#1303](https://github.com/CCSDForge/episciences/pull/1303): Removing the chief editor role of a user who stays editor (or the reverse) no longer disables their paper assignments as editor.
- [#1260](https://github.com/CCSDForge/episciences/pull/1260): The session cookie is now sent with the `SameSite=Lax` attribute (configurable with `resources.session.cookie_samesite`).
- [#1299](https://github.com/CCSDForge/episciences/pull/1299): `enrichment:extract-biblio-refs` reports papers whose host is not allowed by the Biblioref service, or whose PDF contains no reference, as skipped instead of failed, so they no longer fail the cron run.
- [#1291](https://github.com/CCSDForge/episciences/pull/1291): The document files of a paper (comments, copy-editing, data descriptor) follow the access rules of the paper pages.
- [#1265](https://github.com/CCSDForge/episciences/pull/1265): Saving the roles of a user no longer disables their paper assignments when the current user is not allowed to edit their editor roles.
- [#1265](https://github.com/CCSDForge/episciences/pull/1265): A user whose roles are all unchecked keeps the member role, so they stay in the journal user list.
- [#1009](https://github.com/CCSDForge/episciences/pull/1009): remove redundant account linking confirmation when emails match
- [#1009](https://github.com/CCSDForge/episciences/issues/1009), [#1109](https://github.com/CCSDForge/episciences/pull/1109): Avoid suggesting that an invitation be linked to another account if it has already been accepted, has expired or has been cancelled.
- [#1256](https://github.com/CCSDForge/episciences/pull/1256): Mail templates: editing, saving and reverting a template now only apply to a default template or a template of the current journal;
  the revert is submitted as a confirmed form instead of a link, and an expired session shows a message instead of an error page.
- [#1278](https://github.com/CCSDForge/episciences/pull/1278): A refused paper resubmitted through the inbox (COAR Notify) keeps the submission date of the refused paper,
  which is shared by all the versions of a paper, instead of getting the current date.
- [#1264](https://github.com/CCSDForge/episciences/pull/1264): The website settings (menu, style, languages), the page content, the mail templates, the mailing form, the rating grids, and the volume and section management actions now check the form token before saving or deleting.
- [#1259](https://github.com/CCSDForge/episciences/pull/1259): The website public resources page now checks the form token before adding or removing a file.
- [#1249](https://github.com/CCSDForge/episciences/pull/1249), [#1250](https://github.com/CCSDForge/episciences/issues/1250): Replacing a paper by a new version from the submission form:
  - a rejected replacement now gives an explicit, translated message (paper not found, not the author, publication in progress);
  - the concept identifier, version and update datetime of the submitted document are the ones resolved on the server, not the ones posted by the form, both on the submission form and on the new version page;
  - a paper that is ready to publish or approved by its author is no longer replaced from the submission form (this added a second active version and removed the files of the stored one): the message tells that the publication process is in progress;
  - the files of the replaced paper are only removed when the paper is replaced in place, so a refused paper submitted again keeps its files;
  - submitting a refused paper again only renames ("-REFUSED" suffix) the versions of that paper in its journal, never another paper sharing its identifier.
- [#1371](https://github.com/CCSDForge/episciences/pull/1371): Review report attachments: with the conflict of interest check enabled, a reviewer could no longer download the attachments of their own report.
- [#1216](https://github.com/CCSDForge/episciences/pull/1216) Paper illustration (graphical abstract): every error now returns a JSON response with a proper HTTP status (the "file too large" case returned nothing), the result of `move_uploaded_file()` is checked, and the illustration is carried over to new versions (temporary version, new version, COAR Notify new version) instead of being lost.
- [#1208](https://github.com/CCSDForge/episciences/pull/1208) Fix change contributor modal: bind listeners once and release document event listeners on close, prevent race condition when closing modal quickly, close on Escape and backdrop click, and restore focus to the trigger button (WCAG 2.4.3).
- [#1203](https://github.com/CCSDForge/episciences/pull/1203) Fix interface language resolution (`Episciences_Translation_Plugin::resolveLocale()`): an unsupported `?lang=` or cookie value now falls through to the next source (cookie, then browser) instead of forcing French, and the `lang` cookie now stores the language actually used (an English-only journal no longer sets `lang=fr`). The `lang` cookie is now only set on an explicit choice (`?lang=` or language URL prefix), no longer on every response; a logged-in user without such a choice gets their account language before the browser one. The language is resolved once per request: an internal forward (journal home page to `page/index`) no longer rebuilds the translator nor sends the `lang` cookie twice.
- [#1198](https://github.com/CCSDForge/episciences/pull/1198) Fix submission acknowledgment never reaching the author (and co-authors) when no editorial committee member is left to notify, e.g. a chief editor submitting to their own journal (`Episciences_Submit::sendNotifications()`, regression from v1.0.54).
- [#1198](https://github.com/CCSDForge/episciences/pull/1198) Fix conflict-of-interest filtering of submission recipients comparing UIDs against list positions instead of UID values (`Episciences_Submit::filterConflictRecipients()`).
- [#1199](https://github.com/CCSDForge/episciences/pull/1199) Fix editor-to-author message notifications being lost or reported as sent when they were not: a plain exception while notifying another assigned editor no longer skips the author email, the author send result is checked, an unresolved author is reported as a failure, and the editor is warned when a notification could not be queued (`PaperDefaultController::newCommentNotifyManager()`, `AuthorEditorCommunicationControllerTrait`).
- [#1199](https://github.com/CCSDForge/episciences/pull/1199) Fix paper status/revision emails sent from modals being logged as `CODE_MAIL_SENT` in the paper history even when `writeMail()` failed (`PaperDefaultController::sendMailFromModal()`).
- [#1199](https://github.com/CCSDForge/episciences/pull/1199) Fix author-to-editor message notifications to co-authors: a failed co-author send is no longer counted as sent, and a former co-author skipped at send time no longer triggers a false failure warning (`PaperDefaultController::newCommentNotifyManager()`).
- [#1200](https://github.com/CCSDForge/episciences/pull/1200) Fix COAR Notify inbox processing (`notify:process-inbox`) failing to notify editors or notifying the wrong journal's editors: `Undefined constant "RVID"` when conflict-of-interest is enabled (`PapersManager::keepOnlyUsersWithoutConflict()` and `User::hasOnlyAdministratorRole()` now accept an RVID), stale `Episciences_Review::$_currentReviewId` with cached journals, email write failures logged as successes, a translator initialisation failure silenced, `http://` links in emails, and `%%PAPER_VIEW_URL%%`/`%%PAPER_ADMINISTRATION_URL%%`/`%%PAPER_RATING_URL%%` built with a null `RVCODE` (`Episciences_Mail::setDocid()` now uses the mail's own journal code).
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Default invalid or missing CSV paper status to `accepted` (`STATUS_ACCEPTED`), not published, in `import:papers`.
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Fix `Episciences_Volume::save()` silently dropping titles passed as a nested array instead of flat `title_{lang}` keys.
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Fix `import:volumes` never defining the `RVID` constant on a real (non-dry-run) write.
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Set finite Guzzle timeouts on the HAL publication-date lookup in `PublicationDateResolver` to avoid stalling sequential imports.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Guard `Episciences_Paper::updateXml()` against unresolvable review lookups (`Episciences_ReviewsManager::find()` returning `false`), preventing fatal errors when fetching review settings.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Guard `$oVolume` with `instanceof Episciences_Volume` before calling `getSetting()` in `Episciences_Paper::updateXml()`, preventing fatal errors on the dashboard when a paper has no primary volume.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Remove redundant unguarded review lookup in `Episciences_Paper::updateXml()` by reusing `$oReview`.
- [#1145](https://github.com/CCSDForge/episciences/issues/1145), [#1146](https://github.com/CCSDForge/episciences/pull/1146) Sort bibliographic references by `referenceOrder` in both `BiblioRefApiClient` and `biblioRef.js`, placing missing or invalid orders last.
- [#1142](https://github.com/CCSDForge/episciences/pull/1142): Guard `Episciences_Paper::getGraphical_abstract()` against MySQL `JSON_UNQUOTE(JSON_EXTRACT())` returning the literal string `"null"` for JSON null values, avoiding invalid image paths (`/public/documents/{docId}/null`).
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Make the arXiv article password panel collapsible in `paper_password_form.phtml` by wrapping its contents in a `.panel-body` container.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144) Restore the article download for every repository that does not mirror its files into `PAPER_FILES` (arXiv, HAL, bioRxiv, medRxiv, ARCHE). `Episciences_Repositories::hasHook()` only reports that a hooks class exists, yet it was read as "this repository's files live in `PAPER_FILES`". Giving arXiv ([#1135](https://github.com/CCSDForge/episciences/pull/1135)) and HAL ([#1137](https://github.com/CCSDForge/episciences/pull/1137)) a metadata-filtering hooks class therefore made `Paper::getMainPaperUrl()` look for a row that never exists, so `/{docid}/pdf` answered `404 no PDF files found`, the "Download the article" button disappeared from the article page, and `database.current.mainPdfUrl` and `files` went empty in the JSON v2 export. bioRxiv, medRxiv and ARCHE had been broken the same way ever since they got a hooks class. Capabilities are now asked for explicitly, through the interfaces the hook classes already declare: `hasFilesEnrichment()`, `hasLinkedDataEnrichment()`, `handlesOwnEnrichment()` and `hasConceptIdentifier()`. The XML node driving the download button is renamed `notHasHook` → `hasMainPaperUrl` after what it actually holds.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Require a version number again when submitting an arXiv or HAL paper. Their `hookIsRequiredVersion()` returns `[]` to keep the historical default, but callers read `['result']` straight off it, so the version block was hidden in the submission form and skipped by its client-side validation. The default now lives in `Episciences_Repositories::isVersionRequired()`. Also fixes the same lookup on temporary papers, which queried the hook with `repoId = 0` instead of the repository of their first version.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Run the generic dataset enrichment (`Episciences_Submit::datasetsProcessing()`) again for arXiv and HAL papers, at submission and on metadata refresh: both were silently skipped because they now have a hooks class, even though neither declares any enrichment capability.
- [#1140](https://github.com/CCSDForge/episciences/issues/1140), [#1141](https://github.com/CCSDForge/episciences/pull/1141) Refresh the `PAPERS.DOCUMENT` JSON column after a secondary volume change, so API consumers no longer read stale volume data. Papers already in database keep a `DOCUMENT` without the new key until resaved: run `php scripts/console.php papers:update-document` after deploying (API consumers should read the key as `?? null` in the meantime).
- [#1142](https://github.com/CCSDForge/episciences/pull/1142) Report `database.current.graphical_abstract_file` as `null` instead of `""` in the JSON v2 paper export when the paper has no graphical abstract, and drop the dead `unset()` that was meant to do it. Consumers must treat both `null` and a missing key as "no graphical abstract".
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Block review report attachment downloads for users with a declared Conflict of Interest (COI).
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Ensure paper authors' access precedence over editorial staff COI checks when downloading review report attachments.
- [#1132](https://github.com/CCSDForge/episciences/pull/1132) Pass `previousVersion` context to `hookVersion` in `savenewpostedversionAction` to correctly update Zenodo version identifiers when posting a new version.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Define missing `RVID` constant in `ZbjatsZipperCommand` CLI bootstrap.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Allow non-numeric volume numbers (`varchar(6)`) in volume management forms.
- [#1113](https://github.com/CCSDForge/episciences/pull/1113): Stream Crossref DOI XML in-memory and scope volume/DOAJ caches under `rvcode`.
- [#1111](https://github.com/CCSDForge/episciences/pull/1111): Allow authorized roles to visualize all bibliographic references on unpublished papers.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Add explicit `string` type hint to `Ccsd_Website_Header::$_langDir` for PHP 8 compatibility.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Stop `Episciences_Submit::getDoc()` from crashing on a `latestObsoleteDocId` that does not resolve. The value arrives straight from the `/submit/getdoc` POST, so `partialGet()` returns `null` for a stale docid or one belonging to another journal, and the previous paper was dereferenced without a check — `Call to a member function isTmp() on null`. The lookup now degrades to "no previous version", which every downstream check already handles, and the real latest version is still found by `findExistingDocId()`. Same for a temporary paper with no previous version, which indexed an array with `array_key_first([])`. The `addContext()` call feeding the `hookVersion` parameters is guarded too: it takes a non-nullable `Episciences_Paper`, and the `TypeError` it raised is an `Error`, which `getDoc()`'s `catch (Exception)` does not intercept.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Restore submissions from Cryptology ePrint and DSpace, broken by the `hasConceptIdentifier()` capability check introduced above: that check was scoped to Zenodo's own hooks interface, while Cryptology ePrint's and DSpace's hooks set a concept identifier through `hookApiRecords()` without implementing it. Every submission (or new version) from these two repositories therefore threw `InvalidArgumentException` in `Episciences_Paper::setConcept_identifier()`, caught as a generic error by `Episciences_Submit::getDoc()`. The capability is now a dedicated `ConceptIdentifierInterface`, implemented by all three hook classes.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Stop `Episciences_Submit::getDoc()` from throwing a `TypeError` when a temporary previous paper has no previous version of its own: `getPreviousVersions()` returns `null` (not `[]`) in that case, and `array_key_first([])` requires an array.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148) Scope volume paper queries to the volume's own `rvid` instead of the global `RVID` constant in `Episciences_Volume::getPaperListFromVolume()`, fixing empty paper lists in multi-journal CLI batch runs (`zbjats:zip`).
- [#1148](https://github.com/CCSDForge/episciences/pull/1148) Scope the `vid` filter subquery to the requested journal's `rvid` in `Episciences_PapersManager::getVolumesQuery()`, and accept uppercase `'RVID'` key in `applyFilters()`.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148) Ensure zbJATS ZIP files (permissions `0644`) and destination directory (`0755`) are readable by all users, skip archive creation when there are no published papers, and check `ZipArchive::close()` return value.
- [#1149](https://github.com/CCSDForge/episciences/pull/1149): Wrap Messenger message dispatching in `tryGetPort()` across `RevalidationService` and `SolrIndexing` to prevent lazy DBAL connection failures from interrupting database writes.
- [#1089](https://github.com/CCSDForge/episciences/pull/1089): Ensure Solr indexing only processes published papers (`STATUS_PUBLISHED`).
- [#1149](https://github.com/CCSDForge/episciences/pull/1149): Scope Messenger transports to distinct `queue_name` identifiers (`solr_index` and `next_revalidation`) to avoid cross-queue message collisions.
- [#1149](https://github.com/CCSDForge/episciences/pull/1149): Prevent Next.js cache revalidation exceptions from interrupting paper deletion in `Episciences_PapersManager::delete()`.
- [#1174](https://github.com/CCSDForge/episciences/pull/1174) Fix `%%PERMANENT_ARTICLE_ID%%` tag availability in reviewer invitation and reminder email templates, use normalized docid in `%%PAPER_ID%%` tag and generated URLs, and scope article-id/permanent-id pairing to each recipient construction.
- [#1186](https://github.com/CCSDForge/episciences/pull/1186) Require POST and validate CSRF token in `savenewpostedversionAction`, and send CSRF token from version editing form AJAX submissions.
- [#1184](https://github.com/CCSDForge/episciences/pull/1184) Normalize hook version parsing for posted repository versions, ensure Zenodo's `hookVersion` returns a normalized version, and reject non-scalar latest repository versions before rights checks.
- [#1182](https://github.com/CCSDForge/episciences/pull/1182), [#1183](https://github.com/CCSDForge/episciences/pull/1183) Recover all creator affiliations (not just the first one) from DataCite OAI records for Zenodo, and ensure ORCID matching takes precedence over name matching.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Prevent description language fallback from overwriting existing abstracts when updating paper metadata.
- [#1161](https://github.com/CCSDForge/episciences/pull/1161), [#1162](https://github.com/CCSDForge/episciences/pull/1162) Centralize account email availability checks and enforce uniqueness (`User_EmailPolicy`), revalidate email availability against the saved value, invalidate stale identity cache before refreshing session after email changes, and restrict `forward-controller`/`forward-action` to safe route segments.
- [#1157](https://github.com/CCSDForge/episciences/pull/1157) Handle COAR Notify inbox edge cases: add `STATUS_OUTDATED` to stop retrying superseded notifications, tolerate `object.url` as fallback for `ietf:item`, accept `coar-notify:EndorsementAction` from HAL, handle double-JSON-encoded payloads, and fix bootstrap ordering in `ProcessInboxNotificationsCommand`.
- [#1153](https://github.com/CCSDForge/episciences/pull/1153) Honor custom review mail display code in automatic reminders (`reminders.php`).
- [#1150](https://github.com/CCSDForge/episciences/pull/1150) Escape email subjects in paper history and log views to prevent stored XSS.
- Retain existing `WHEN` timestamp value during assignment updates in `Episciences_Assignment::save()` ([RT#294893](https://github.com/CCSDForge/episciences/pull/1171)).
- Prevent paper registration failure when the OAI `RECORD` value is null, allow marking older versions obsolete ([RT#296898](https://github.com/CCSDForge/episciences/pull/1165)), and fix failure to update to the latest version ([RT#296918](https://github.com/CCSDForge/episciences/pull/1166)).
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Simplify paper modification request cancellation by linking the action form directly to the article's current status instead of requiring a new parent-linked comment.
- [#1190](https://github.com/CCSDForge/episciences/pull/1190) Resolve bibliographic reference PDF URL from the paper's own journal and front version.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Define `RVID` and ensure write-free execution in `doi:manage --dry-run`.
- [#1152](https://github.com/CCSDForge/episciences/pull/1152): Rename `--version` option to `--paper-version` in `export:papers` to prevent collision with Symfony Console's application version option.
- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Register `Zend_Translate` before journal locking in `import:papers`.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Stop console progress bars from garbling interleaved log output.
- [#1164](https://github.com/CCSDForge/episciences/pull/1164): Fix BAOBAB repository ID collision with existing id 21.
- [#1369](https://github.com/CCSDForge/episciences/pull/1369): Paper volume assignment: checks hardened.
- [#1371](https://github.com/CCSDForge/episciences/pull/1371): Review report attachments: checks hardened.
- [#1370](https://github.com/CCSDForge/episciences/pull/1370): Volume and section pages: checks hardened.
- [#1360](https://github.com/CCSDForge/episciences/pull/1360): Token checks hardened.
- [#1231](https://github.com/CCSDForge/episciences/pull/1231) Website header: only administrators, chief editors, secretaries, webmasters and root can edit it, and the form requires a CSRF token. The generated header escapes every value and only keeps http(s), mailto and tel links. A submission with an invalid CSRF token is not saved: the form is shown again with an error message. Labels still accept the entities already typed, line breaks and the `b`, `strong`, `i` and `em` tags without attributes; any other markup is now shown as text. The CSS style of a label only keeps typographic properties (no positioning, background or `url()`) and its CSS classes are limited to valid class names. Run `website:regenerate-headers --all` once after deployment to rewrite the existing header files, which are otherwise only regenerated when a journal saves its header.
- [#1216](https://github.com/CCSDForge/episciences/pull/1216) Paper illustration (graphical abstract) upload: the actual MIME type is now checked (the extension alone, case-sensitive, was trusted), SVG is no longer accepted, and access checks are tightened.
- [#1176](https://github.com/CCSDForge/episciences/pull/1176) Update `league/commonmark` to 2.10.1 to patch DoS vulnerability in Attributes extension.
- [#1175](https://github.com/CCSDForge/episciences/pull/1175) Patch transitive npm vulnerabilities flagged by Dependabot in the build/test toolchain via yarn resolutions.

### Performances

- [#1143](https://github.com/CCSDForge/episciences/pull/1143) Memoise the paper's primary volume on the `Episciences_Paper` instance, so `toJson()`, `getXml()` and `XmlExportManager::xmlExport()` share a single lookup instead of loading it twice: 38 to 34 queries per paper export.
- [#1143](https://github.com/CCSDForge/episciences/pull/1143): Stop reloading volume settings from `Episciences_Volume::getProceedingInfo()`; every caller reaches it through `isProceeding()`, which already needs them loaded.
- [#1143](https://github.com/CCSDForge/episciences/pull/1143): Load every volume's settings in one query on `/browse/volumes` via `Episciences_VolumesManager::loadSettingsForVolumes()`, instead of one query per volume.
- [#1203](https://github.com/CCSDForge/episciences/pull/1203) Build the web translator once per request from an explicit, sorted list of dictionaries (`Episciences\Translation\TranslatorFactory::create()`): the application languages directory is no longer scanned twice and the 220 e-mail templates (`<locale>/emails/*.phtml`) are no longer included and rejected on every request (~3.7 ms to ~0.4 ms per request under PHP-FPM with OPcache). Duplicate keys now resolve in a filesystem-independent order.
- [#1204](https://github.com/CCSDForge/episciences/pull/1204) Build the CLI translators (`Script::initTranslator()`, `reminders.php`, `inbox:process`, `solr:*`, `UpdatePapersDocumentCommand`) with the same `TranslatorFactory` as the web: same dictionary order, e-mail templates no longer included, journal dictionaries loaded last. The locale each script used is unchanged.

### Removed

- [#1151](https://github.com/CCSDForge/episciences/pull/1151): Remove legacy interactive `scripts/update_papers.php` script (replaced by `import:papers` CLI command).
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Drop the redundant `!$previousPaper->hasHook` guard from `Episciences_Submit::assertDateTimeVersion()`. The branch is selected by the `UPDATE_DATETIME` key, which only ever reaches the result from a repository's `hookApiRecords()`, so the guard added nothing — and it dereferenced a nullable `Episciences_Paper`, raising `Attempt to read property "hasHook" on null` whenever `partialGet()` returned nothing for an existing `docId`. `Episciences_Paper::$hasHook` now has no caller left and is marked `@deprecated`.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Remove the dead `hasHook` plumbing left behind now that repository capabilities are asked for explicitly: the `hasHook` JavaScript global on the article and submission pages (declared, assigned from `/submit/ajaxhashook`, never read), the `hasHook` view variable of the version-number form, and the `h_hasHook` hidden form element, whose value came from a `$defaults['hasHook']` key that had been commented out. `/submit/ajaxhashook` now answers `isRequiredVersion` only.

### Refactored

- [#1151](https://github.com/CCSDForge/episciences/pull/1151) Refactor `import:papers`, `import:volumes`, and `import:sections` console commands to delegate business logic to dedicated `Episciences\Paper\Import\*`, `Episciences\Volume\Import\*`, and `Episciences\Section\Import\*` classes with testable `Row` and `Importer` components.
- [#1144](https://github.com/CCSDForge/episciences/pull/1144): Route `Episciences_Submit::getRepositoriesForm()` and `getNewVersionForm()` through `Episciences_Repositories::isVersionRequired()` instead of calling `callHook('hookIsRequiredVersion', ...)` directly and re-implementing its `[]` → required fallback locally, so the two form-building sites this PR left behind stay in sync with the centralized default.
- [#793](https://github.com/CCSDForge/episciences/issues/793) Add `hookFilterMetadata` repository hook and strip surplus arXiv `dc:description` comment nodes at ingestion.
- [#1137](https://github.com/CCSDForge/episciences/pull/1137) Strip HAL's non-abstract `dc:description` markers (`International audience`, `National audience`, `soumission à Episciences`) at ingestion instead of filtering them in every consumer. A new `Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput()` removes the nodes from the raw OAI record before it reaches `PAPERS.RECORD`, so the two independent render paths — `Paper::getMetadata()` and `Paper::getXslt()` → `public/xsl/*.xsl` — stay consistent on their own. Removes the eight downstream workarounds in `Paper::getAbstractsCleaned()`, `Paper_Export`, the DataCite and zbJATS exports and the three paper XSLTs, and fixes the TEI export, which never filtered the marker at all. Matching is content-based and case-insensitive on normalized whitespace, so a real (possibly multilingual) abstract can never be dropped. `National audience` was never filtered anywhere and was displayed as an abstract; it is now handled with the others. Existing rows are migrated by the new `papers:clean-hal-descriptions` command (`--dry-run`, `--update-document`, `--no-reindex`).
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Remove static `Ccsd_Auth` dependencies to improve testability.
- [#1148](https://github.com/CCSDForge/episciences/pull/1148): Simplify portal module and remove dead code.
- [#1089](https://github.com/CCSDForge/episciences/pull/1089) Replace legacy Solr indexing (`Ccsd_Search_Solr_Indexer*`, `solrJob.php`, `INDEX_QUEUE` polling) with decomposed `DocumentBuilder` field builders and Messenger message handlers.
- [#1149](https://github.com/CCSDForge/episciences/pull/1149): Migrate Next.js cache revalidation off the legacy `queue_messages` table and its cron consumer (`scripts/NextRevalidationQueue.php`) onto the same Symfony Messenger + Doctrine DBAL infrastructure as Solr indexing, on its own `next_revalidation` transport so a slow Solr document build never blocks a cheap revalidation POST. The Messenger plumbing itself (transports, buses, worker event dispatcher, bounded-retry dispatch, dispatch-failure store) is extracted out of the Solr-only code into a generic `Episciences\Messenger\*` library that both producers now share, and `solr:worker`/`solr:queue` are replaced by transport-agnostic `episciences:worker`/`episciences:queue --transport=<solr_index|next_revalidation>` commands (see `docs/console-commands.md#messenger-queues`, `docs/next-revalidation.md`). `RevalidationService::revalidateOrEnqueue()` (synchronous POST with queue fallback) is removed — every call site is now fully asynchronous — and `next:revalidate-cache` gains a variadic tag argument and a `--queue` option.
- [#1179](https://github.com/CCSDForge/episciences/pull/1179) Bump `monolog/monolog` to `^3.0` (`psr/log` to `3.x`) and add `Episciences\Log\LoggerFactory` (`rotating()` / `cli()`), routing the previously duplicated `RotatingFileHandler`/CLI logger setup through it (`MonologFactory`, `Notify/Hal`, `Notify/Reader`, both CAS adapters, `AbstractScript`, the Messenger queue/worker commands, `Solr/BootstrapsSolrEnvironment`), and dropping `Episciences\Messenger\Log\CliLoggerFactory`. `ProgressAwareStreamHandler::write()` is adapted to the Monolog 3 `LogRecord` handler contract, and `ScholexplorerApiClient::create()` now builds an explicit fallback logger (Monolog 2 removed the implicit `php://stderr` handler-less fallback, so records would otherwise be silently dropped). Log lines now use Monolog 3's default ISO 8601 timestamp instead of the previous `Y-m-d H:i:s` format.
- [#1178](https://github.com/CCSDForge/episciences/pull/1178) Extract available repository version lookup into `Episciences\Paper\RepositoryVersionsService`, make collaborators injectable, distinguish Dataverse major and minor versions, and handle Guzzle exceptions on bioRxiv and Dataverse API calls.

## v1.0.57.1 - 2026-10-22

### Fixed

- Fix a fatal error during bootstrap (`TypeError` from `Episciences_Auth::getRoles()`) when the roles of the logged-in user cached in the session had been loaded for another journal: the user now falls back to the member role (`Episciences_Auth::getRoles()`).
- [#1298](https://github.com/CCSDForge/episciences/pull/1298): Fix new and temporary versions of an imported paper being saved as imported: the version is cloned from the previous one and kept its `FLAG`, which is now reset to `submitted` (`PaperController::initializeNewPaper()`, `PaperController::savetmpversionAction()`).
- Fix `Episciences_Paper::isImported()` comparing the publication date with a missing acceptance date: the check is skipped when no acceptance date is known.

## v1.0.57 - 2026-10-21

### Added
- [zbJATS] `zbjats:zip` caches the downloaded PDF and zbJATS XML files (Symfony Cache) instead of writing loose files, supports the new front-end URLs and gains a `--remove-cache` option to fetch everything again.
- [Bibliographic references] `enrichment:extract-biblio-refs`: review status options, reference counts per DOCID at the end of the run; published papers are only processed with the opt-in `--published` option. See `docs/biblioref-extraction.md`.

### Changed
- The application runs on PHP 8.3: the Docker image uses PHP 8.3. PHP 8.1 remains supported.
- The continuous integration workflows now generate the configuration and the database the PHPUnit
  bootstrap requires. The test suite was aborting before its first test and reporting success: no
  PHP test had ever actually run on CI.
- A command line script called with an unknown option now exits with an error code instead of 0,
  so a cron or a CI job is no longer told it succeeded.
- Sitemap: only public front-end URLs are advertised (whitelist of public pages, articles by paper id without duplicates, `/boards` added, `/news` and `/articles-accepted` only when the journal has content for them).
- Cancelling a modification request no longer requires a new comment: the form is linked to the current status of the paper.
- The new version form is hardened.
- Update dependencies.

### Fixed
[RT#293890]:
- Sorting by invitation status does not reflect the actual situation:
    A reviewer’s report is categorised as an "Additional reviewer’s report",
    whereas this should only occur in cases where an editor adds a review directly 
- The dashboard does not display pending invitations that were sent via a temporary account.
- Reviewers were listed once per assignment history row on the paper page, instead of once per invitation.
- An expired invitation no longer keeps its paper pinned to the reviewer's assigned papers list.
- A reviewer holding a temporary account and invited more than once on the same paper was returned
  without any identity, which made the paper page fail.

[RT#301096]:
- An editor (or guest editor) assigned to a paper could not access its previous versions:
  the assignment is now checked on the latest version.
- Permissions are now also checked on obsolete versions in the paper management page.
- On the page of a previous version, the assigned editor (or guest editor) can now see the reviews
  and the non-public details.
- A guest editor (or copy editor) assigned to a paper could not download the review report
  attachments ("File not found"): the download permission now relies on the assignment check,
  same as the review display.

[RT#296918]:
- Updating a paper to the latest version of its HAL or Zenodo deposit failed: a HAL connection error no longer interrupts the process and only Zenodo versions newer than the current one are offered.

[RT#297893]:
- Repository versions posted as a new version (e.g. `v1.2.3`) are normalised the same way for every repository; an invalid value no longer makes the update fail.

[RT#294893]:
- Updating an existing assignment no longer resets its date (`WHEN`).

- Fix editor-to-author message notifications being lost or reported as sent when they were not: a plain exception while notifying another assigned editor no longer skips the author email, the author send result is checked, and the editor is warned when a notification could not be queued (`PaperDefaultController::newCommentNotifyManager()`, `AuthorEditorCommunicationControllerTrait`).
- Fix paper status/revision emails sent from modals being logged as `CODE_MAIL_SENT` in the paper history even when `writeMail()` failed (`PaperDefaultController::sendMailFromModal()`).
- Fix submission acknowledgment never reaching the author (and co-authors) when no editorial committee member is left to notify, e.g. a chief editor submitting to their own journal (`Episciences_Submit::sendNotifications()`, regression from v1.0.54).
- Fix conflict-of-interest filtering of submission recipients comparing UIDs against list positions instead of UID values (`Episciences_Submit::filterConflictRecipients()`).
- Fix HAL and arXiv submissions keeping the version suffix in the stored identifier (e.g. `2310.02192v2` instead of `2310.02192`) with a mismatching version: the trailing `vN` is now removed server-side and used as the document version (`Episciences_Submit::getDoc()`).
- [Zenodo] Fix a new version stored with the Zenodo identifier in `VERSION` instead of an incremented version number, and `IDENTIFIER` never updated (`AdministratepaperController::savenewpostedversionAction()`).
- [Zenodo] All the affiliations of each creator, with their ROR identifiers, are now imported from the DataCite OAI-PMH record; the REST API only returns one affiliation per creator. Applies to new imports only.
- [Zenodo] A description whose language has no ISO 639-1 code no longer overwrites another abstract.
- [#793](https://github.com/CCSDForge/episciences/issues/793) [arXiv] Only the first `dc:description` is kept as the abstract; the following ones (author comments, publication notes) are discarded.
- Bibliographical references were dropped when a paper document was regenerated from the command line: the PDF URL is now built from the paper's own journal and front-end version (`Episciences_Paper::getBibRef()`).
- Papers without a graphical abstract rendered a broken image (`/public/documents/{docId}/null`).
- Volume numbers that are not numeric (e.g. `S1`) were truncated when saving the volume form.
- The email change form now checks the availability of the address actually saved.
- OAI-PMH client: a non-XML `GetRecord` response (e.g. an HTML error page) now raises a `Ccsd_Error` instead of a `TypeError`.
- `zbjats:zip` no longer reports a ZIP archive as created when no file was written, defines `RVID` from `--rvid`, and processes every journal of a multi-journal run (volume papers are filtered by the volume's own journal).
- Fix the redirection after saving the refused papers monitoring.
- `php console.php` failed for every command because `enrichment:extract-biblio-refs` could not be loaded before the application bootstrap.

## v1.0.56.1 - 2026-08-04

### Added

- [CLOCKSS] Additional article URLs, published ahead of the actual switch to ease harvesting during the transition period: `/articles/{paperid}` and `/articles/{paperid}/download`, both also accepting an optional `en`, `fr` or `es` language prefix (e.g. `/en/articles/{paperid}`). They are addressed by paper id — the canonical reference, as used by the new interfaces — and answer `200` with the published version, without redirecting to its docid: `Episciences_ArticleAlias_Plugin` resolves the paper id at routing time. A docid is still accepted, and the historical `/{docid}` and `/{docid}/pdf` URLs are unchanged.
- [#1011](https://github.com/CCSDForge/episciences/issues/1011) Redesign the "Manage the journal" dashboard panel as a four-quadrant grid (evaluation, revisions & suggestions, copy-editing & publication, archives), with a compact paper search box moved into each panel header.
- [#1011](https://github.com/CCSDForge/episciences/issues/1011) Add a "decision suggestion" filter (acceptance / refusal / revision) to the paper list, and show the number of papers with a pending suggestion on the dashboard. A suggestion stops being counted as pending once the editor in chief has ruled, whether by accepting the paper or by requesting revisions. The filter is restricted to users allowed to manage papers.

### Fixed

- Allow paper authors to download review report attachments after an editorial decision by aligning authorization checks in `FileController::reportAction()`.
- Fix XML export corruption during Solr indexing by checking `isRegistered()` in `AppRegistry::getMonoLogger()` instead of catching exceptions.
- Fix `TypeError` in `ZbjatsTools` and `BiblioRefApiClient` by normalizing CSL response parsing to accept both array and JSON-encoded string formats from the bibliographic reference API.
- [#1118](https://github.com/CCSDForge/episciences/issues/1118) Add missing French and English translations for intra-work relationship types and group headers in linked data forms.

### Changed

- Update dependencies.

## v1.0.56 - 2026-07-16

### Added
- [CLOCKSS] Include the CLOCKSS Permissions Statement to allow archiving and ingestion of open access Archival Units.
- [Bibliographic References] Display open-access links (retrieved e.g. from OpenAlex) when available and distinct from the DOI link.
- [OpenAlex] Support configuring and appending an `api_key` query parameter to OpenAlex API requests when the `OPENALEX_APIKEY` constant is defined.
- For submissions containing multiple PDF files, it is now possible to designate the main file. This is the file that will appear in previews on public websites.

- Migration of captcha system (hCaptcha/reCAPTCHA) to a self-hosted, privacy-friendly solution: **ALTCHA** (utilizing the Argon2id PoW algorithm and bundled via Webpack).
- Export journal ISSN as `dc:identifier` (`urn:ISSN:...`) in OAI-PMH ListSets.
- Add Cron daemon to the PHP-FPM container to automatically run the mail queue flushing command every minute in the development environment.
- Add a visual count badge in the bibliographical references panel indicating the number of automatically detected problematic references.
- [#1061](https://github.com/CCSDForge/episciences/pull/1061) Allow secretaries and editors to accept a review invitation on behalf of the reviewer.
- [#937](https://github.com/CCSDForge/episciences/issues/937) Admin paper list (`/administratepaper/list`): columns **Reviewers**, **Editors**, **Copy editors**, and **Contributor** are now sortable server-side.
- [#1058](https://github.com/CCSDForge/episciences/pull/1058) OAI-PMH ListSets: add `<setDescription>` with Dublin Core metadata (title, publisher, date, description, subjects) for journal sets.
- Volume and section reordering: added editable position inputs to reorder them directly by typing numbers.
- Volume settings: added copy-to-clipboard buttons for volume access codes in the volumes list and edit pages.
- Website settings: added support for journal description, keywords, and creation year fields.
- MIME type: added support check for `file` binary presence on the system in `FileBinaryMimeTypeGuesser`.
- [#922](https://github.com/CCSDForge/episciences/issues/922) Add cover letter requirement setting for file attachments. Initial submission form supports three options: disabled (hidden), optional, or required. Author comment form on paper page supports two options: disabled (hidden) or displayed.
- [#1081](https://github.com/CCSDForge/episciences/pull/1081) Add dedicated cover image upload interface to the volume edit page (`/volume/edit/`), replacing the legacy `tile` metadata naming convention.
- [#1087](https://github.com/CCSDForge/episciences/pull/1087) Add a console command `users:normalize-affiliations` to normalize and backfill existing user affiliations data to `{label, rorId}`.

### Changed

- Remove redundant per-type add buttons in linked-data panels.
- Add Subresource Integrity (SRI) to CDN-hosted `cookieconsent.min.js`.
- Remove flag emojis from language-switcher dropdown links.
- Refactor `AccessControlControllerTrait` to reduce cognitive complexity.
- MySQL 8.4 compatibility:
  - Migrate `ON DUPLICATE KEY UPDATE column = VALUES(column)` statements to the modern `AS new_row ... UPDATE column = new_row.column` syntax.
  - Drop the non-standard foreign key `paper_projects_ibfk_1` from the `paper_projects` schema.
- Add unit tests to cover `updateNestedJsonDocument()` and `savemasterfileAction()` wiring.
- [#1080](https://github.com/CCSDForge/episciences/pull/1080) Replace cover letter requirement magic numbers with class constants in `Episciences_Review` and related managers.
- Replace native language switcher dropdown with an accessible WAI-ARIA custom dropdown.
- Route development emails to a local Mailpit SMTP server instead of dumping them to the UI, and remove legacy UI mail dump.
- Development Docker environment: delegate DB, Solr, ZooKeeper, and phpMyAdmin services to the centralized `episciences-infrastructure` repository, and route local traffic via Traefik with default HTTPS support.
- Align document relationship types with HAL schema and refactor their configurations.
- Migrated ROR (Research Organization Registry) API integration to v2.
- Upgraded `symfony/cache` to `^6.4` and updated `shardj/zf1-future` dependencies.
- Sitemap generation:
  - Added per-language URL prefixes based on the `WEBSITE_SETTINGS` configuration.
  - Added individual volume and section pages (`/volumes/{vid}` and `/sections/{sid}`) to the sitemap.
  - Replaced static `changefreq` and `priority` elements with real last modification dates (`lastmod`).
- Replaced jQuery UI sortable with SortableJS for better volume and section reordering behavior and touch support.
- Modernized section list datatable from DataTables 1.9 to 1.10 API.
- [#952](https://github.com/CCSDForge/episciences/issues/952) Add EiC, Editorial Sec and Administrators to recipients of "New answer to your revision request: comment" (paper_revision_answer).
- [#1038](https://github.com/CCSDForge/episciences/issues/1038) It is now possible to publish the article at any stage of the editorial process if it is accepted.
- Paper admin: modal save now does a targeted AJAX refresh instead of a full page reload.
- Paper admin: other volumes form replaced by a Tom Select `<select multiple>` (`checkbox_options` plugin).
- Paper admin (i18n): "Master volume" renamed to "Main volume".
- Admin paper list: filter panel reorganised into 3 rows — Status/Editors/Reviewers on the first row, Volume+DOI on the second, Section+Repositories on the third; Volume and Section are wider (`col-sm-8`) to accommodate long titles.
- Admin paper list: removed redundant page description blockquote.
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)".
- Modernized `Ccsd_Form_Filter_Clean` filter and `Ccsd_Form_Validate_NotSame` validator (introduced strict typing, comprehensive type hinting, and robust recursive array filtering for the `Clean` filter).
- Display the Article status and Versions panels side by side in the paper administration view, and render the versions list as a condensed table.

### Performances

- Eliminate N+1 query patterns on the volume list page (reducing SQL queries from 894 to ~4).
- Batch-load mail templates in 2 SQL queries instead of 2×N.
- Implemented PSR-6 request-scoped caching for database entities (authors, projects, comments, and user assignments repositories) to reduce database queries.
- Eliminated N+1 query patterns on paper list pages by batch-loading users and roles in chunks of 1000 and priming a request-level identity map cache before render loops (`AdministratepaperController`, `UsersManager`).
- `loadRoles()` is now lazy: returns immediately when roles are already loaded, avoiding redundant per-user DB queries in callers such as `AdministratemailController::getcontactsAction()`.
- [#937](https://github.com/CCSDForge/episciences/issues/937) Sorting by Reviewers / Editors / Copy editors uses a derived-table `LEFT JOIN` with `MIN(SCREEN_NAME)` aggregation (single evaluation per query) rather than a correlated subquery.
- Paper admin: volume/section assignment now uses a native `<dialog>` modal with Tom Select; `paper-assignment-modal.js` (vanilla JS) replaces `volume-assignment.js` and `section-assignment.js`.
- Paper admin: Tom Select `dropdown_input` and `remove_button` plugins in modal selects.
- Admin paper list: Tom Select search on all 7 filter selects (Status, Volume, Section, Editors, Reviewers, DOI, Repositories) — type to filter within long lists, animated chevron indicates dropdown, placeholder shows "All" when nothing is selected.
- Avoid repeated `REVIEW_SETTING` queries in a single request by caching loaded review settings on the current review object and sharing cached review instances between `RVID` and `RVCODE` lookups.

### Fixed

- Fix PHP 8 compatibility by explicitly declaring `Ccsd_Website_Header::$_langDir` as `string` to prevent fatal type variance errors in subclasses.
- Fix deprecation warning "Implicit conversion from float to int loses precision" when updating metadata.
- Fix missing translation caused by a curly apostrophe character (`’`) in templates.
- Fix application error in `Episciences_Paper_FilesManager::getMainFile()` where a null `$docId` parameter caused a type crash.
- Fix malformed `Content-Type` headers in COAR Notify client requests sent to HAL by injecting a custom cURL HTTP layer (`CoarNotifyHttpLayer`).
- Fix JSON path mapping in `updateNestedJsonDocument` (`$.document.database.mainPdfUrl` -> `$.database.current.mainPdfUrl`) and add a null-safe operator to database preparation.
- Adapt the master file choice logic so that the option to select a main file does not apply to Software and Dataset submissions.
- Fix COAR Notify Location-header lookup warnings.
- [#1087](https://github.com/CCSDForge/episciences/pull/1087) Normalize user affiliations to `{label, rorId}` format during account signup to ensure shape consistency with profile edits.
- [#1080](https://github.com/CCSDForge/episciences/pull/1080) Fix cover letter validation error target (attaching the error to the file element instead of the comments field) and default the setting value to optional (1) for older reviews.
- Escaping of XML special characters in OAI-PMH ListSets and setDescription (by using `createTextNode`).
- Explicitly set permission to `0644` on uploaded volume metadata files.
- Fix linked-data: remove premature `changePlaceholder` calls before AJAX resolves.
- [#1059](https://github.com/CCSDForge/episciences/issues/1059) Fixed method naming inconsistency (`RelationsShips` → `RelationShips`) in `Episciences\Paper\Relationship` and its callers; added missing `string` type hint on closure parameter to comply with PHPStan level 6.
- [#1035](https://github.com/CCSDForge/episciences/issues/1035) Fixed an issue where editors were unable to proceed to the "copy editing" stage because the transition button was hidden behind an incorrect conditional block in the status dropdown menu.
- Prevent the submission of a dataset or software that does not include a descriptor.
- [#1048](https://github.com/CCSDForge/episciences/issues/1048) Volume metadata: quotes in preface/content caused JSON parsing error when editing. Removed `double_encode=false` from `form.phtml` display while keeping it in `Volume.php` save to prevent double-encoding (#962).
- [#1039](https://github.com/CCSDForge/episciences/issues/1039) The MIME type for docx files is detected as "application/octet-stream".
- Fixed crash (`TypeError`) in PHP 8.1 in `Episciences_Form_Validate_MimeType` when the file parameter is not an array.
- Fixed the `%type%` placeholder not being included in validation error messages when file upload failed due to incorrect MIME type.
- Fixed the `%%SUBMISSION_DATE%%` email tag using the paper creation/update timestamp instead of the actual initial submission date.
- Fixed volume editor list not refreshing after save in volume settings.
- Fixed section refresh updating all rows instead of only the changed paper row in the section list.
- Fixed deadline modal submitting form natively instead of via AJAX.
- Fixed report attachment deletion redirecting reviewers away from the rating page.
- Fixed reviewer role not being saved when accepting a review invitation.
- Validated comment attachment extensions to reject disallowed file types.
- Trimmed visit log entries by keeping only client IP and proxy checks to reduce IPv6 log entry size.
- Fixed Application Error when calling `Episciences_Submit::assignEditors()` where `$sid` or `$vid` arguments were passed as string instead of integer or null.
- Application error: Call to a member function getVol_num() on bool.
- `scripts/update_papers.php`: fixed issue where importing a new version (e.g. version 2) of an existing paper (e.g. version 1) overwrote the version 1 entry in the database instead of creating a new version record.
- BibTeX export: month field was rendered in the user's UI locale (e.g. `mai` for French users) instead of English (`May`); locale is now forced to `en` in the BibTeX template.
- [#1030](https://github.com/CCSDForge/episciences/issues/1030): missing "Ask the author for the sources (copy editing by the journal): because the "Allow post - acceptance revisions of articles" option is used, which alters the workflow steps, as it is assumed that the journal already has the source files. At this stage, it is now possible to request the sources.
- Paper admin: closed modal remained visible (`display:flex` SCSS overrode native `dialog:not([open])` behaviour).
- Paper admin: Tom Select selected items now styled as blue pills in the modal.
- Paper admin: translation failure on volume-alert text (curly apostrophe in `views.php` vs plain ASCII in template).
- Paper admin: "Assign editors" checkbox now disabled+unchecked when no section is selected.
- Paper admin: article position badge wrapped in a Bootstrap `label-default` span with an accessible tooltip.
- Paper admin: main volume cell not updated in list after remove+reassign (stale `btn.dataset.vid` + missing fallback in `refreshallmastervolumesAction` for papers without a position row).
- Admin paper list: `checkFilterParams` crashed with `TypeError: Cannot read properties of null (reading 'length')` when clicking "Filter" with Tom Select multiselects returning `null` for empty selections; fallback to `['']` restores expected behaviour.
- [#930](https://github.com/CCSDForge/episciences/issues/930) New email template tags for volume metadata: `%%VOLUME_NUMBER%%`, `%%VOLUME_YEAR%%`, and `%%VOLUME_TYPE%%`.
- Mailing lists: added `created_at` and `updated_at` columns to the `mailing_lists` table.
- Mailing lists: four MySQL triggers propagate `updated_at` to the parent row when individual members or roles are modified.
- Mailing lists: `v_mailing_lists_resolved` view now exposes `list_created_at` and `list_updated_at`.
- Mailing lists: "Last updated" column displayed after "Name" in the dashboard table.
- Mailing lists: creation date displayed (read-only) in the list edit form.
- Fix an unterminated SQL string literal in `Episciences_User::filterUsers()` when filtering users without roles.
- Fix history filter date popover sizing, correct jQuery UI datepicker positioning/z-index, and fix calendar-icon click interaction in the paper administration view.
- Fix multiple bugs and potential XSS issues found during a public JavaScript audit (including strict tooltip option defaults, escaping regex patterns in search inputs, and removing incorrect JSON dataType requirements).
- Improve `Episciences_Paper_FilesManager::syncFiles()`: generate unique self-link hashes to prevent database collisions when file links are empty or set to `#`, resolve argument order in file difference checking, and clean up paper file deletion logic.
- [#1125](https://github.com/CCSDForge/episciences/pull/1125) Improve report attachment access control: allow paper authors to download, block users with declared COI.
### Deprecated

- Deprecate obsolete `Ccsd_Form_Element_Thesaurus` form element (scheduled for removal).

### Removed

- [#1088](https://github.com/CCSDForge/episciences/pull/1088) Remove unused `AGENT`, `CITY`, `LAT`, and `LON` columns from the `PAPER_STAT` table and delete the legacy `scripts/stat.php` script (superseded by the `stats:process` console command).
- Remove obsolete `google/recaptcha` and `neverbehave/hcaptcha` libraries from `composer.json`.
- Removed obsolete, unused, and deprecated authentication adapters: `Asso`, `DbTable`, `Idp`, `Asso/Ext`, and `Orcid` (`Ccsd/Auth`).
- Removed obsolete `DEAD CODE AUDIT` deprecation warnings from `UserFtpQuota` and `UserFtpQuotaMapper` classes.

### Security

- Restructured ACL and CSRF usage
- Cleaned up and restructured CAS and MySQL authentication adapters (`Ccsd/Auth/Adapter/Mysql`, `Ccsd/Auth/Adapter/Cas`).
- Removed obsolete, unused, and deprecated authentication adapters: `Asso`, `DbTable`, `Idp`, `Asso/Ext`, and `Orcid` (`Ccsd/Auth`).
- Translated ACL and Auth comments to English and enforced strict typing on plugins.
- Prevent potential XSS by HTML-escaping user metadata in the email autocomplete suggestions, and sanitize screen names at the source (cleaning tags, control characters, and whitespace).

## v1.0.55.3 - 2026-05-20

### Fixed

- [RT#287093]: Failed to submit "acceptedAskAuthorsFinalVersion" Form: CSRF validation failed (CSRF field missing)
- Adapt "acceptedAskAuthorsFinalVersion" form to the requirements for setting a deadline for requesting a revision after acceptance

### Changed

- [#1010](https://github.com/CCSDForge/episciences/issues/1010) The allowed range for modifying the deadline is now between the original deadline minus "rating_deadline_min" and the original deadline plus "rating_deadline_max".
- [#998](https://github.com/CCSDForge/episciences/issues/998) Improved COI declaration
  button labels: "Continue (No conflict of interest)" / "Stop (I have a conflict of interest)"

## v1.0.55.2 - 2026-05-19

### Fixed

- Improved article URL resolution in `ExtractBiblioRefsCommand` by adding fallbacks to `getMainPaperUrl()` and `getDocUrl()` when the repository template is empty (e.g., for Zenodo).
- Added debug logging for resolved article URLs and included raw response snippets in JSON decode error messages in `ExtractBiblioRefsCommand`.

## v1.0.55.1 - 2026-05-19

### Added

- Legend for bibliographic reference status icons in both admin and public views (publicly shown only when problematic references are detected).

### Changed

- Replaced bibliographic reference status indicators with standardized square icons (red `fa-square-xmark` for problematic, green `fa-square-check` for validated, and gray `fa-square` for automatically extracted references).
- Improved accessibility of bibliographic references (added ARIA landmarks, screen-reader-only status prefixes, descriptions for status badges, and warnings for links opening in new tabs).
- Optimized bibliographic references layout by moving the status legend inline with the action button.
- Renamed 'Not validated reference' to 'Automatically extracted reference' for clarity.
- Renamed 'Manage' button to 'Manage References' / 'Gérer les références' in the administration view.

### Fixed

- Removed obsolete BibTeX import button (feature migrated to an external application).
- Removed hardcoded Semantic Scholar source attribution from bibliographic references display.
- Skip temporary versions (without external repository URL) during bibliographic reference extraction.
- Fixed a missing dependency (`require_once`) for `GetDoiCommand` in `console.php`.

## v1.0.55 - 2026-05-18

### Added

- Automatic detection of problematic papers cited in the references of the papers. This is heavily based on the huge work provided by the Problematic Paper Screener https://dbrech.irit.fr/pls/apex/f?p=9999:1::::::
  For more information: Cabanac, G., Labbé, C., & Magazinov, A. (2022). The ‘Problematic Paper Screener’ automatically selects suspect publications for post-publication (re)assessment.
  Presented at WCRI 2022: 7th World Conference on Research Integrity. arXiv preprint. https://doi.org/10.48550/arXiv.2210.04895

- [#630](https://github.com/CCSDForge/episciences/issues/630) COI (Conflict of Interest) notifications:
    - Email notification to the editor-in-chief when an editor declares a positive COI (answered "yes")
    - Email notification to other assigned editors (if any) when an editor declares a COI
- Many legacy scripts migrated to Symfony Console commands (e.g., `doi:manage` replaces `getDoi.php`, `sitemap:generate --all`, translations update).
- Expanded unit test coverage for repository connectors (Zenodo, BioMedRxiv, ARCHE, Dspace) and COI notifications (+60 tests).

### Changed

- Sending the invitation in the user’s language, including when using the autocomplete input field for CAS users who already have a profile on the site.
- Simplified news add/edit form for a better user experience.
- Refactored OpenAIRE integration into a dedicated `OpenAireApiClient`.
- Improved PHP 8.1 compatibility and robustness across the codebase (removed deprecations and warnings).
- Optimized the submission workflow processing order.

### Fixed

- A bug that likely appeared in the latest update: section and/or volume editors are not assigned automatically, even if the relevant settings are enabled.
- Panel not opening when updating linked data
- Submission of a dataset without a data descriptor due to an error while uploading attached files (one of the file types exceeded the maximum allowed size for that field type)
- Corrected article position in volumes (1-based instead of 0-based) for CSL generation.
- Resolved relative `hydra:next` URLs in sitemap generation.
- Fixed several potential `TypeError` and crashes when metadata or publication dates are missing.

## v1.0.54.3 - 2026-04-23

### Fixed

- The link to the new version of the data descriptor isn't working; it still points to the first version.
- [RT#285106]: Application error when submitting with 'display secondary volume' option enabled
- Always SELECT RECORD so Paper::getMetadata() / toJson() can resolve titles and abstracts
- Log parser: extract IP from syslog-prefixed Apache log lines

## v1.0.54.2 - 2026-04-16

### Fixed

- Inability to change the review deadline (restore loading js via url).

### Added

- docs: add documentation index to README and update docs
- stats:import-logs command to parse Apache logs into STAT_TEMP. Introduces ImportApacheLogsCommand (stats:import-logs), a Symfony Console command that parses Apache access logs and inserts article visits into STAT_TEMP. Replaces the legacy UpdateStatistics.php . Run src/mysql/2025-08-24-stat-processing-log-table.sql before deploying

## v1.0.54.1 - 2026-04-14

### Changed

- Refactored English email templates, grammar only, no new features, no new tags

### Fixed

- [#962](https://github.com/CCSDForge/episciences/issues/962) Fixed double HTML encoding bug in volume metadata titles where special
  characters like `<`, `>`, and `'` were displayed as `&lt;`, `&gt;`, `&#039;`
- TypeError in enrichment:creators on non-sequential affiliation keys
- isImported detection for published papers when the the publication date has been fixed after publication
- [#985](https://github.com/CCSDForge/episciences/issues/985) Impossible to submit a final version
- Fixed bug in management of journal headers for adding and updating logo and texts

## v1.0.54 - 2026-04-09

### Added

- [#779](https://github.com/CCSDForge/episciences/issues/779) [Feature request] Display the secondary volume on the article page. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Adding "Publish" => "Proposing special issues" template page [#951](https://github.com/CCSDForge/episciences/issues/951)
- New supported servers:
    - Cryptology ePrint Archive (https://eprint.iacr.org)
    - Support for any data repositories powered by Dspace (eg. [repositorium.uminho(University of Minho)](https://repositorium.uminho.pt))
- Statistics: new Symfony Console commands `stats:process` (replaces legacy `scripts/stat.php`) and `stats:update-robots-list` for UA-based bot detection using the COUNTER Robots list; real client IP is now stored in `STAT_TEMP` and anonymized at processing time.
- Documentation: added `docs/console-commands.md` listing all 19 CLI commands available via `scripts/console.php` with options and usage examples.
- OAI-PMH: added `crossref` as a new metadata format (`metadataPrefix=crossref`), exposing Crossref 5.3.1 XML for each published paper. The depositor email is automatically replaced with a generic `noreply@{domain}` address in this context.
- Comprehensive Jest test suite for ORCID authors management (`tests/js/updateOrcidAuthors.test.js`).
- Unit tests for `Episciences_Paper_Projects` entity (setOptions aliases, DateTime handling, toArray serialization, fluent setters).
- Unit tests for `Episciences_Paper_Projects_EnrichmentService` pure functions (EU/ANR response normalization, OpenAIRE relation filtering).
- Unit tests for `Episciences_Paper_Projects_ViewFormatter` (empty-array return, URL HTML-escaping, unidentified title suppression).
- Unit tests for `Episciences_Paper_Citations_ViewFormatter` (20 tests: sort, author formatting, ORCID links, XSS prevention, book-chapter/proceedings-article reordering).
- Unit tests for `Episciences_Paper_Citations` entity (8 tests: `toArray()` key, fluent setters, nullable `updatedAt`, constructor).
- Comprehensive unit tests for `Episciences_Paper_Authors_ViewFormatter` covering HTML display logic and XSS prevention.
- Unit tests for `Episciences_View_Helper_DoiAsLink`, `Episciences_View_Helper_FormatIssn`, `Episciences_View_Helper_Log`, `Episciences_View_Helper_Tag`, `Episciences_View_Helper_UserAvatar`, `Episciences_View_Helper_GetAvatar` and `Episciences_CommentsManager`.
- 89+ unit tests for `Ccsd\Auth` adapters (`CasAbstract`, `Idp`, `Orcid`, `AdapterFactory`, `Asso`), `Ccsd\User` models (`User`, `UserTokens`, `UserFtpQuota`) and `Episciences\User` entities covering pure logic without DB or network access.
- Updated `tests/README.md` with accurate Docker-based testing instructions, `make` target reference, subset-run examples, and contributor guidelines.
- [#883](https://github.com/CCSDForge/episciences/issues/883) Allow json files as attachments.
- Optional Webhook: It is now possible to report status changes to an external entry point (can be configured by journal).
- [#658](https://github.com/CCSDForge/episciences/issues/658) It is now possible to link an invitation that is not intended for you to your account.
- allow the co-author to view the publication.
- Statistics: the script has a new parameter `--all` - Process all statistics (with confirmation prompt).
- New option to allow Editors to receive 'Comments for editors' before declaring a conflict of interest (disabled by default).
- Volume Settings: add New configuration section(displayEmptyVolumes and allowEditVolumeTitleWithPublishedArticles) in journal settings.
- [#679](https://github.com/CCSDForge/episciences/issues/679) "Ask other editors for their opinion" form now includes chief editors (ROLE_CHIEF_EDITOR) in addition to regular editors (ROLE_EDITOR).
- [#691](http://github.com/CCSDForge/episciences/issues/691) Display "(optional)" label below comment and cover letter fields in submission forms to clarify these fields are not required.
- [#350](https://github.com/CCSDForge/episciences/issues/350) Author-Editor Communication Settings:
    - New option to allow authors to contact assigned editors (`authorEditorCommunication`)
    - New option to disclose editor names to authors or keep them anonymized (`discloseEditorNamesToAuthors`)
- feat(navigation): sync predefined page titles with T_PAGES table on menu save.
- New `library/Episciences/Api/` namespace with 5 injectable, independently-testable API clients: `AbstractApiClient`, `CrossrefApiClient`, `OpenAlexApiClient`, `OpenCitationsApiClient`, `OpenAireApiClient`.
- Five Symfony Console commands replacing the legacy JournalScript enrichment scripts: `enrichment:citations`, `enrichment:creators`, `enrichment:funding`, `enrichment:licences`, `enrichment:links`.
- Added MSC2020 Classification in ZBJATS export.
- `Episciences\Notify\NotifySourceConfig` — immutable value object holding per-source configuration (repoId, label, originId, originInbox, acceptedTypes); new repositories can be added without touching `InboxNotifications`
- `Episciences\Notify\NotifySourceRegistry` — registry with a `createFromConstants()` factory; resolves source config from a payload's `origin.inbox`
- `Episciences\Notify\ValidationResult` — pure value object wrapping a validation outcome (success / failure + error message)
- `Episciences\Notify\PreprintUrlParser` — pure helper extracting version and identifier from preprint landing-page URLs (HAL-style)
- `Episciences\Notify\PayloadValidator` — validates COAR Notify 1.0.1 payloads for Offer patterns (Request Review / Request Endorsement): all required fields including `object.id` (HTTP URI) and `object['ietf:item']` (id, type, mediaType)

### Changed

- [#947](https://github.com/CCSDForge/episciences/issues/947) [Feature request] wording on the platform - refused / rejected and refused/declined
- Refactored `public/js/paper/updateOrcidAuthors.js` from jQuery to modern vanilla ES6+ with a class-based architecture.
- Enhanced ORCID form validation: added duplicate detection while maintaining support for empty submissions for deletions.
- Update TinyMCE form 7.3.0 to 8.1.2.
- Increased Volume Description length to 1024 chars.
- DOI panel: requesting, saving, and cancelling a DOI no longer triggers a full page reload; success feedback message is suppressed.
- Refactored `Episciences_Paper_ProjectsManager` into `Episciences_Paper_Projects_Repository`, `Episciences_Paper_Projects_HalApiClient`, `Episciences_Paper_Projects_EnrichmentService`, and `Episciences_Paper_Projects_ViewFormatter`.
- Refactored `Episciences_Paper_CitationsManager` into `Episciences_Paper_Citations_Repository`, `Episciences_Paper_Citations_ViewFormatter`, `Episciences_Paper_Citations_EnrichmentService`, and `Episciences_Paper_Citations_Logger`.
- Redundant PHPDoc removed from all new citations classes via Rector.
- Replaced `Episciences_Cache` with `symfony/cache` 5.4 across all internal usages.
- `PapersManager::getList()`: removed `$cached` parameter; paper list is now always fetched fresh.
- `Review::getPapers()`, `CopyEditor::loadAssignedPapers()`, `Editor::loadAssignedPapers()`, `Reviewer::loadAssignedPapers()`, `Volume::getPaperListFromVolume()`: updated signatures and call sites following the removal of `$cached`.
- `Oai/Server::getIds()`: OAI resumption token cache migrated to PSR-6.
- Statistics: log import script now supports anonymized log files (.access_log_anonym.gz) as a fallback.
- Improved accessibility of the ORCID authors modal: proper `<label>` associations, `aria-modal="true"`, and corrected `aria-labelledby`.
- Modernized ORCID authors row layout using Flexbox.
- Standardized modal button styles for ORCID updates (Cancel: `btn-default`, Save: `btn-primary`).
- ORCID input fields: URLs are now automatically cleaned to keep only the 16-digit identifier.
- `administratepaper/view.phtml`: reordered panels; moved "Volumes & Rubriques" earlier; `paper_versions` moved to the bottom; removed redundant label.
- `paper/paper_datasets.phtml`: "Liens publications – données – logiciels" panel is now collapsed by default.
- `paper/paper_graphical_abstract.phtml`: graphical abstract panel is now collapsed by default when empty.
- `partials/coauthors.phtml`: "Ajouter un co-auteur" panel is now collapsed by default.
- `partials/paper_affiliation_authors.phtml`: "Ajouter une affiliation" panel is now collapsed by default.
- `volume/list.phtml`: Added "Number" and "Year" columns; improved table accessibility; Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: Removed redundant inline scripts; construction of the editors list optimized.
- Updated icons from `fas fa-address-card` to `fa-regular fa-address-card`.
- Improved visual indicators for editor availability in `editor-availability.js`.
- Refactored `scripts/zbjatZipper.php`: renamed class to `ZbjatZipper`, replaced `echo` with Monolog, extracted God method, switched to HTTPS.
- Refactored `Episciences_Paper_AuthorsManager` into `Episciences_Hal_TeiCacheManager`, `Episciences_Paper_Authors_HalTeiParser`, `Episciences_Paper_Authors_Repository`, `Episciences_Paper_Authors_EnrichmentService`, `Episciences_Paper_Authors_AffiliationHelper`, and `Episciences_Paper_Authors_ViewFormatter`.
- Refactored `Episciences_Paper_Authors_ViewFormatter` to separate data fetching from formatting logic.
- Moved `normalizeOrcid()` implementation from `AuthorsManager` to `HalTeiParser`.
- `AuthorsManager::ONE_MONTH` now references `TeiCacheManager::ONE_MONTH`.
- Added `TeiCacheManager::fetchAndGet()` to combine fetch-if-needed + read in a single call.
- The "Encapsulate reviewers" parameter is now hidden.
- [#528](https://github.com/CCSDForge/episciences/issues/528): 'upload a new version' on top; updated contact label.
- Paper metrics Refactored; 1 query instead of 2.
- The Locale is now stored in a cookie instead of the session.
- Redirect to the article to be reviewed when accepting an invitation.
- Allow volume years to be a string (AAAA or AAAA-AAAA).
- Synchronization of predefined page titles between navigation menu and T_PAGES table when saving menu configuration.
- Replaced `cottagelabs/coar-notifications` (Doctrine ORM + Guzzle) with `cottagelabs/coarnotify` (stateless pattern builder) and a custom PDO persistence layer (`Notification` DTO + `NotificationsRepository`)
- Refactored `InboxNotifications` (1390-line God Class) into focused private methods (`processFirstSubmission`, `processSubsequentSubmission`, `setupJournalTranslations`, `resolveNewVersionStatus`, `logVersionUpdate`, …) and pure, dependency-free helper classes
- `checkNotifyPayloads()` and `initSubmission()` are now source-aware: the COAR Notify source is resolved from `origin.inbox` via `NotifySourceRegistry`
- `InitSubmissionsFromHalInbox.php` is now a direct CLI entry point on `InboxNotifications`; the empty subclass has been removed

### Fixed

- [#858](https://github.com/CCSDForge/episciences/issues/858) fix: only use "et al." when 2+ authors are omitted. External contributor [WillemScholten](https://github.com/WillemScholtenLeiden)
- Limit the editor’s permissions to allow assigning only the "reviewer" role.
- [#944](https://github.com/CCSDForge/episciences/issues/944): An author informed us that they received this message signed with their name: %%SENDER_FULL_NAME%% and %%RECIPIENT_SCREEN_NAME%% filled with the same value
- [#258](https://github.com/CCSDForge/episciences-front/issues/258): Tables are not handled by default: Explicit table conversion
- Fixed RT#277365: in order to correct the printing of extra lines, two options have been added to the TinyMCE configuration to insert a <br> instead of a <p> and another option to remove extra <br>s at the end of a block
- Fixed tooltip initialization in volume and section assignment after AJAX refreshes and DataTable redraws.
- Fixed Solr search engine indexation of Authors with a middle name.
- Consider an additional review when sending reminders for an insufficient number of reviewers.
- `Projects`: `$_dateUpdated` default fixed; `getDateUpdated()` return type consistency.
- `Projects::setFunding()`: made method fluent.
- `Projects::setOptions()`: fixed `setIdproject()` alias and case-insensitive method lookup.
- `Projects::toArray()`: fixed `DateTime` serialization.
- `Repository::insert()`: replaced deprecated `VALUES()` syntax with row-alias syntax for MySQL 8.0.20+.
- `Repository`: exceptions now propagate instead of terminating script on DB errors.
- `ViewFormatter`: fixed XSS by escaping `$vfunding['url']` and other user-controlled values in HTML attributes.
- `HalApiClient::doGet()`: added `E_USER_WARNING` severity level.
- `EnrichmentService::resolveHalProjectIds()`: removed duplicate log message; sanitized cache key.
- `Citations_ViewFormatter`: removed double escaping; fixed unstable compound sort; added ORCID format validation.
- `Citations_Repository`: replaced deprecated MySQL `VALUES()` function; added check for `$docId <= 0`.
- `Citations` entity: fixed `toArray()` key name; fixed `$_updatedAt` type and default.
- ORCID normalization: `cleanLowerCaseOrcid()` fix; applied normalization in Zenodo and ARCHE hooks.
- `findAffiliationsOneAuthorByPaperId()`: fixed potential undefined variable.
- `hasAcronym()`: fixed iteration over nested `id` array.
- `HalTeiParser::getAuthorsFromHalTei()`: fixed logic to prevent enriching the wrong author.
- `ViewFormatter::buildAffiliationListHtml()`: fixed Stored XSS by escaping the affiliation acronym.
- `ViewFormatter`: fixed `html_entity_decode(htmlspecialchars())` no-op.
- `EnrichmentService::mergeExistingAffiliations()`: fixed matching key lookup with `array_search()`.
- `AffiliationHelper::isAcronymDuplicate()`: now iterates all identifiers.
- `AffiliationHelper::setOrUpdateRorAcronym()`: returns first match deterministically.
- `TeiCacheManager::buildApiUrl()`: applied `urlencode()` to prevent Solr query injection.
- `TeiCacheManager::getFromCache()`: removed dead `expiresAfter()` call.
- `Repository`: fixed `JSON_DECODE_FLAGS`.
- `CommentsManager::$_typeLabel`: added missing entry for `TYPE_CONTRIBUTOR_TO_REVIEWER`.
- `CommentsManager::updateUid()`: fixed negative UID guard.
- `FormatIssn::FormatIssn()`: fixed substring length.
- `Log::log()`: fixed uncaught exception from `$logger->log()`.
- `DoiAsLink::DoiAsLink()`: fixed label when no text provided.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass with `preg_quote()` and anchors.
- Fixed RT#277365: added TinyMCE configuration options to handle `<br>` and `<p>` correctly.
- [#886](https://github.com/CCSDForge/episciences/issues/886): reminder logic for lack of reviewers fixed.
- `convertToBytes()`: fixed handling of pure numeric strings and added validation.
- `isHal()`, `isHalUrl()`, `isArxiv()`: fixed regex anchors.
- Added comprehensive test coverage for `Episciences_Tools`.
- [#236](https://github.com/CCSDForge/episciences-front/issues/236): HTML entities (&) displayed in the title.
- [#695](https://github.com/CCSDForge/episciences/issues/695) Fixed CC/BCC fields not clickable in paper status change forms.
- [#830](https://github.com/CCSDForge/episciences/issues/830) Paper number messed up for secondary volume; altered secondary volume rendering.
- [#776](https://github.com/CCSDForge/episciences/issues/776) Action Required: Fix Renovate Configuration.
- [#657](https://github.com/CCSDForge/episciences/issues/657) Conditionally remove the `<hr>` separator in metadata views.
- [#786](https://github.com/CCSDForge/episciences/issues/786) English translation of 'Télécharger le fichier'.
- [#646](https://github.com/CCSDForge/episciences/issues/646) Redirect to article page after submission.
- [#780](https://github.com/CCSDForge/episciences/issues/780) Option to lock volume name when an article is published.
- [#147](https://github.com/CCSDForge/episciences/issues/147) Add new pages Acknowledgements in menu 'About'.
- Fixed Paper Metrics based on wrong DocId.
- Fixed Pre-defined pages not deleted from the database.
- [#77](https://github.com/CCSDForge/episciences-front/issues/77) Fix orphan assignments when deleting sections or volumes.
- `CrossrefTools`: fixed fatal error on constant name; fixed raw `CacheItem` return.
- `OpenalexTools`: fixed `getPages()` return value and trailing separator in `getAuthors()`.
- `OpencitationsTools`: fixed caching of empty responses; fixed DOI extraction and updated API format support.
- `getLinkData`: switched to HTTPS; added null guards for `$valuesResult`.
- `getLicenceDataEnrichment`: fixed ineffective cache.
- `OpenAireResearchGraphTools`: enabled strict mode for `array_search()`.
- `Projects/EnrichmentService`: fixed log path and added `(int)` cast for source ID.
- `Citations/EnrichmentService`: added null guards for OpenAlex API fields.
- [#875](https://github.com/CCSDForge/episciences/issues/875): fixed HTML/Emoji translation issue.

### Deprecated

- `Episciences_Cache` and its parent `Ccsd_Cache` are now marked `@deprecated`; use `Symfony\Component\Cache\Adapter\FilesystemAdapter` instead.
- `ProjectsManager` kept as a thin backward-compatible facade.
- `CitationsManager` kept as backward-compatible facade with `@deprecated` proxies.
- `AuthorsManager` kept as orchestrator with backward-compatible `@deprecated` proxies.
- `Episciences_Paper_Visits::count` is deprecated.

### Removed

- Redundant "Statut actuel :" label prefix from the article status panel.
- Stray `<br>` and inlined `versionCache` script tag in `partials/paper_affiliation_authors.phtml`.
- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`.

### Security

- Removed implicit `unserialize()` on filesystem-cached paper data in `PapersManager::getList()`, eliminating a potential PHP object injection vector.
- OAI resumption token cache keys are now MD5-hashed before use, preventing cache-key injection.
- Escaped output in `volume/editors_list.phtml` to prevent potential XSS.
- Fixed XSS vulnerability in `Projects/ViewFormatter`: funding URL was interpolated unescaped into `href` attribute and link text.
- Fixed XSS in `Citations_ViewFormatter`: `href=` attributes for DOI and OA links were unquoted.
- Fixed XSS vulnerability in `ViewFormatter::buildAuthorHtml()` and `buildAffiliationListHtml()` regarding user-controlled values in HTML attributes.
- Fixed potential Solr query injection in `TeiCacheManager::buildApiUrl()`.
- `GetAvatar::asPaperStatusSvg()`: fixed two path traversal vectors ($lang sanitization and $paperStatus casting).
- `DoiAsLink::DoiAsLink()`: added `rel="noopener noreferrer"` to prevent tab-napping.
- `Ccsd\Auth\Adapter\Idp::filterEmail()`: fixed regex bypass that allowed authentication from unauthorized email domains.

### Performances

- `volume/list.phtml`: Eager load volume settings to prevent N+1 queries.
- `volume/editors_list.phtml`: construction of the editors list optimized.
- `Episciences_Paper_Citations_Logger`: Singleton Monolog logger (avoids Logger recreation on every call).
- Paper metrics Refactored; 1 query instead of 2.
