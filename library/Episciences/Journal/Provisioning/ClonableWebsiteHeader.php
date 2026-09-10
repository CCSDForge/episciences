<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use Ccsd_Website_Header;

/**
 * Ccsd_Website_Header bound to an explicit $sid instead of the ambient RVID constant.
 * See ClonableWebsiteStyle for why this is needed instead of Episciences_Website_Header.
 */
final class ClonableWebsiteHeader extends Ccsd_Website_Header
{
    public function __construct(
        int $sid,
        string $publicDir = '',
        string $publicUrl = '',
        string $layoutDir = '',
        string $langDir = ''
    ) {
        parent::__construct($sid, $publicDir, $publicUrl, $layoutDir, $langDir);
        $this->_fieldSID = 'RVID';
    }
}
