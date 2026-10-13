# Read-Only Maintenance Mode

The read-only mode keeps the platform available for **public consultation** (papers, PDFs, volumes, search, exports, RSS, OAI-PMH) while guaranteeing that the web application sends **no write query to the database**. It is meant for database maintenance: schema migrations, MySQL upgrades, node switchover, restoration.

While it is enabled:

- visitors see a maintenance banner on every page (journals and portal), with the planned schedule when configured (it can also be announced beforehand, see [Maintenance banner](#maintenance-banner));
- only public pages that do not write to the database are served, whoever is logged in; every other page answers **HTTP 503** with a `Retry-After` header;
- logins, account creation, submissions and every form are suspended;
- paper visits are not recorded in the statistics.

Outside maintenance the mode is disabled and has no effect.

---

## Configuration

All settings live in the `EPISCIENCES` section of `config/pwd.json` (see the tracked template `config/dist-pwd.json`):

```json
{
  "EPISCIENCES": {
    "READ_ONLY_MODE": true,
    "READ_ONLY_MODE_START": "2026-10-20T08:00:00+02:00",
    "READ_ONLY_MODE_END": "2026-10-20T12:30:00+02:00"
  }
}
```

| Key | Constant | Default | Description |
|---|---|---|---|
| `READ_ONLY_MODE` | `EPISCIENCES_READ_ONLY_MODE` | `false` | Enables the read-only mode (boolean; `true`/`false`, `1`/`0`, `on`/`off` and `yes`/`no` are also accepted) |
| `READ_ONLY_MODE_START` | `EPISCIENCES_READ_ONLY_MODE_START` | `""` | Planned start, displayed to visitors and announced beforehand (optional) |
| `READ_ONLY_MODE_END` | `EPISCIENCES_READ_ONLY_MODE_END` | `""` | Planned end, displayed to visitors and used for `Retry-After` (optional) |

Notes:

- `config/pwd.json` is read on every request: changes take effect **immediately**, without reloading PHP-FPM. It must be changed on **every web server**.
- A syntax error in `config/pwd.json` makes the whole site unavailable (`public/bdd_const.php` answers 503): validate the file after editing it, e.g. `python3 -m json.tool config/pwd.json`.
- Any other `READ_ONLY_MODE` value (a typo such as `"oui"`) leaves the mode **disabled** and logs a `warning` in the application log.
- The planned start and end are **informational only**: they never switch the mode on or off. Only `READ_ONLY_MODE` does.
- Dates use the ISO 8601 format **with a UTC offset** (`2026-10-20T08:00:00+02:00`); the `Z` suffix (`2026-10-20T06:00:00Z`) and time zone names or abbreviations (`Europe/Paris`, `CEST`) are accepted too, the banner always displays the UTC offset. Without an offset, the server time zone is assumed. Either date may be omitted; an empty or invalid value is ignored (the schedule is simply not displayed).

### Displayed schedule

The banner shows the dates in the interface language, in the time zone they were configured with, followed by their UTC offset, so that visitors from any time zone can convert them:

| Configured | English | French |
|---|---|---|
| start and end | Maintenance scheduled from October 20, 2026 at 8:00 AM (UTC+02:00) to … | Maintenance prévue du 20 octobre 2026 à 08:00 (UTC+02:00) au … |
| end only | Maintenance scheduled to end on … | Fin de maintenance prévue : … |
| start only, upcoming | Maintenance scheduled to start on … | Début de maintenance prévu : … |
| start only, in progress | Maintenance started on … | Maintenance commencée le … |

### `Retry-After`

The 503 page sends `Retry-After` (in seconds):

- the time remaining until `READ_ONLY_MODE_END` when it is in the future (at least 60 seconds);
- 3600 seconds otherwise (no planned end, or planned end already past).

### Maintenance banner

A single banner is displayed at the top of every page (journals and portal), by priority:

| Situation | Banner |
|---|---|
| `READ_ONLY_MODE` is `true` | Maintenance in progress, with the planned schedule |
| `READ_ONLY_MODE` is `false` and `READ_ONLY_MODE_START` is in the future | **Upcoming** maintenance, with the planned schedule |
| otherwise, `RENDER_ALERT_BANNER.STATUS` is set | The free message of `RENDER_ALERT_BANNER` |
| otherwise | No banner |

A read-only maintenance can therefore be announced days in advance just by setting `READ_ONLY_MODE_START` (and `READ_ONLY_MODE_END`) while `READ_ONLY_MODE` is still `false`; the announcement disappears by itself once the planned start is past. The read-only banners always take precedence over `RENDER_ALERT_BANNER`, so a forgotten announcement is never displayed next to them.

`RENDER_ALERT_BANNER` remains for any other announcement, for instance a full service interruption:

```json
"RENDER_ALERT_BANNER": {
  "STATUS": 1,
  "MESSAGE": {
    "FR": "Arrêt de service le … de … à …, la plateforme sera inaccessible.",
    "DEFAULT_LANG": "Service interruption on … from … to …, the platform will be unavailable."
  }
}
```

`FR` is displayed when the interface language is not English, `DEFAULT_LANG` when it is. The messages may contain HTML (they come from the server configuration). The key may be omitted from `pwd.json`.

---

## Architecture

```
Request
   │
   ▼
Episciences\Controller\Plugin\ReadOnlyGate      (preDispatch, registered first in application.ini)
   │   mode disabled or OAI module ─────────────► unchanged
   │   POST with only "lang" ───────────────────► 303 redirect to the same page (language switcher)
   │   allowed page ────────────────────────────► unchanged
   │   anything else ───────────────────────────► error/readonly (HTTP 503 + Retry-After)
   ▼
Episciences_Auth_Plugin                          (usual ACL check, on the possibly rewritten request)
   ▼
Controller action
```

| Component | Role |
|---|---|
| `library/Episciences/Maintenance/ReadOnlyMode.php` | Single source of truth: `isEnabled()`, planned schedule, `Retry-After`, date formatting |
| `library/Episciences/Controller/Plugin/ReadOnlyGate.php` | Front controller plugin deciding which pages are served |
| `ErrorDefaultController::readonlyAction()` | 503 page (`application/modules/{journal,portal}/views/scripts/error/readonly.phtml`) |
| `layout/commons/maintenance-banner.phtml` | Single banner (read-only, upcoming, `RENDER_ALERT_BANNER`), included in `layout.phtml` and `portal.phtml`; uses `read-only-schedule.phtml` and `alert-banner.phtml` |
| `Episciences_Paper_Visits::add()` | Skips the `STAT_TEMP` insert in read-only mode |
| `Bootstrap::_initAcl()` | Everyone gets the guest navigation |
| `Ccsd_View_Helper_Navbar` (`accountActions` option) | Hides the account menu and the login form; logged-in users keep a "Log out" link |

### Which pages are served

The gate uses an **allow-list**, so that a new page is denied by default during maintenance. A request is served when all of the following hold:

1. the HTTP method is `GET` or `HEAD`;
2. the `controller-action` resource is open to the **`guest` role** in the ACL (`application/configs/acl.ini` and the navigation files), resolved exactly like `Episciences_Auth_Plugin` does (`Episciences_Auth_Plugin::resolveAclResource()`);
3. the resource is not listed in `ReadOnlyGate::WRITING_GUEST_RESOURCES`, the guest pages that write to the database even on `GET`:

| Resource | Why it writes |
|---|---|
| `user-login` | CAS callback synchronises the local user and its roles |
| `user-create`, `user-altcha-challenge` | Account creation |
| `user-activate` | Activation link (e-mail) activates the account and deletes the token |
| `user-lostpassword`, `user-lostlogin`, `user-resetpassword` | Tokens and password changes |
| `user-change_account_email` | E-mail change |
| `api-merge` | Server-to-server account merge |
| `reviewer-invitation` | Invitation link: accepting it, or linking it to the logged-in account |

The rule applies **whoever is logged in**: an editor with an open session only sees the public site. Sessions are kept, so users find them back after the maintenance.

Logging out stays possible (`user-logout`, `user-logoutfromcas`), for instance on a shared computer. Its only database write, recording the end of a switch user session in the audit trail (`user_su_log`), is skipped in read-only mode: a switch user session ended by a logout during the maintenance is not recorded.

Exceptions:

- the **OAI-PMH** module is never filtered (it is read-only and the protocol allows `POST`);
- `error-error` and `error-readonly` are always served (`error/readonly` answers 404 outside read-only mode);
- resources unknown to the ACL are left to `Episciences_Auth_Plugin`, which never dispatches them.

> When adding a page open to guests that writes to the database on `GET` (for instance a new link sent by e-mail), add its resource to `ReadOnlyGate::WRITING_GUEST_RESOURCES`.

### Statistics

`Episciences_Paper_Visits::add()` is the only database write triggered by an anonymous consultation (`paper/view`, `paper/pdf`, `file/oafiles`). It returns immediately in read-only mode: visits during the maintenance window are not counted.

Outside read-only mode, a database error on this insert (for instance MySQL running with `super_read_only` while the application flag was forgotten) is logged at `error` level instead of breaking the page.

### What is not covered

The mode only concerns the **web application** (journals, portal, OAI-PMH). Other processes write to the same database on their own and must be stopped by the operators (see below):

- the scheduled jobs and the Messenger workers (including the Next.js front-end revalidation worker);
- the Symfony API, and every client calling it with write access (new front-end, webhooks, third-party services): put it in read-only or maintenance mode too, or rely on `super_read_only`. PHP sessions, caches, OAI resumption tokens and logs are stored on the filesystem and are not affected.

---

## Operating procedure

The commands below are indicative and must be adapted to the infrastructure.

### 0. Announce (optional, days before)

On every web server, set `READ_ONLY_MODE_START` and `READ_ONLY_MODE_END` in `config/pwd.json`, leaving `READ_ONLY_MODE` to `false`: visitors see the upcoming maintenance banner.

### 1. Switch to read-only

1. Suspend the scheduled jobs (infrastructure crontab), then **wait for running executions to finish**. Jobs that write to the database include `scripts/send_mails.php`, `scripts/reminders.php`, `notify:process-inbox` / `scripts/InboxNotifications.php`, `doi:manage` / `scripts/getDoi.php`, `stats:import-logs`, `stats:process`, `enrichment:*`, `papers:*`, `import:*` (see [Console Commands](./console-commands.md)).
2. Stop the Messenger workers:
   ```bash
   sudo systemctl stop episciences-worker@solr episciences-worker@next
   ```
3. On **every web server**, set `READ_ONLY_MODE` to `true` in `config/pwd.json`. Validate the JSON.
4. Enforce it at the database level as a safety net:
   ```sql
   SET GLOBAL super_read_only = ON;
   ```

### 2. Check

- A paper page, a PDF, a volume, the search, an export and OAI-PMH answer 200 and show the banner (journal and portal).
- `/user/login`, an activation link, an editorial page with an open session and any form submission show the maintenance page (503).
- The write counters do not move between two readings:
  ```sql
  SHOW GLOBAL STATUS WHERE Variable_name IN ('Com_insert', 'Com_update', 'Com_delete', 'Com_replace');
  ```
- The application log contains no `Cannot record paper visit` entry (otherwise the flag is missing on a web server).

### 3. Database maintenance

Run the planned operations (schema migrations, MySQL upgrade, switchover, restoration…). The public site stays available meanwhile.

`super_read_only` also blocks administrative accounts: operations that write to the database cannot run while it is enabled.

1. Keep the scheduled jobs and the Messenger workers stopped, and `READ_ONLY_MODE` set to `true` on every web server: from now on, they are the only protection against application writes.
2. On the server being written to, disable the safety net:
   ```sql
   SET GLOBAL super_read_only = OFF;
   SET GLOBAL read_only = OFF;
   ```
3. Run the operations that write (migrations, restoration…).
4. If the read-only period continues afterwards, enable it again: `SET GLOBAL super_read_only = ON;`. Otherwise, go directly to "Back to normal".

### 4. Back to normal (reverse order)

1. ```sql
   SET GLOBAL super_read_only = OFF;
   SET GLOBAL read_only = OFF;
   ```
2. Set `READ_ONLY_MODE` back to `false` on every web server (and empty the schedule).
3. Restart the workers: `sudo systemctl start episciences-worker@solr episciences-worker@next`.
4. Re-enable the scheduled jobs.

---

## Tests

| Test | Covers |
|---|---|
| `tests/unit/library/Episciences/Maintenance/ReadOnlyModeTest.php` | Flag (including invalid values), schedule parsing, `Retry-After`, date formatting (including `Z` and abbreviations) |
| `tests/unit/library/Episciences/Controller/Plugin/ReadOnlyGateTest.php` | Allowed and denied pages, methods, OAI, language switcher |
| `tests/unit/library/Episciences/Paper/VisitsReadOnlyTest.php` | No visit insert in read-only mode; database errors do not break the page |
| `tests/unit/modules/common/controllers/ErrorDefaultControllerTest.php` | 503 and `Retry-After`, XHR without layout, language switch redirect, 404 outside read-only mode |
| `tests/unit/modules/common/controllers/UserDefaultControllerSwitchUserTest.php` | No switch user audit write on logout in read-only mode |
| `tests/unit/library/Episciences/Auth/Episciences_Auth_PluginTest.php` | No redirection to the profile form in read-only mode |
| `tests/unit/library/Ccsd/View/Helper/NavbarTest.php` | `accountActions` option: no login form, "Log out" link kept |
| `tests/unit/modules/common/views/MaintenanceBannerPartialTest.php` | Banner priorities, announcement, schedule rendering, `RENDER_ALERT_BANNER` |

```bash
make test-php
```
