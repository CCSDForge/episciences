#!/bin/bash
#
# End-to-end check of the security log of denied accesses on the local Docker environment.
#
# Each scenario sends real HTTP requests to the dev journal, with its own User-Agent, then reads the
# events written to logs/security-YYYY-MM-DD.log for that User-Agent:
#
#   - guest on a protected page                 -> auth_required (INFO)
#   - unknown URL                               -> nothing
#   - existing action removed from acl.ini      -> acl_missing (ERROR)
#   - invitation of another journal             -> not_found_masked (NOTICE)
#   - member on an editorial page               -> acl_denied (WARNING)
#   - AJAX POST without request token           -> csrf_failed
#   - wrong password                            -> login_failed (NOTICE), the password never logged
#
# The logged-in scenarios need the MySQL authentication adapter ("AUTH_ADAPTER_NAME": "MYSQL" in the
# EPISCIENCES section of config/pwd.json, see config/dist-dev.pwd.json) and a member account of the dev
# journal created by `make init-dev-users` (password "password123"). They are skipped under CAS.
#
# acl.ini is modified for the acl_missing scenario and restored on exit, whatever happens.
#
# The journal is read from BASE_URL: the first path segment when there is one (journal prefixed URLs of the
# *-epi-manager branches, e.g. https://manager-dev.episciences.org/dev, the journal having
# REVIEW.is_new_front_switched = 'yes'), the first label of the host name otherwise (https://dev.episciences.org).
#
# Usage: bash scripts/check-security-log.sh
# Environment: BASE_URL (default https://dev.episciences.org, without trailing slash), JOURNAL_CODE (code of the
# journal of BASE_URL, read from BASE_URL by default), MEMBER_USERNAME, MEMBER_PASSWORD

set -uo pipefail

cd "$(dirname "$0")/.." || exit 1

BASE_URL="${BASE_URL:-https://dev.episciences.org}"
MEMBER_PASSWORD="${MEMBER_PASSWORD:-password123}"
BASE_URL="${BASE_URL%/}"
url_rest="${BASE_URL#*://}"
url_host="${url_rest%%/*}"
url_path="${url_rest#"$url_host"}"
url_path="${url_path#/}"
JOURNAL_CODE="${JOURNAL_CODE:-${url_path%%/*}}"
JOURNAL_CODE="${JOURNAL_CODE:-${url_host%%.*}}"
PHP_CONTAINER="php-fpm"
APP_USER="www-data"
APP_DIR="/var/www/htdocs"
LOG_DIR="logs"
ACL_FILE="application/configs/acl.ini"
ACL_BACKUP="tmp/acl.ini.security-log-check.bak"
# Guest resource removed from the ACL for the acl_missing scenario (UserController inherits photoAction
# from UserDefaultController, a shared parent: the action exists)
MISSING_ACL_LINE='allow.user-photo = ""'

RUN_ID="$(date +%s)-$$"
WORK_DIR="tmp/security-log-check-$RUN_ID"
COOKIES="$WORK_DIR/cookies.txt"

PASSED=0
FAILED=0
SKIPPED=0

mkdir -p "$WORK_DIR"

