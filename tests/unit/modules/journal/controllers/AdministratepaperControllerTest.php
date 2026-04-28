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
    // ajaxrequestnewdoiAction — dedicated suite
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * layout()->disableLayout() and setNoRender() must be called before ANY
     * early-return guard (auth check, method check) so that every code path
     * — including error paths — emits clean JSON without an HTML layout wrapper.
     *
     * Bug (original): the two calls appeared AFTER the auth block, meaning an
     * unauthorized request received JSON + rendered layout in the same response.
     */
    public function testAjaxrequestnewdoiActionDisablesLayoutBeforeAuthCheck(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        $disablePos  = strpos($method, 'disableLayout');
        $setNoRender = strpos($method, 'setNoRender');
        $authPos     = strpos($method, 'isLogged');

        $this->assertNotFalse($disablePos,  'disableLayout() call not found in ajaxrequestnewdoiAction');
        $this->assertNotFalse($setNoRender, 'setNoRender() call not found in ajaxrequestnewdoiAction');
        $this->assertNotFalse($authPos,     'isLogged() call not found in ajaxrequestnewdoiAction');

        $this->assertLessThan(
            $authPos,
            $disablePos,
            'BUG: disableLayout() must appear before the isLogged() auth check — every response path must emit clean JSON only'
        );
        $this->assertLessThan(
            $authPos,
            $setNoRender,
            'BUG: setNoRender() must appear before the isLogged() auth check — every response path must emit clean JSON only'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * The auth failure block must contain a `return` to stop further execution.
     */
    public function testAjaxrequestnewdoiActionAuthFailureContainsReturn(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        preg_match('/if\s*\(!Episciences_Auth::isLogged\(\).*?\}/s', $method, $matches);
        $this->assertNotEmpty($matches, 'Auth check block not found in ajaxrequestnewdoiAction');

        $this->assertStringContainsString(
            'return',
            $matches[0],
            'BUG: ajaxrequestnewdoiAction auth failure block must contain a return statement'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * $docId must be read from POST only (getPost), not from the combined
     * GET+POST+routing parameter bag (getParam). Using getParam allows an
     * attacker to supply the docid via the query string on a forged POST.
     */
    public function testAjaxrequestnewdoiActionReadsDocIdFromPost(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        $this->assertStringContainsString(
            "getPost('docid')",
            $method,
            'BUG: $docId must be read via getPost() to restrict it to the POST body only'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * $docId must be cast to (int) before use so that a malformed string
     * value cannot reach PapersManager::get() or trigger unexpected behaviour.
     */
    public function testAjaxrequestnewdoiActionCastsDocIdToInt(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        preg_match_all('/\$docId\s*=\s*[^;]+;/', $method, $assignments);
        $this->assertNotEmpty($assignments[0], '$docId assignment not found in ajaxrequestnewdoiAction');

        $hasIntCast = false;
        foreach ($assignments[0] as $assignment) {
            if (str_contains($assignment, 'getPost') || str_contains($assignment, 'getParam')) {
                $hasIntCast = $hasIntCast || str_contains($assignment, '(int)');
            }
        }

        $this->assertTrue(
            $hasIntCast,
            'BUG: $docId must be cast to (int) in ajaxrequestnewdoiAction before use'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * PapersManager::get() returns false when no paper matches the given ID.
     * The method must perform an instanceof check before calling any method on
     * the result — calling canBeAssignedDOI() on false causes a fatal error.
     */
    public function testAjaxrequestnewdoiActionChecksInstanceBeforeCallingMethods(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        $this->assertTrue(
            str_contains($method, 'instanceof Episciences_Paper') ||
            str_contains($method, 'is_object($paper)') ||
            str_contains($method, '!$paper'),
            'BUG: ajaxrequestnewdoiAction must check that PapersManager::get() returned a valid Episciences_Paper instance before calling canBeAssignedDOI() — get() returns false when no paper is found'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * Every JSON response — including auth failure and validation errors —
     * must contain all four keys expected by the JavaScript handler:
     * doi, doi_status, feedback, error_message.
     * The original auth error omitted the 'feedback' key.
     */
    public function testAjaxrequestnewdoiActionAllResponsePathsIncludeFeedbackKey(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        // Find every json_encode call and check that the array literal
        // passed to it contains a 'feedback' key.
        preg_match_all('/json_encode\(\s*\[([^\]]+)\]/s', $method, $matches);
        $this->assertNotEmpty($matches[1], 'No json_encode([...]) calls found in ajaxrequestnewdoiAction');

        foreach ($matches[1] as $arrayLiteral) {
            $this->assertStringContainsString(
                "'feedback'",
                $arrayLiteral,
                "BUG: a json_encode([...]) call in ajaxrequestnewdoiAction is missing the 'feedback' key — the JavaScript handler requires all four keys in every response"
            );
        }
    }

    /**
     * @covers AdministratepaperController::ajaxrequestnewdoiAction
     *
     * The method guard must require BOTH isPost() AND isXmlHttpRequest().
     * Using '&&' (bail only if both are false) allowed plain POST requests
     * from non-XHR clients to reach the handler; '||' is required so that
     * only genuine XHR POST requests proceed.
     */
    public function testAjaxrequestnewdoiActionRequiresBothPostAndXhr(): void
    {
        $method = $this->extractMethod('ajaxrequestnewdoiAction');

        // Must NOT use the original weak '&&' guard.
        $this->assertDoesNotMatchRegularExpression(
            '/!\s*\$request->isXmlHttpRequest\(\)\s*&&\s*!\s*\$request->isPost\(\)/',
            $method,
            'BUG: ajaxrequestnewdoiAction must not use the weak && guard — use || to require both POST and XHR'
        );

        // Must use the stricter '||' guard (at least one of the two checks is present).
        $hasStrictGuard = (
            preg_match('/!\s*\$request->isPost\(\)\s*\|\|/', $method) ||
            preg_match('/!\s*\$request->isXmlHttpRequest\(\)\s*\|\|/', $method)
        );

        $this->assertTrue(
            (bool) $hasStrictGuard,
            'BUG: ajaxrequestnewdoiAction must guard with || so that both isPost() and isXmlHttpRequest() are required'
        );
    }

    // ---------------------------------------------------------------
    // savedoiAction — dedicated suite
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * disableLayout() and setNoRender() must appear before every guard so that
     * even error responses are returned as clean JSON without an HTML wrapper.
     */
    public function testSavedoiActionDisablesLayoutBeforeAuthCheck(): void
    {
        $method = $this->extractMethod('savedoiAction');

        $disablePos  = strpos($method, 'disableLayout');
        $setNoRender = strpos($method, 'setNoRender');
        $authPos     = strpos($method, 'isLogged');

        $this->assertNotFalse($disablePos,  'disableLayout() call not found in savedoiAction');
        $this->assertNotFalse($setNoRender, 'setNoRender() call not found in savedoiAction');
        $this->assertNotFalse($authPos,     'isLogged() call not found in savedoiAction');

        $this->assertLessThan(
            $authPos,
            $disablePos,
            'BUG: disableLayout() must appear before the isLogged() auth check in savedoiAction'
        );
        $this->assertLessThan(
            $authPos,
            $setNoRender,
            'BUG: setNoRender() must appear before the isLogged() auth check in savedoiAction'
        );
    }

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * The method guard must require BOTH isPost() AND isXmlHttpRequest().
     * Using '&&' allows plain POST requests from non-XHR clients through;
     * '||' is required so that only genuine XHR POST requests proceed.
     */
    public function testSavedoiActionRequiresBothPostAndXhr(): void
    {
        $method = $this->extractMethod('savedoiAction');

        // Must NOT use the weak && guard.
        $this->assertDoesNotMatchRegularExpression(
            '/!\s*\$request->isXmlHttpRequest\(\)\s*&&\s*!\s*\$request->isPost\(\)/',
            $method,
            'BUG: savedoiAction must not use the weak && guard — use || to require both POST and XHR'
        );

        $hasStrictGuard = (
            preg_match('/!\s*\$request->isPost\(\)\s*\|\|/', $method) ||
            preg_match('/!\s*\$request->isXmlHttpRequest\(\)\s*\|\|/', $method)
        );

        $this->assertTrue(
            (bool) $hasStrictGuard,
            'BUG: savedoiAction must guard with || so that both isPost() and isXmlHttpRequest() are required'
        );
    }

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * docid and paperid must be read from POST only; using getParam() also
     * reads GET and routing parameters, opening an injection surface.
     */
    public function testSavedoiActionReadsFromPostOnly(): void
    {
        $method = $this->extractMethod('savedoiAction');

        $this->assertStringNotContainsString(
            "getParam('docid')",
            $method,
            "BUG: savedoiAction must not fall back to getParam('docid') — use getPost() only"
        );

        $this->assertStringNotContainsString(
            "getParam('paperid')",
            $method,
            "BUG: savedoiAction must not fall back to getParam('paperid') — use getPost() only"
        );
    }

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * $docId and $paperId must be cast to (int) before use to prevent
     * a malformed string from reaching the database layer.
     */
    public function testSavedoiActionCastsIdsToInt(): void
    {
        $method = $this->extractMethod('savedoiAction');

        preg_match_all('/\$(docId|paperId)\s*=\s*[^;]+;/', $method, $assignments);
        $this->assertNotEmpty($assignments[0], '$docId/$paperId assignments not found in savedoiAction');

        foreach ($assignments[0] as $assignment) {
            if (str_contains($assignment, 'getPost') || str_contains($assignment, 'getParam')) {
                $this->assertStringContainsString(
                    '(int)',
                    $assignment,
                    'BUG: savedoiAction must cast $docId/$paperId to (int) — found: ' . $assignment
                );
            }
        }
    }

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * An empty DOI must be rejected with a JSON error response.
     * The original code silently passed an empty DOI through the regex check
     * (the condition was `$doi !== ''`, which allowed an empty string).
     */
    public function testSavedoiActionRejectsEmptyDoi(): void
    {
        $method = $this->extractMethod('savedoiAction');

        // Must contain an explicit empty-string check on $doi.
        $this->assertMatchesRegularExpression(
            '/\$doi\s*===\s*\'\'/',
            $method,
            "BUG: savedoiAction must explicitly reject an empty DOI with a JSON error response"
        );
    }

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * The DOI regex must use an escaped dot `10\.` so that only a literal dot
     * is accepted as the separator.  Without the escape, `10X1234/suffix`
     * would also match.
     */
    public function testSavedoiActionEscapesDotInRegex(): void
    {
        $method = $this->extractMethod('savedoiAction');

        $this->assertStringNotContainsString(
            "'/^10.\\d",
            $method,
            'BUG: savedoiAction DOI pattern uses unescaped dot — replace /^10. with /^10\. to match only a literal dot'
        );

        $this->assertStringContainsString(
            '10\\.',
            $method,
            'BUG: savedoiAction DOI pattern must escape the dot: /^10\\.\\d{4,9}/'
        );
    }

    /**
     * @covers AdministratepaperController::savedoiAction
     *
     * Every response path must return JSON ({ success, doi, error }) so that
     * the JavaScript handler can reliably parse the response.
     * The original code returned a mix of plain text and HTML div strings.
     */
    public function testSavedoiActionAllResponsePathsAreJson(): void
    {
        $method = $this->extractMethod('savedoiAction');

        // Must contain no plain `echo 'Unauthorized access'` or printf HTML.
        $this->assertStringNotContainsString(
            "echo 'Unauthorized access'",
            $method,
            'BUG: savedoiAction must return JSON, not plain text, on auth failure'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/printf\s*\(\s*[\'"]<div/',
            $method,
            'BUG: savedoiAction must return JSON responses, not raw HTML divs'
        );

        // Every response (echo json_encode call) must include the 'success' key.
        // We anchor the pattern to `echo` to exclude non-response json_encode calls
        // such as the one used to build the logger detail payload.
        preg_match_all('/echo\s+json_encode\(\s*\[([^\]]+)\]/s', $method, $matches);
        $this->assertNotEmpty($matches[1], 'No echo json_encode([...]) calls found in savedoiAction');

        foreach ($matches[1] as $arrayLiteral) {
            $this->assertStringContainsString(
                "'success'",
                $arrayLiteral,
                "BUG: an echo json_encode([...]) in savedoiAction is missing the 'success' key"
            );
        }
    }

    // ---------------------------------------------------------------
    // ajaxrequestremovedoiAction — dedicated suite
    // ---------------------------------------------------------------

    /**
     * @covers AdministratepaperController::ajaxrequestremovedoiAction
     *
     * The original action had no authentication check at all.
     * Any user — even unauthenticated — could cancel a DOI request.
     */
    public function testAjaxrequestremovedoiActionHasAuthCheck(): void
    {
        $method = $this->extractMethod('ajaxrequestremovedoiAction');

        $this->assertTrue(
            str_contains($method, 'Episciences_Auth::isLogged()') ||
            str_contains($method, 'Episciences_Auth::isAllowedToManageDoi()'),
            'BUG: ajaxrequestremovedoiAction must check that the user is logged in and allowed to manage DOIs'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestremovedoiAction
     *
     * The method guard must require BOTH isPost() AND isXmlHttpRequest().
     * The original action only checked isXmlHttpRequest(), allowing GET requests.
     */
    public function testAjaxrequestremovedoiActionRequiresBothPostAndXhr(): void
    {
        $method = $this->extractMethod('ajaxrequestremovedoiAction');

        $hasStrictGuard = (
            preg_match('/!\s*\$request->isPost\(\)\s*\|\|/', $method) ||
            preg_match('/!\s*\$request->isXmlHttpRequest\(\)\s*\|\|/', $method)
        );

        $this->assertTrue(
            (bool) $hasStrictGuard,
            'BUG: ajaxrequestremovedoiAction must guard with || to require both isPost() and isXmlHttpRequest()'
        );
    }

    /**
     * @covers AdministratepaperController::ajaxrequestremovedoiAction
     *
     * paperId and docId must be cast to (int) to prevent malformed strings
     * from reaching the database layer.
     */
    public function testAjaxrequestremovedoiActionCastsIdsToInt(): void
    {
        $method = $this->extractMethod('ajaxrequestremovedoiAction');

        preg_match_all('/\$(paperId|docId)\s*=\s*[^;]+;/', $method, $assignments);
        $this->assertNotEmpty($assignments[0], '$paperId/$docId assignments not found in ajaxrequestremovedoiAction');

        foreach ($assignments[0] as $assignment) {
            if (str_contains($assignment, 'getPost') || str_contains($assignment, 'getParam')) {
                $this->assertStringContainsString(
                    '(int)',
                    $assignment,
                    'BUG: ajaxrequestremovedoiAction must cast $paperId/$docId to (int) — found: ' . $assignment
                );
            }
        }
    }

    /**
     * @covers AdministratepaperController::ajaxrequestremovedoiAction
     *
     * Every response path must emit a JSON object so that the JavaScript
     * handler can reliably parse the outcome.
     * The original action echoed a raw integer (json_encode($update)) or
     * nothing at all on failure paths.
     */
    public function testAjaxrequestremovedoiActionAllResponsePathsAreJson(): void
    {
        $method = $this->extractMethod('ajaxrequestremovedoiAction');

        // Must not echo a plain integer or non-object scalar.
        $this->assertDoesNotMatchRegularExpression(
            '/echo\s+json_encode\s*\(\s*\$\w+\s*,/',
            $method,
            'BUG: ajaxrequestremovedoiAction must not echo a bare variable via json_encode — use an associative array response'
        );

        // Every echo json_encode call must contain the 'success' key.
        preg_match_all('/echo\s+json_encode\(\s*\[([^\]]+)\]/s', $method, $matches);
        $this->assertNotEmpty($matches[1], 'No echo json_encode([...]) calls found in ajaxrequestremovedoiAction');

        foreach ($matches[1] as $arrayLiteral) {
            $this->assertStringContainsString(
                "'success'",
                $arrayLiteral,
                "BUG: an echo json_encode([...]) in ajaxrequestremovedoiAction is missing the 'success' key"
            );
        }
    }

    /**
     * @covers AdministratepaperController::ajaxrequestremovedoiAction
     *
     * The original action used a raw Zend_Db SELECT query to check whether the
     * paper exists. PapersManager::paperExists() must be used instead to keep
     * database access behind the business logic layer.
     */
    public function testAjaxrequestremovedoiActionUsesPapersManagerForExistenceCheck(): void
    {
        $method = $this->extractMethod('ajaxrequestremovedoiAction');

        $this->assertStringContainsString(
            'Episciences_PapersManager::paperExists',
            $method,
            'BUG: ajaxrequestremovedoiAction must use PapersManager::paperExists() instead of a raw DB SELECT query'
        );

        // The original raw DB query must be gone.
        $this->assertStringNotContainsString(
            '$db->select()->from(T_PAPERS)',
            $method,
            'BUG: the raw DB SELECT query must be removed from ajaxrequestremovedoiAction in favour of PapersManager::paperExists()'
        );
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
