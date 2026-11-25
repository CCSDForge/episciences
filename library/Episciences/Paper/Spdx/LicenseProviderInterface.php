<?php

namespace Episciences\Paper\Spdx;
interface LicenseProviderInterface
{
    public function loadSpdxCode(): ?array;
}

