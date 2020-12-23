<?php


class Ccsd_Externdoc_Inra_ResearchReport extends Ccsd_Externdoc_Inra
{

    /**
     * @var string
     */
    public $_type = 'REPORT';

    public $array_mapping_type_report = [
      'Rapport de recherche'=>'RR',
      'Rapport de fin de contrat'=>'RC',
      'Réponse à appel d\'offre'=>'RC',
      'Rapport intermédiaire de projet'=>'RC',
      'Livrable'=>'RC',
      'Rapport annuel de projet' => 'RC',
      'Rapport d\'expertise/EsCo'=>'RC',
      'Avis'=>'AV',
      'Rapport de prospective'=>'AU',
      'Autres rapports'=>'AU',
      'Rapport d\'étude'=>'RT',
      'Compte rendu de mission'=>'AU',
      'Rapport technique'=>'RT',
      'Etat de l\'art/Analyse bibliographique'=>'EA',
      'Rapport d\'audit'=>'RA'
    ];

    public $array_mapping_type_undefined = [
        'Preprint'=>'PP',
        'Working paper'=>'WP'
    ];

    protected $defaut_reportType='RR';
    protected $defaut_reportType_undefined = 'PP';

    protected $_specific_wantedTags = [
        self::META_REPORTNUMBER,
        self::META_REPORTTYPE,
        self::META_NBPAGES_INRA,
        self::META_DATE,
        self::META_JELCODE,
        /**
        self::META_COMMANDITAIRE,
        self::META_PARTNER,
         **/
        self::META_PUBLISHER,
        self::META_REPORTNUMBER,
        /**
         * self::META_COMMENT_COLLECTION,
         *
         */
        self::META_DOCUMENTLOCATION
        ];


    /**
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra_ResearchReport
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Inra_ResearchReport($id);

        $domxpath = self::dom2xpath($xmlDom);

        $doc->setDomPath($domxpath);
        return $doc;
    }

    public function setTypDoc(){
        if ($this->getReportType()==='preprint' || $this->getReportType()==='Working paper'){
            $this->_type = 'UNDEFINED';

        }
        else $this->_type = 'REPORT';

    }




    public function getReportTypeReport()
    {
        $reportType = $this->getReportType();
        if ($this->_type === 'REPORT') {

            if (array_key_exists($reportType, $this->array_mapping_type_report)) {
                $reportType = $this->array_mapping_type_report[$reportType];
            } else {
                $reportType = $this->defaut_reportType;
            }
        }
        else if ($this->_type === 'UNDEFINED'){

            if (array_key_exists($reportType,$this->array_mapping_type_undefined)) {
                $reportType = $this->array_mapping_type_undefined[$reportType];
            }
            else {
                $reportType = $this->defaut_reportType_undefined;
            }
        }

        return $reportType;
    }


    public function getJournalInfo(){

        $issn = $this->getIssn();

        $journal2 = $this->getPublisher();
        $journal = $this->getSerie();
        $volume = $this->getVolume();

        $journalLink = $this->getJournalLink();

        $meta = '';
        if ($journal !== ''){
            $meta.= 'Ce rapport est publié dans :'.$journal.' ';
        }
        else if ($journal ==='' && $journal2!==''){
            $meta.= 'Ce rapport est disponible chez :'.$journal2.' ';
        }
        if ($issn!==''){
            $meta.= '('.$issn.') ';
        }

        if ($journalLink!==''){
            $meta.=': '.$journalLink.' ';
        }

        if ($volume !== ''){
            if ($meta !== '' ) $meta .= "\u{0A}";
            $meta.='Volume :'.$volume;
        }

        return $meta;

    }

    public function getComment()
    {
        $comment = '';
        // gestion spécifique de la concaténation de nombreux champs :
        // 1 nom du commanditaire.
        $comment.=implode(' ',$this->getFunding());
        if ($comment !== '' ){
            $comment .= "\u{0A} ------ \u{0A}";
        }
        $comment.=$this->getOrder();

        if ($comment !== '' ){
            $comment .= "\u{0A} ------ \u{0A}";
        }

        $comment.=$this->getJournalInfo();

        return $comment;

    }


    /**
     * @return array
     */
    public function getMetadatas()
    {

        $this->setTypDoc();

        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = parent::getMetadatas();




        foreach ($this->_specific_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_REPORTNUMBER :
                    $meta = $this->getReportNumber();
                    break;
                case self::META_REPORTTYPE :
                    $meta = $this->getReportTypeReport();
                    break;
                case self::META_NBPAGES_INRA :
                    $meta = $this->getNbPage();
                    break;
                case self::META_VOLUME :
                    $meta = $this->getVolume();
                    break;
                case self::META_COMMENT :
                    $meta = $this->getComment();
                    break;
     //           case self::META_PARTNER :
     //               $meta = $this->getAffiliationsPartners();
     //               break;
                case self::META_DIRECTOR :
                    $meta = $this->getDirector();
                    break;
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }


        return $this->_metas;


    }
}


Ccsd_Externdoc_Inra::registerType("/produit/record[@xsi:type='ns2:researchReport']", "Ccsd_Externdoc_Inra_ResearchReport");