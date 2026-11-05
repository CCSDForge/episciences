#!/usr/bin/env bash
# Run every post-merge check of a manager target (*-epi-manager) in one go and
# print an OK / REVIEW / KO summary. Run it from the repository root, with the
# target checked out, after the merge commit and the possible fix(urls) commit.
#
# Usage: post_merge_checks.sh <sha-before> <target> <source>
#   <sha-before>  target tip noted before the merge
# Env:
#   SKIP_TESTS=1  skip the full PHPUnit suite and Jest
#   CONTAINER     PHP container (default episciences-php-fpm)
#
# Checks:
#   urls      audit_urls.sh on everything added since <sha-before>
#   url-build added lines that build a URL elsewhere (getUrl(), APPLICATION_URL...)
#   methods   check_methods.py (issues and [unused] notes)
#   hardcoded .github/scripts/check-hardcoded-internal-urls.php (blocking on manager branches)
#   menu      hardcoded '/website/...' calls in the menu area
#   js-prefix JS_PREFIX_URL read at the top level of a public/js file: these
#             files load in <head>, before the layout declares JS_PREFIX_URL
#   phpunit   full suite, compared with the cached baseline of <sha-before>
#             (phpunit_failures.sh run on the target before the merge)
#   jest      only if JS files were touched
# Read-only: it never modifies the repository. Exit code 1 if a check is KO.

set -uo pipefail

if [[ $# -ne 3 ]]; then
    echo "Usage: $0 <sha-before> <target> <source>" >&2
    exit 2
fi

before="$1"
target="$2"
source="$3"
dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
container="${CONTAINER:-episciences-php-fpm}"
# Same cache directory as phpunit_failures.sh
cache="${TMPDIR:-$(git rev-parse --show-toplevel)/tmp/merge-branches}"

if [[ "$(git branch --show-current)" != "$target" ]]; then
    echo "Check out ${target} first: the tests run on the checked-out branch." >&2
    exit 2
fi

results=()
status=0
record() { # name, state, detail
    results+=("$(printf '%-10s %-7s %s' "$1" "$2" "$3")")
    [[ "$2" == "KO" ]] && status=1
    return 0
}

echo "######## urls"
audit="$(bash "${dir}/audit_urls.sh" "$before" "$target" 2>&1)"
echo "$audit"
count="$(printf '%s\n' "$audit" | sed -nE 's/^Suspicious URL lines: ([0-9]+)$/\1/p')"
if [[ "${count:-}" == "0" ]]; then
    record urls OK "0 suspicious line"
else
    record urls REVIEW "${count:-?} line(s): fix or dismiss each one as a false positive"
fi

echo "######## url-build"
built="$(git diff "$before" "$target" -- '*.php' '*.phtml' \
    | grep -nE '^\+.*(getUrl\(\)|getBackEndUrl|APPLICATION_URL|SERVER_PROTOCOL|DOMAIN)' \
    | grep -vE '^[0-9]+:\+[[:space:]]*(\*|//|#|/\*)' || true)"
echo "${built:-none}"
if [[ -z "$built" ]]; then
    record url-build OK "none"
else
    record url-build REVIEW "$(printf '%s\n' "$built" | wc -l) line(s) building a URL: read them"
fi

echo "######## methods"
methods="$(python3 "${dir}/check_methods.py" "$before" "$target" "$source" 2>&1)"
methods_rc=$?
echo "$methods"
notes="$(printf '%s\n' "$methods" | grep -c '^\[unused\]' || true)"
if [[ $methods_rc -ne 0 ]]; then
    record methods KO "$(printf '%s\n' "$methods" | grep '^Issues:')"
elif [[ "$notes" != "0" ]]; then
    record methods REVIEW "Issues: 0, ${notes} [unused] note(s)"
else
    record methods OK "Issues: 0"
fi

echo "######## hardcoded"
guard=.github/scripts/check-hardcoded-internal-urls.php
if git cat-file -e "${target}:${guard}" 2>/dev/null; then
    if docker exec -u www-data -w /var/www/htdocs "$container" php "$guard" 2>&1 | tail -15; then
        record hardcoded OK "no hardcoded internal URL"
    else
        record hardcoded KO "check-hardcoded-internal-urls.php fails (blocking)"
    fi
else
    record hardcoded REVIEW "${guard} absent from ${target}: report it"
fi

echo "######## menu"
menu="$(git grep -nE "'/website/(ajax|menu|reset)" "$target" -- public/js/website application/modules/common || true)"
echo "${menu:-none}"
if [[ -z "$menu" ]]; then
    record menu OK "none"
else
    record menu KO "hardcoded /website/ calls in the menu area"
fi

echo "######## js-prefix"
# Unindented line = top level of the file, run when the script loads
toplevel="$(git grep -nE '^[^[:space:]/*].*JS_PREFIX_URL' "$target" -- 'public/js/*.js' || true)"
echo "${toplevel:-none}"
if [[ -z "$toplevel" ]]; then
    record js-prefix OK "none"
else
    record js-prefix KO "JS_PREFIX_URL read when the script loads: ReferenceError"
fi

if [[ "${SKIP_TESTS:-0}" == "1" ]]; then
    record phpunit SKIPPED "SKIP_TESTS=1"
    record jest SKIPPED "SKIP_TESTS=1"
else
    echo "######## phpunit"
    baseline="${cache}/phpunit-failures-$(git rev-parse "$before").txt"
    after="$(bash "${dir}/phpunit_failures.sh" | tail -1)"
    status=$?
    summary="$(cat "${cache}/phpunit-summary-$(git rev-parse HEAD).txt" 2>/dev/null)"
    if [[ $status -ne 0 ]]; then
        record phpunit KO "the suite did not run (see the output above)"
    elif [[ ! -f "$baseline" ]]; then
        record phpunit REVIEW "${summary}; no baseline for ${before}: compare by hand"
    else
        new="$(comm -13 "$baseline" "$after")"
        if [[ -z "$new" ]]; then
            record phpunit OK "${summary}; no new failure"
        else
            echo "New failures:"
            echo "$new"
            record phpunit KO "${summary}; $(printf '%s\n' "$new" | wc -l) new failure(s)"
        fi
    fi

    echo "######## jest"
    if [[ -n "$(git diff --name-only "$before" "$target" -- '*.js')" ]]; then
        if yarn test --roots '<rootDir>/tests/js' 2>&1 | tail -8; then
            record jest OK "passes"
        else
            record jest KO "fails"
        fi
    else
        record jest SKIPPED "no JS file touched"
    fi
fi

echo
echo "== Post-merge checks: ${source} -> ${target} (since ${before:0:9}) =="
printf '%s\n' "${results[@]}"
exit $status
