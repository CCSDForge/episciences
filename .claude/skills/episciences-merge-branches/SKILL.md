---
name: episciences-merge-branches
description: Propagate Episciences branches in the infra order main → staging, main → main-epi-manager, staging → preprod-epi-manager, protecting the path-based URL routing (PREFIX_URL, PREFIX_ROUTE, JS_PREFIX_URL, UrlBuilder) of the *-epi-manager branches, fixing incoming hardcoded URLs and checking that merged code only calls methods/traits that exist on the target branch (APIs differ between main, staging and *-epi-manager). Use this skill whenever the user asks to "merge the branches", "propagate main", "update staging / preprod / epi-manager", "do the infra merges", "sync the deployment branches" (or in French: "merger les branches", "propager main", "mettre à jour staging / preprod / epi-manager", "faire les merges d'infra", "synchroniser les branches de déploiement"), or any merge involving staging, main-epi-manager or preprod-epi-manager — even if only one of those merges is mentioned.
---

# Episciences — propagating the infra branches

Episciences deploys from four long-lived branches. Changes always flow in the same direction:

| # | Source  | Target                | URL-sensitive |
|---|---------|-----------------------|---------------|
| 1 | main    | staging               | no            |
| 2 | main    | main-epi-manager      | **yes**       |
| 3 | staging | preprod-epi-manager   | **yes**       |

The order matters: merge 3 must carry what merge 1 has just brought into `staging`. If the user only asks for a subset, still respect this relative order.

## Why the `*-epi-manager` branches are sensitive

`main` and `staging` serve one journal per subdomain (`<rvcode>.episciences.org/administratepaper/view`), so an absolute URL such as `/administratepaper/view` works there.

The `*-epi-manager` branches serve every journal under a single host, with a **path prefix**: `manager.episciences.org/<rvcode>/administratepaper/view`. Any URL starting with `/controller/...` skips the prefix: it lands on the portal or on a 404, often **with no visible error at merge time**. The manager-specific mechanisms are:

- PHP: `PREFIX_URL`, `PREFIX_ROUTE`, `$this->url([...])` / `$this->view->url([...])`, the `Episciences\Trait\UrlBuilder` trait (`buildBaseUrl()`, `buildAdminPaperUrl()`, `buildPublicPaperUrl()`), redirects `redirector(..., [PREFIX_ROUTE => RVCODE])`, and the action helper `Episciences_Controller_Action_Helper_Url`, registered by `Bootstrap::_initRoutes()` in place of Zend's so that `$this->_helper->url($action, $controller)` keeps `PREFIX_URL`.
- Absolute URL of a journal (mails, CLI scripts): `$review->getBackEndUrl()` (points to `manager.../<rvcode>` when the journal has switched to the new front), not `$review->getUrl()`, which always returns the `<rvcode>.episciences.org` subdomain. `$review->getUrl() . '/administratepaper/...'` is therefore a hardcoded URL just like `'/administratepaper/...'`, even though it starts with a protocol. For a back-office link to a paper, the manager form is `self::buildAdminPaperUrl($docId, $journalOptions)` (classes that `use UrlBuilder`, e.g. `Episciences_Mail_Reminder`), with `$journalOptions = ['rvCode' => $review->getCode(), Episciences_Review::IS_NEW_FRONT_SWITCHED => $review->isNewFrontSwitched()]`.
- JS: `JS_PREFIX_URL + 'controller/action'`.
- Config: `public/const.php` (defines `PREFIX_URL` / `APPLICATION_URL`), `application/Bootstrap.php` (prefixed routes), `application/configs/*.ini` (`resources.router.routes.*.map.rvcode`), `src/apache/vhost.conf`, `config/dist*pwd.json`.

### The branches do not share the same API

Beyond URLs, the **code** differs between `main`/`staging` and `*-epi-manager`: some traits, methods and signatures exist on one side only, sometimes under the same name. Real example:

