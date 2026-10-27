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
  `e2e_author`, `e2e_reviewer`, `e2e_editor`, `e2e_secretary`, `e2e_admin` (administrator and editor
  in chief);
- it removes what previous runs left in the journal: every paper of `e2e_author`, then every volume
  and section whose English title starts with `E2E `, with the rating grid of each removed volume.
  Nothing else is touched.

Environment variables:

| Variable        | Default                                                           | Purpose                                           |
|-----------------|-------------------------------------------------------------------|---------------------------------------------------|
| `BASE_URL`      | `https://dev.episciences.org/`                                    | Journal under test                                |
| `APP_MODE`      | -                                                                 | `manager` for the `*-epi-manager` branches        |
| `MAILPIT_URL`   | `https://mailpit.episciences.org`                                 | Mailpit API                                       |
| `E2E_PHP_EXEC`  | `docker exec -u www-data -w /var/www/htdocs episciences-php-fpm php` | Runs the PHP scripts (seed, mail queue)        |
| `E2E_SKIP_SEED` | -                                                                 | `1` to skip the seed and reset                    |

PHP scripts run as `www-data`: run as root, they would leave cache files the web server cannot
write anymore (`Failed saving metadata to metadataCache` notices).

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
│   ├── 07-review/             reviewer invitation by email, acceptance, review with a volume grid
│   ├── 08-editorial-decisions/ acceptance, rejection
│   ├── 09-revisions/          minor revision with a new version, major revision answered as is
│   ├── 10-publication/        publication in a volume, copy editing by the author
│   └── 11-journal-configuration/ rating grid lifecycle, journal settings
├── fixtures/
│   ├── e2e-fixtures.js        logged-in page per role (authorPage, reviewerPage, editorPage,
│   │                          secretaryPage, adminPage) and the mailpit client
│   └── files/minimal.pdf      file uploaded by the tests
├── helpers/
│   ├── editorial-workflow.js  submit, decide, invite, create a volume or a grid... through the UI
│   ├── mailpit-client.js      waits for an email, extracts its links
│   ├── app-cli.js             runs the PHP scripts (seed and reset, mail queue)
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
- **TinyMCE editors.** Some buttons are only enabled by an `input` event of the editor: type into
  its iframe (`frameLocator('#<id>_ifr').locator('body')`) rather than calling `setContent()`.

## CI

`.github/workflows/e2e.yml` installs the application on the runner, serves it with
`php -S` (`.github/scripts/e2e-router.php` mirrors `public/.htaccess`) under `dev.episciences.org`,
which absolute URLs require, and runs the suite with `E2E_PHP_EXEC=php`.

It runs on pull requests targeting `staging` or `main`, and can be triggered manually
(`workflow_dispatch`).
