<?php

require_once __DIR__ . '/AclprobeController.php';

/**
 * A routed controller extending another one (as CommentsController extends PaperController)
 */
class AclprobechildController extends AclprobeController
{
    public function ownAction(): void
    {
    }
}
