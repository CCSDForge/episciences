# End-to-end tests (Playwright)

The suite drives the application in a real browser, against the docker-compose development stack
(`https://dev.episciences.org`), or against the PHP built-in server on CI.

## Prerequisites

1. The docker-compose stack is running (`make up`), including Mailpit.
2. `config/pwd.json` uses the local authentication adapter and has the HMAC secrets (both are set
   in `config/dist-dev.pwd.json`):
   ```json
   "EPISCIENCES": { "AUTH_ADAPTER_NAME": "MYSQL" },
   "REVIEWER_INVITATION": { "HMAC_SECRET": "..." },
   "UPLOADED_FILE": { "HMAC_SECRET": "..." }
   ```
   With the CAS adapter, the login form is on the CAS server, where the E2E accounts do not exist.
   Without `UPLOADED_FILE.HMAC_SECRET`, every file upload is refused.
3. `dev.episciences.org` and `mailpit.episciences.org` resolve to the stack (`/etc/hosts`).
4. The PHP container can reach arXiv: papers are submitted from arXiv.

## Running

```bash
yarn test:e2e                                    # whole suite
npx playwright test tests/e2e/specs/07-review/   # one directory
npx playwright test -g "minor revision"          # by test title
yarn test:e2e:ui                                 # interactive mode
yarn test:e2e:report                             # last HTML report
yarn lint:e2e                                    # ESLint with eslint-plugin-playwright, run by the CI
```

Before the tests, `global-setup.js` runs `php scripts/console.php app:e2e-seed --reset`:

- it creates (or resets) the E2E accounts, all with the password `password123`:
  `e2e_author`, `e2e_reviewer`, `e2e_referee` (second reviewer), `e2e_editor`, `e2e_secretary`, `e2e_admin` (administrator and editor
  in chief);
- it removes what previous runs left in the journal: every paper of `e2e_author`, then every volume
  and section whose English title starts with `E2E `, with the rating grid of each removed volume.
  Nothing else is touched;
- it sets the journal settings the suite relies on: one completed review at least before a paper can
  be accepted, published or sent back for revision (`requiredReviewers`), in the first review round
  only (`requiredReviewersRounds` = 1, `requiredReviewersLaterRounds` empty, that is none); editors may accept papers
  (`editorsCanAcceptPapers`). The specs have the paper reviewed first (`completeReview()` in
  `helpers/editorial-workflow.js`). Reviewers can send messages to authors (`reviewersCanCommentArticles`),
  and the conflict of interest declaration (`isCoiEnabled`) follows `E2E_COI`. The initial settings are
  restored after the run.

Environment variables:

| Variable        | Default                                                           | Purpose                                           |
|-----------------|-------------------------------------------------------------------|---------------------------------------------------|
| `BASE_URL`      | `https://dev.episciences.org/`                                    | Journal under test                                |
| `APP_MODE`      | -                                                                 | `manager` for the `*-epi-manager` branches (default `BASE_URL`: `https://manager-dev.episciences.org/dev/`) |
| `E2E_RVCODE`    | `dev`                                                             | Code of the journal under test (seed, reminders)  |
| `MAILPIT_URL`   | `https://mailpit.episciences.org`                                 | Mailpit API                                       |
| `E2E_PHP_EXEC`  | `docker exec -u www-data -w /var/www/htdocs episciences-php-fpm php` | Runs the PHP scripts (seed, mail queue)        |
| `E2E_SKIP_SEED` | -                                                                 | `1` to skip the seed and reset                    |
| `E2E_COI`       | -                                                                 | `1` to run with the conflict of interest declaration enabled |

PHP scripts run as `www-data`: run as root, they would leave cache files the web server cannot
write anymore (`Failed saving metadata to metadataCache` notices).

### Manager mode (`*-epi-manager` branches)

On the `main-epi-manager` and `preprod-epi-manager` branches, a journal is served under its code
(`https://manager-dev.episciences.org/dev/paper/view/...`) instead of its own host name. The same
suite runs in both modes; on a `*-epi-manager` checkout:

```bash
APP_MODE=manager yarn test:e2e
```

The journal must be switched to the new front-end, otherwise its code is not read from the URL:
`UPDATE REVIEW SET is_new_front_switched = 'yes' WHERE CODE = 'dev';`. The `manager-dev`
virtual host (`src/apache/vhost.conf`) has no `RVCODE`, which would disable the prefix.

## Layout

