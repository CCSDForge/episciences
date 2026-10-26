<?php

require_once dirname(__DIR__) . '/AclprobeDefaultController.php';

/**
 * Controller only known to Episciences_Auth_PluginTest: actions that exist without being declared in the ACL.
 */
class AclprobeController extends AclprobeDefaultController
{
    public function existingAction(): void
    {
    }

    protected function protectedAction(): void
    {
    }

    /**
     * Not dispatchable: Zend_Controller_Action::dispatch() does not see it
     */
    private function hiddenAction(): void
    {
    }
}
