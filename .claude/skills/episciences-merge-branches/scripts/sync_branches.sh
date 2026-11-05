#!/usr/bin/env bash
# Prepare the infra branches before a propagation: check that no tracked file is
# modified, fetch origin, fast-forward every branch to origin and enable rerere.
# Goes back to the starting branch at the end.
#
# Usage: sync_branches.sh [branch...]
#   default branches: main staging main-epi-manager preprod-epi-manager
#
# Never resets nor stashes: a branch that cannot be fast-forwarded is reported
# with its local-only commits, and the exit code is 1.

set -uo pipefail

branches=("$@")
if [[ ${#branches[@]} -eq 0 ]]; then
    branches=(main staging main-epi-manager preprod-epi-manager)
fi

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Tracked files are modified: commit them or ask the user (never stash)." >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

start="$(git branch --show-current)"
echo "Start branch: ${start:-detached HEAD}"

git fetch origin --prune || exit 1
git config rerere.enabled true && git config rerere.autoupdate false

status=0
for b in "${branches[@]}"; do
    if ! git checkout -q "$b"; then
        echo "[KO] ${b}: checkout failed"
        status=1
        continue
    fi
    if git merge -q --ff-only "origin/${b}" 2>/dev/null; then
        echo "[OK] ${b} at $(git rev-parse --short HEAD)"
    else
        echo "[KO] ${b}: cannot fast-forward to origin/${b}; local-only commits:"
        git log --oneline "origin/${b}..${b}"
        status=1
    fi
done

[[ -n "$start" ]] && git checkout -q "$start"
exit $status