| | `main` / `staging` | `*-epi-manager` |
|---|---|---|
| Trait `Episciences\Trait\UrlBuilder` | does not exist | `final public static buildAdminPaperUrl()`, `buildPublicPaperUrl()`, `buildBaseUrl()`… used by `PaperDefaultController`, `UserDefaultController`, `Mail`, `PapersManager`… |
| `PaperDefaultController` | `public function buildAdminPaperUrl()` / `buildPublicPaperUrl()` (instance) | renamed `adminPaperUrl()` / `publicPaperUrl()` |

Consequences:
- Reintroducing the instance method `buildAdminPaperUrl()` coming from `staging`:
  - in a **subclass** of `PaperDefaultController` (`AdministratepaperController`, `PaperController`, `CoiController`…) triggers a **fatal error** as soon as the class is loaded (`Cannot override final method`), because the inherited trait method is `final`;
  - in `PaperDefaultController` itself, which does `use UrlBuilder`, PHP accepts it without error: the class method **silently replaces** the trait's. Calls written for the trait (`self::buildAdminPaperUrl($id, [...])`, including from the trait itself) then hit an instance method with another signature, and fail at runtime.
- A call written for the source (`$this->x()`) may target a method that is missing on the target, or present with another status (static or not) or another signature.
- A manager-specific helper (`adminPaperUrl()`, `UrlBuilder::…`) only exists in the manager classes that define it or `use` the trait. Do not use it in another class without checking, and never let it flow down to `main` or `staging`.

`php -l` sees **none** of these problems: it loads neither traits nor parent classes. Hence the `check_methods.py` script of step g.

To know which files exist only on the manager side (and therefore the API that must never flow down), do not rely on a fixed list: `git diff --name-status origin/main origin/main-epi-manager | awk '$1=="A"'`. It notably includes `Episciences/Controller/Action.php` (which provides `$this->url()` to controllers), `Trait/UrlBuilder.php`, `View/Helper/Url.php`, `Controller/Action/Helper/Url.php`, `View/Helper/Pagelink.php` and `View/Helper/Sidebar.php`.

### Diverging API area: the website menu (`website/menu`)

Storage (`WEBSITE_NAVIGATION` table, `menu` language files), the `Episciences_Website_Navigation_Page_*` classes, the JS editor `MenuManager` (endpoints `ajaxorder`, `ajaxformpage`, `ajaxrmpage`) and the CSRF token are shared by the four branches. The `*-epi-manager` branches add a layer that the merge does not flag:

| Topic | `main` / `staging` | `*-epi-manager` |
|---|---|---|
| Parent of `WebsiteDefaultController` | `Zend_Controller_Action` | `Episciences_Controller_Action` (provides `url()`) |
| Page types | raw `getPageTypes()` | `Episciences_Website_Navigation::getPageTypes(): array`, filtered by `$ignoredPageTypes` |
| "Page type" select | `menu.phtml` loops over `$this->pageTypes` | loops over `$this->groupedPageTypes` (`<optgroup>`, "Choose a page"), built by `processPageTypes()` from `$groupedPages` and `Review::isNewFrontSwitched()` (manager-only column) |
| Predefined pages | — | `getPredefinedPageTypes()`, `Page::isPredefined()`, 🛈 marker, visibility forced to "public" in `Predefined.php` |
| `menuAction` POST | `if ($request->isPost())` | `isPost() && !getPost('lang')` |
| URLs | `/website/menu`, `/website/reset`, `'/website/ajax…'` | `url([...])`, `JS_PREFIX_URL + 'website/ajax…'` (`JS_PREFIX_URL` does not exist on `main`) |
| Public menu | `Ccsd_View_Helper_Pagelink($page, $prefixUrl = '/')` | wrappers `Episciences_View_Helper_Pagelink` / `Sidebar` that pass `PREFIX_URL` |

When resolving a conflict in this area:
- a source change in the select loop is rewritten on `groupedPageTypes`, keeping the grouping and the predefined-page marker;
- any new condition on the `menuAction` POST (CSRF, validation…) keeps the `!getPost('lang')` guard;
- `groupedPages`, `ignoredPageTypes`, `isNewFrontSwitched()`, `isPredefined()` and `JS_PREFIX_URL` never flow down to `main` or `staging`;
- the `/css/website/menu.css` and `/js/website/*.js` assets stay absolute on every branch: the audit flags them, they are false positives.

