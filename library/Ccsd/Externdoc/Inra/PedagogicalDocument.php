<?php


class Ccsd_Externdoc_Inra_PedagogicalDocument extends Ccsd_Externdoc_Inra
{


    /**
     * @var string
     */
    protected $_type = "LECTURE";



    protected $_specific_wantedTags = [
        self::META_PEDAGOGICALDOCUMENTTITLE,
        self::META_PEDAGOGICALDOCUMENTLEVEL,
        self::META_AUTHORITYINSTITUTION,
    ];

    const META_ARRAY_LECTURETYPE = array(
        'DEA'                   => '1',
        'École thématique'      => '2',
        '3ème cycle'            => '7',
        'École d\'ingénieur'    => '10',
        'Licence'               => '11',
        'Master'                => '12',
        'Doctorat'              => '13',
        'DEUG'                  => '14',
        'Maitrise'              => '15',
        'Licence / L1'          => '21',
        'Licence / L2'          => '22',
        'Licence / L3'          => '23',
        'Master / M1'           => '31',
        'Master / M2'           => '32',
        'Vulgarisation'         => '40',
    );

    const DEGREE_TRANSFORMATION = array(
        'Bac +1' => 'DEUG',
        'Bac +2' => 'DEUG',
        'Bac +3' => 'Licence',
        'Bac +4' => 'Maîtrise',
        'Bac +5' => 'Master',
        'Bac +6' => '3ème cycle',
    );

    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_PedagogicalDocument
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_PedagogicalDocument($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }

    /**
     * @return array
     */
    public function getMetadatas()
    {

        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = parent::getMetadatas();




        foreach ($this->_specific_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_PEDAGOGICALDOCUMENTTITLE:
                    $meta = $this->getPedagogicalDocumentTitle();
                    break;
                case self::META_PEDAGOGICALDOCUMENTLEVEL:
                    $meta = $this->getDegreeTranslationKey();
                    break;
                case self::META_AUTHORITYINSTITUTION:
                    $meta = $this->getOrganizationDegree();
                    break;
                default:
                    break;
            }

            if (!is_array($meta) && $meta === '0') {
                $this->_metas[self::META][$metakey] = $meta;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }

        return $this->_metas;


    }

    public function getDegreeTranslationKey()
    {
        $degree = $this->getDegree();
        if (!empty($degree)) {
            if (array_key_exists($degree, self::DEGREE_TRANSFORMATION)) {
                $degree = self::DEGREE_TRANSFORMATION[$degree];
            }

            if (array_key_exists($degree, self::META_ARRAY_LECTURETYPE)) {
                $key = self::META_ARRAY_LECTURETYPE[$degree];
            }
        }

        if (!isset($key) || empty($key)) {
            $key = '0';
        }

        return $key;
    }

    public function getOrganizationDegree()
    {
        $organization = '';

        $name = $this->getRecordOrganizationDegreeName();
        if (!empty($name)) {
            $organization .= $name;
        }

        $acronym = $this->getRecordOrganizationDegreeAcronym();
        if (!empty($acronym)) {
            if (!empty($organization)) {
                $organization .= ' ';
            }

            $organization .= '('.$acronym.')';
        }

        return $organization;
    }
}


Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:pedagogicalDocument']", "Ccsd_Externdoc_Inra_PedagogicalDocument");
