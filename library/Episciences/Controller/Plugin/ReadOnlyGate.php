<?php

declare(strict_types=1);

namespace Episciences\Controller\Plugin;

use Episciences\Maintenance\ReadOnlyMode;
use Episciences_Acl;
use Episciences_Auth_Plugin;
use Zend_Acl;
use Zend_Controller_Front;
use Zend_Controller_Plugin_Abstract;
use Zend_Controller_Request_Abstract;
use Zend_Controller_Request_Http;

/**
 * Read-only maintenance mode: only public, side-effect free pages are dispatched.
 *
 * Allow-list: a page is served only if it is open to the "guest" role in the ACL (acl.ini + navigation)
 * and does not write to the database, whoever is logged in. Anything else is routed to error/readonly (HTTP 503).
 * Must be registered before Episciences_Auth_Plugin, which then checks the rewritten request.
 */
final class ReadOnlyGate extends Zend_Controller_Plugin_Abstract
{
    public const DENIED_CONTROLLER = 'error';
    public const DENIED_ACTION = 'readonly';

    /**
     * Guest resources that write to the database, even on GET (email links, CAS callback, server API).
     * Logging out stays possible: its only write (end of a switch user session) is skipped in read-only mode.
     */
    public const WRITING_GUEST_RESOURCES = [
        'user-login',
        'user-create',
        'user-altcha-challenge',
        'user-activate',
        'user-lostpassword',
        'user-lostlogin',
        'user-resetpassword',
        'user-change_account_email',
        'api-merge',
        'reviewer-invitation',
    ];

    private const ALWAYS_ALLOWED = ['error-error', 'error-readonly'];

    private const SAFE_METHODS = ['GET', 'HEAD'];

    private string $module;

    /**
     * @param Zend_Acl|null $acl defaults to the bootstrap "acl" resource
     * @param string|null $module defaults to APPLICATION_MODULE
     */
    public function __construct(private ?Zend_Acl $acl = null, ?string $module = null)
    {
        $this->module = $module ?? APPLICATION_MODULE;
    }

    public function preDispatch(Zend_Controller_Request_Abstract $request): void
    {
        // OAI-PMH is read-only and may use POST
        if (!ReadOnlyMode::isEnabled() || $this->module === OAI) {
            return;
        }

        if ($this->isLanguageSwitch($request)) {
            // The journal language switcher posts the form: answer with a GET of the same page instead,
            // the language cookie being set by Episciences_Translation_Plugin from the posted value.
            $this->getResponse()->setRedirect(self::safeRedirectUri((string) $request->getRequestUri()), 303);
            $this->deny($request);
            return;
        }

        if (!$this->isAllowed($request)) {
            $this->deny($request);
        }
    }

    private function deny(Zend_Controller_Request_Abstract $request): void
    {
        $request->setControllerName(self::DENIED_CONTROLLER);
        $request->setActionName(self::DENIED_ACTION);
    }

    private function isLanguageSwitch(Zend_Controller_Request_Abstract $request): bool
    {
        return $request instanceof Zend_Controller_Request_Http
            && $request->isPost()
            && array_keys((array) $request->getPost()) === ['lang'];
    }

    /**
     * Local path only: never redirect to another host.
     */
    private static function safeRedirectUri(string $uri): string
    {
        return str_starts_with($uri, '/') && !str_starts_with($uri, '//') && !str_contains($uri, '\\') ? $uri : '/';
    }

    private function isAllowed(Zend_Controller_Request_Abstract $request): bool
    {
        $acl = $this->getAcl();
        if ($acl === null) {
            return false;
        }

        $resource = Episciences_Auth_Plugin::resolveAclResource(
            $acl,
            (string) $request->getControllerName(),
            (string) $request->getActionName()
        );

        if (in_array($resource, self::ALWAYS_ALLOWED, true)) {
            return true;
        }

        if (!$request instanceof Zend_Controller_Request_Http
            || !in_array(strtoupper((string) $request->getMethod()), self::SAFE_METHODS, true)) {
            return false;
        }

        if (!$acl->has($resource)) {
            // Unknown resource: left to Episciences_Auth_Plugin, which never dispatches it
            return true;
        }

        return !in_array($resource, self::WRITING_GUEST_RESOURCES, true)
            && $acl->isAllowed(Episciences_Acl::ROLE_GUEST, $resource);
    }

    private function getAcl(): ?Zend_Acl
    {
        if ($this->acl === null) {
            $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
            $acl = is_object($bootstrap) && method_exists($bootstrap, 'getResource') ? $bootstrap->getResource('acl') : null;
            $this->acl = $acl instanceof Zend_Acl ? $acl : null;
        }

        return $this->acl;
    }
}