Tests coming from the source often break here without any conflict: a double of `_session->website` must return an **array** for `getPageTypes()`, and `Episciences_ReviewsManager::find(RVCODE)` must be resolvable (prime the private cache `_cache['rvcode_' . RVCODE]` by reflection, then `Episciences_ReviewsManager::clearCache()` in tearDown), otherwise `processPageTypes()` fails (`TypeError`, or a call on `false`). On the JS side, `tests/js/website/menu-manager.test.js` defines `global.JS_PREFIX_URL`.

Known pitfall: on the manager side `APPLICATION_URL` already contains `PREFIX_URL`, and `$this->view->url()` adds it too. `APPLICATION_URL . $this->view->url(...)` therefore duplicates the journal code (`/epijinfo/epijinfo/...`). On the manager side, use `self::buildBaseUrl() . $this->view->url(...)` or the `UrlBuilder` helpers.

Another pitfall: `$this->_helper->url($action, $controller)` (`Zend_Controller_Action_Helper_Url::simple()`) builds the URL without going through the router, unlike `gotoSimple()`, and so loses the journal code. On the manager side, `Bootstrap::_initRoutes()` replaces it with `Episciences_Controller_Action_Helper_Url`, which adds `PREFIX_URL`: such code therefore arrives without changes. When resolving a conflict in `Bootstrap.php`, keep this registration (`check-hardcoded-internal-urls.php` fails if it disappears), and never instantiate `Zend_Controller_Action_Helper_Url` directly.

## Procedure

### 0. Prerequisites

The scripts run from the repository root, with `bash` or `python3` (they are mode 644).

First copy them out of the working tree: a target branch that does not track this skill yet removes them when it is checked out. Every later command uses this copy, written `<scripts>` below (`post_merge_checks.sh` finds its neighbours next to itself):

```bash
mkdir -p tmp/merge-branches/scripts && cp <this-skill-dir>/scripts/* tmp/merge-branches/scripts/   # <scripts> = tmp/merge-branches/scripts
bash <scripts>/sync_branches.sh   # note the starting branch, to return to at the end
```

The script:
- refuses to continue if tracked files are modified. In that case, stop and ask the user. Never stash on their behalf: they might forget those changes. Untracked files are fine;
- runs `git fetch origin --prune`, then `git merge --ff-only origin/<b>` on `main`, `staging`, `main-epi-manager` and `preprod-epi-manager` (pass a list of branches as arguments for a subset);
- enables `rerere` (`rerere.enabled true`, `rerere.autoupdate false`), then returns to the starting branch.

A `[KO]` line flags a branch that cannot fast-forward: it holds unpushed commits or has diverged, and the script prints `git log --oneline origin/<b>..<b>`. Ask how to proceed. Do not reset.

Why `rerere`: the same conflicts come back from one merge to the next (a fix on `main` can conflict identically on `main-epi-manager` and then, through `staging`, on `preprod-epi-manager`), and after an undo. With `autoupdate false`, the replayed resolution stays in the working tree without being staged: review it and run `git add` yourself, as for a manual resolution.

Establish a **test baseline** on each sensitive target before merging, so that pre-existing failures are not blamed on the merge (for instance file-permission tests that fail because the container runs as root):

```bash
git checkout <target>
bash <scripts>/phpunit_failures.sh
```

The `episciences-php-fpm` container mounts the repository: it tests the branch currently checked out. The script stops if the container mounts another checkout (another clone, a worktree): run it from the mounted one, or set `CONTAINER`. The script runs the full suite (`--no-coverage`) as `www-data`, like `make test-php` (as `root`, files created by the tests become unreadable for later runs, and file-permission tests wrongly pass), and stores the list of failing tests, without their number, in `phpunit-failures-<sha>.txt`. The file goes to `$TMPDIR` if the variable is exported, otherwise to `tmp/merge-branches/` (ignored by git). The cache is keyed by commit: if the target has not moved since a previous run, the result is reused without rerunning the suite (`--force` to rerun it). If PHPUnit prints no summary (bootstrap error, missing dependency: run `composer install` in the container after switching branches), the script exits with an error without caching anything, and `post_merge_checks.sh` marks `phpunit` as KO. `post_merge_checks.sh` (step g) then compares against this baseline for the "before" SHA.

