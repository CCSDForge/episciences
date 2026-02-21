<?php

namespace unit\library\Episciences;

use Episciences_Paper_ProjectsManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Paper_ProjectsManager
 * @covers Episciences_Paper_Projects_EnrichmentService
 */
final class Episciences_Paper_ProjectsManagerTest extends TestCase {

    /**
     * @dataProvider sampleOaProjects
     * @param $sampleOaProjects
     * @return void
     */
    public function testFormatFundingOAForDB($sampleOaProjects): void {
        $filterFundingsFromOaApiAndFormatForDB = Episciences_Paper_ProjectsManager::formatFundingOAForDB($sampleOaProjects,[],[]);
        self::assertIsArray($filterFundingsFromOaApiAndFormatForDB);
        self::assertCount(2,$filterFundingsFromOaApiAndFormatForDB);
        // mandatory keys

        self::assertArrayHasKey("projectTitle",$filterFundingsFromOaApiAndFormatForDB[0]);
        self::assertArrayHasKey("projectTitle",$filterFundingsFromOaApiAndFormatForDB[1]);
        self::assertArrayHasKey("funderName",$filterFundingsFromOaApiAndFormatForDB[0]);
        self::assertArrayHasKey("funderName",$filterFundingsFromOaApiAndFormatForDB[1]);
        self::assertArrayHasKey("code",$filterFundingsFromOaApiAndFormatForDB[0]);
        self::assertArrayHasKey("code",$filterFundingsFromOaApiAndFormatForDB[1]);

        // extra info if exist

        self::assertArrayNotHasKey("acronym",$filterFundingsFromOaApiAndFormatForDB[0]);
        self::assertArrayHasKey("acronym",$filterFundingsFromOaApiAndFormatForDB[1]);
        self::assertEquals("ANR-11-LABX-0010",$filterFundingsFromOaApiAndFormatForDB[1]['code']);
        self::assertEquals("DRIIHM / IRDHEI",$filterFundingsFromOaApiAndFormatForDB[1]['acronym']);
    }

    /**
     * @dataProvider sampleHalEuProjects
     * @param array $sampleHalEuProjects
     * @return void
     */

    public function testFormatFundingHalEUForDB(array $sampleHalEuProjects): void {

        $formatEuHal = Episciences_Paper_ProjectsManager::formatEuHalResp($sampleHalEuProjects);

        self::assertCount(6,$formatEuHal[0]);

        self::assertArrayHasKey('projectTitle',$formatEuHal[0]);
        self::assertEquals('NEtwork MOtion',$formatEuHal[0]['projectTitle']);

        self::assertArrayHasKey('acronym',$formatEuHal[0]);
        self::assertEquals('NEMO',$formatEuHal[0]['acronym']);

        self::assertArrayHasKey('code',$formatEuHal[0]);
        self::assertEquals('788851',$formatEuHal[0]['code']);

        self::assertArrayHasKey('callId',$formatEuHal[0]);
        self::assertEquals('ERC-2017-ADG',$formatEuHal[0]['callId']);

        self::assertArrayHasKey('projectFinancing',$formatEuHal[0]);
        self::assertEquals('ERC',$formatEuHal[0]['projectFinancing']);

        self::assertArrayHasKey('funderName',$formatEuHal[0]);
        self::assertEquals('European Commission',$formatEuHal[0]['funderName']);


    }

    /**
     * @dataProvider sampleHalAnrProjects
     * @param array $sampleHalAnrProjects
     * @return void
     */


    public function testFormatFundingHalANRForDB(array $sampleHalAnrProjects): void {
        $formatAnrHal = Episciences_Paper_ProjectsManager::formatAnrHalResp($sampleHalAnrProjects);

        self::assertCount(4,$formatAnrHal[0]);

        self::assertArrayHasKey('projectTitle',$formatAnrHal[0]);
        self::assertEquals('Du territoire au marché : histoire de l\'industrie des sports et loisirs alpins au XXe siècle',$formatAnrHal[0]['projectTitle']);

        self::assertArrayHasKey('acronym',$formatAnrHal[0]);
        self::assertEquals('TIMSA',$formatAnrHal[0]['acronym']);

        self::assertArrayHasKey('code',$formatAnrHal[0]);
        self::assertEquals('ANR-10-BLAN-2008',$formatAnrHal[0]['code']);

        self::assertArrayHasKey('funderName',$formatAnrHal[0]);
        self::assertEquals('French National Research Agency (ANR)',$formatAnrHal[0]['funderName']);

    }