cleanup() {
    if [ -f "$ACL_BACKUP" ]; then
        cp "$ACL_BACKUP" "$ACL_FILE" && rm -f "$ACL_BACKUP"
    fi
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

pass() { echo "  PASS  $1"; PASSED=$((PASSED + 1)); }
fail() { echo "  FAIL  $1"; FAILED=$((FAILED + 1)); }
skip() { echo "  SKIP  $1"; SKIPPED=$((SKIPPED + 1)); }

user_agent() { echo "security-log-check/$RUN_ID/$1"; }

# HTTP status of a request; extra curl arguments are passed through
http() {
    local scenario="$1"
    shift
    curl -sk -o /dev/null -w '%{http_code}' -A "$(user_agent "$scenario")" -b "$COOKIES" -c "$COOKIES" "$@"
}

# Security events of a scenario, one JSON object per line
events() {
    local agent
    agent="$(user_agent "$1")"
    cat "$LOG_DIR"/security-*.log 2>/dev/null | jq -c --arg ua "$agent" 'select(.context.user_agent == $ua)'
}

# Check that a scenario logged exactly one event, with the given name and level
expect_event() {
    local scenario="$1" event="$2" level="$3" label="$4"
    local found
    found="$(events "$scenario")"

    if [ "$(echo -n "$found" | grep -c '^')" -ne 1 ]; then
        fail "$label: expected one $event event, got: ${found:-nothing}"
        return 1
    fi
    if [ "$(echo "$found" | jq -r '.message')" != "$event" ] || [ "$(echo "$found" | jq -r '.level_name')" != "$level" ]; then
        fail "$label: expected $event ($level), got: $found"
        return 1
    fi

    pass "$label: $(echo "$found" | jq -c '{event: .message, level: .level_name, reason: .context.reason, resource: .context.resource, username: .context.username}' | jq -c 'with_entries(select(.value != null))')"
}

# Run PHP code in the application container; the following arguments are given to it in $argv
php_in_container() {
    local code="$1"
    shift
    docker compose exec -T -u "$APP_USER" -w "$APP_DIR" "$PHP_CONTAINER" php -r "$code" -- "$@"
}

JOURNAL_RVID="$(php_in_container '
    require "public/const.php";
    require "public/bdd_const.php";
    $epi = new PDO(EPI_PDO_URL, EPI_USER, EPI_PWD);
    $stmt = $epi->prepare("SELECT RVID FROM REVIEW WHERE CODE = ?");
    $stmt->execute([$argv[1]]);
    echo (string)$stmt->fetchColumn();
' "$JOURNAL_CODE" 2>/dev/null)"

if [ -z "$JOURNAL_RVID" ]; then
    echo "Unknown journal '$JOURNAL_CODE' (read from $BASE_URL): set JOURNAL_CODE" >&2
    exit 1
fi

echo "Security log check on $BASE_URL, journal $JOURNAL_CODE (RVID $JOURNAL_RVID), run $RUN_ID"
echo

# ---------------------------------------------------------------------------------------------------
echo "Guest"

http auth_required "$BASE_URL/paper/submitted" > /dev/null
expect_event auth_required auth_required INFO "guest on a protected page"

http unknown_url "$BASE_URL/nosuchpage$RUN_ID/foo" > /dev/null
if [ -z "$(events unknown_url)" ]; then
    pass "unknown URL: nothing logged"
else
    fail "unknown URL: expected nothing, got: $(events unknown_url)"
fi

if grep -qxF "$MISSING_ACL_LINE" "$ACL_FILE"; then
    cp "$ACL_FILE" "$ACL_BACKUP"
    grep -vxF "$MISSING_ACL_LINE" "$ACL_BACKUP" > "$ACL_FILE"
    http acl_missing "$BASE_URL/user/photo/uid/1" > /dev/null
    cp "$ACL_BACKUP" "$ACL_FILE" && rm -f "$ACL_BACKUP"
    expect_event acl_missing acl_missing ERROR "existing action removed from acl.ini"
else
    skip "existing action removed from acl.ini: '$MISSING_ACL_LINE' not found in $ACL_FILE"
fi

# Latest invitation whose assignment belongs to another journal than the one of BASE_URL
other_journal_invitation="$(php_in_container '
    require "public/const.php";
    require "public/bdd_const.php";
    $epi = new PDO(EPI_PDO_URL, EPI_USER, EPI_PWD);
    $stmt = $epi->prepare("SELECT i.ID FROM USER_INVITATION i JOIN USER_ASSIGNMENT a ON a.ID = i.AID WHERE a.RVID <> ? ORDER BY i.ID DESC LIMIT 1");
    $stmt->execute([(int)$argv[1]]);
    echo (string)$stmt->fetchColumn();
' "$JOURNAL_RVID" 2>/dev/null)"

if ! grep -qF "'invitation_not_in_journal'" application/modules/journal/controllers/ReviewerController.php; then
    skip "invitation of another journal: not logged by this version of ReviewerController (PR #1449)"
elif [ -n "$other_journal_invitation" ]; then
    http not_found_masked "$BASE_URL/reviewer/invitation/id/$other_journal_invitation" > /dev/null
    if expect_event not_found_masked not_found_masked NOTICE "invitation of another journal (id $other_journal_invitation)"; then
        if [ "$(events not_found_masked | jq -r '.context.reason')" != "invitation_not_in_journal" ]; then
            fail "invitation of another journal: expected the reason invitation_not_in_journal"
        fi
    fi
else
    skip "invitation of another journal: no invitation of a journal other than RVID $JOURNAL_RVID in the database"
fi

# ---------------------------------------------------------------------------------------------------
echo
echo "Logged-in member"

login_redirect="$(curl -sk -o /dev/null -w '%{redirect_url}' "$BASE_URL/user/login")"
if [[ "$login_redirect" == *"/cas/"* ]]; then
    for label in "wrong password" "member on an editorial page" "AJAX POST without request token"; do
        skip "$label: the CAS adapter is active, set AUTH_ADAPTER_NAME to MYSQL in config/pwd.json"
    done
else
    # A member of the dev journal without any other role, whose password is the dev one
    MEMBER_USERNAME="${MEMBER_USERNAME:-$(php_in_container '
        require "public/const.php";
        require "public/bdd_const.php";
        $epi = new PDO(EPI_PDO_URL, EPI_USER, EPI_PWD);
        $roles = $epi->prepare("SELECT UID FROM USER_ROLES WHERE RVID = ? GROUP BY UID HAVING SUM(ROLEID <> \"member\") = 0");
        $roles->execute([(int)$argv[2]]);
        $uids = $roles->fetchAll(PDO::FETCH_COLUMN);
        if ($uids === []) { exit(0); }
        $cas = new PDO(CAS_PDO_URL, CAS_USER, CAS_PWD);
        $in = implode(",", array_map("intval", $uids));
        $stmt = $cas->prepare("SELECT USERNAME FROM T_UTILISATEURS WHERE UID IN ($in) AND VALID = 1 AND PASSWORD = SHA2(?, 512) LIMIT 1");
        $stmt->execute([$argv[1]]);
        echo (string)$stmt->fetchColumn();
    ' "$MEMBER_PASSWORD" "$JOURNAL_RVID" 2>/dev/null)}"

    if [ -z "$MEMBER_USERNAME" ]; then
        for label in "wrong password" "member on an editorial page" "AJAX POST without request token"; do
            skip "$label: no member account found, run make init-dev-users or set MEMBER_USERNAME"
        done
    else
        echo "  (member account: $MEMBER_USERNAME)"

        wrong_password="wrong-password-$RUN_ID"
        http login_failed "$BASE_URL/user/login" --data-urlencode "username=$MEMBER_USERNAME" --data-urlencode "password=$wrong_password" > /dev/null
        if expect_event login_failed login_failed NOTICE "wrong password"; then
            if [ "$(events login_failed | jq -r '.context.username')" != "$MEMBER_USERNAME" ]; then
                fail "wrong password: the tested login is not logged"
            fi
        fi
        if cat "$LOG_DIR"/security-*.log | grep -qF "$wrong_password"; then
            fail "wrong password: the password was written to the security log"
        else
            pass "wrong password: the password is not in the security log"
        fi

        rm -f "$COOKIES"
        http login "$BASE_URL/user/login" > /dev/null
        http login "$BASE_URL/user/login" --data-urlencode "username=$MEMBER_USERNAME" --data-urlencode "password=$MEMBER_PASSWORD" > /dev/null
        # A page rendered with the layout stores the request token in the session
        http login "$BASE_URL/" > /dev/null

        http acl_denied "$BASE_URL/administratemail/templates" > /dev/null
        expect_event acl_denied acl_denied WARNING "member on an editorial page"

        status="$(http csrf_failed -X POST -H 'X-Requested-With: XMLHttpRequest' --data 'docId=1' "$BASE_URL/file/upload")"
        if expect_event csrf_failed csrf_failed WARNING "AJAX POST without request token" && [ "$status" != "403" ]; then
            fail "AJAX POST without request token: expected a 403 response, got $status"
        fi
    fi
fi

# ---------------------------------------------------------------------------------------------------
echo
echo "Warnings of this run (jq query of the test plan)"
cat "$LOG_DIR"/security-*.log 2>/dev/null \
    | jq -c --arg run "security-log-check/$RUN_ID/" \
        'select((.context.user_agent // "") | startswith($run)) | select(.level_name=="WARNING") | [.datetime,.context.ip,.context.uid,.message]'

echo
echo "Passed: $PASSED, failed: $FAILED, skipped: $SKIPPED"
[ "$FAILED" -eq 0 ]