**Shell.** Do not assume which interactive shell runs the commands: write commands that behave the same in any of them.
- Always delimit a variable followed by `:`: `"${b}:path"`, never `"$b:path"` (some shells read `:l`, `:h`… as modifiers).
- Do not rely on implicit word splitting of an unquoted variable (`set -- $var`, `for x in $var`): not every shell splits it. Use an array, or `bash -c '…'` if a loop is really needed.
- Above all, prefer the skill's scripts (run with `bash` or `python3`, they do not depend on the current shell) or one simple command per branch over compound `for` loops.

### 1. For each merge, in order

**a. Measure what is coming**

```bash
git log --oneline <target>..<source>
git log --oneline --cherry-mark --left-right <target>...<source> | grep '^='   # equivalent commits on both sides
```

If the first command returns nothing, the target is already up to date: note it and move on to the next merge.

Measure each merge **right before doing it**, never upfront for all three at once. Before merge 1, `preprod-epi-manager..staging` can be empty, while merge 1 is precisely about to bring into `staging` what feeds merge 3. A measurement taken at the start would wrongly conclude that merge 3 is unnecessary.

The second command spots **parallel PRs**: the same feature ported separately to `main` and to `staging`. They cause add/add conflicts and, above all, **duplicates in areas merged without conflict** (a block of code or a line of doc present twice). For the files touched by these commits, compare the **whole** result with the most complete version (`git diff :2:<f> <f>` or `:3:`), not just the conflicting hunks. The same signal applies when both sides have commits with identical messages but different SHAs.

**b. Sensitive merges only (2 and 3): audit URLs before merging**

```bash
bash <scripts>/audit_urls.sh <target> <source>   # run from the repository root
```

The script only lists lines **added** by the source since the merge base. It flags hardcoded absolute URLs in PHP, PHTML and JS, `APPLICATION_URL . url()`, `->getUrl() . '/controller…'` (journal subdomain followed by an internal path), `$review->getUrl()` passed as an argument or assigned to a variable (the path is appended later, e.g. in `Reminder::buildInvitationUrl()`), `sprintf('/%s/%s', …)`, `$_SERVER['SERVER_NAME']`, `redirector()` / `gotoSimple()` without `[PREFIX_ROUTE => RVCODE]`, direct use of `Zend_Controller_Action_Helper_Url`, plus routing or infra files touched. It changes nothing. It is a pattern-based net: a call spread over several lines can produce a false positive, and an unusual URL construction can slip through. So check every flagged line, and stay alert while resolving conflicts.

