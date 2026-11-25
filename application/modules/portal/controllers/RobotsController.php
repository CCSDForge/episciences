<?php

require_once APPLICATION_PATH . '/modules/common/controllers/RobotsDefaultController.php';

class RobotsController extends RobotsDefaultController
{
    /**
     * The portal is never indexed, even in production.
     */
    protected function isIndexingAllowed(): bool
    {
        return false;
    }
}
