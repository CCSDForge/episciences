<?php

/**
 * Shared parent of the fixture controllers, outside the controller directory (as DefaultController is):
 * its actions are actions of the controllers extending it.
 */
class AclprobeDefaultController extends Zend_Controller_Action
{
    public function sharedAction(): void
    {
    }
}
