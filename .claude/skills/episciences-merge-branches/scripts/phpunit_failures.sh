#!/usr/bin/env bash
# Run the full PHPUnit suite on the checked-out commit, in the container that
# mounts the repository, and keep the list of failing tests, cached by commit:
#   <dir>/phpunit-failures-<sha>.txt   failing tests, one per line, unnumbered
#   <dir>/phpunit-summary-<sha>.txt    PHPUnit summary line
# <dir> is $TMPDIR if exported, else tmp/merge-branches/ in the repository
# (ignored by git). A second call on the same commit reuses the cache instead
# of running the suite.
#
# Usage: phpunit_failures.sh [--force]
#   --force   run the suite even if the cache exists
# Env: CONTAINER (default episciences-php-fpm)
# The suite runs as www-data, like `make test-php`: as root, files created by
# the tests become unwritable for later runs, and permission tests pass wrongly.
# Exit code 1, without caching anything, if PHPUnit prints no summary
# (bootstrap error, missing dependency...).
#
# Prints the path of the failure list on the last line.

set -euo pipefail

dir="${TMPDIR:-$(git rev-parse --show-toplevel)/tmp/merge-branches}"
mkdir -p "$dir"
container="${CONTAINER:-episciences-php-fpm}"

if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Tracked files are modified (merge in progress?): the result would not match a commit." >&2
    exit 1
fi

sha="$(git rev-parse HEAD)"
failures="${dir}/phpunit-failures-${sha}.txt"
summary="${dir}/phpunit-summary-${sha}.txt"

if [[ "${1:-}" != "--force" && -f "$failures" && -f "$summary" ]]; then
    echo "Cached result for $(git rev-parse --short HEAD) ($(git branch --show-current))"
else
    output="$(docker exec -u www-data -e XDEBUG_MODE=off -w /var/www/htdocs "$container" vendor/bin/phpunit --no-coverage 2>&1 || true)"
    if ! grep -qE '^(OK|Tests:)' <<<"$output"; then
        printf '%s\n' "$output" | tail -20 >&2
        echo "PHPUnit printed no summary (bootstrap error?): nothing cached." >&2
        exit 1
    fi
    # Numbers ("1) ") shift from one run to another: drop them so lists can be compared
    printf '%s\n' "$output" | grep -E '^[0-9]+\) ' | sed -E 's/^[0-9]+\) //' | sort -u > "$failures" || true
    printf '%s\n' "$output" | grep -E '^(OK|Tests:)' | tail -1 > "$summary" || true
fi

cat "$summary"
cat "$failures"
echo "$failures"
