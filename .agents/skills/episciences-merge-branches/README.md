# episciences-merge-branches

Agent skill that propagates the Episciences deployment branches in the infra order
and protects the path-based URL routing of the `*-epi-manager` branches:

| # | Source  | Target              | URL-sensitive |
|---|---------|---------------------|---------------|
| 1 | main    | staging             | no            |
| 2 | main    | main-epi-manager    | yes           |
| 3 | staging | preprod-epi-manager | yes           |

The agent instructions live in [`SKILL.md`](SKILL.md). This README is for the people
who install, use and maintain the skill; agents do not need it.

## Contents

- [Format and layout](#format-and-layout)
- [Supported agents](#supported-agents)
- [Local installation](#local-installation)
- [Usage](#usage)
- [Scripts](#scripts)
- [Maintenance](#maintenance)
- [Testing a change locally](#testing-a-change-locally)
- [Troubleshooting](#troubleshooting)

## Format and layout

The skill follows the open [Agent Skills](https://agentskills.io) format: a folder
named after the skill, holding a `SKILL.md` file (YAML frontmatter with `name` and
`description`, then Markdown instructions) and optional resources.

```
.agents/skills/episciences-merge-branches/   source of truth (cross-agent location)
├── SKILL.md                                 instructions loaded by the agent
├── README.md                                this file
└── scripts/
    ├── sync_branches.sh                     step 0: fetch, fast-forward, enable rerere
    ├── phpunit_failures.sh                  step 0: PHPUnit baseline per commit
    ├── audit_urls.sh                        step b: hardcoded URLs brought by the source
    ├── check_methods.py                     step g: method/trait consistency
    └── post_merge_checks.sh                 step g: every post-merge check at once

.claude/skills/episciences-merge-branches -> ../../.agents/skills/episciences-merge-branches
```

`.agents/skills/` is the only copy. `.claude/skills/episciences-merge-branches` is a
symbolic link for Claude Code, which reads `.claude/skills/` and not `.agents/skills/`.
`.gitignore` versions both paths and nothing else under `.claude/`.

How an agent uses the skill:

1. At startup it only reads the `name` and `description` of each skill.
2. When a request matches the description (for example "propage main", "merge the
   infra branches"), it loads the whole `SKILL.md`.
3. It runs the scripts from `scripts/` when the procedure tells it to.

## Supported agents

Project-level skills are read from the repository; nothing has to be installed for
an agent that reads `.agents/skills/` or `.claude/skills/`.

| Agent | Project folder read | User folder (all projects) |
|---|---|---|
| Claude Code | `.claude/skills/` (symlink) | `~/.claude/skills/` |
| OpenAI Codex | `.agents/skills/` | `~/.agents/skills/` |
| Gemini CLI | `.gemini/skills/`, `.agents/skills/` | `~/.gemini/skills/`, `~/.agents/skills/` |
| Cursor | `.cursor/skills/`, `.agents/skills/`, `.claude/skills/` | `~/.cursor/skills/`, `~/.agents/skills/` |
| GitHub Copilot (VS Code, CLI) | `.github/skills/`, `.agents/skills/`, `.claude/skills/` | `~/.copilot/skills/`, `~/.agents/skills/` |

These folders change with the agent versions: check the documentation of your agent
if the skill does not show up. Some agents ask for confirmation before enabling a
project skill.

## Local installation

### In this repository

Nothing to do: clone the repository (or check out a branch that contains
`.agents/skills/`) and start the agent from the repository root.

Requirements:

- symbolic links enabled in Git (`git config core.symlinks` must not be `false`;
  on Windows, enable the Developer Mode or use WSL);
- `bash`, `python3` and `git` on the host;
- the Docker stack of the project running, with the `episciences-php-fpm` container
  mounting this checkout (PHPUnit runs there; see `CONTAINER` below);
- `yarn` dependencies installed for the Jest check.

### For all your projects (optional)

To keep the skill available outside this repository, link it into your user
folder instead of copying it, so that `git pull` keeps it up to date:

```bash
# from the repository root
mkdir -p ~/.agents/skills
ln -s "$PWD/.agents/skills/episciences-merge-branches" ~/.agents/skills/episciences-merge-branches

# Claude Code does not read ~/.agents/skills
mkdir -p ~/.claude/skills
ln -s "$PWD/.agents/skills/episciences-merge-branches" ~/.claude/skills/episciences-merge-branches
```

Do not install it both in the user folder and in the project for the same agent:
some agents list the skill twice.

### Check the installation

Start a new session (skills are discovered at startup), then:

- Claude Code: type `/` and look for `episciences-merge-branches`;
- other agents: use their skills command if they have one, or ask "Which skills are
  available?".

## Usage

Start the agent from the repository root, on a clean working tree (untracked files
are fine), then ask, for example:

- "Merge the branches": the three merges, in order;
- "Propagate main into main-epi-manager": a single merge;
- "Update preprod": merge 3, after merge 1 if `staging` is behind `main`.

The skill description also lists French phrasings, so the same requests work in
French.

If the agent does not pick the skill by itself, name it: "Use the
`episciences-merge-branches` skill to merge the branches" (Claude Code also accepts
`/episciences-merge-branches`).

What the agent does:

1. syncs the four branches with `origin` (fast-forward only, never reset nor stash)
   and records a PHPUnit baseline on each manager target;
2. for each merge: measures what is coming, audits the URLs (manager targets), merges
   with `--no-ff`, resolves conflicts, fixes incoming hardcoded URLs in a separate
   `fix(urls)` commit and checks method consistency;
3. presents a summary (conflicts, behaviour changes, URL fixes, tests);
4. **asks before each push**, branch by branch. Nothing is pushed without your answer.

Read the "Behaviour changes" section of the summary before answering: it lists what a
conflict resolution changed on the target branch.

To undo the local merges, ask the agent ("undo the merges"): it resets only the
commits it created and that were not pushed, after a few safety checks (step 3 of
`SKILL.md`).

## Scripts

The scripts can be used without an agent. Run them from the repository root with
`bash` or `python3`. They read the repository and never change it, except
`sync_branches.sh` (fast-forward and `rerere` configuration).

Copy them out of the working tree first: a target branch that does not contain the
skill removes them when it is checked out.

```bash
mkdir -p tmp/merge-branches/scripts
cp .agents/skills/episciences-merge-branches/scripts/* tmp/merge-branches/scripts/
S=tmp/merge-branches/scripts
```

| Script | Usage | Exit code 1 when |
|---|---|---|
| `sync_branches.sh` | `bash $S/sync_branches.sh [branch...]` | tracked files are modified, or a branch cannot fast-forward |
| `phpunit_failures.sh` | `bash $S/phpunit_failures.sh [--force]` (target checked out) | PHPUnit prints no summary |
| `audit_urls.sh` | `bash $S/audit_urls.sh <target> <source>` | wrong arguments (findings are only printed) |
| `check_methods.py` | `python3 $S/check_methods.py <before-sha> <target> <source>` | issues found |
| `post_merge_checks.sh` | `bash $S/post_merge_checks.sh <before-sha> <target> <source>` (target checked out) | a check is KO |

Environment variables:

- `CONTAINER`: PHP container (default `episciences-php-fpm`);
- `SKIP_TESTS=1`: `post_merge_checks.sh` skips PHPUnit and Jest;
- `TMPDIR`: where the PHPUnit baselines are cached (default `tmp/merge-branches/`,
  ignored by Git).

Each script documents its options and output in its header.

## Maintenance

- Edit the files under `.agents/skills/episciences-merge-branches/`. The `.claude/`
  path is a link to the same files.
- `SKILL.md` is written for the agent. Keep it in English, imperative and
  self-contained: the agent only sees this file and the scripts.
- Keep the frontmatter valid for every agent: `name` equal to the folder name
  (lowercase letters, digits and hyphens), `description` under 1,024 characters,
  saying both what the skill does and when to use it (with the French phrasings that
  should trigger it).
- Keep the instructions agent-neutral: no tool or command specific to one agent, and
  "no AI/agent mention" in commit messages.
- When a script gains a check or an option, update its header, the matching section
  of `SKILL.md` and the table above.
- The scripts are run with `bash` / `python3` explicitly; their file mode does not
  matter.
- New pitfalls found during a merge (a diverging API, a URL form the audit missed)
  belong in `SKILL.md`, with the real example and, when possible, a new pattern in
  `audit_urls.sh` or a new check in `post_merge_checks.sh`.

## Testing a change locally

Before pushing a change to the skill:

1. Static checks:
   ```bash
   S=.agents/skills/episciences-merge-branches
   for f in $S/scripts/*.sh; do bash -n "$f" && echo "ok $f"; done
   python3 -c 'import ast, sys; ast.parse(open(sys.argv[1]).read())' $S/scripts/check_methods.py
   shellcheck $S/scripts/*.sh          # if installed
   head -4 $S/SKILL.md                 # frontmatter: name, description
   ```
2. Link and Git state:
   ```bash
   readlink .claude/skills/episciences-merge-branches   # ../../.agents/skills/episciences-merge-branches
   git ls-files -s .claude/skills                       # mode 120000 (symlink)
   git status --short --ignored .agents .claude         # no stray file in the skill
   ```
3. Discovery: start a new session of each agent you use and check that the skill is
   listed (see [Check the installation](#check-the-installation)).
4. Dry run of the scripts, read-only, on the current state of the branches:
   ```bash
   bash $S/scripts/audit_urls.sh origin/main-epi-manager origin/main
   bash $S/scripts/audit_urls.sh origin/preprod-epi-manager origin/staging
   python3 $S/scripts/check_methods.py origin/main-epi-manager origin/main-epi-manager origin/main
   ```
   With the same ref as `<before-sha>` and `<target>`, the diff is empty: the script
   must run and report `Issues: 0`. The audits only print; they change nothing.
5. End-to-end: ask the agent to merge the branches and stop at the push question.
   Then undo the local merges (ask the agent, or `git reset --hard <before-sha>` on
   each branch that was not pushed).

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| The skill is not listed | Start the agent from the repository root; check that the agent reads `.agents/skills/` or `.claude/skills/` (table above) and that the symlink is not a plain text file (`core.symlinks`). |
| `.claude/skills/episciences-merge-branches` is a small text file | The checkout was made with `core.symlinks=false`: enable it, then `git checkout -- .claude/skills`. |
| The scripts disappear in the middle of a merge | The target branch does not contain the skill yet: use the copy in `tmp/merge-branches/scripts`. |
| `phpunit_failures.sh` refuses to run | The container mounts another checkout (clone, worktree): run it from that checkout or set `CONTAINER`. |
| `phpunit` is KO with no test failure | No PHPUnit summary (bootstrap error, missing dependency): run `composer install` in the container after switching branches. |
| The agent wants to push | It must ask first, branch by branch. Answer only for the branch it names. |