    /**
     * Open Aire sample ANR funding (same thing for European)
     * @return array
     */
    public function sampleOaProjects(): array {
        return [[json_decode('{
  "0": {
    "@inferred": true,
    "@trust": "0.72",
    "@inferenceprovenance": "iis::document_referencedProjects",
    "@provenanceaction": "iis",
    "to": {
      "@class": "isProducedBy",
      "@scheme": "dnet:result_project_relations",
      "@type": "project",
      "$": "nserc_______::1e5e62235d094afd01cd56e65112fc63"
    },
    "title": {
      "$": "unidentified"
    },
    "code": {
      "$": "unidentified"
    },
    "funding": {
      "funder": {
        "@id": "nserc_______::NSERC",
        "@shortname": "NSERC",
        "@name": "Natural Sciences and Engineering Research Council of Canada",
        "@jurisdiction": "CA"
      }
    }
  },
  "1": {
    "@inferred": true,
    "@trust": "0.85",
    "@inferenceprovenance": "propagation",
    "@provenanceaction": "result:organization:instrepo",
    "to": {
      "@class": "hasAuthorInstitution",
      "@scheme": "dnet:result_organization_relations",
      "@type": "organization",
      "$": "openorgs____::c80a8243a5e5c620d7931c88d93bf17a"
    },
    "country": {
      "@classid": "FR",
      "@classname": "France",
      "@schemeid": "dnet:countries",
      "@schemename": "dnet:countries"
    },
    "legalname": {
      "$": "Université Paris Diderot"
    },
    "legalshortname": {
      "$": "Université Paris Diderot"
    }
  },
  "2": {
    "@inferred": true,
    "@trust": "0.9",
    "@inferenceprovenance": "iis::document_similarities_standard",
    "@provenanceaction": "iis",
    "to": {
      "@class": "IsAmongTopNSimilarDocuments",
      "@scheme": "dnet:result_result_relations",
      "@type": "publication",
      "$": "doi_dedup___::d7f1413afbde675d1bce14a2ecabf9b4"
    },
    "collectedfrom": {
      "0": {
        "@name": "Hyper Article en Ligne - Sciences de l\'Homme et de la Société",
        "@id": "opendoar____::96da2f590cd7246bbde0051047b0d6f7"
      },
      "1": {
        "@name": "HAL-Pasteur",
        "@id": "opendoar____::2cad8fa47bbef282badbb8de5374b894"
      },
      "2": {
        "@name": "HAL-Inserm",
        "@id": "opendoar____::d9731321ef4e063ebbee79298fa36f56"
      },
      "3": {
        "@name": "Mémoires en Sciences de l\'Information et de la Communication",
        "@id": "opendoar____::1534b76d325a8f591b52d302e7181331"
      },
      "4": {
        "@name": "HAL - UPEC / UPEM",
        "@id": "opendoar____::d3e8fc83b3e886a0dc2aa9845a5215bf"
      },
      "5": {
        "@name": "Hyper Article en Ligne",
        "@id": "opendoar____::7e7757b1e12abcb736ab9a754ffb617a"
      },
      "6": {
        "@name": "UnpayWall",
        "@id": "openaire____::8ac8380272269217cb09a928c8caa993"
      },
      "7": {
        "@name": "ORCID",
        "@id": "openaire____::806360c771262b4d6770e7cdf04b5c5a"
      },
      "8": {
        "@name": "Hal-Diderot",
        "@id": "opendoar____::18bb68e2b38e4a8ce7cf4f6b2625768c"
      },
      "9": {
        "@name": "Crossref",
        "@id": "openaire____::081b82f96300b6a6e3d282bad31cb6e2"
      },
      "10": {
        "@name": "HAL Clermont Université",
        "@id": "opendoar____::e98741479a7b998f88b8f8c9f0b6b6f1"
      },
      "11": {
        "@name": "Microsoft Academic Graph",
        "@id": "openaire____::5f532a3fc4f1ea403f37070f59a7a53a"
      }
    },
    "pid": {
      "@classid": "doi",
      "@classname": "Digital Object Identifier",
      "@schemeid": "dnet:pid_types",
      "@schemename": "dnet:pid_types",
      "$": "10.1080/11956860.2018.1542783"
    },
    "dateofacceptance": {
      "$": "2018-01-31"
    },
    "publisher": {
      "$": "HAL CCSD"
    },
    "title": {
      "@classid": "alternative title",
      "@classname": "alternative title",
      "@schemeid": "dnet:dataCite_title",
      "@schemename": "dnet:dataCite_title",
      "$": "OHMi-Nunavik: a multi-thematic and cross-cultural research program studying the cumulative effects of climate and socio-economic changes on Inuit communities"
    }
  },
  "3": {
    "@inferred": true,
    "@trust": "0.72",
    "@inferenceprovenance": "iis::document_referencedProjects",
    "@provenanceaction": "iis",
    "to": {
      "@class": "isProducedBy",
      "@scheme": "dnet:result_project_relations",
      "@type": "project",
      "$": "anr_________::9fb7cc85ed5f1c9819dd26f41e8528a7"
    },
    "code": {
      "$": "ANR-11-LABX-0010"
    },
    "acronym": {
      "$": "DRIIHM / IRDHEI"
    },
    "title": {
      "$": "Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux"
    },
    "funding": {
      "funder": {
        "@id": "anr_________::ANR",
        "@shortname": "ANR",
        "@name": "French National Research Agency (ANR)",
        "@jurisdiction": "FR"
      }
    }
  },
  "4": {
    "@inferred": true,
    "@trust": "0.9",
    "@inferenceprovenance": "iis::document_similarities_standard",
    "@provenanceaction": "iis",
    "to": {
      "@class": "IsAmongTopNSimilarDocuments",
      "@scheme": "dnet:result_result_relations",
      "@type": "otherresearchproduct",
      "$": "dedup_wf_001::ac6050cff2576c4ed3a7f774a18b70c2"
    },
    "dateofacceptance": {
      "$": "2021-09-06"
    },
    "collectedfrom": {
      "0": {
        "@name": "Hyper Article en Ligne - Sciences de l\'Homme et de la Société",
        "@id": "opendoar____::96da2f590cd7246bbde0051047b0d6f7"
      },
      "1": {
        "@name": "Mémoires en Sciences de l\'Information et de la Communication",
        "@id": "opendoar____::1534b76d325a8f591b52d302e7181331"
      },
      "2": {
        "@name": "Hal-Diderot",
        "@id": "opendoar____::18bb68e2b38e4a8ce7cf4f6b2625768c"
      },
      "3": {
        "@name": "Hyper Article en Ligne",
        "@id": "opendoar____::7e7757b1e12abcb736ab9a754ffb617a"
      },
      "4": {
        "@name": "HAL AMU",
        "@id": "opendoar____::2d2c8394e31101a261abf1784302bf75"
      }
    },
    "title": {
      "@classid": "main title",
      "@classname": "main title",
      "@schemeid": "dnet:dataCite_title",
      "@schemename": "dnet:dataCite_title",
      "@inferred": false,
      "@provenanceaction": "sysimport:crosswalk:repository",
      "@trust": "0.9",
      "$": "OHMi Patagonia - Bahia Exploradores (Chili)"
    },
    "publisher": {
      "$": "HAL CCSD"
    }
  }
}',true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]];
    }

    /**
     * Hal sample European Funding (same thing for ANR)
     * @return array
     */
    public function sampleHalEuProjects():array {
        return [[json_decode('{
    "response": {
        "numFound": 1,
        "start": 0,
        "numFoundExact": true,
        "docs": [
            {
                "projectTitle":"NEtwork MOtion",
                "acronym":"NEMO",
                "code":"788851",
                "callId":"ERC-2017-ADG",
                "projectFinancing":"ERC"
            }
        ]
    }
}',true,512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]];
    }

    /**
     * Hal sample ANR Funding (same thing for Eu)
     * @return array
     */
    public function sampleHalAnrProjects():array {
        return [[json_decode('{
    "response": {
        "numFound": 1,
        "start": 0,
        "numFoundExact": true,
        "docs": [
            {
                "projectTitle": "Du territoire au marché : histoire de l\'industrie des sports et loisirs alpins au XXe siècle",
                "acronym": "TIMSA",
                "code": "ANR-10-BLAN-2008"
            }
        ]
    }

}',true,512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]];
    }


}
