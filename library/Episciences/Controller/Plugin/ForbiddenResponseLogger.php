<?php

declare(strict_types=1);

namespace Episciences\Controller\Plugin;

use Episciences\Security\AccessLog;
use Zend_Controller_Plugin_Abstract;

/**
 * Safety net of the security access log: any 401/403 response that no more specific event
 * has already recorded (ad hoc denials in the controllers) is logged once the dispatch is over.
 */
final class ForbiddenResponseLogger extends Zend_Controller_Plugin_Abstract
{
    private const DENIED_CODES = [401, 403];

    public function dispatchLoopShutdown(): void
    {
        if (AccessLog::wasLogged() || $this->isDenyLandingPage()) {
            return;
        }

        $code = $this->getResponse()->getHttpResponseCode();
        if (in_array($code, self::DENIED_CODES, true)) {
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
