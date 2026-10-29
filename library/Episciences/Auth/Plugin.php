<?php

use Episciences\Maintenance\ReadOnlyMode;
use Episciences\Security\AccessLog;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * Access rights verification for a resource
 *
 */
class Episciences_Auth_Plugin extends Ccsd_Auth_Plugin
{
    private const ACTIONS_CACHE_NAMESPACE = 'controllerActions';


    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        // Retrieve access rules
        $this->_acl = $this->getAcl();
        // Retrieve resource ID (to be modified)
        if (APPLICATION_MODULE == OAI) {
            return true;
        }
        $resource = $this->resolveResource($request->getControllerName(), $request->getActionName());
        if ($this->_acl->has($resource)) {
            // The requested resource exists
            if (!$this->isAllowed($resource)) {
                // The user cannot access the page
                if (!Episciences_Auth::isLogged()) {
                    // The user is not logged in
                    AccessLog::denied(AccessLog::AUTH_REQUIRED, '', ['resource' => $resource]);
                    $request->setParam('forward-action', $request->getActionName());
                    $request->setParam('forward-controller', $request->getControllerName());
                    $request->setControllerName('user');
                    $request->setActionName('login');
                } else {
                    AccessLog::denied(AccessLog::ACL_DENIED, '', ['resource' => $resource]);
                    $request->setControllerName(self::FAIL_AUTH_CONTROLLER);
                    $request->setActionName(self::FAIL_AUTH_ACTION);
                    $request->setParam('error_message', "Accès refusé");
                    $request->setParam('error_description', "Vous ne disposez pas des droits nécessaires pour accéder à cette page.");
                }
            } else if (Episciences_Auth::isLogged() && !ReadOnlyMode::isEnabled() && $resource !== 'user-edit' && $resource !== 'user-logout' && $resource !== 'user-photo') {
                // The user is allowed to access the resource, we check if their account is completed on Episciences
                $epiUser = new Episciences_User();
                if (!$epiUser->hasLocalData(Episciences_Auth::getUid())) {
                    // The account must be completed
                    $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
                    $redirector->gotoUrl('user/edit');
                }
            }
        } else {
            $this->denyUnknownResource($request);
        }
    }

    /**
     * The requested resource is not defined in the ACL (or is ambiguous): never let it reach the
     * dispatcher unchecked, whatever the request type.
     *
     * An existing action missing from the ACL is a page closed by mistake: it is logged as a configuration
     * error. Unknown XHR resources are logged too; regular requests to unknown pages (crawlers) are not.
     */
    public function denyUnknownResource(Zend_Controller_Request_Abstract $request): void
    {
        $isXhr = $request instanceof Zend_Controller_Request_Http && $request->isXmlHttpRequest();
        $resource = $request->getControllerName() . '-' . $request->getActionName();

        if ($this->actionExists($request)) {
            AccessLog::denied(AccessLog::ACL_MISSING, '', ['resource' => $resource]);
        } elseif ($isXhr) {
            AccessLog::denied(AccessLog::UNKNOWN_RESOURCE, '', ['resource' => $resource]);
        }

        if ($isXhr) {
            $request->setControllerName(self::FAIL_AUTH_CONTROLLER);
            $request->setActionName(self::FAIL_AUTH_ACTION);
            $request->setParam('error_message', "Accès refusé");
            $request->setParam('error_description', "Vous ne disposez pas des droits nécessaires pour accéder à cette page.");
            return;
        }

        $request->setControllerName('index');
        $request->setActionName('notfound');
    }

    /**
     * Whether the request matches a controller action that would actually be dispatched.
     */
    protected function actionExists(Zend_Controller_Request_Abstract $request): bool
    {
        try {
            $dispatcher = Zend_Controller_Front::getInstance()->getDispatcher();
            if (!$dispatcher instanceof Zend_Controller_Dispatcher_Standard || !$dispatcher->isDispatchable($request)) {
                return false;
            }

            $controllerClass = $dispatcher->getControllerClass($request);
            if (!is_string($controllerClass)) {
                return false;
            }

            $actions = $this->controllerActions($dispatcher, $controllerClass);

            return in_array(strtolower($dispatcher->getActionMethod($request)), $actions, true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Action method names of a controller, lowercase (see readActions()). Cached by controller file, so that
     * the unknown pages requested by crawlers do not load the controller and its includes on every request;
     * the entry is rebuilt when one of the files of the class hierarchy has changed.
     *
     * @return list<string>
     */
    private function controllerActions(Zend_Controller_Dispatcher_Standard $dispatcher, string $controllerClass): array
    {
        $file = $dispatcher->getDispatchDirectory() . DIRECTORY_SEPARATOR . $dispatcher->classToFilename($controllerClass);

        $cache = $this->actionsCache();
        $item = $cache?->getItem(hash('sha256', $file));
        $entry = $item?->isHit() ? $item->get() : null;
        if (is_array($entry) && is_array($entry['actions'] ?? null) && self::filesUnchanged($entry['files'] ?? null)) {
            return $entry['actions'];
        }

        $entry = self::readActions($dispatcher, $dispatcher->loadClass($controllerClass));
        if ($cache !== null && $item !== null) {
            $cache->save($item->set($entry));
        }

        return $entry['actions'];
    }

    /**
     * The actions the dispatcher would run on this controller: methods visible from Zend_Controller_Action
     * (public or protected, as get_class_methods() sees them in dispatch()), declared by the controller or
     * by its shared parents. The actions inherited from another routed controller (CommentsController
     * extends PaperController) are left out: they are only reachable under their own controller name.
     *
     * @return array{files: array<string, int|false>, actions: list<string>}
     */
    private static function readActions(Zend_Controller_Dispatcher_Standard $dispatcher, string $controllerClass): array
    {
        $files = [];
        $declaringClasses = [];
        $routedAncestorReached = false;

        for ($class = new ReflectionClass($controllerClass); $class !== false; $class = $class->getParentClass()) {
            $classFile = $class->getFileName();
            if ($classFile !== false) {
                $files[$classFile] = @filemtime($classFile);
            }

            $routedAncestorReached = $routedAncestorReached
                || ($class->getName() !== $controllerClass && self::isRoutedController($dispatcher, $class->getName()));
            if (!$routedAncestorReached) {
                $declaringClasses[] = $class->getName();
            }
        }

        $actions = [];
        foreach ((new ReflectionClass($controllerClass))->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            $name = strtolower($method->getName());
            if (str_ends_with($name, 'action') && in_array($method->getDeclaringClass()->getName(), $declaringClasses, true)) {
                $actions[] = $name;
            }
        }

        return ['files' => $files, 'actions' => $actions];
    }

    /**
     * A controller of the dispatched module, as opposed to a shared parent (DefaultController...)
     */
    private static function isRoutedController(Zend_Controller_Dispatcher_Standard $dispatcher, string $class): bool
    {
        return str_ends_with($class, 'Controller')
            && is_file($dispatcher->getDispatchDirectory() . DIRECTORY_SEPARATOR . $dispatcher->classToFilename($class));
    }

    /**
     * @param mixed $files modification times by file name, as stored by readActions()
     */
    private static function filesUnchanged(mixed $files): bool
    {
        if (!is_array($files) || $files === []) {
            return false;
        }

        foreach ($files as $file => $mtime) {
            if (@filemtime((string)$file) !== $mtime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Null when no cache directory is configured: the controller is then loaded on each lookup.
     */
    protected function actionsCache(): ?CacheItemPoolInterface
    {
        return defined('CACHE_PATH_METADATA')
            ? new PhpFilesAdapter(self::ACTIONS_CACHE_NAMESPACE, 0, CACHE_PATH_METADATA)
            : null;
    }

    /**
     * Normalize a controller or action name the way the dispatcher does before resolving the method
     * (lowercase, non-alphanumeric characters removed), so that every spelling reaching the same
     * action maps to the same string.
     */
    public static function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
    }

    /**
     * Return the ACL resource matching the requested controller/action.
     *
     * An exact match wins. Otherwise the names are compared in their dispatcher-normalized form, so that
     * a case or delimiter variant of a protected action is checked against the rules of that action.
     * When nothing matches, or when several resources match (ambiguous), the raw "controller-action" key
     * is returned (unknown resource), so that the request fails closed.
     */
    public function resolveResource(string $controller, string $action): string
    {
        return self::resolveAclResource($this->_acl, $controller, $action);
    }

    /**
     * @see resolveResource() Same resolution against a given ACL, shared with the read-only maintenance gate.
     */
    public static function resolveAclResource(Zend_Acl $acl, string $controller, string $action): string
    {
        $raw = $controller . '-' . $action;
        if ($acl->has($raw)) {
            return $raw;
        }

        $matches = [];
        foreach ($acl->getResources() as $candidate) {
            if (self::matchesRequest((string)$candidate, $controller, $action)) {
                $matches[] = (string)$candidate;
            }
        }

        return count($matches) === 1 ? $matches[0] : $raw;
    }

    /**
     * Check whether an ACL resource key ("controller-action") matches the request once both sides are
     * dispatcher-normalized. Every hyphen is tried as the controller/action boundary, because both parts
     * may themselves contain hyphens.
     */
    private static function matchesRequest(string $resource, string $controller, string $action): bool
    {
        $wantedController = self::normalizeName($controller);
        $wantedAction = self::normalizeName($action);

        $offset = 0;
        while (($pos = strpos($resource, '-', $offset)) !== false) {
            if (
                self::normalizeName(substr($resource, 0, $pos)) === $wantedController
                && self::normalizeName(substr($resource, $pos + 1)) === $wantedAction
            ) {
                return true;
            }
            $offset = $pos + 1;
        }

        return false;
    }

    public function getAcl(): ?Episciences_Acl
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('acl');
    }

    public function isAllowed($resource): bool
    {
        $allow = false;
        if ($this->_acl->has($resource)) {
            foreach (Episciences_Auth::getRoles() as $role) {
                if ($this->_acl->isAllowed($role, $resource)) {
                    $allow = true;
                    break;
                }
            }
        } else {
            $allow = true;
        }
        return $allow;
    }
}