"0 suspicious lines" is not enough to conclude. After the merge, also review added URL constructions whose path is assembled elsewhere (variable, another line): `git diff <before-sha> <target> -- '*.php' '*.phtml' | grep -nE '^\+.*(getUrl\(\)|getBackEndUrl|APPLICATION_URL|SERVER_PROTOCOL|DOMAIN)'`. This is how, after an empty audit, two links `$review->getUrl() . '/administratepaper/view/id/…'` coming from `staging` were found in the reminders to supervisors (#974), then a `$review->getUrl()` passed to `buildInvitationUrl()` (#1319). Both forms are now in the audit, but other variants remain possible.

If `origin/<source>` moves between the merge and the push (source push rejected), merge `origin/<source>` into the local source rather than redoing the merge: the target has already taken the local merge commit, and rewriting it would leave in the target a merge missing from the source. Then redo the merge into the affected target (steps a to g) and ask again before pushing.

Keep the audit output: these lines will be **fixed in step f**, after the merge commit. Do not fix them during the merge itself.

**c. Merge**

```bash
git checkout <target>
git rev-parse --short HEAD        # note this "before" SHA: it is used in step f
git merge --no-ff <source>        # default message: "Merge branch '<source>' into <target>"
```

Keep the default message: it is the convention of the repository history. Add no Claude mention and no `Co-Authored-By` line.

**d. Conflicts, if any**

List them with `git diff --name-only --diff-filter=U`. For each file, read both sides (`git diff`, or `git show :2:<f>` / `:3:<f>`) and understand what each one meant to do before deciding.

- On a `*-epi-manager` target, **the target's URL mechanics always win**: keep `PREFIX_URL`, `JS_PREFIX_URL`, `url([...])`, `UrlBuilder`, `PREFIX_ROUTE`. **The source's business logic wins**: new features, fixes, new parameters. The right resolution is almost always a combination of both: the source code, rewritten with the target's prefixed URLs. In a conflicting area, adapt the URL directly, since the resolution is a rewrite anyway. Hardcoded URLs that arrived **without conflict** wait for step f.
- For infra files (`public/const.php`, `Bootstrap.php`, route `*.ini`, `vhost.conf`, `config/dist*pwd.json`), start from the target version and only carry over source additions that are clearly independent of routing (a new constant, a new key). When in doubt, ask.
- For `main → staging`, resolve normally.
- **Empty target side** (`<<<<<<< HEAD` immediately followed by `=======`): the target deleted or moved this code, which the source has just modified. Before deciding, find out why with `git log -S'<excerpt of the source code>' --oneline <target> -- <file>`, then read that commit. Often the target already does the same thing elsewhere with its own mechanics. Real example: the tags of the "forgotten login" link are built on the manager branches in `Episciences_Mail_Reminder` (`self::buildLostLoginUrl($journalOptions)`), whereas `main` built them in `scripts/reminders.php` from `$review->getUrl()`. Taking the source version would then have reintroduced a link to the subdomain and overwritten the correct link. In that case, keep the target version, but check that every case covered by the source is also covered on the target (here, each reminder type).
- If a conflict is ambiguous (two incompatible business logics), stop and ask. Do not run a blanket `git checkout --ours/--theirs` on code files.
- **Spot behaviour changes.** Letting the source logic win can remove from the target a behaviour it had on purpose: a manager-specific condition such as `$displayLogin` in `layout.phtml`, a permission (who can edit, view, validate), a visibility, a redirect. For each conflict, ask yourself: "after my resolution, can someone on the target branch do or see something different from before?" If so, keep the resolution that seems most correct to you, but record it explicitly in the summary ("Behaviour changes" section). The user reviews dozens of files; these lines are the ones they must not miss. A merge can look clean and still silently remove a rule from preprod.

After resolution:

```bash
git grep -nE '^(<<<<<<<|=======|>>>>>>>)( |$)' -- . ':!vendor' ':!node_modules'   # must be empty
php -l <each resolved .php/.phtml file>
git diff HEAD -- <file>   # if you kept the target version: must be empty
git add <files> && git commit --no-edit
```

`git diff HEAD -- <file>` shows what the resolution changes compared with the pre-merge target. An empty diff confirms the target version is intact. It is also the fastest way to review a resolution replayed by `rerere` ("Resolved using previous resolution").

**e. Check**

```bash
git log --oneline -1 && git diff --stat HEAD^1 HEAD | tail -1
```

**f. Sensitive merges only: fix incoming hardcoded URLs, in a dedicated commit**

A hardcoded URL coming from the source breaks the target's path-based routing, even when the merge went through without conflict. It must therefore be fixed before any push. Put the fix in a **separate commit**, right after the merge commit: the merge stays faithful to the source, and the fix stays identifiable, reviewable and revertible on its own (`git revert`).

1. Start from the audit output (step b) and check each line in the merged code. Discard false positives (multi-line call that already contains `PREFIX_ROUTE`, external URL, static asset path served by an Apache alias) and note why.
2. Fix each retained line with the mechanism the target already uses in the same file or a neighbouring one. Follow the existing code rather than inventing a form:

   | Context | Hardcoded | Manager form |
   |---|---|---|
   | PHTML view (link, form, img) | `href="/user/edit"` | `href="<?= $this->url(['controller' => 'user', 'action' => 'edit']) ?>"` or `<?= PREFIX_URL ?>user/edit` |
   | Controller, redirector | `$this->_helper->redirector('list', 'user')` | `$this->_helper->redirector('list', 'user', null, [PREFIX_ROUTE => RVCODE])` |
   | Controller, built URL | `gotoUrl(sprintf('/%s/%s', $c, $a))` | `redirect($this->url(['controller' => $c, 'action' => $a]))` |
   | Absolute URL (mail…) | `APPLICATION_URL . $this->view->url(...)` | `self::buildBaseUrl() . $this->view->url(...)` or `UrlBuilder` helpers, **only if the class `use UrlBuilder`** (otherwise, see what the neighbouring file does) |
   | Absolute journal URL (reminders, CLI scripts) | `$review->getUrl() . '/administratepaper/view/id/' . $docId` | `self::buildAdminPaperUrl($docId, $journalOptions)` if the class `use UrlBuilder`, otherwise `$review->getBackEndUrl() . '/administratepaper/view/id/' . $docId` |
   | JavaScript | `'/administratepaper/save'` | `JS_PREFIX_URL + 'administratepaper/save'` |

   In JS, only read `JS_PREFIX_URL` inside a function run later (event handler, `DOMContentLoaded`, function that builds the URL), never at the top level of the file: `const URL = JS_PREFIX_URL + '…'` throws `ReferenceError: JS_PREFIX_URL is not defined` and stops the whole script. Files added through `$this->jQuery()->addJavascriptFile()` are written in the `<head>`, while the layout only declares `JS_PREFIX_URL` at the end of the `<body>` (`application/modules/common/views/layout/layout.phtml`). Replace such a constant with a function (`function viewUrl(id) { return JS_PREFIX_URL + 'administratepaper/view/id/' + encodeURIComponent(id); }`). Jest does not see this bug if the test defines `global.JS_PREFIX_URL` before loading the script: `post_merge_checks.sh` looks for it (`js-prefix`). Real case: `public/js/stats/paper-list.js` after `dd119ad7a`, fixed by `125606fbe`.

   Check consistency between PHP and JS: JS that selects `form[action^="…"]` must use the same prefix as the `action` attribute generated by the view. If a unit test asserts the old hardcoded URL, adapt it in the same commit.
3. Check:
   ```bash
   php -l <modified .php/.phtml files>
   node --check <modified .js files>
   bash <scripts>/audit_urls.sh <before-sha> <target>   # must only list the discarded false positives
   docker exec -u www-data -w /var/www/htdocs episciences-php-fpm php .github/scripts/check-hardcoded-internal-urls.php   # blocking: must pass (make check-hardcoded-urls)
   ```
   Run the audit after committing the fix (step 4): it then covers everything the merge and the fix brought into the target since the "before" SHA noted in step c. `post_merge_checks.sh` (end of step g) reruns these two checks along with the others.

   `.github/scripts/check-hardcoded-internal-urls.php` (guard of the manager branches, absent from `main` and `staging`, run by CI and by `make check-hardcoded-urls`; it is not a PHPUnit test, because it reads the source and `check-source-reading-tests.php` would refuse it) scans all of `public/js`, the `.phtml` files and the PHP of `application/` and `library/Episciences/`: a hardcoded internal path (`'/administratepaper/…'`, `href="/user/…"`, `gotoUrl('/paper/…')`, `header('Location: /…')`) or a `redirector()` / `gotoSimple()` / `gotoRoute()` call without `PREFIX_ROUTE` makes it fail with one `::error file=…,line=…` annotation per URL. It complements the audit, which only sees the diff. It does not see a URL assembled from variables (`sprintf('/%s/%s', $c, $a)`): the audit and the conflict review remain necessary. Do not disable it to get a merge through: fix the URL, or add a justified exception if it is a false positive: `ALLOWED_FILES` for a whole file, `ALLOWED_LINES` for a line, identified by a text it contains (not by its number, which moves with every change above it). If it does not exist yet on the target (`git ls-tree <target> .github/scripts/check-hardcoded-internal-urls.php`), report it in the summary.
4. Commit, listing the files explicitly (never `git add -A` or `git add .`):
   ```bash
   git add <fixed files>
   git commit -m "fix(urls): prefix hardcoded URLs merged from <source> into <target>"
   ```
   The message body lists the fixed files. No Claude mention.

Hardcoded URLs **already present** on the target before the merge are out of scope: do not fix them, but mention them if you come across them. Exception: those flagged by `check-hardcoded-internal-urls.php`, since it must pass; fix them in a `fix(urls)` commit separate from the merge.

**g. Sensitive merges only: check method consistency**

Run the check after the merge and the possible `fix(urls)` commit, from the repository root:

```bash
python3 <scripts>/check_methods.py <before-sha> <target> <source>
```

For every PHP/PHTML line added to the target, the script flags:
- `[missing]`: a called method, defined on the source but nowhere on the target (code written against the source API);
- `[static-call]`: a `self::` / `static::` / `ProjectClass::` call to a method that is not static on the target (calls to external classes, Zend, Symfony or PHP, are ignored);
- `[signature]`: a called method whose signature differs between source and target, in the same file or because a trait replaces the method on the target. Example: `buildAdminPaperUrl($docId, 'reviews')` written for `staging` `(int, ?string)` lands on the manager trait `(int, array)`, hence a TypeError;
- `[removed]`: a method that existed on the target before the merge, has disappeared (often a file taken whole from the source) and is still called elsewhere;
- `[FATAL]`: a class touched by the merge that redefines a method inherited from a parent class or from a trait used by a parent class, while that method is `final` or does not have the same static status. PHP refuses to load the class;
- `[shadow]` / `[override]`: a class touched by the merge that redefines a method of a trait it uses itself. PHP accepts it (the class method replaces the trait's), but it is `[shadow]` if the trait method is `final` or does not have the same static status: calls written for the trait then reach the class method. Treat `[shadow]` as a bug; for `[override]`, check that the redefinition is intended.

It also prints, separately and without counting them in `Issues`, `[unused]` notes: a method that the merge adds to the target and that nothing calls there (tests excluded). It signals two parallel mechanisms: a helper written for the source, while the target already does the same thing differently. Example: `Episciences_Mail_Reminder::getLostLoginTags()`, called by `reminders.php` on `main` but unused on the manager, which goes through `buildLostLoginUrl()`. It is not a bug: do not delete the method, since the merge must stay faithful to the source and its test uses it. Report it in the summary: a future fix of this helper on the source will have no effect on the target.

The script works by method name, without resolving the class of the called object. So it does not see a method that exists elsewhere but not in the called class, and it only compares signatures within the same file or against a target trait. Conversely, a name shared with a native or external method (e.g. `addChild()` of `SimpleXMLElement`) can produce a false positive, especially as `[removed]`. For each call added or rewritten in the **conflict areas**, check yourself, with `git grep -n "function <name>" <target>`, that the method does exist in the relevant class, its parents or its traits, and that the signature matches. For each flagged line, discard false positives and note why, as for the URL audit.

Any problem found is a merge bug, not a remark: fix it by aligning with the target API (e.g. `adminPaperUrl()` instead of `buildAdminPaperUrl()` in `PaperDefaultController` and its subclasses). Do it in the resolution if the merge is not committed yet, otherwise in a `fix(merge): align <file> with <target> API` commit. Rerun the script until only discarded false positives remain (ideally `Issues: 0`), then list these fixes and the false positives in the summary.

Finally, run the tests: `php -l` and `check_methods.py` do not see behaviour differences between branches (e.g. a source test whose double does not match the target API).

**Grouped checks.** Once the merge and any corrective commits are done, run all the checks of steps f and g at once, with the target checked out:

```bash
bash <scripts>/post_merge_checks.sh <before-sha> <target> <source>
```

| Line | What is checked | KO if |
|---|---|---|
| `urls` | `audit_urls.sh <before-sha> <target>` | — (REVIEW: each line must be fixed or discarded) |
| `url-build` | added lines that build a URL (`getUrl()`, `getBackEndUrl`, `APPLICATION_URL`, `SERVER_PROTOCOL`, `DOMAIN`), excluding comments | — (REVIEW: to be read) |
| `methods` | `check_methods.py` | `Issues` > 0 (REVIEW if only `[unused]` notes) |
| `hardcoded` | `.github/scripts/check-hardcoded-internal-urls.php` | the script fails (REVIEW if absent from the target) |
| `menu` | `'/website/(ajax\|menu\|reset)` in the menu area | any occurrence |
| `phpunit` | full suite, compared with the baseline of the "before" SHA (`phpunit_failures.sh`) | a new failure (REVIEW if there is no baseline) |
| `jest` | `yarn test --roots '<rootDir>/tests/js'` (Jest restricted to the repository: `tmp/` holds copies), only if `.js` files changed | Jest fails |

`SKIP_TESTS=1` skips PHPUnit and Jest for a quick rerun after a fix. The script changes nothing. On failure, `phpunit <tests of the touched files>` in the container helps isolate the problem. Every REVIEW is read line by line and reported in the summary.

Only new failures compared with the baseline are due to the merge. A source test that fails because of the target API is adapted in a separate `test(<area>): …` commit; an assertion on a hardcoded URL is adapted in the `fix(urls)` commit.

### 2. Push: always ask

Every push is visible to the team and can trigger a deployment. Once the three merges are done locally, present the summary below, then ask **for each branch** whether you may push. Approval for one branch does not extend to the others. Never use `--force`.

```bash
git push origin <branch>
```

If the push is rejected (someone pushed in the meantime), do not force. Fetch again, then `git merge --ff-only` if possible, otherwise re-merge; re-audit and ask again.

Finally, return to the branch noted in step 0.

### 3. Undoing the merges, if the user asks

The ban on reset in step 0 protects the user's work. It does not apply to undoing, at their request, the commits you have just created, **provided they have not been pushed**. For each branch:

```bash
git rev-parse --short origin/<b>       # must equal the "before" SHA noted in step c
git log --oneline <before-sha>..<b>    # must only contain your commits (merge, fix(urls), fix(merge), test) and those brought by the source
git status --porcelain --untracked-files=no   # must be empty
git checkout <b>
git diff -z --name-only --diff-filter=A <b> <before-sha> | xargs -0 -r ls -d 2>/dev/null   # must be empty
git reset --hard <before-sha>
```

The `ls` line lists the paths that the merge deleted and that an untracked or ignored file now occupies: `reset --hard` would overwrite them without any possible recovery. If it prints anything, stop and ask the user.

If a branch has already been pushed, do not reset: suggest reverting, newest first, every commit you added after the merge (`fix(urls)`, `fix(merge)`, `test`), then the merge itself (`git revert -m 1 <merge>`), and ask. Reverting the merge alone would leave these corrective commits on the branch, with code that depends on the reverted merge. Remind the user that the removed commits remain recoverable for a while through `git reflog`, and that `rerere` has kept the resolutions for next time.

## Summary to present

Write the summary in the user's language.

```
## Branch propagation

| Merge                          | Commits | Conflicts  | Merge commit    | URL fix        | Pushed |
|--------------------------------|---------|------------|-----------------|----------------|--------|
| main → staging                 | N       | 0          | abc1234         | —              | no     |
| main → main-epi-manager        | N       | 2 resolved | def5678         | 9a8b7c6 (3)    | no     |
| staging → preprod-epi-manager  | 0       | —          | already up to date | —           | —      |

### Resolved conflicts
- `file`: what each side was doing, choice made

### ⚠ Behaviour changes on the target (to validate)
- `file`: before / after (e.g. "role X can no longer do Y", "block Z is now also shown on the portal")

### Incoming hardcoded URLs (fixed in the `fix(urls)` commit)
- `file:line`: before → after
- Discarded (false positives): `file:line`, reason
- Hardcoded URLs already present on the target (left untouched): …

### Method consistency (check_methods.py)
- `Issues: 0` on each manager branch, or list of fixes made (`file`: call/method before → after)

### Incoming code unused on the target (`[unused]` notes)
- `Class::method()`: called on the source by …, replaced on the target by …; a future fix of this helper will not reach the target

### Tests
| Target | PHPUnit (after merge) | New failures / baseline | Jest |
|---|---|---|---|
| staging | not run (merge without conflict) or result | — | — |
| main-epi-manager | Tests: N, Failures: F | 0 (baseline: …) | not run (no .js) |

### Infra/routing files modified by the source
- …
```

End with the push question.
