#!/usr/bin/env bash
# Audit the lines a merge would bring into a manager branch (*-epi-manager) and
# report hardcoded absolute URLs that bypass the path-based routing
# (PREFIX_URL / PREFIX_ROUTE / JS_PREFIX_URL / $this->url() / UrlBuilder).
#
# Usage: audit_urls.sh <target-branch> <source-branch>
#   e.g. audit_urls.sh preprod-epi-manager staging
#
# Only lines ADDED by <source> since the merge base are inspected
# (git diff <target>...<source>), so pre-existing code is not reported.
# This script only reports; it never modifies anything.

set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <target-branch> <source-branch>" >&2
    exit 2
fi

target="$1"
source="$2"

# Internal controller paths, shared by the PHP and JS patterns
controllers="administrate[a-z]*|paper|user|volume|section|review|file|export|submit|page|mailing|coi|doi|stats|browse|search|website"
# Full URL of an episciences.org host followed by an internal path: it skips
# the journal prefix just like a root-relative one. Documentation and portal
# pages (doc., www.) do not use these paths and stay out of scope.
absolute_internal="https?://[a-z0-9.-]*episciences\\.org/(${controllers})/"

# Patterns of hardcoded root-relative URLs. A leading "/" followed by a
# lowercase letter matches controller paths such as /administratepaper/view.
php_patterns=(
    "href=[\"']/[a-z]"
    "action=[\"']/[a-z]"
    "src=[\"']/[a-z]"
    "redirect\\([\"']/[a-z]"
    "gotoUrl\\( *[\"']/[a-z]"
    "setAction\\( *[\"']/[a-z]"
    "sprintf\\( *[\"']/%s"
    "Location: */[a-z]"
    "APPLICATION_URL *\\. *\\\$this->(view->)?url\\("
    # Journal subdomain URL followed by an internal path: on manager branches
    # use getBackEndUrl() or UrlBuilder (buildAdminPaperUrl()...) instead.
    "->getUrl\\(\\) *\\. *[\"']/[a-z]"
    # Journal subdomain URL passed on (argument, variable) to build a link
    # elsewhere: the internal path is appended out of sight of this audit.
    "\\\$[a-zA-Z_]*[rR]eview->getUrl\\(\\) *[,)]"
    "= *\\\$[a-zA-Z_]*[rR]eview->getUrl\\(\\) *;"
    "\\\$_SERVER\\[[\"']SERVER_NAME[\"']\\]"
    "$absolute_internal"
)
js_patterns=(
    "url: *[\"'\`]/[a-z]"
    "fetch\\( *[\"'\`]/[a-z]"
    "\\\$\\.(get|post|ajax|getJSON)\\( *[\"'\`]/[a-z]"
    "(window\\.)?location(\\.href)? *= *[\"'\`]/[a-z]"
    "action=[\"']/[a-z]"
    "href=[\"']/[a-z]"
    "[\"'\`]/(${controllers})/"
    "$absolute_internal"
)

join_patterns() {
    local IFS='|'
    echo "$*"
}

php_re="$(join_patterns "${php_patterns[@]}")"
js_re="$(join_patterns "${js_patterns[@]}")"

# Emit "file:line: content" for every added line, using hunk headers to
# compute line numbers in the source branch.
added_lines() {
    git diff --no-color -U0 "${target}...${source}" -- "$@" \
        ':(exclude)tests/**' ':(exclude)vendor/**' ':(exclude)node_modules/**' \
        ':(exclude)public/build/**' ':(exclude)**/*.min.js' \
        ':(exclude).github/scripts/check-hardcoded-internal-urls.php' \
        | awk '
            /^\+\+\+ b\// { file = substr($0, 7); next }
            /^@@/ {
                split($3, a, ",")
                line = substr(a[1], 2) + 0
                next
            }
            /^\+/ && !/^\+\+\+/ { print file ":" line ": " substr($0, 2); line++ }
        '
}

php_lines="$(added_lines '*.php' '*.phtml')"
php_hits="$(grep -E -- "$php_re" <<< "$php_lines" || true)"
js_hits="$(added_lines '*.js' '*.ts' | grep -E -- "$js_re" || true)"

# Controller redirections build their URL from route params: on manager
# branches they must carry [PREFIX_ROUTE => RVCODE], otherwise the review
# code is dropped. Calls split over several lines may be false positives.
redirect_hits="$(grep -E -- "redirector\\(|->gotoSimple\\(" <<< "$php_lines" \
    | grep -v -- "PREFIX_ROUTE" || true)"

# Zend_Controller_Action_Helper_Url::simple() builds the URL without the
# router. Manager branches replace it (Bootstrap) with
# Episciences_Controller_Action_Helper_Url, which adds PREFIX_URL: a direct
# use of the Zend class bypasses that replacement.
zend_url_hits="$(grep -E -- "Zend_Controller_Action_Helper_Url\\b" <<< "$php_lines" || true)"

echo "== URL audit: ${source} -> ${target} =="
echo "Merge base: $(git merge-base "$target" "$source" | cut -c1-12)"
echo

count=0
if [[ -n "$php_hits" ]]; then
    echo "-- PHP / PHTML (prefer \$this->url([...]), PREFIX_URL, UrlBuilder, getBackEndUrl()) --"
    echo "$php_hits"
    echo
    count=$((count + $(printf '%s\n' "$php_hits" | wc -l)))
fi
if [[ -n "$redirect_hits" ]]; then
    echo "-- Redirections without PREFIX_ROUTE (add null, [PREFIX_ROUTE => RVCODE]) --"
    echo "$redirect_hits"
    echo
    count=$((count + $(printf '%s\n' "$redirect_hits" | wc -l)))
fi
if [[ -n "$zend_url_hits" ]]; then
    echo "-- Direct use of the Zend Url action helper (prefer \$this->_helper->url() or \$this->url([...])) --"
    echo "$zend_url_hits"
    echo
    count=$((count + $(printf '%s\n' "$zend_url_hits" | wc -l)))
fi
if [[ -n "$js_hits" ]]; then
    echo "-- JavaScript (prefer JS_PREFIX_URL + 'controller/action') --"
    echo "$js_hits"
    echo
    count=$((count + $(printf '%s\n' "$js_hits" | wc -l)))
fi

# Infra files whose manager-side content must be preserved.
sensitive="$(git diff --name-only "${target}...${source}" -- \
    public/const.php application/Bootstrap.php 'application/configs/*.ini' \
    src/apache/vhost.conf 'config/dist*pwd.json' \
    library/Episciences/Trait/UrlBuilder.php library/Episciences/View/Helper/Url.php \
    library/Episciences/Controller/Action/Helper/Url.php \
    library/Ccsd/View/Helper/Pagelink.php library/Episciences/View/Helper/Pagelink.php \
    2>/dev/null || true)"
if [[ -n "$sensitive" ]]; then
    echo "-- Routing/infra files touched by ${source} (review manually) --"
    echo "$sensitive"
    echo
fi

echo "Suspicious URL lines: ${count}"
