<?php

declare(strict_types=1);

namespace unit\modules\support;

use Zend_Controller_Action_Helper_Redirector;

/**
 * Redirector whose exit stops the action with ResponseSent instead of ending the PHP process.
 *
 * The redirection itself (status code and Location header) is set on the response as usual.
 */
final class StoppingRedirector extends Zend_Controller_Action_Helper_Redirector
{
    public function redirectAndExit(): never
    {
        throw new ResponseSent('Redirected');
    }

    public function getName(): string
    {
        return 'Redirector';
    }
}
