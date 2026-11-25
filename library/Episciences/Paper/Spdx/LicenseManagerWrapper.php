<?php

namespace Episciences\Paper\Spdx;

class LicenseManagerWrapper implements LicenseProviderInterface
{

    public function loadSpdxCode(): ?array
    {
        return LicenseManager::loadSpdxCode();
    }
}