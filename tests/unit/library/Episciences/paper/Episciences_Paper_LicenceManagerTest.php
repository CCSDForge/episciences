<?php

namespace unit\library\Episciences;

use Episciences_Paper_LicenceManager;
use PHPUnit\Framework\TestCase;


final class Episciences_Paper_LicenceManagerTest extends TestCase {
    /**
     * @return void
     */
    public function testCleanLicence()
    {

        $licenceHttp = Episciences_Paper_LicenceManager::cleanLicence("http://creativecommons.org/licenses/by/4.0/");
        self::assertEquals("https://creativecommons.org/licenses/by/4.0",$licenceHttp);
        $licenceLegalCode = Episciences_Paper_LicenceManager::cleanLicence("http://creativecommons.org/licenses/by/legalcode");
        self::assertEquals('https://creativecommons.org/licenses/by',$licenceLegalCode);
        $licenceEtalab = Episciences_Paper_LicenceManager::cleanLicence("http://hal.archives-ouvertes.fr/licences/etalab/");
        self::assertEquals("https://raw.githubusercontent.com/DISIC/politique-de-contribution-open-source/master/LICENSE",$licenceEtalab);
        $licencePublicDomain = Episciences_Paper_LicenceManager::cleanLicence("http://hal.archives-ouvertes.fr/licences/publicDomain/");
        self::assertEquals('https://creativecommons.org/publicdomain/zero/1.0',$licencePublicDomain);
        $licenceNoVersionNcSa = Episciences_Paper_LicenceManager::cleanLicence("http://creativecommons.org/licenses/by-nc-sa/");
        self::assertEquals('https://creativecommons.org/licenses/by-nc-sa/4.0',$licenceNoVersionNcSa);
        $licenceNoVersionBy = Episciences_Paper_LicenceManager::cleanLicence("http://creativecommons.org/licenses/by/");
        self::assertEquals('https://creativecommons.org/licenses/by/4.0',$licenceNoVersionBy);
    }
}
