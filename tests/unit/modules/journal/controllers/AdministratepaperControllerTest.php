<?php

namespace unit\modules\journal\controllers;

use PHPUnit\Framework\TestCase;

/**
 * Security and bug regression tests for AdministratepaperController.
 *
 * Strategy: source-code pattern analysis (static analysis via PHP string inspection).
 * No database or HTTP dispatch needed — tests are fast and run without side effects.
 *
 * Each test documents one concrete bug found during a security audit.
 * Currently failing tests mark existing bugs (red). They will turn green once fixed.
 *
 * @covers AdministratepaperController
 */
class AdministratepaperControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $this->source = file_get_contents(
            APPLICATION_PATH . '/modules/journal/controllers/AdministratepaperController.php'
        );
    }

    // ---------------------------------------------------------------
    // Helper: extract a method body from the source by its name
    // ---------------------------------------------------------------

    private function extractMethod(string $methodName): string
    {
        $start = strpos($this->source, 'function ' . $methodName);
        $this->assertNotFalse($start, "Method $methodName not found in AdministratepaperController");

        $end = strpos($this->source, 'function ', $start + strlen('function ' . $methodName));
        if ($end === false) {
            return substr($this->source, $start);
        }

        return substr($this->source, $start, $end - $start);
    }

    // ---------------------------------------------------------------
    // BUG #1 — AUTH BYPASS: missing `return` after auth failure
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * Bug: line 165 echoes an error JSON but has NO `return` statement.
     * Execution falls through to line 173+ where it reads $docId and
     * calls Episciences_PapersManager::get($docId) on behalf of an
     * unauthorized user.
     *
     * Fix: add `return;` after `echo json_encode($resBack);` on line 170.
     */
    public function testAjaxrequestnewdoiActionMissingReturnAfterAuthFailure(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        // Extract the auth-check block: from "if (!Episciences_Auth::isLogged()" to its closing "}"
        preg_match('/if\s*\(!Episciences_Auth::isLogged\(\)[^}]+\}/s', $method, $matches);
        $this->assertNotEmpty($matches, 'Auth check block not found in ajaxrequestnewdoiAction');

        $this->assertStringContainsString(
            'return',
            $matches[0],
            'BUG #1: ajaxrequestnewdoiAction auth failure block must contain a return statement to prevent unauthorized execution after echoing the error response'
        );
    }

    // ---------------------------------------------------------------
    // BUG #2 — SQL INJECTION: `$docId` not cast to int before query
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::savepublicationdateAction
     *
     * Bug: line 4242 assigns $docId from POST without (int) cast.
     * Line 4275 interpolates it directly into a SQL string.
     * An attacker submitting docid='1 OR 1=1' can manipulate all rows.
     *
     * Fix: cast at assignment time:
     *   $docId = (int)(($request->getPost('docid')) ?: $request->getParam('docid'));
     */
    public function testSavepublicationdateActionDocIdIsCastToInt(): void
    {
        $method = $this->extractMethod('savepublicationdateAction');

        // Find all assignments of $docId from the request
        preg_match_all('/\$docId\s*=\s*[^;]+;/', $method, $assignments);
        $this->assertNotEmpty($assignments[0], '$docId assignment not found in savepublicationdateAction');

        foreach ($assignments[0] as $assignment) {
            if (str_contains($assignment, '$request')) {
                $this->assertStringContainsString(
                    '(int)',
                    $assignment,
                    'BUG #2: $docId must be cast to (int) before use in savepublicationdateAction — found: ' . $assignment
                );
            }
        }
    }

    // ---------------------------------------------------------------
    // BUG #3 — SQL INJECTION: string interpolation instead of bound parameters
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::savepublicationdateAction
     *
     * Bug: line 4275 builds the SQL via string interpolation then calls prepare().
     * This is "false security": prepare() on an already-interpolated string
     * provides NO protection against injection.
     *
     * Fix: use bound parameters:
     *   $sql = "UPDATE PAPER_LOG pl SET pl.DATE = ? WHERE pl.DOCID = ? AND pl.status = ?";
     *   $db->query($sql, [$newPublicationDate, $docId, $status]);
     */
    public function testSavepublicationdateActionUsesBoundParameters(): void
    {
        $method = $this->extractMethod('savepublicationdateAction');

        // The SQL string must NOT contain variable interpolation ($ inside double-quoted string)
        $this->assertDoesNotMatchRegularExpression(
            '/"[^"]*UPDATE PAPER_LOG[^"]*\$[^"]*"/',
            $method,
            'BUG #3: SQL in savepublicationdateAction must use ? placeholders, not string interpolation — raw variables inside a double-quoted SQL string expose the query to injection'
        );
    }

    // ---------------------------------------------------------------
    // BUG #4 — WRONG FLASH NAMESPACE: 'error' used for success message
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::addcoauthorAction
     *
     * Bug: line 4815 — when $user->hasLocalData() && $user->hasRoles() is TRUE
     * (the happy path: a known user is added as co-author), the flash message
     * is added to the 'error' namespace instead of 'success'.
     *
     * Fix: replace setNamespace('error') with setNamespace('success') at line 4815.
     */
    public function testAddcoauthorActionUsesSuccessNamespaceForSuccessMessage(): void
    {
        $method = $this->extractMethod('addcoauthorAction');

        // Isolate the "hasLocalData && hasRoles" success branch.
        // The outer else begins with the comment "// Récupération des données CAS",
        // so everything before that comment belongs to the success path.
        $casElsePos = strpos($method, '// Récupération des données CAS');
        $this->assertNotFalse($casElsePos, '"Récupération des données CAS" comment not found — cannot isolate success branch in addcoauthorAction');
        $successBranch = substr($method, 0, $casElsePos);

        $this->assertStringNotContainsString(
            "setNamespace('error')",
            $successBranch,
            "BUG #4: addcoauthorAction must not use the 'error' flash namespace when successfully adding a known co-author (hasLocalData && hasRoles branch)"
        );
    }

    // ---------------------------------------------------------------
    // BUG #5 — MISSING AUTHORIZATION: no role check before adding co-author
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::addcoauthorAction
     *
     * Bug: the method has no explicit role check before executing addRoleCoAuthor().
     * Any logged-in user who can reach this route (e.g., a reviewer) could add
     * arbitrary co-authors to any paper.
     *
     * Fix: add at method start:
     *   if (!Episciences_Auth::isAdministrator() && !Episciences_Auth::isSecretary()) { return; }
     */
    public function testAddcoauthorActionRequiresPrivilegedRole(): void
    {
        $method = $this->extractMethod('addcoauthorAction');

        $hasRoleCheck = (
            str_contains($method, 'isAdministrator()') ||
            str_contains($method, 'isSecretary()') ||
            str_contains($method, 'isEditor()') ||
            str_contains($method, 'checkPermissions(')
        );

        $this->assertTrue(
            $hasRoleCheck,
            'BUG #5: addcoauthorAction must check user role (isAdministrator/isSecretary/isEditor/checkPermissions) before adding a co-author — any logged-in user can currently trigger this action'
        );
    }

    // ---------------------------------------------------------------
    // BUG #6 — REVERSED LOGIC: form validation condition inverted
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::suggeststatusAction
     *
     * Bug line 2135: `if (!$this->getRequest()->isPost() && !$form->isValid(...))`
     *
     * The AND means a GET request alone won't trigger the redirect because
     * `!isPost()` is TRUE but `!isValid(GET)` may be FALSE. The intended
     * guard ("redirect back when POSTing with an invalid form") is bypassed.
     *
     * Fix: change `!$this->getRequest()->isPost()` to `$this->getRequest()->isPost()`.
     */
    public function testSuggeststatusActionFormValidationLogicIsCorrect(): void
    {
        $method = $this->extractMethod('suggeststatusAction');

        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*!\s*\$this->getRequest\(\)->isPost\(\)\s*&&\s*!\s*\$form->isValid/',
            $method,
            'BUG #6: suggeststatusAction uses reversed validation logic — condition reads !isPost() && !isValid() but should be isPost() && !isValid()'
        );
    }

    // ---------------------------------------------------------------
    // BUG #7 — UNVALIDATED INPUT: `$type` not whitelisted before use
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::revisionAction
     *
     * Bug: $type comes from the request and is used to build field names like
     * `$type . 'revisionsubject'` (line ~4376). The sequential equality checks
     * (=== 'minor', === 'major') do guard against unknown types but miss the
     * missing `return` after the redirector call, letting execution continue.
     *
     * Fix: use an explicit in_array whitelist with an early return:
     *   $allowedTypes = ['minor', 'major', 'acceptedAskAuthorsFinalVersion'];
     *   if (!in_array($type, $allowedTypes, true)) { return; }
     */
    public function testRevisionActionTypeParameterIsValidatedAgainstWhitelist(): void
    {
        $method = $this->extractMethod('revisionAction');

        $hasWhitelistCheck = (
            str_contains($method, 'in_array($type') ||
            str_contains($method, "['minor'") ||
            str_contains($method, "['major'")
        );

        $this->assertTrue(
            $hasWhitelistCheck,
            'BUG #7: revisionAction must validate $type against an explicit allowed-values array (in_array whitelist) before using it to construct field names — the current sequential equality checks lack an early return after the redirect call'
        );
    }

    // ---------------------------------------------------------------
    // BUG #8 — UNSAFE JSON: no validation after json_decode
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::savereviewerinvitationAction
     *
     * Bug line 1615: `json_decode($reviewer, true)` result is used immediately
     * in array operations. If the JSON is malformed or the value is not a string,
     * json_decode returns null and subsequent `foreach` / `array_key_exists` calls
     * will trigger PHP warnings/errors.
     *
     * Fix: validate after decode:
     *   $reviewerData = json_decode($reviewer, true, 512, JSON_THROW_ON_ERROR);
     *   if (!is_array($reviewerData)) { return; // error response }
     */
    public function testSavereviewerinvitationActionValidatesJsonDecodeResult(): void
    {
        $method = $this->extractMethod('savereviewerinvitationAction');

        $jsonDecodePos = strpos($method, 'json_decode($reviewer');
        $this->assertNotFalse($jsonDecodePos, 'json_decode($reviewer, ...) not found in savereviewerinvitationAction');

        // Check for is_array() or JSON_THROW_ON_ERROR within 200 chars after json_decode
        $afterDecode = substr($method, $jsonDecodePos, 200);
        $hasValidation = (
            str_contains($afterDecode, 'is_array') ||
            str_contains($afterDecode, 'JSON_THROW_ON_ERROR')
        );

        $this->assertTrue(
            $hasValidation,
            'BUG #8: savereviewerinvitationAction must validate that json_decode result is an array before using it — a malformed JSON payload currently produces PHP warnings and may cause errors'
        );
    }

    // ---------------------------------------------------------------
    // BUG #9 — HOST HEADER INJECTION: raw $_SERVER['SERVER_NAME'] in URLs
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController (multiple actions)
     *
     * Bug: multiple actions concatenate $_SERVER['SERVER_NAME'] directly into
     * notification/callback URLs. An attacker controlling the HTTP Host header
     * can inject a malicious hostname into emails sent by the application.
     *
     * Fix: use the application's configured base URL instead of raw SERVER_NAME:
     *   Zend_Registry::get('reviewSettings')['EPISCIENCES_URL']
     *   or: $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost()
     */
    public function testControllerDoesNotUseRawServerNameInUrlConstruction(): void
    {
        // Match concatenation of $_SERVER['SERVER_NAME'] inside URL-building expressions
        // Pattern: . $_SERVER['SERVER_NAME'] or . $_SERVER["SERVER_NAME"]
        preg_match_all(
            '/\.\s*\$_SERVER\s*\[\s*[\'"]SERVER_NAME[\'"]\s*\]/',
            $this->source,
            $matches
        );

        $this->assertEmpty(
            $matches[0],
            'BUG #9: ' . count($matches[0]) . ' location(s) build URLs by concatenating raw $_SERVER["SERVER_NAME"] — use Zend_Controller_Request_Http::getHttpHost() or a configured base URL constant instead to prevent Host header injection'
        );
    }

    // ---------------------------------------------------------------
    // BUG #10 — MISSING CSRF: critical state-changing actions lack token check
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::acceptAction
     * @covers AdministratepaperController::refuseAction
     * @covers AdministratepaperController::publishAction
     * @covers AdministratepaperController::revisionAction
     *
     * Bug: these four actions perform irreversible state changes on papers
     * (accept, refuse, publish, request revision) but do not validate a CSRF token.
     * An attacker can craft a cross-site request to trigger these actions on behalf
     * of a logged-in editor.
     *
     * Fix: each action must validate a CSRF token, e.g.:
     *   $form = new Ccsd_Form();
     *   if (!$form->isValid($this->getRequest()->getPost())) { return; }
     *   or validate via Zend_Session / hash_equals on a hidden token field.
     */
    public function testCriticalActionsValidateCsrfToken(): void
    {
        $criticalActions = ['acceptAction', 'refuseAction', 'publishAction', 'revisionAction'];

        foreach ($criticalActions as $action) {
            $method = $this->extractMethod($action);

            $hasCsrf = (
                stripos($method, 'csrf') !== false ||
                str_contains($method, 'hash_equals') ||
                str_contains($method, 'getToken') ||
                // Accept Zend_Form::isValid() only if a form with CSRF element is used
                (str_contains($method, 'isValid') && str_contains($method, 'csrf'))
            );

            $this->assertTrue(
                $hasCsrf,
                "BUG #10: $action performs an irreversible state change but has no CSRF token validation — add a CSRF check (hash_equals, token comparison, or Zend_Form with CSRF element) before processing POST data"
            );
        }
    }
}
