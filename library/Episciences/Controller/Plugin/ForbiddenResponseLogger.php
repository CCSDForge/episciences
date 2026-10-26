<?php

declare(strict_types=1);

namespace Episciences\Controller\Plugin;

use Episciences\Security\AccessLog;
use Zend_Controller_Plugin_Abstract;
use Zend_Controller_Request_Abstract;

/**
 * Safety net of the security access log: any 401/403 response that no more specific event
 * has already recorded (ad hoc denials in the controllers) is logged once the dispatch is over.
 *
 * The responses sent with header() followed by exit() never reach dispatchLoopShutdown():
 * they are checked at the end of the script instead.
 */
final class ForbiddenResponseLogger extends Zend_Controller_Plugin_Abstract
{
    private const DENIED_CODES = [401, 403];

    private bool $checked = false;

    public function routeStartup(Zend_Controller_Request_Abstract $request): void
    {
        register_shutdown_function(function (): void {
            $this->checkExitedResponse(http_response_code());
        });
    }

    public function dispatchLoopShutdown(): void
    {
        $this->checked = true;

        if ($this->isDenyLandingPage()) {
            return;
        }

        $this->logDeniedCode($this->getResponse()->getHttpResponseCode());
    }

    /**
     * End of the script: only for a request that left the dispatch loop with exit()
     *
     * @param int|false $code the status code sent, false when none (CLI)
     */
    public function checkExitedResponse(int|false $code): void
    {
        if ($this->checked) {
            return;
        }

        $this->checked = true;

        if ($code !== false && !$this->isDenyLandingPage()) {
            $this->logDeniedCode($code);
        }
    }

    private function logDeniedCode(int $code): void
    {
        if (in_array($code, self::DENIED_CODES, true) && !AccessLog::wasDenialLogged()) {
            AccessLog::denied(AccessLog::HTTP_FORBIDDEN, '', ['status' => $code]);
        }
    }

    /**
     * error/deny is only reached by a redirection, whose denial was logged by the previous request.
     */
    private function isDenyLandingPage(): bool
    {
        $request = $this->getRequest();

        return $request->getControllerName() === 'error'
            && $request->getActionName() === 'deny';
    }
}
