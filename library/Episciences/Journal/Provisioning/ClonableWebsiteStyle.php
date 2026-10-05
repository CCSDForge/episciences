<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Ccsd_Website_Style;

/**
 * Ccsd_Website_Style bound to an explicit $sid instead of the ambient RVID constant.
 *
 * Episciences_Website_Style (the class every other part of the app uses) hardcodes
 * `$this->_sid = RVID`, so it can only ever act on the journal of the current request — it
 * cannot be pointed at an arbitrary source or target journal. WebsiteCloner needs exactly that
 * (read a template's styles, write a new journal's styles in the same process), so it goes
 * through the parent Ccsd_Website_Style directly. The one thing Episciences_Website_Style adds
 * on top of the parent is `_fieldSID = 'RVID'` (WEBSITE_STYLES is keyed on RVID, not the
 * parent's default 'SID') — reproduced here, parameterized.
 */
final class ClonableWebsiteStyle extends Ccsd_Website_Style
{
    public function __construct(int $sid, string $dirname = '', string $publicUrl = '')
    {
        parent::__construct($sid, $dirname, $publicUrl);
        $this->_fieldSID = 'RVID';
    }
}
