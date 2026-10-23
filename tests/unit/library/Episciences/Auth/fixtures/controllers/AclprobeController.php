<?php

/**
 * Controller only known to Episciences_Auth_PluginTest: an action that exists without being declared in the ACL.
 */
class AclprobeController extends Zend_Controller_Action
{
    public function existingAction(): void
    {
    }
}