```
tests/e2e/
├── *.spec.js                  anonymous pages (home, browse, search, login guard...)
├── specs/
│   ├── 01-auth/               access of each role
│   ├── 02-volumes/            volume CRUD and reordering
│   ├── 03-sections/           section CRUD and reordering
│   ├── 04-paper-sorting/      paper list sorting, filtering and paging
│   ├── 05-comments/           comment attachment deletion security
│   ├── 06-submission/         submission from arXiv
│   ├── 07-review/             reviewer invitation by email, acceptance (also by the editor on behalf
│   │                          of the reviewer), review with a volume grid
│   ├── 08-editorial-decisions/ acceptance, rejection, minimum number of reviews per review round
│   ├── 09-revisions/          minor revision with a new version, major revision answered as is
│   ├── 10-publication/        publication in a volume, copy editing by the author
│   ├── 11-journal-configuration/ rating grid lifecycle, journal settings
│   └── 12-conflict-of-interest/ declaration of conflicts of interest (one test per E2E_COI mode)
├── fixtures/
│   ├── e2e-fixtures.js        logged-in page per role (authorPage, reviewerPage, refereePage, editorPage,
│   │                          secretaryPage, adminPage) and the mailpit client
│   └── files/minimal.pdf      file uploaded by the tests
├── helpers/
│   ├── editorial-workflow.js  submit, assign an editor, decide, invite, create a volume or a grid...
│   │                          through the UI
│   ├── mailpit-client.js      waits for an email, extracts its links
│   ├── app-cli.js             runs the PHP scripts (seed and reset, mail queue)
│   ├── app-url.js             base URL of both URL modes, toAppPath() for links read in emails or pages
│   └── login.js               logs in through the login form
└── global-setup.js
```

## Writing tests

- **One session per worker.** Each worker logs in once per role (`.auth/<role>-worker-<n>.json`).
  Flash messages and one-time form tokens live in the PHP session: two tests sharing a session would
  consume each other's confirmation messages.
- **Each workflow test owns its data.** A test submits its own arXiv preprint (one preprint can be
  submitted only once to a journal, so every spec uses a different identifier) and creates its own
  volumes and grids, titled `E2E ...` so that the next run removes them. Never rely on existing
  papers, volumes or document ids of the journal: they do not exist on CI.
- **Never submit from HAL.** Publishing a HAL paper sends a COAR Notify announcement to HAL.
- **Emails.** The application queues emails on disk; `mailpit.waitForEmail()` flushes the queue
  (`scripts/send_mails.php`) before each search. Tests share the mailbox: never empty it, match on
  something only your test can match, such as the paper id that every paper email subject contains
  (`dev #123 - ...`).
- **A workflow is one test**, split with `test.step()`: its steps depend on each other. There is no
  retry, as a failed workflow cannot run again on the same data until the next reset.
- **Assert, do not guard.** Do not wrap actions in `if (await locator.isVisible())`: a test that
  silently skips its actions passes for the wrong reason. Conditions are only legitimate for journal
  settings (for instance the data descriptor some journals require at submission). `yarn lint:e2e`
  flags conditional assertions, fixed waits and `networkidle`.
- **Conflict of interest.** Every test must pass with `E2E_COI=1` and without it. With the
  declaration enabled, the editorial team is sent to `/coi/report` before managing a paper:
  `openPaperManagement()` answers "no conflict" itself, so always open the management page through it.
- **Rating comments.** Fill the criterion comments with `fillRatingComments()`: the rating page also
  holds the TinyMCE editor of the message to the author.
- **TinyMCE editors.** Some buttons are only enabled by an `input` event of the editor: type into
  its iframe (`frameLocator('#<id>_ifr').locator('body')`) rather than calling `setContent()`.

- **Relative URLs only.** Navigate with paths relative to the base URL (`page.goto('paper/view')`,
  never `'/paper/view'`), and turn the links read in emails or in the page (`href`) into such paths
  with `toAppPath()` (`helpers/app-url.js`): the manager mode serves the journal under `/dev/`.
  `yarn lint:e2e` rejects absolute paths in `goto()` / `request.get()`. Match URLs with unanchored
  patterns (`toHaveURL(/paper\/ratings/)`).

## CI

`.github/workflows/e2e.yml` installs the application on the runner, serves it with
`php -S` (`.github/scripts/e2e-router.php` mirrors `public/.htaccess`) under `dev.episciences.org`,
which absolute URLs require, and runs the suite with `E2E_PHP_EXEC=php`.

The CI database is the development dataset (`src/mysql/docker/episciences/dev-episciences.sql`,
also loaded by `make load-dev-db`): the dev journal with its repositories, settings and mail
templates. The Messenger tables are created with `episciences:queue --setup`, and
`tests/fixtures/e2e-navigation.json` declares the public pages of the journal. A new migration
must also be applied to that dataset, or the E2E suite runs on an outdated schema. A Solr
container (configset of `src/solr/episciences`) and the Solr indexing worker run next to the web
server: the volume pages list the papers indexed in Solr.

It runs on pull requests targeting `staging`, `main`, `main-epi-manager` or `preprod-epi-manager`,
and can be triggered manually (`workflow_dispatch`):

- pull requests targeting a `*-epi-manager` branch (for instance the merge of `main` into
  `main-epi-manager`) run in manager mode: the journal is flagged `is_new_front_switched`, served
  without `RVCODE` under `manager-dev.episciences.org/dev/`, and the router mirrors the aliases of
  the manager virtual host. A manual run chooses the mode (`app_mode` input);

- draft pull requests and documentation only changes (`docs/**`, `*.md`) are not tested; a draft is
  tested once marked ready for review;
- a new push to a pull request cancels the run of its previous commit;
- the suite runs without the conflict of interest declaration on every pull request, and also with
  it (`E2E_COI=1`) on pull requests targeting `main` and on manual runs. Each mode has its own
  runners and database;
- each mode is split into two shards (`--shard`) running at the same time, each with its own report
  artifact.

Locally, run both modes one after the other (`E2E_COI=1 yarn test:e2e`): the setting applies to the
whole journal.
