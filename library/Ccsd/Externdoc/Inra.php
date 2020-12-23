<?php
/**
 * Created by PhpStorm.
 * User: genicot
 * Date: 07/03/19
 * Time: 16:59
 */

use LanguageDetection\Language;
use SameerShelavale\PhpCountriesArray\CountriesArray;


class Ccsd_Externdoc_Inra extends Ccsd_Externdoc
{

    /**
     * @var string
     */
    protected $_idtype = 'doi';


    const DOC_TYPE = 'typdoc';

    protected $_dbHalV3;



    /**
     * Clé : Le XPATH qui permet de repérer la classe => Valeur : La classe à créer
     * @var array
     */
    static public $_existing_types = [];

    protected $_xmlNamespace = array('ns2'=>'http://record.prodinra.inra.fr',
                                     'xsi'=>'http://www.w3.org/2001/XMLSchema-instance');


    public static $NAMESPACE = array('ns2'=>'http://record.prodinra.inra.fr',
                                        'xsi'=>'http://www.w3.org/2001/XMLSchema-instance');
    /**
     * @var DOMXPath
     */
    protected $_domXPath = null;

    const META_SUBTITLE = 'subTitle';
    const META_COMMENT_INRA = 'inra_inraComment_local';

    // Metadata Génériques pour tous les documents INRA

    const ARRAY_MAPPING_ROLE = [];

    // Typdoc
    const META_OTHERTYPE = 'inra_otherType_Other_local';
    const META_ARTICLETYPE = 'inra_otherType_Art_local'; //articleType';
    const META_BOOKTYPE = 'inra_otherType_Ouv_local';
    const META_COMMUNICATIONTYPE = 'inra_otherType_Comm_local';
    const META_OTHERTYPE_OTHERTYPE = 'inra_otherType_Other_local';
    const META_AUDIOTYPE = 'audioType';
    const META_IMAGETYPE = 'imageType';
    const META_DEVELOPMENTTYPE_INRA = 'inra_developmentType_local';
    const META_REPORTTYPE = 'inra_reportType_local';
    const META_RESEARCHREPORTTYPE = 'inra_reportType_local';


    const META_INDEXATION = 'inra_indexation_local';

    const META_PROCEEDINGSTYPE = 'proceedingsType';

    const META_BOOKTITLE = 'bookTitle';

    const META_ALTTITLE = 'alternateTitle';
    const META_SENDING = 'hal_sending';
    const META_CLASSIFICATION = 'hal_classification';
    const META_DOCUMENTLOCATION = 'inra_lieu_local';
    const META_EXPERIMENTALUNIT = 'inra_infra_local';
    const META_ISSN = 'issn';
    const META_SOURCE = 'source';
    const META_NOSPECIAL = 'inra_noSpecial_local';
    const META_TITRESPECIAL = 'inra_titreSpecial_local';
    const META_TARGETAUDIENCE = 'inra_publicVise_local';
    const EXTAUTHORS = 'extAuthors';
    const META_EUROPEANPROJECT = 'europeanProject';
    const META_FUNDING = 'funding';
    const META_DIRECTOR = 'scientificEditor';
    const META_PEERREVIEWED = 'peerReviewing';
    const META_DOI = 'doi';
    const META_PMID = 'pubmed';
    const META_PRODINRA = 'prodinra';
    const META_JELCODE = 'jel';
    const META_WOS = 'wos';

    const META_NOARTICLE_INRA = 'inra_noArticle_local';
    const META_NBPAGES_INRA = 'inra_nbPages_local';
    const META_DEPOSIT_INRA = 'inra_deposit_local';
    const META_VALIDITY_INRA = 'inra_validity_local';
    const META_SUPPORT_INRA = 'inra_support_local';
    const META_HOSTLABORATORY_INRA = 'inra_hostLaboratory_local';
    const META_TRAININGSUPERVISOR_INRA = 'inra_trainingSupervisor_local';
    const META_DIFFUSEUR_INRA  = 'inra_diffuseur_local';
    const META_EMISSION_INRA = 'inra_diffuseur_local';

    const META_SPECIALITY_INRA = 'inra_speciality_local';
    const META_GRANT_INRA = 'inra_grant_local';
    const META_AUTHORITYINSTITUTION = 'authorityInstitution';
    const META_JURYCOMPOSITION = 'committee';


    const META_ISBN = 'isbn';
    const META_INRA_ISSN = 'inra_issn_local';
    const META_CONFLOCATION     = "conferenceLocation";
    const META_CONFISBN         = "conferenceISBN";
    const META_CONFTITLE        = "conferenceTitle";
    const META_LINK = 'publisherLink';
    const META_COORDINATOR = 'inra_coordinator_local';

    const META_REPORTNUMBER = 'number';
    const META_PATENTNUMBER = 'number';


    const META_JEL = 'jel';
    const META_BOOK_DIRECTOR = 'seriesEditor';
    const META_PUBLISHED = 'published';
    const META_PUBLISHER_NAME = 'publisher';
    const META_PUBLISHER_CITY = 'city';
    const META_PUBLISHER_COUNTRY = 'country';
    const META_PUBLICATION_LOCATION = 'publicationLocation';

    const META_BOOKAUTHOR = 'director';
    const META_DISSERTATION_DIRECTOR = 'director';

    const META_BOOKLINK = 'bookLink';
    const META_SPECIALTITLE = 'inra_titreSpecial_local';

    const META_SCALE = 'scale';
    const META_DESCRIPTION = 'description';

    const META_VOIRAUSSI = 'seeAlso';

    const META_CONFINVITE = 'invitedCommunication';
    const META_PAPERNUMBER = 'number';

    const META_DATEDEFENDED = 'date';
    const META_JURYCHAIR    = 'director';
    const META_THESISSCHOOL = 'thesisSchool';

    const META_DATESUBMISSION = 'date';

    const META_PATENTCLASSIFICATION = 'classification';

    const META_PEDAGOGICALDOCUMENTTITLE = 'lectureName';
    const META_PEDAGOGICALDOCUMENTLEVEL = 'lectureType';
    const META_DISSERTATIONLEVEL = 'lectureType';

    const META_FIRSTVERSIONYEAR = 'comment';
    const META_VERSION = 'version';
    const META_DEVELOPMENTSTATUS = 'developmentStatus';
    const META_LICENSE = 'softwareLicence';
    const META_DOCUMENTATION = 'comment';
    const META_USERINTERFACE = 'comment';
    const META_PROGRAMMINGLANGUAGE = 'programmingLanguage';
    const META_DIFFUSIONMODE = 'comment';
    const META_ENVIRONMENT = 'platform';
    const META_RELATEDSOFTWARE = 'runtimePlatform';
    const META_APPDEPOSITNUMBER = 'comment';
    const META_PREREQUISITES = 'comment';

    const META_DURATION = 'duration';

    const META_CONFORGANIZER = 'conferenceOrganizer';

    // XPATH GENERIQUES

    // Racine de l'article

    const XPATH_ROOT = '/ns2:produits/produit';

    const XPATH_ROOT_TEST = '/produit';

    const XPATH_ROOT_RECORD = '//produit/record';

        const REL_XPATH_RECORD_ID = '/identifier';
        const REL_XPATH_RECORD_TYPE = '/itemType';


    const REL_XPATH_RECORD_THEMATIC = '/thematic';
        const REL_XPATH_THEMATIC_IDENTIFIER = '/identifier';
        const REL_XPATH_THEMATIC_NAME = '/name';
        const REL_XPATH_THEMATIC_INRACLASSIFICATION = '/inraClassification';
            const REL_XPATH_INRACLASSIFICATION_INRACLASSIFICATIONIDENTIFIER = '/inraClassificationIdentifier';
            const REL_XPATH_INRACLASSIFICATION_USEDTERM = '/usedTerm';
            const REL_XPATH_INRACLASSIFICATION_ENGTERM = '/engTerm';

    //Classification Hal
    const REL_XPATH_HAL_CLASSIFICATION = '/halClassification';

        const REL_XPATH_HAL_CLASSIFICATION_CODE = '/code';
        const REL_XPATH_HAL_CLASSIFICATION_FR = '/french';
        const REL_XPATH_HAL_CLASSIFICATION_EN = '/english';

    const REL_XPATH_RECORD_HALIDENTIFIER = '/halIdentifier';


    const REL_XPATH_HOSTLABORATORY_INRALABORATORY = '/inraLaboratory';
    //Auteurs : inraAuthor ou externalAuthor
    const REL_XPATH_RECORD_AUT = "/creator/author[@xsi:type='ns2:inraAuthor']";
    const REL_ROOT_AUT = "/author[@xsi:type='ns2:inraAuthor']";
        const REL_XPATH_AUT_LASTNAMES = '/lastName';
        const REL_XPATH_AUT_FIRSTNAMES = '/firstName';
        const REL_XPATH_AUT_INITIALS = '/initial';
        const REL_XPATH_AUT_PUBLICATIONNAME = '/';
        const REL_XPATH_AUT_EMAIL = '/email';
        const REL_XPATH_AUT_ROLE = '/role';
        const REL_XPATH_AUT_INRAIDENTIFIER = '/inraIdentifier';
        const REL_XPATH_AUT_ORCID = "/personIdentifier[@type='Orcid']";
        const REL_XPATH_AUT_IDREF = "/personIdentifier[@type='IdRef']";
        const REL_XPATH_AUT_RESEARCHERID = "/personIdentifier[@type='ResearcherID']";
        const REL_XPATH_AUT_PEPS = '/peps';
        const REL_XPATH_AUT_FUNDINGDEPARTMENT = '/fundingDepartment';


        const REL_XPATH_AUT_NEUTRALAFFILIATION = '/affiliation';

    // Affiliations pour chaque auteur externe ou INRA
        const REL_XPATH_AUT_AFFILIATION = '/inraAffiliation';
            const REL_XPATH_AFFILIATION_NAME = '/name';
            const REL_XPATH_AFFILIATION_ACRONYM = '/acronym';

                //Description des affiliations en Unité
                const REL_XPATH_AFFILIATION_UNIT = '/unit';
                    const REL_XPATH_UNIT_NAME = '/name';
                    const REL_XPATH_UNIT_CODE = '/code';
                    const REL_XPATH_UNIT_TYPE = '/type';
                    const REL_XPATH_UNIT_LABORATORY = '/laboratory';
                    const REL_XPATH_UNIT_CITY = '/city';
                    const REL_XPATH_UNIT_COUNTRY = '/country';
                    const REL_XPATH_UNIT_RNSR = '/rnsr';
                    const REL_XPATH_UNIT_ACRONYM = '/acronym';
                    // En centre
                    const REL_XPATH_UNIT_CENTER = '/center';
                        const REL_XPATH_CENTER_CODE = '/code';
                        const REL_XPATH_CENTER_NAME = '/name';
                        const REL_XPATH_CENTER_ACRONYM = '/acronym';
                    // En département
                    const REL_XPATH_UNIT_DEPARTMENT = '/department';
                        const REL_XPATH_DEPARTMENT_CODE = '/code';
                        const REL_XPATH_DEPARTMENT_NAME = '/name';
                        const REL_XPATH_DEPARTMENT_ACRONYM = '/acronym';
                        const REL_XPATH_DEPARTMENT_AUTHORITYTYPE= '/authorityType';
                    // En partenaires
                    const REL_XPATH_UNIT_AFFILIATIONPARTNERS = '/affiliationPartners';
                    const REL_XPATH_AFFILIATIONPARTNERS_NAME = '/name';
                    const REL_XPATH_AFFILIATIONPARTNERS_ACRONYM = '/acronym';
                    const REL_XPATH_AFFILIATIONPARTNERS_COUNTRY = '/country';
                    const REL_XPATH_AFFILIATIONPARTNERS_IDENTIFIER = '/identifier';

        //Auteurs externes : meme chose
        const REL_XPATH_RECORD_EXTAUT = "/creator/author[@xsi:type='ns2:externalAuthor']";
        const REL_ROOT_EXTAUT = "/author[@xsi:type='ns2:externalAuthor']";
            const REL_XPATH_EXTAUT_LASTNAMES = '/lastName';
            const REL_XPATH_EXTAUT_FIRSTNAMES = '/firstName';
            const REL_XPATH_EXTAUT_INITIALS = '/initial';
            const REL_XPATH_EXTAUT_BUPLICATIONNAME ='/publicationName';
            const REL_XPATH_EXTAUT_EMAIL = '/email';
            const REL_XPATH_EXTAUT_ROLE = '/role';
            const REL_XPATH_EXTAUT_ORCID = '/orcid';

            const REL_XPATH_EXTAUT_EXTAFFILIATION = '/externalAffiliation';
                const REL_XPATH_EXTAFFILIATION_NAME = '/name';
                const REL_XPATH_EXTAFFILIATION_ID = '/identifier';
                const REL_XPATH_EXTAFFILIATION_ACRONYM = '/acronym';
                const REL_XPATH_EXTAFFILIATION_SECTION = '/section';
                const REL_XPATH_EXTAFFILIATION_CITY = '/city';
                const REL_XPATH_EXTAFFILIATION_COUNTRY = '/country';

                const REL_XPATH_EXTAFFILIATION_PARTNERS ='/partners';
                    const REL_XPATH_PARTNERS_ID = '/identifier';
                    const REL_XPATH_PARTNERS_NAME = '/name';
                    const REL_XPATH_PARTNERS_ACRONYM = '/acronym';
                    const REL_XPATH_PARTNERS_COUNTRY = '/country';

        // Informations de base sur l'article
        const REL_XPATH_RECORD_TITLE = '/title';
        const REL_XPATH_RECORD_TITLE_LANG = '/title/@language';
        const REL_XPATH_RECORD_ALTERNATETITLE = '/alternateTitle';
        const REL_XPATH_RECORD_SUBTITLE = '/subTitle';
        const REL_XPATH_RECORD_LANGUAGE = '/language';
        const REL_XPATH_RECORD_ABSTRACT = '/abstract';
        const REL_XPATH_RECORD_ABSTRACT_LANGUAGE = '/abstract/@language';
        const REL_XPATH_RECORD_YEAR = '/year';
        const REL_XPATH_RECORD_PAGES = '/pages';
        const REL_XPATH_RECORD_PAGINATION = '/pagination';
        const REL_XPATH_RECORD_NOTE = '/notes';
        const REL_XPATH_RECORD_DOI = '/doi';
        const REL_XPATH_RECORD_LINK = '/link';
        const REL_XPATH_RECORD_UTKEY = '/utKey';
        const REL_XPATH_RECORD_ACCESSCONDITION = '/recordAccessCondiion';
        const REL_XPATH_RECORD_KEYWORDS = '/keywords/keyword';
        const REL_XPATH_RECORD_TARGETAUDIENCE = '/targetAudience';
        const REL_XPATH_RECORD_CONTRACT = '/contract';
        const REL_XPATH_RECORD_EUROPEANPROJECT = '/fp';
            const REL_XPATH_EUROPEANPROJECT_GRANTNUMBER = '/grantNumber';
        const REL_XPATH_RECORD_ITEMTYPE = '/itemType';
        const REL_XPATH_RECORD_PMID = '/pmid';
        const REL_XPATH_RECORD_JELCODE = '/jelCode';
        const REL_XPATH_RECORD_HALSENDING = '/halSending';
        const REL_XPATH_RECORD_PEERREVIEWED = '/peerReviewed';

        const REL_XPATH_RECORD_TRAININGTITLE = '/trainingTitle';
        const REL_XPATH_RECORD_COURSETITLE = '/courseTitle';
        const REL_XPATH_RECORD_DEGREE = '/degree';
        const REL_XPATH_RECORD_ORGANIZATIONDEGREE = '/organizationDegree';
            const REL_XPATH_RECORD_ORGANIZATIONDEGREE_NAME                  = '/name';
            const REL_XPATH_RECORD_ORGANIZATIONDEGREE_ACRONYM               = '/acronym';
            const REL_XPATH_RECORD_ORGANIZATIONDEGREE_CITY                  = '/city';
            const REL_XPATH_RECORD_ORGANIZATIONDEGREE_SECTION               = '/section';
            const REL_XPATH_RECORD_ORGANIZATIONDEGREE_COUNTRY               = '/country';
            const REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS   = '/affiliationPartners'; //FIXME: position du noeud inconnu
                const REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_NAME      = '/name';
                const REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_ACRONYM   = '/acronym';
                const REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_COUNTRY   = '/country';
        const REL_XPATH_RECORD_AFFILIATIONPARTNERS = '/affiliationPartners'; //FIXME: position du noeud inconnu
            const REL_XPATH_RECORD_AFFILIATIONPARTNERS_NAME     = '/name';
            const REL_XPATH_RECORD_AFFILIATIONPARTNERS_ACRONYM  = '/acronym';
            const REL_XPATH_RECORD_AFFILIATIONPARTNERS_COUNTRY  = '/country';

        const REL_XPATH_RECORD_GRANT = '/grant';
        const REL_XPATH_RECORD_SPECIALITY = '/speciality';

        const REL_XPATH_RECORD_THESISDIRECTOR = '/thesisDirector';
        const REL_XPATH_RECORD_JURYCOMPOSITION = '/juryComposition';
        const REL_XPATH_RECORD_GRADUATESCHOOL = '/graduateSchool';


        const REL_XPATH_RECORD_DISSERTATIONTYPE = '/dissertationType';
        const REL_XPATH_RECORD_DISSERTATIONDIRECTOR = '/dissertationDirector';
        const REL_XPATH_RECORD_INTERNSHIPSUPERVISOR = '/internshipSupervisor';
        const REL_XPATH_RECORD_DEFENSEDATE = '/defenseDate';

        const REL_XPATH_RECORD_JURYCHAIR = '/juryChair';

        const REL_XPATH_RECORD_RESEARCHREPORTTYPE = '/researchReportType';

        const REL_XPATH_RECORD_ORDER = '/order';
            const REL_XPATH_ORDER_CONTRACTNUMBER = '/contractNumber';
            const REL_XPATH_ORDER_FUNDING = '/funding';
            const REL_XPATH_ORDER_SUPERVISOR = '/supervisor';
            const REL_XPATH_ORDER_BACKER_IDENTIFIER = '/backer/identifier';

        const REL_XPATH_RECORD_REPORTDIRECTOR = '/reportDirector';
        const REL_XPATH_RECORD_REPORTNUMBER = '/reportNumber';
        const REL_XPATH_RECORD_OTHERREPORTTYPE = '/otherReportType';

        const REL_XPATH_RECORD_ASSIGNEE = '/assignee';
        const REL_XPATH_RECORD_SUBMISSIONDATE = '/submissionDate';
        const REL_XPATH_RECORD_PATENTNUMBER = '/patentNumber';

        const REL_XPATH_RECORD_CLASSIFICATION = '/classification';
        const REL_XPATH_RECORD_PATENTLANDSCAPE = '/patentLandscape';

        const REL_XPATH_RECORD_AUDIOTYPE = '/audioType';
        const REL_XPATH_RECORD_BOOKTYPE = '/bookType';
        const REL_XPATH_RECORD_REPORTTYPE = '/reportType';
        const REL_XPATH_RECORD_REPORTTITLE = '/reportTitle';
        const REL_XPATH_RECORD_PROCEEDINGPAPERTYPE = '/paperProceedingsType';
        const REL_XPATH_RECORD_PAPERTYPE = '/paperType';


        const REL_XPATH_RECORD_DURATION = '/duration';
        const REL_XPATH_RECORD_MEDIA = '/media';

        const REL_XPATH_RECORD_ATTACHEDDOCUMENTS = '/attachedDocuments';
        const REL_XPATH_RECORD_SCALE = '/scale';
        const REL_XPATH_RECORD_SIZE = '/size';
        const REL_XPATH_RECORD_GEOGRAPHICSCOPE = '/geographicScope';
        const REL_XPATH_RECORD_RIGHTS = '/rights';

        const REL_XPATH_RECORD_FIRSTVERSIONYEAR ='/firstVersionYear';
        const REL_XPATH_RECORD_VERSIONNUMBER ='/versionNumber';
        const REL_XPATH_RECORD_MATURITY = '/maturity';
        const REL_XPATH_RECORD_LICENSE = '/license';
        const REL_XPATH_RECORD_DOCUMENTATION = '/documentation';
        const REL_XPATH_RECORD_USERINTERFACE = '/userInterface';
        const REL_XPATH_RECORD_PROGRAMLANGUAGE = '/programLanguage';
        const REL_XPATH_RECORD_DIFFUSIONMODE = '/diffusionMode';
        const REL_XPATH_RECORD_ENVIRONMENT = '/environment';
        const REL_XPATH_RECORD_RELATEDSOFTWARE = '/relatedSoftware';
        const REL_XPATH_RECORD_RELATEDSOFTWARELINK = '/relatedSoftwareLink';
        const REL_XPATH_RECORD_APPDEPOSITNUMBER = '/appDepositNumber';
        const REL_XPATH_RECORD_PREREQUISITES = '/prerequisites';

        const REL_XPATH_RECORD_SOFTWARETYPE = '/softwareType';

        const REL_XPATH_RECORD_PROCEEDINGSTYPE = '/proceedingsType';
        const REL_XPATH_RECORD_PROCEEDINGSTITLE = '/proceedingsTitle';
        const REL_XPATH_RECORD_INVITEDCONFERENCE = '/invitedConference';
        const REL_XPATH_RECORD_PAPERNUMBER = '/paperNumber';

        const REL_XPATH_RECORD_HOSTLABORATORY = '/hostLaboratory';
            const REL_XPATH_HOSTLABORATORY = '/inraLaboratory';

        const REL_XPATH_RECORD_BOOKAUTHOR = '/bookAuthor';








        // Information de collection
        const REL_XPATH_RECORD_COLLECTION = '/collection';
            const REL_XPATH_COLLECTION_ID = '/idCollection';
            const REL_XPATH_COLLECTION_TITLE = '/title';
            const REL_XPATH_COLLECTION_SHORTTITLE = '/shortTitle';
            const REL_XPATH_COLLECTION_ISSN = '/issn';
            const REL_XPATH_COLLECTION_OPENACCESS = '/openAccess';
            const REL_XPATH_COLLECTION_ISSUE_NUMBER = '/issue/number';
            const REL_XPATH_COLLECTION_ISSUE_VOLUME = '/issue/volume';
            const REL_XPATH_COLLECTION_ISSUE_DIRECTOR = '/issue/directorIssue';
            const REL_XPATH_COLLECTION_JOURNALLINK = '/journalLink';
            const REL_XPATH_COLLECTION_SPECIALTITRE = '/issue/specialIssue/title';
            const REL_XPATH_COLLECTION_SPECIALTYPE = '/issue/specialIssue/type';
            const REL_XPATH_COLLECTION_DIRECTORISSUE = '/issue/directorIssue';
            const REL_XPATH_COLLECTION_ARTICLEAUTHOR = '/articleAuthor';

        const REL_XPATH_RECORD_EXPERIMENTALUNIT = '/experimentalUnit';

    // informations sur le book
        const REL_XPATH_RECORD_BOOKINFOS = '/bookInfos';
            const REL_XPATH_BOOKINFOS_TITLE = '/title';
            const REL_XPATH_BOOKINFOS_SUBTITLE = '/subtitle';
            const REL_XPATH_BOOKINFOS_BOOKAUTHOR = '/bookAuthor';
            const REL_XPATH_BOOKINFOS_BOOKDIRECTOR = '/bookDirector';
            const REL_XPATH_BOOKINFOS_BOOKLINK = '/bookLink';
            const REL_XPATH_BOOKINFOS_CHAPTERAUTHOR = '/chapterAuthor';
            const REL_XPATH_BOOKINFOS_CHAPTERTYPE = '/chapterType';
            const REL_XPATH_BOOKINFOS_PAGES = '/pages';
            const REL_XPATH_BOOKINFOS_PAGINATION = '/pagination';

    //informations sur l'article dans sa publication
        const REL_XPATH_RECORD_ARTICLEINFOS = '/articleInfos';
            const REL_XPATH_ARTICLEINFOS_TYPE = '/articleType';
            const REL_XPATH_ARTICLEINFOS_NUMBER = '/articleNumber';
            const REL_XPATH_ARTICLEINFOS_PEERREVIEWED = '/peerReviewed';
            const REL_XPATH_ARTICLEINFOS_PAGINATION = '/pagination';

    //informations sur le titre de l'ouvrage
        const REL_XPATH_RECORD_BOOKTITLEINFOS = '/bookTitleInfos';
            const REL_XPATH_BOOKTITLEINFOS_TITLE = '/title';
            const REL_XPATH_BOOKTITLEINFOS_SUBTITLE = '/subtitle';
            const REL_XPATH_BOOKTITLEINFOS_BOOKLINK = '/bookLink';
            const REL_XPATH_BOOKTITLEINFOS_CHAPTERAUTHOR = '/chapterAuthor';
            const REL_XPATH_BOOKTITLEINFOS_CHAPTERTYPE = '/chapterType';
            const REL_XPATH_BOOKTITLEINFOS_PAGINATION = '/pagination';
            const REL_XPATH_BOOKTITLEINFOS_PAGES = '/pages';

    //informations sur la localisation geographique

        const REL_XPATH_RECORD_DOCUMENTLOCATION = '/documentLocation';
            const REL_XPATH_DOCUMENTLOCATION_NAME = '/libraryName';
            const REL_XPATH_DOCUMENTLOCATION_COTE = '/libraryClassificationMark';
            const REL_XPATH_DOCUMENTLOCATION_UNIT = '/libraryUnit';
            const REL_XPATH_DOCUMENTLOCATION_CENTER = '/libraryCenter';

    //informations sur un évènement
        const REL_XPATH_RECORD_EVENT = '/event';
            const REL_XPATH_EVENT_ORGANIZER = '/organizer';
                const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION = '/inraAffiliation';
                    const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_NAME    = '/name';
                    const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_ACRONYM = '/acronym';
                    const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT    = '/unit';
                        const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT_NAME = '/name';
                        const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT_CODE = '/code';
                        const REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT_TYPE = '/type';
                const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION = '/externalAffiliation';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_NAME                = '/name';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_ACRONYM             = '/acronym';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_SECTION             = '/section';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_RNSR                = '/rnsr';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_CITY                = '/city';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_COUNTRY             = '/country';
                    const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS = '/affiliationPartners';
                        const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS_NAME    = '/name';
                        const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS_ACRONYM = '/acronym';
                        const REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS_COUNTRY = '/country';
            const REL_XPATH_EVENT_NAME = '/name';



    //informations sur le lieu de l'event
            const REL_XPATH_EVENT_MEETING = '/meeting';
                const REL_XPATH_MEETING_COUNTRY = '/country';
                const REL_XPATH_MEETING_CITY = '/city';
                const REL_XPATH_MEETING_STARTDATE = '/period/startDate';
                const REL_XPATH_MEETING_ENDDATE = '/period/endDate';



    // Informations sur l'editeur
        const REL_XPATH_RECORD_PUBLISHING = '/publishing';
            const REL_XPATH_PUBLISHING_PUBLISHED = '/published';
            const REL_XPATH_PUBLISHING_PUBLICATION = '/publication';
            const REL_XPATH_PUBLICATION_ID = '/idPublisher';
            const REL_XPATH_PUBLICATION_NAME = '/publisherName';
            const REL_XPATH_PUBLICATION_CITY = '/publisherCity';
            const REL_XPATH_PUBLICATION_COUNTRY = '/publisherCountry';
            const REL_XPATH_PUBLICATION_ISBN = '/isbn';
            const REL_XPATH_PUBLICATION_PAGES = '/pages';

    //informations concernant la pièce jointe (le document)
    const XPATH_ROOT_ATTACHMENT = '/produit/ns2:attachment';
        const REL_XPATH_ATTACHMENT_ACCESSCONDITION = '/accessCondition';
        const REL_XPATH_ATTACHMENT_TITLE = '/title';
        const REL_XPATH_ATTACHMENT_FILENAME = '/fileName';
        const REL_XPATH_ATTACHMENT_RIGHTS = '/rights';
        const REL_XPATH_ATTACHMENT_ORIGINAL = '/original';
        const REL_XPATH_ATTACHMENT_MIMETYPE = '/fileMimeType';
        const REL_XPATH_ATTACHMENT_ATTACHMENTID = '/attachmentId';
        const REL_XPATH_ATTACHMENT_VERSION = '/version';


    const META_COLLECTION_SHORTTITLE = 'collectionShortTitle';
    const META_VULGARISATION = 'popularLevel';

	const META_ARRAY_NOSPECIAL =
		[   'Hors-série'=>'HS',
			'Numéro spécial'=>'NS',
			'Supplément'=>'SU'
		];


	protected $_wantedTags = array(
        self::META_TITLE,
        self::META_ALTTITLE,
        self::META_SUBTITLE,
        self::META_DOMAIN,
        self::META_ABSTRACT,
        self::META_KEYWORD,
        self::META_INDEXATION,
        self::META_JOURNAL,
        self::META_ISBN,
        self::META_LANG,
        self::META_VOIRAUSSI,
        self::META_DATE,
        self::META_VOLUME,
        self::META_PAGE,
        self::META_ISSUE,
        self::META_MESH,
        self::META_CITY,
        self::META_COUNTRY,
        self::META_BOOKTITLE,
        self::META_CONFTITLE,
        self::META_CONFDATESTART,
        self::META_CONFDATEEND,
        self::META_BIBLIO,
        self::META_BIBLIO_TITLE,
        self::META_PUBLISHER,
        self::META_PUBLISHER_COUNTRY,
        self::META_PUBLISHER_CITY,
        self::META_DOCUMENTLOCATION,
        self::META_CONFLOCATION,
        self::META_CLASSIFICATION,
        self::META_SENDING,
        self::META_EXPERIMENTALUNIT,
        self::META_SOURCE,
        self::META_EUROPEANPROJECT,
        self::META_FUNDING,
        self::META_DIRECTOR,
        self::META_COMMENT,
        self::META_LINK,
        self::META_JELCODE,
        self::META_TARGETAUDIENCE,
        self::META_NOSPECIAL,
        self::META_VULGARISATION,
        self::ERROR
    );

    public function __construct($id){

        parent::__construct($id);
        $this->_dbHalV3 = Zend_Db_Table_Abstract::getDefaultAdapter();

    }


    /**
     * @param $xmlDom
     * @return DOMXPath
     */
    public static function dom2xpath($xmlDom): DOMXPath
    {
        $domxpath = new DOMXPath($xmlDom);
        foreach (self::$NAMESPACE as $key => $value) {
            $domxpath->registerNamespace($key, $value);
        }
        return $domxpath;
    }


    /**
     * @param $node
     * @return DOMXPath
     */
    public function getDomXPath($node) : DOMXPath
    {

        $dom = new DOMDocument();
        if ($node instanceof DOMNode){
            $dom->appendChild($dom->importNode($node,true));
        }
        else if ($node instanceof DOMElement) {
            $dom->appendChild($node);
        }
        return self::dom2xpath($dom);

    }

    public function gereTagInra($text){

        return str_replace(['[i]','[/i]','[b]','[/b]','[br/]'],['<em>','</em>','<strong>','</strong>',"\u{0A}"],$text);

    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordAccessCondition()
    {

        $recordAccessCondition = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ACCESSCONDITION);
        $recordAccessCondition = empty($recordAccessCondition) ? '' : $recordAccessCondition;
        return $recordAccessCondition;

    }

//Todo JB : A concaténer
    public function getCoordinator(){

        $coordinator = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKAUTHOR,null,', ');
        $coordinator = empty($coordinator) ? '' : $coordinator;
        return $coordinator;

    }


    /**
     * @return DOMNodeList|DOMNodeList[]
     */
    public function getHalSending()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HALSENDING);
    }

    /**
     * @param string        $key
     * @param string|array  $value
     */
    public function addMeta($key, $value)
    {
        if (
            (isset($key) && !empty($key))
            && (
                (isset($value) && !empty($value))
                || (!is_array($value) && '0' === $value)
            )
        ) {
            $this->_metas[self::META][$key] = $value;
        }
    }

    /**
     * @param array $keys
     */
    public function deleteMetas(array $keys)
    {
        if (isset($keys) && !empty($keys) && is_array($keys)) {
            foreach ($keys as $key) {
                unset($this->_metas[self::META][$key]);
            }
        }
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getHalDomain()
    {

        $classif = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_HAL_CLASSIFICATION.self::REL_XPATH_HAL_CLASSIFICATION_CODE);
        if (is_array($classif)){
            $classif = $this->selectDomain($classif);
        }
        else {
           $classif = $this->selectDomain([$classif]);
        }


        $classif = empty($classif) ?  ['sdv'] : $classif;
        return $classif;
    }

    public function selectDomain(array $classif)
    {
        $array_return = [];
        if (!empty($classif)){
            foreach ($classif as $key => $item) {
                if (!empty($item)) {
                    $item = strtolower(str_replace([':','_'], ['.','-'], $item));
                    $item = $this->testDomain($item);
                    if ($item!=='') {
                        $array_return[] = $this->testDomain($item);
                    }
                }
            }
        }
        return $array_return;
    }

    public function testDomain($domain){

        $ep = $this->_dbHalV3->query("SELECT CODE FROM `REF_DOMAIN` WHERE CODE ='" . trim($domain) . "'");
        $array_db = $ep->fetchAll();
        if (count($array_db) === 0) {
            $array_domain = explode('.',$domain);
            array_pop($array_domain);
            if (empty($array_domain)){
                return '';
            }
            else {
                return $this->testDomain(implode('.',$array_domain));
            }
        }
        else {
            return $domain;
        }
    }

    /**
     * @param   string $key
     * @param   string $field
     * @return  string|array
     */
    public function getMetaValue($key, $field)
    {
        return $this->_metas[$key][$field];
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getIsbn()
    {
        $isbn = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PUBLISHING.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_ISBN);
        if (is_array($isbn)){
            $isbn = implode(' ',$isbn);
        }
        $isbn = trim(preg_replace('/\s+/',' ',$isbn));
        $isbn = empty($isbn) ? '' : $isbn;
        if (trim($isbn)!==''){
            $x=1;
        }
        if (strlen($isbn)>17) {
            $isbn = '';
        }
        return $isbn;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */

    public function getRawIsbn()
    {
        $isbn = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PUBLISHING.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_ISBN);
        if (is_array($isbn)){
            $isbn = implode(' ',$isbn);
        }
        $isbn = trim(preg_replace('/\s+/',' ',$isbn));
        $isbn = empty($isbn) ? '' : $isbn;
        return $isbn;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getIssn()
    {
        $issn = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_ISSN);
        if (is_array($issn)){
            foreach ($issn as $key=>$is){
                $issn[$key] = str_replace('x','X',$is);
            }
        }
        else {
            $issn = str_replace('x','X',$issn);
        }

        $issn = empty($issn) ? '' : $issn;
        return $issn;
    }

    public function getJournalLink()
    {
        $jl = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_JOURNALLINK);
        $jl = empty($jl) ? '' : $jl;
        return $jl;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getLink(){
        if (!empty($this->getJournalLink())) return $this->getJournalLink();
        if (!empty($this->getRecordLink())) return $this->getRecordLink();
        if (!empty($this->getBookLink())) return $this->getBookLink();
    }


    /**
     * @return string
     */
    public function getRecordLink()
    {
        $link = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_LINK);
        $link = empty($link) ? '' : $link;
        return $link;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getCollectionShortTitle(){
        $shortTitle = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_SHORTTITLE);
        $shortTitle = empty($shortTitle) ? '' : $shortTitle;
        return $shortTitle;
    }

    /**
     * @return string
     */

    public function getOriginalAuthor()
    {
        $oAuth = $this->getVlaue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_ARTICLEAUTHOR);
        $oAuth = empty($oAuth) ? '' : $oAuth;
        return $oAuth;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPaperNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PAPERNUMBER);
    }


    //Todo

    /**
     * @return string
     */
    public function getDate()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_YEAR);

        /**
        $monthconst = $this->getValue(self::XPATH_PUBLICATION_DATE.self::REL_XPATH_PUBLICATION_MONTH);
        $monthconst = empty($monthconst) ? '' : $monthconst;

        $dayconst = $this->getValue(self::XPATH_PUBLICATION_DATE.self::REL_XPATH_PUBLICATION_DAY);
        $dayconst = empty($dayconst) ? '' : $dayconst;

        return $this->formateDate($yearconst, $monthconst, $dayconst);
         **/
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSerie()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD . self::REL_XPATH_RECORD_COLLECTION . self::REL_XPATH_COLLECTION_TITLE);
    }

    /**
     * @return string
     */
    public function getFormattedCollection()
    {
        $support = '';

        $serie = $this->getSerie();
        if (!empty($serie)) {
            $support .= $serie;
        }

        $issn = $this->getIssn();
        if (!empty($issn)) {
            if (!empty($support)) {
                $support .= ' ';
            }

            $support .= '('.$issn.')';
        }

        return $support;
    }

    public function getDirector(){

        $director = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_DIRECTORISSUE);
        $director = empty($director) ? '' : $director;
        return $director;

    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getVolume()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_ISSUE_VOLUME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getIssue()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_ISSUE_NUMBER);
    }


    public function getAuthorityInstitution()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_GRADUATESCHOOL);
    }

    /**
     * @return string
     */
    public function getPage() : string
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_PAGINATION);
    }

    /**
     * return string
     */
    public function getNbPage() : string
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PAGES);
    }

    /**
     * @return string
     */
    public function getConferenceInvite() : string
    {
        $confInvit = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_INVITEDCONFERENCE);
        $confInvit = empty($confInvit) ? '' : $confInvit;

        if (!empty($confInvit) && $confInvit === 'true'){
            return true;
        }
        else if (!empty($confInvit) && $confInvit === 'false'){
            return false;
        }

        return $confInvit;

    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getProceedingType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PROCEEDINGSTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getProceedingTitle()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PROCEEDINGSTITLE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     * @Todo ( no information about publisher in echantillonnage )
     */
    public function getPublisher()
    {
        $publisher = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PUBLISHING.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_NAME);
        $publisher = empty($publisher) ? '' : $publisher;
        if (is_array($publisher)){
            $publisher = implode(' ',$publisher);
        }
        return $publisher;
    }

    public function getAllPublisherInfo()
    {

        $pub = $this->getPublisher();
        $pub = empty($pub) ? '' : $pub;

        $pubPlace = $this->getPubPlace();
        if (!empty($pubPlace)) {
            if (!empty($pub)) {
                $pub .= ' ';
            }

            $pub .= $pubPlace;
        }

        return $pub;
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     * @Todo ( no information about publisher in echantillonnage )
     */
    public function getPubPlace()
    {
        $pubPlace = $this->getPubPlaceCity();
        if (is_array($pubPlace)) {
            $pubPlace = implode(',', $pubPlace);
        }

        $pubPlaceCountry = $this->getPubPlaceCountry();
        if (!empty($pubPlaceCountry)) {
            if (empty($pubPlace)) {
                $pubPlace = '';
            } else {
                $pubPlace .= ' ';
            }

            $pubPlace .= '('.$pubPlaceCountry.')';
        }

        return $pubPlace;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubPlaceCity()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PUBLISHING.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_CITY);
    }

    public function getHalIdentifier()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HALIDENTIFIER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubPlaceCountry()
    {

        $pubPlaceCount = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PUBLISHING.self::REL_XPATH_PUBLISHING_PUBLICATION.self::REL_XPATH_PUBLICATION_COUNTRY);
        $pubPlaceCount = empty($pubPlaceCount)? '' : $pubPlaceCount ;


        if (!empty($pubPlaceCount)) {
            /**
             * if ($pubPlaceCountry === 'The Netherlands'){
             * $pubPlaceCountry = 'NLD';
             * }
             * if ($pubPlaceCountry === 'France' || $pubPlaceCountry === 'FR'){
             * $pubPlaceCountry = 'FRA';
             * }
             * if ($pubPlaceCountry === 'UK'){
             * $pubPlaceCountry = 'GBR';
             * }
             * if ($pubPlaceCountry === 'HR'){
             * $pubPlaceCountry = 'HRV';
             * }
             * **/
            if (!is_array($pubPlaceCount)) {
                $pubPlaceCount = [$pubPlaceCount];
            }
            $ppc = [];
            foreach ($pubPlaceCount as $pubPlaceCountry) {
                if (strlen($pubPlaceCountry) > 2) {
                    if (strlen($pubPlaceCountry) === 3) {
                        $arrayCountries = CountriesArray::get('alpha3', 'alpha2');
                    } else {
                        $arrayCountries = CountriesArray::get('name', 'alpha2');
                    }
                    foreach ($arrayCountries as $key => $country) {
                        if (is_array($pubPlaceCountry)) {
                            foreach ($pubPlaceCountry as $keypub => $pub) {
                                if (strtolower($pub) === strtolower($key)) {
                                    $pubPlaceCountry[$keypub] = strtolower($country);
                                }
                            }
                        } else {
                            if (strtolower($pubPlaceCountry) === strtolower($key)) {
                                $pubPlaceCountry = strtolower($country);
                                break;
                            }
                        }
                    }
                } else {
                    $pubPlaceCountry = strtolower($pubPlaceCountry);
                }
                $ppc[] = $pubPlaceCountry;
            }

            /**
             * if ($pubPlaceCountry === 'INT'){
             * $pubPlaceCountry = '';
             * }
             **/
        }
        else {
            $ppc = [''];
        }



        return $ppc[0];
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPublished()
    {
        $isbn = $this->getRawIsbn();
        if (!empty($isbn)) {
            return '1' ;
        } else {
            return '0';
        }
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     *
     */
    public function getKeywords()
    {

        $langArray=['fr','en'];
        $langDoc = $this->formateLang($this->getDocLang(),$this->getDocLang());
        if (!in_array($langDoc,$langArray,true)){
            $langArray[] = $langDoc;
        }
        $keywords = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_KEYWORDS);
        $keywords = Ccsd_Tools::space_clean($keywords);

        if (!is_array($keywords)) {
            if (trim($keywords) !== '') {
                $keywords = [$keywords];
            } else if (trim($keywords) === '') {
                $keywords = [];
            }
        }

       $ld = new Language($langArray);
       $ld->setMaxNgrams(9000);

        $array_keywords=[];
        foreach ($keywords as $kw) {
            $kw = str_replace(chr(194).chr(160),'',$kw);
            if (!empty($kw)) {
                $lang = '' . $ld->detect($kw);
                //$lang = $this->getLanguage($kw);
                $array_keywords[$lang][] = $kw;
            }
        }

        if ($this->getHalTypology()==='THESE'){
        	$array_keywords['fr'][]='thèse';
        	$array_keywords['en'][]='these';
        }

        return $array_keywords;
    }



    public function getLanguage($text) : string
    {
        return 'en';
    }

    /**
     * @param $domUnits
     * @return array
     */
    public function getUnit($domUnits) : array
    {


        $units = [];

        foreach($domUnits as $domUnit){
            $unit=[];
            $domXpath = $this->getDomXPath($domUnit);


            $name = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_NAME, $domXpath);
            if (!empty($name)) {$unit['name'] = $name;}

            $rnsr = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_RNSR, $domXpath);
            if (!empty($rnsr)) {$unit['rnsr'] = $rnsr;}

            $laboratory = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_LABORATORY, $domXpath);
            if (!empty($laboratory)) {$unit['laboratory'] = $laboratory;}

            $type = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_TYPE, $domXpath);
            if (!empty($type)) {$unit['type'] = $type;}

            $code = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_CODE, $domXpath);
            if (!empty($code)) {$unit['code'] = $code;}

            $country = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_COUNTRY, $domXpath);
            if (!empty($country)) {$unit['country'] = $country;}

            $city = $this->getValue(self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_CITY, $domXpath);
            if (!empty($city)) {$unit['city'] = $city;}

            $domDepartments = $domXpath->query(self::REL_XPATH_AFFILIATION_UNIT . self::REL_XPATH_UNIT_DEPARTMENT);
            $departments = $this->getDepartments($domDepartments);
            if (!empty($departments)) {$unit['departments'] = $departments;}


            $domPartners = $domXpath->query(self::REL_XPATH_AFFILIATION_UNIT . self::REL_XPATH_UNIT_AFFILIATIONPARTNERS);
            $affiliationsPartners = $this->getAffiliationsPartners($domPartners);
            if (!empty($affiliationsPartners)) {$unit['affiliationPartners'] = $affiliationsPartners;}

            if (!empty($unit)) {$units[] = $unit;}

        }

        return $units;

    }

    /**
     * @param $domPartners
     * @return array
     */
    public function getAffiliationsPartners($domPartners) : array
    {

        $affiliationsPartners = [];

        foreach ($domPartners as $domPartner){

            $affiliationPartner = [];

            $domXpath = $this->getDomXPath($domPartner);

            $name = $this->getValue(self::REL_XPATH_AFFILIATIONPARTNERS_NAME,$domXpath);
            if (!empty($name)) $affiliationPartner['name'] = $name;

            $acronym = $this->getValue(self::REL_XPATH_AFFILIATIONPARTNERS_ACRONYM,$domXpath);
            if (!empty($acronym)) $affiliationPartner['acronym'] = $acronym;

            if (!empty($affiliationPartner)) $affiliationsPartners[]=$affiliationPartner;

        }

        return $affiliationsPartners;

    }

    /**
     * @param $domDepartments
     * @return array
     */
    public function getDepartments($domDepartments) : array
    {

        $departments = [];

        foreach ($domDepartments as $domDepartment) {

            $department = [];

            $domXpath = $this->getDomXPath($domDepartment);

            $code = $this->getValue(self::REL_XPATH_UNIT_DEPARTMENT.self::REL_XPATH_DEPARTMENT_CODE, $domXpath);
            if (!empty($code)) $department['code']=$code;


            $name = $this->getValue(self::REL_XPATH_UNIT_DEPARTMENT.self::REL_XPATH_DEPARTMENT_NAME,$domXpath);
            if (!empty($name)) $department['name'] = $name;

            $acronym = $this->getValue(self::REL_XPATH_UNIT_DEPARTMENT.self::REL_XPATH_DEPARTMENT_NAME,$domXpath);
            if (!empty($acronym)) $department['acronym'] = $acronym;

            $authType = $this->getValue(self::REL_XPATH_UNIT_DEPARTMENT.self::REL_XPATH_DEPARTMENT_AUTHORITYTYPE,$domXpath);
            if (!empty($authType)) $department['authorityType'] = $authType;

            if (!empty($department)) $departments[]=$department;

        }

        return $departments;
    }


    /**
     * @param $arrayAffiliations
     * @return array
     */
    public function getInraAffiliation($arrayAffiliations) : array
    {

        $affiliations = [];

        foreach($arrayAffiliations as $affiliationNode ){

            $affiliation = [];
            $domXpath = $this->getDomXPath($affiliationNode);

            $name = $this->getValue(self::REL_XPATH_AUT_AFFILIATION.self::REL_XPATH_AFFILIATION_NAME,$domXpath);
            if (!empty($name)) {$affiliation['name'] = $name;}

            $acronym = $this->getValue(self::REL_XPATH_AUT_AFFILIATION.self::REL_XPATH_AFFILIATION_ACRONYM,$domXpath);
            if (!empty($acronym)) {$affiliation['acronym'] = $acronym;}


            $unitNode = $domXpath->query(self::REL_XPATH_AUT_AFFILIATION.self::REL_XPATH_AFFILIATION_UNIT);
            $unit = $this->getUnit($unitNode);
            if (!empty($unit)) {$affiliation['unit'] = $unit;}

            if (!empty($affiliation)) {$affiliations[]=$affiliation;}

        }

        return $affiliations;
    }

    /**
     * @param DOMNodeList $arrayExtAffiliations
     * @return array
     */
    public function getExtAffiliation($arrayExtAffiliations) : array
    {

        $affiliations = [];

        foreach($arrayExtAffiliations as $affiliationNode ){

            $affiliation = [];
            $domXpath = $this->getDomXPath($affiliationNode);

            $name = $this->getValue(self::REL_XPATH_EXTAUT_EXTAFFILIATION.self::REL_XPATH_EXTAFFILIATION_NAME,$domXpath);
            if (!empty($name)) {$affiliation['name'] = $name;}

            $acronym = $this->getValue(self::REL_XPATH_AUT_AFFILIATION.self::REL_XPATH_EXTAFFILIATION_ACRONYM,$domXpath);
            if (!empty($acronym)) {$affiliation['acronym'] = $acronym;}

            $city = $this->getValue(self::REL_XPATH_EXTAUT_EXTAFFILIATION.self::REL_XPATH_EXTAFFILIATION_CITY,$domXpath);
            if (!empty($city)) {$affiliation['city'] = $city;}

            $country = $this->getValue(self::REL_XPATH_EXTAUT_EXTAFFILIATION.self::REL_XPATH_EXTAFFILIATION_COUNTRY,$domXpath);
            if (!empty($country)) {$affiliation['country'] = $country;}

            $id = $this->getValue(self::REL_XPATH_EXTAUT_EXTAFFILIATION.self::REL_XPATH_EXTAFFILIATION_ID,$domXpath);
            if (!empty($id)){
                {$affiliation['identifier'] = $id;}
            }

            $partners = $this->getValue(self::REL_XPATH_EXTAUT_EXTAFFILIATION.self::REL_XPATH_EXTAFFILIATION_PARTNERS,$domXpath);
            if (!empty($partners)) {$affiliation['partners'] = $partners;}

            $section = $this->getValue(self::REL_XPATH_EXTAUT_EXTAFFILIATION.self::REL_XPATH_EXTAFFILIATION_SECTION,$domXpath);
            if (!empty($section)) {$affiliation['section'] = $section;}

            if (!empty($affiliation)) {$affiliations[]=$affiliation;}

        }

        return $affiliations;
    }

    /**
     * @return string
     */
    public function getSeriesEditor() : string
    {
        return '';
    }

    /**
     * Métadonnées spécifiques VOCINRA
     **/
    public function getIndexation()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_THEMATIC.self::REL_XPATH_THEMATIC_INRACLASSIFICATION.self::REL_XPATH_INRACLASSIFICATION_USEDTERM);
    }


    /**
     * @return string
     */
    public function getTypeSpecial() : string
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_SPECIALTYPE);
    }

    /**
     * @return string
     */
    public function getTitreSpecial() : string
    {
        $titreSpecial = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COLLECTION.self::REL_XPATH_COLLECTION_SPECIALTITRE);
        $titreSpecial = $this->gereTagInra($titreSpecial);
        $titreSpecial = empty($titreSpecial) ? '' : $titreSpecial;
        return $titreSpecial;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getNoArticle()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_NUMBER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticleNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_NUMBER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticlePeerReviewed()
    {
        $peerReviewed = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_PEERREVIEWED);

        return ($peerReviewed === 'false') ? '0' : '1';
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticlePagination()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_PAGINATION);
    }

    /**
     * @return string
     */
    public function getRecordPagination()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PAGINATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDocumentLocation(){

        $biblio = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOCUMENTLOCATION.self::REL_XPATH_DOCUMENTLOCATION_NAME);
        $biblio = empty($biblio)? '':$biblio;

        $cote = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOCUMENTLOCATION.self::REL_XPATH_DOCUMENTLOCATION_COTE);
        $cote = empty($cote)? '' : $cote;

        $unite = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOCUMENTLOCATION.self::REL_XPATH_DOCUMENTLOCATION_UNIT);
        $unite = empty($unite)? '' : $unite;

        $center = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOCUMENTLOCATION.self::REL_XPATH_DOCUMENTLOCATION_CENTER);
        $center = empty($center) ? '' : $center;

        if (is_array($biblio)){
            $biblio = implode(' ',$biblio);
        }
        if (is_array($cote)){
            $cote = implode(' ',$cote);
        }
        if (is_array($unite)){
            $unite = implode(' ',$unite);
        }
        if (is_array($center)){
            $center = implode(' ',$center);
        }


        $retour = empty($biblio)? '': $biblio;
        if (!empty($retour)) {
            $retour = empty($unite) ? $retour : $retour . ', ' . $unite;
        }
        else {
            $retour = empty($unite) ? '' : $unite;
        }
        if (!empty($retour)){
            $retour = empty($center) ? $retour : $retour .', '. $center;
        }
        else {
            $retour = empty($center) ? '' : $center;
        }
        if (!empty($retour)) {
            $retour = empty($cote) ? $retour : $retour . ' (' . $cote.')';
        }
        else {
            $retour = empty($cote) ? '' : '('.$cote.')';
        }


        return $retour;

    }

    /**
     * @return array|DOMNodeList|DOMNodeList[]
     */
    public function getExperimentalUnit(){

        $experimentalUnit = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXPERIMENTALUNIT);
        if (is_string($experimentalUnit) && !empty($experimentalUnit)) {
            $experimentalUnit = [$experimentalUnit];
        }
        $experimentalUnit = empty($experimentalUnit) ? [] : $experimentalUnit;
        return $experimentalUnit;


    }

    /**
     * @return bool
     */
    public function getVulgarisation(){

        $vulgarisation = trim($this->getTargetAudience());
        if ($vulgarisation==='Grand public' || $vulgarisation==='Pouvoirs publics' || $vulgarisation==='Autre' ) return '1';
        else return '0';

    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getTargetAudience(){

        $targetAudience = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TARGETAUDIENCE);
        $targetAudience = empty($targetAudience) ? '' : $targetAudience;

        if (is_array($targetAudience)){
            $targetAudience=$targetAudience[0];
        }


        If ($targetAudience === 'Professionnel'){
            $targetAudience = 'TE';
        }
        else if ($targetAudience === 'Grand public'){
            $targetAudience = 'GP';
        }
        else if ($targetAudience === 'Pouvoirs publics'){
            $targetAudience = 'PP';
        }
        else if ($targetAudience === 'Autre'){
            $targetAudience = 'AU';
        }
        else if ($targetAudience === 'Etudiants'){
            $targetAudience = 'ET';
        }
        else if ($targetAudience === 'Scientifique'){
            $targetAudience = 'SC';
        }
        else {
            $targetAudience = '0';
        }


        return $targetAudience;

    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDeposit(){
        $deposit = $this->getValue();
        $deposit = empty($deposit) ? '' : $deposit;
        return $deposit;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getValidity(){

        $validity = $this->getValue();
        $validity = empty($validity) ? '' : $validity;
        return $validity;
    }



    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getHostLaboratory(){

        $inraLaboratory = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_HOSTLABORATORY_INRALABORATORY);
        $inraLaboratory = empty($inraLaboratory) ? '' : $inraLaboratory;



        $acronym = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_ACRONYM);
        $acronym = empty($acronym) ? '' : $acronym;

        $acronympartner = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_AFFILIATIONPARTNERS.self::REL_XPATH_AFFILIATION_ACRONYM);
        $acronympartner = empty($acronympartner) ? '' : $acronympartner;

        $unittype = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_TYPE);
        $unittype = empty($unittype) ? '' : $unittype;

        $unitcode = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_CODE);
        $unitcode = empty($unitcode) ? '' : $unitcode;

        $unitacronym = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_ACRONYM);
        $unitacronym = empty($unitacronym) ? '' : $unitacronym;

        $unitname = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_NAME);
        $unitname = empty($unitname) ? '' : $unitname;

        $centername = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_CENTER.self::REL_XPATH_CENTER_NAME);
        $centername = empty($centername) ? '' : $centername;

        $unitcity = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_CITY);
        $unitcity = empty($unitcity) ? '' : $unitcity;

        $unitcountry = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_HOSTLABORATORY.self::REL_XPATH_AUT_NEUTRALAFFILIATION.self::REL_XPATH_AFFILIATION_UNIT.self::REL_XPATH_UNIT_COUNTRY);
        $unitcountry = empty($unitcountry) ? '' : $unitcountry;

        $array = ['acronym','acronympartner','unittype','unitacronym','unitname','centername','unitcity','unitcountry'];

        foreach($array as $namevar){
            if (is_array($$namevar)){
                $$namevar = implode(' ',$$namevar);
            }
        }

            $hostLaboratory = $acronym.' - '.$acronympartner.', '.$unittype.' '.$unitcode.' '.$unitacronym.' '.$unitname.'. '.$centername.', '.$unitcity.', '.$unitcountry.'.';


        return $hostLaboratory;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getExtLaboratory(){
        $extLaboratory = $this->getValue();
        $extLaboratory = empty($extLaboratory) ? '' : $extLaboratory;
        return $extLaboratory;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSupport(){

        $support = $this->getValue();
        $support = empty($support) ? '' : $support;
        return $support;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getReportType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_REPORTTYPE);
    }

    public function getReportTitle($defaultLang='en'){

        $children= $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_REPORTTITLE);

        $array = [];
        if (isset($children)){
            foreach ($children as $blocTitle){
                $title = Ccsd_Tools::space_clean($blocTitle->nodeValue);
                $lang = Ccsd_Tools::space_clean($blocTitle->getAttribute('language'));
                $docLang = $this->getDocLang();


                $lang = $this->formateLang($lang,$docLang,$defaultLang);

                $array[$lang] = $title;
            }
        }

        $arrayAltTitle = $this->getAltTitle();
        foreach($arrayAltTitle as $key=>$value){
            $array[$key]=$value;
        }


        return $array;

    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getTrainingSupervisor(){
        $supervisor = $this->getValue();
        $supervisor = empty($supervisor) ? '' : $supervisor;
        return $supervisor;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDiffuseur(){
        $diffuseur = $this->getValue();
        $diffuseur = empty($diffuseur) ? '' : $diffuseur;
        return $diffuseur;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEmission(){


        $emission = $this->getValue();
        $emission = empty($emission) ? '' : $emission;
        return $emission;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDevelopmentType(){

        $developmentType = $this->getValue();
        $developmentType = empty($developmentType) ? '' : $developmentType;
        return $developmentType;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getInraComment(){

        $inraComment = $this->getValue();
        $inraComment = empty($inraComment) ? '' : $inraComment;
        return $inraComment;
    }

    /**
     * Fin Métadonnées spécifiques INRA
     */



    /**
     * @param $interMetas
     * @param $internames
     * @return array
     */
    public function getAuthors() : array
    {

        $authors = [];


        $nodeAuthors = $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT);

        foreach ($nodeAuthors as $node ){

            $author = [];
            $domxpathAut = $this->getDomXPath($node);

            $firstNames = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_FIRSTNAMES,$domxpathAut);
            if (!empty($firstNames)){
                $author['firstName'] = $firstNames;
            }

            $lastNames = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_LASTNAMES,$domxpathAut);
            if (!empty($lastNames)){
                $author['lastName'] = $lastNames;
            }

            $initials = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_INITIALS,$domxpathAut);
            if (!empty($initials)){
            	$author['initial'] = $initials;
            }

            $affiliationsNode = $domxpathAut->query(self::REL_ROOT_AUT.self::REL_XPATH_AUT_AFFILIATION);
            $affiliations = $this->getInraAffiliation($affiliationsNode);
            if (!empty($affiliations)){
                $author['affiliation'] = $affiliations;
            }

            $extAffiliationsNode = $domxpathAut->query(self::REL_ROOT_AUT.self::REL_XPATH_EXTAUT_EXTAFFILIATION);
            $extAffiliations = $this->getExtAffiliation($extAffiliationsNode);
            if (!empty($extAffiliations)){
                $author['affiliation externe'] = $extAffiliations;
            }

            $orcIds = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_ORCID,$domxpathAut);
            if (!empty($orcIds)){
                $author['orcid'] = $orcIds;
            }

            $idref = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_IDREF,$domxpathAut);
            if (!empty($idref)){
            	$author['idref']=$idref;
            }

            $researcherId = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_RESEARCHERID,$domxpathAut);
            if (!empty($researcherId)){
            	$author['researcherId']=$researcherId;
            }

            $emails = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_EMAIL,$domxpathAut);
            if (!empty($emails)){
                $author['email'] = $emails;
            }

            $role = $this->getValue(self::REL_ROOT_AUT.self::REL_XPATH_AUT_ROLE,$domxpathAut);
            if (!empty($role)){
            	$author['role'] = $role;
            }

            if (!empty($author)){
                $authors[]=$author;
            }

        }


        return $authors;
        /**
        $fullNames = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT);
        $fullNames = is_array($fullNames) ? $fullNames : [$fullNames];

        $firstNames = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT.self::REL_XPATH_AUT_FIRSTNAMES);
        $firstNames = is_array($firstNames) ? $firstNames : [$firstNames];

        $lastNames = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT.self::REL_XPATH_AUT_LASTNAMES);
        $lastNames = is_array($lastNames) ? $lastNames : [$lastNames];

        $affiliations = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT.self::REL_XPATH_AUT_AFFILIATION);
        $affiliations = is_array($affiliations) ? $affiliations : [$affiliations];

        $orcids = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT.self::REL_XPATH_AUT_ORCID);
        $orcids = is_array($orcids) ? $orcids : [$orcids];

        $emails = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT.self::REL_XPATH_AUT_EMAIL);
        $emails = is_array($emails) ? $emails : [$emails];

        return $this->formateAuthors($fullNames, $firstNames, $lastNames, $affiliations, $orcids,$emails);
         * **/
    }

    /**
     * @return array
     */
    public function getExtAuthors() : array
    {

        $authors = [];

        $nodeAuthors = $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXTAUT);

        foreach ($nodeAuthors as $node ){

            $author = [];
            $domxpathAut = $this->getDomXPath($node);

            $firstNames = $this->getValue(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_FIRSTNAMES,$domxpathAut);
            if (!empty($firstNames)) $author['firstName'] = $firstNames;

            $lastNames = $this->getValue(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_LASTNAMES,$domxpathAut);
            if (!empty($lastNames)) $author['lastName'] = $lastNames;

	        $initials = $this->getValue(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_INITIALS,$domxpathAut);
	        if (!empty($initials)){
		        $author['initial'] = $initials;
	        }

            $extaffiliationsNode = $domxpathAut->query(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_EXTAFFILIATION);
            $extaffiliations = $this->getExtAffiliation($extaffiliationsNode);
            if (!empty($extaffiliations)) $author['affiliation externe'] = $extaffiliations;

            $orcids = $this->getValue(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_ORCID,$domxpathAut);
            if (!empty($orcids)) $author['orcid'] = $orcids;

            $emails = $this->getValue(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_EMAIL,$domxpathAut);
            if (!empty($emails)) $author['email'] = $emails;

	        $role = $this->getValue(self::REL_ROOT_EXTAUT.self::REL_XPATH_EXTAUT_ROLE,$domxpathAut);
	        if (!empty($role)){
		        $author['role'] = $role;
	        }

            if (!empty($author)) $authors[]=$author;

        }


        return $authors;
        /**

        $fullNames = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXTAUT);
        $fullNames = is_array($fullNames) ? $fullNames : [$fullNames];

        $firstNames = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXTAUT.self::REL_XPATH_EXTAUT_FIRSTNAMES);
        $firstNames = is_array($firstNames) ? $firstNames : [$firstNames];

        $lastNames = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXTAUT.self::REL_XPATH_EXTAUT_LASTNAMES);
        $lastNames = is_array($lastNames) ? $lastNames : [$lastNames];

        $affiliations = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXTAUT.self::REL_XPATH_EXTAUT_EXTAFFILIATION);
        $affiliations = is_array($affiliations) ? $affiliations : [$affiliations];

        $orcids = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EXTAUT.self::REL_XPATH_EXTAUT_ORCID);
        $orcids = is_array($orcids) ? $orcids : [$orcids];

        $emails = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUT.self::REL_XPATH_AUT_EMAIL);
        $emails = is_array($emails) ? $emails : [$emails];

        return $this->formateAuthors($fullNames, $firstNames, $lastNames, $affiliations, $orcids,$emails);
         **/
    }


    /**
     * @return string
     */
    public function getSource() :string
    {
        return 'Prodinra';
    }

    /**
     * Création d'un Doc INRA à partir d'un XPATH
     * L'objet INRA est seulement une factory pour un sous-type réel.
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return Ccsd_Externdoc_Inra | NULL
     */
    public static function createFromXML($id,$xmlDom)
    {
        $domxpath = self::dom2xpath($xmlDom);
        // On recherche le type de document associé au DOI à partir du XPATH de référence
        foreach (self::$_existing_types as $order => $xpath2class) {
            /**
             * @var string  $xpath
             * @var Ccsd_Externdoc $type
             */
            foreach ($xpath2class as $xpath => $type) {

                if ($domxpath->query($xpath)->count() > 0) {
                    return $type::createFromXML($id,$xmlDom);
                }
            }
        }

        return null;

    }


    /**
     * On recrée les auteurs à partir des tableaux de Noms Complet / Prénoms / Noms
     * @param $fullNames
     * @param $firstNames
     * @param $lastNames
     * @param $orcids
     * @param $emails
     * @return array
     * @deprecated fonction non utilisee afin de garder la structure déjà présente dans les documents.
     */
    protected function formateAuthors($fullNames, $firstNames, $lastNames, $affiliations = [], $orcids = [],$emails = [])
    {
        $finalAuthors = [];

        // Boucle sur chaque 'auteur'
        foreach ($fullNames as $i => $fullName) {

            foreach ($firstNames as $firstname) {
                $firstname = self::cleanFirstname($firstname);

                // Le prénom doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $firstname) !== false) {
                    $finalAuthors[$i]['firstname'] = $firstname;
                    break;
                }
            }

            foreach ($lastNames as $lastName) {
                // Le nom doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $lastName) !== false) {
                    $finalAuthors[$i]['lastname'] = $lastName;
                    break;
                }
            }

            foreach ($orcids as $orcid) {
                // L'orcid doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $orcid) !== false) {
                    $finalAuthors[$i]['orcid'] = $orcid;
                    break;
                }
            }

            foreach ($affiliations as $affiliation) {
                // L'affiliation doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $affiliation) !== false) {
                    $finalAuthors[$i]['affiliation'] = $affiliation;
                    break;
                }
            }

            foreach($emails as $email){
                // L'email doit se trouver dans l'informations complète de l'auteur
                if (strpos($fullName,$email) !== false){
                    $finalAuthors[$i]['email'] = $email;
                }
            }
        }

        return $finalAuthors;
    }


    /**
     * @param string $defaultLang
     * @return array
     */
    public function getTitle($defaultLang='en'):array
    {
        $children= $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TITLE);

        $array = [];
        if (isset($children)){
            foreach ($children as $blocTitle){
                $title = Ccsd_Tools::space_clean($blocTitle->nodeValue);

                $title = $this->gereTagInra($title);

                $lang = Ccsd_Tools::space_clean($blocTitle->getAttribute('language'));
                $docLang = $this->getDocLang();


                $lang = $this->formateLang($lang,$docLang,$defaultLang);

                $array[$lang] = $title;
            }
        }

        $arrayAltTitle = $this->getAltTitle();
        foreach($arrayAltTitle as $key=>$value){
            $array[$key]=$value;
        }


        return $array;
        /**
        $title = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TITLE);
        $title = empty($title) ? '' : $title;

        $lang = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TITLE.self::REL_XPATH_RECORD_TITLE_LANG);

        // Transformation du titre en tableau avec la clé comme langue
        return $this->metasToLangArray($title, $defaultLang);
         * */
    }


    /**
     * @return array
     */
    public function getAltTitle():array
    {
        $children = $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ALTERNATETITLE);

        $array = [];
        if (isset($children)){
            foreach ($children as $blocTitle){
                $title = Ccsd_Tools::space_clean($blocTitle->nodeValue);
                if ($title !=='') {
                    $lang = Ccsd_Tools::space_clean($blocTitle->getAttribute('language'));

                    $title = $this->gereTagInra($title);

                    $docLang = $this->getDocLang();


                    $lang = $this->formateLang($lang, $docLang);

                    $array[$lang] = $title;
                }
            }
        }

        return $array;
    }




    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSubtitle()
    {
        $subtitle = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SUBTITLE);
        $subtitle = $this->gereTagInra($subtitle);
        $subtitle = empty($subtitle) ? '' : $subtitle;
        return $subtitle;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]
     */
    public function getIdentifier()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ID);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getUtKey()
    {
        $utkey = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_UTKEY);
        $utkey = empty($utkey) ? '' : $utkey;

        if ($utkey !==''){
            $x=1;
        }

        return $utkey;
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDOI()
    {
        $doi = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOI);
        $doi = empty($doi) ? '' : $doi;

        $doi = explode(' ',$doi);

        $doi = $doi[0];
        if (strlen($doi) > 100) {
            $doi = substr($doi,0,99);
        }
        return $doi;
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDocLang()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_LANGUAGE);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getAbstract()
    {

        $children= $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ABSTRACT);
        $array = [];
        if (isset($children)){
            foreach ($children as $blocTitle){
                $abstract = Ccsd_Tools::space_clean($blocTitle->nodeValue);
                $abstract = $this->gereTagInra($abstract);
                $lang = Ccsd_Tools::space_clean($blocTitle->getAttribute('language'));
                $docLang = $this->getDocLang();


                $lang = $this->formateLang($lang,$docLang);

                $array[$lang] = $abstract;
            }
        }

        return $array;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPubmedId()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PMID);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */

    public function getJelCode()
    {
        $jelCode = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_JELCODE);
        $array_jl =[];
        if (is_array($jelCode)){
            foreach($jelCode as $value){
                if (!empty(trim($value))){
                    $inter_array = explode('/',$value);
                    $index = count($inter_array) - 1 ;
                    $code = trim(explode('-',$inter_array[$index])[0]);
                    $array_jl[]=$code;
                }
            }
        }
        else if (!empty(trim($jelCode))){
            $code = trim(explode('-',$jelCode)[0]);
            $array_jl[]=$code;

        }

        foreach ($array_jl as $key=>$jl){
            if (strlen($jl) === 2 ){
                $jcode =''.$jl[0].'.'.$jl[0].$jl[1];
                $array_jl[$key] = $jcode;
            }
            else if (strlen($jl) === 3){
                $jcode =''.$jl[0].'.'.$jl[0].$jl[1].'.'.$jl[0].$jl[1].$jl[2];
                $array_jl[$key] = $jcode;
            }

            $array_jl[$key] = str_replace('.D92','',$array_jl[$key]);

        }

        return $array_jl;
    }

    /**
     * @param $xpath
     * @param $type
     * @param $order
     */
    public static function registerType($xpath, $type, $order = 50)
    {
        self::$_existing_types[$order][$xpath] = $type;
        // Il faut trier suivant l'ordre car PHP ne tri pas numeriquement par defaut
        ksort(self::$_existing_types);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getComment(){

        $note = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_NOTE);
        if (is_array($note)) {
            $note = implode($note);
        }
        $note = $this->gereTagInra($note);
        $note = empty($note) ? '' : $note;
        return $note;

    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEuropeanProject(){



        $ep = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EUROPEANPROJECT.self::REL_XPATH_EUROPEANPROJECT_GRANTNUMBER);
        $ep = empty($ep) ? '' : $ep;

        if (!empty($ep)){

            $ep = $this->_dbHalV3->query("SELECT DISTINCT PROJEUROPID FROM `REF_PROJEUROP` WHERE NUMERO ='".$ep."' and VALID = 'VALID'");
            $array_db = $ep->fetchAll();
            if (count($array_db)>0){
                $ep = new Ccsd_Referentiels_Europeanproject($array_db[0]['PROJEUROPID']);
            }
            else{
                //echo 'Pas de projet europeen trouvé avec le GrantNumber '.$ep ;
                $ep='';
            }
        }
        else {
            $ep ='';
        }
        return $ep;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getFunding(){
        $contract = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_CONTRACT);
        $contract = empty($contract) ? [] : $contract;
        if (!is_array($contract)) $contract = [$contract];
        return $contract;
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getTrainingTitle()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_TRAININGTITLE);
        $meta = $this->gereTagInra($meta);
        $meta = empty($meta) ? '' : $meta;

        return $meta;
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getCourseTitle()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_COURSETITLE);
        $meta = $this->gereTagInra($meta);
        $meta = empty($meta) ? '' : $meta;
        return $meta;
    }

    public function getPedagogicalDocumentTitle()
    {
        $title = '';

        $trainingTitle = trim($this->getTrainingTitle());
        if (!empty($trainingTitle)) {
            $title .= $trainingTitle;
        }

        $courseTitle = trim($this->getCourseTitle());
        if (!empty($courseTitle)) {
            if (!empty($title)) {
                $title .= ' ';
            }

            $title .= '('.$courseTitle.')';
        }

        return $title;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDegree()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DEGREE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|array
     */
    public function getRecordOrganizationsDegree()
    {
        $organizationsDegree = array();

        $nodesOrganizationDegree = $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE);
        foreach ($nodesOrganizationDegree as $nodeOrganizationDegree) {
            $organizationDegree = array();

            $domXPath = $this->getDomXPath($nodeOrganizationDegree);

            $section                = $this->getRecordOrganizationDegreeSection($domXPath);
            $name                   = $this->getRecordOrganizationDegreeName($domXPath);
            $acronym                = $this->getRecordOrganizationDegreeAcronym($domXPath);
            $city                   = $this->getRecordOrganizationDegreeCity($domXPath);
            $country                = $this->getRecordOrganizationDegreeCountry($domXPath);
            $affiliationPartners    = $this->getRecordOrganizationDegreeAffiliationPartners($domXPath);
            if (
                (isset($section) && !empty($section))
                || (isset($name)                && !empty($name))
                || (isset($acronym)             && !empty($acronym))
                || (isset($city)                && !empty($city))
                || (isset($country)             && !empty($country))
                || (isset($affiliationPartners) && !empty($affiliationPartners))
            ) {
                $organizationDegree['section']              = $section;
                $organizationDegree['name']                 = $name;
                $organizationDegree['acronym']              = $acronym;
                $organizationDegree['city']                 = $city;
                $organizationDegree['country']              = $country;
                $organizationDegree['affiliation_partners'] = $affiliationPartners;
            }

            if (!empty($organizationDegree)) {
                $organizationsDegree[] = $organizationDegree;
            }
        }

        return $organizationsDegree;
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeName($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_NAME;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_NAME;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeAcronym($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_ACRONYM;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_ACRONYM;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeCity($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_CITY;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_CITY;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeSection($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_SECTION;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_SECTION;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeCountry($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_COUNTRY;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_COUNTRY;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param DOMXPath $domXPath
     *
     * @return array
     */
    public function getRecordOrganizationDegreeAffiliationPartners($domXPath)
    {
        $affiliationPartners = array();

        if (isset($domXPath) && !empty($domXPath) && $domXPath instanceof \DOMXPath) {
            $nodesAffiliationPartners = $domXPath->query(self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS);
            foreach ($nodesAffiliationPartners as $nodeAffiliationPartners) {
                $domXPathAffiliationPartners = $this->getDomXPath($nodeAffiliationPartners);

                $affiliationPartner = $this->formatAffiliationPartnerToArray(
                    $this->getRecordOrganizationDegreeAffiliationPartnersName($domXPathAffiliationPartners),
                    $this->getRecordOrganizationDegreeAffiliationPartnersAcronym($domXPathAffiliationPartners),
                    $this->getRecordOrganizationDegreeAffiliationPartnersCountry($domXPathAffiliationPartners)
                );

                if (!empty($affiliationPartner)) {
                    $affiliationPartners[] = $affiliationPartner;
                }
            }
        }

        return $affiliationPartners;
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeAffiliationPartnersName($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_NAME;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_NAME;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeAffiliationPartnersAcronym($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_ACRONYM;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_ACRONYM;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordOrganizationDegreeAffiliationPartnersCountry($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_COUNTRY;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_ORGANIZATIONDEGREE_AFFILIATIONPARTNERS_COUNTRY;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @return array
     */
    public function getRecordAffiliationPartners()
    {
        $affiliationPartners = array();

        $nodesAffiliationPartners = $this->getDomPath()->query(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS);
        foreach ($nodesAffiliationPartners as $nodeAffiliationPartners) {
            $domXPath = $this->getDomXPath($nodeAffiliationPartners);

            $affiliationPartner = $this->formatAffiliationPartnerToArray(
                $this->getRecordAffiliationPartnersName($domXPath),
                $this->getRecordAffiliationPartnersAcronym($domXPath),
                $this->getRecordAffiliationPartnersCountry($domXPath)
            );

            if (!empty($affiliationPartner)) {
                $affiliationPartners[] = $affiliationPartner;
            }
        }

        return $affiliationPartners;
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordAffiliationPartnersName($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS_NAME;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS_NAME;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordAffiliationPartnersAcronym($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS_ACRONYM;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS_ACRONYM;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param  $domXPath
     *
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordAffiliationPartnersCountry($domXPath = null)
    {
        $path = self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS_COUNTRY;

        if (isset($domXPath) && !empty($domXPath)) {
            $path = self::REL_XPATH_RECORD_AFFILIATIONPARTNERS.self::REL_XPATH_RECORD_AFFILIATIONPARTNERS_COUNTRY;
        }

        return $this->getValue($path, $domXPath);
    }

    /**
     * @param string $name
     * @param string $acronym
     * @param string $country
     *
     * @return array
     */
    public function formatAffiliationPartnerToArray(string $name, string $acronym, string $country)
    {
        $affiliationPartner= array();

        if (
            (isset($name) && !empty($name))
            || (isset($acronym) && !empty($acronym))
            || (isset($country) && !empty($country))
        ) {
            $affiliationPartner['name']     = $name;
            $affiliationPartner['acronym']  = $acronym;
            $affiliationPartner['country']  = $country;
        }

        return $affiliationPartner;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getGrant()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_GRANT);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSpeciality()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SPECIALITY);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getThesisDirector()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_THESISDIRECTOR);
        $meta = empty($meta) ? '' : $meta;
        return $meta;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getJuryComposition()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_JURYCOMPOSITION);
    }

    /** Création du Referentiel Journal
     * @return Ccsd_Referentiels_Journal
     */
    public function getJournal()
    {

        $issn = $this->getIssn();

        $fulltitle = $this->getSerie();

        $eissn = $issn;

        $abbrevtitle = $this->getCollectionShortTitle();

        return $this->formateJournal($fulltitle, $abbrevtitle, $issn, $eissn);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getGraduateSchool()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_GRADUATESCHOOL);
        $meta = empty($meta) ? '' : $meta;
        return $meta;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDissertationType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DISSERTATIONTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDissertationDirector()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DISSERTATIONDIRECTOR);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getInternshipSupervisor()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_INTERNSHIPSUPERVISOR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDefenseDate()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DEFENSEDATE);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getJuryChair()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_JURYCHAIR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getResearchReportType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_RESEARCHREPORTTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getOrder()
    {
        $order = '';
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORDER.self::REL_XPATH_ORDER_CONTRACTNUMBER);
        if (is_array($meta)) {
            $meta = implode(' ',$meta);
        }
        if (trim($meta) !== ''){
            $order.="contrat : ".$meta;
        }



        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORDER.self::REL_XPATH_ORDER_FUNDING);
        if (is_array($meta)) {
            $meta = implode(' ',$meta);
        }
        if (trim($meta) !== ''){
            if (trim($order)!==''){
                $order.="\u{0A}";
            }
            $order.="financement : ".$meta;
        }


        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORDER.self::REL_XPATH_ORDER_SUPERVISOR);

        if (is_array($meta)) {
            $meta = implode(' ',$meta);
        }
        if (trim($meta) !== ''){
            if (trim($order)!==''){
                $order.="\u{0A}";
            }
            $order.="Superviseur : ".$meta;
        }

        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ORDER.self::REL_XPATH_ORDER_BACKER_IDENTIFIER);
        if (is_array($meta)) {
            $meta = implode(' ',$meta);
        }
        if (trim($meta) !== '' && trim($meta) !== '0'){
            if (trim($order)!==''){
                $order.="\u{0A}";
            }
            $order.="Autre référence : ".$meta;
        }
        if (trim($order)!==''){
            $order.="\u{0A}";
        }

        $order = empty($order) ? '' : $order;
        return $order;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getReportDirector()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_REPORTDIRECTOR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getReportNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_REPORTNUMBER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getOtherReportType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_OTHERREPORTTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getAssignee()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ASSIGNEE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSubmissionDate()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SUBMISSIONDATE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPatentNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PATENTNUMBER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getClassification()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_CLASSIFICATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPatentLandscape()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PATENTLANDSCAPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getAudioType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_AUDIOTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDuration()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DURATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getMedia()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_MEDIA);
        $meta = empty($meta) ? '' : $meta;
        return $meta;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getScale()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SCALE);
        $meta = empty($meta) ? '' : $meta;
        if (!empty($meta)){
            $meta = str_replace(['1:','1/',' '],'',$meta);
            //$meta = str_replace(' ','',$meta);
            $meta = 1 / (int) $meta;
            $meta = ''.$meta;
        }
        return $meta;
    }

    public function getDescription()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ATTACHEDDOCUMENTS);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSize()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SIZE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getGeographicScope()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_GEOGRAPHICSCOPE);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRights()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_RIGHTS);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getFirstVersionYear()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_FIRSTVERSIONYEAR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getVersionNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_VERSIONNUMBER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getMaturity()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_MATURITY);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getLicense()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_LICENSE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDocumentation()
    {
        return $this->getValue(
            self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DOCUMENTATION,
            null,
            ', '
        );
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getUserInterface()
    {
        return $this->getValue(
            self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_USERINTERFACE,
            null,
            ', '
        );
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getProgramLanguage()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PROGRAMLANGUAGE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getDiffusionMode()
    {
        return $this->getValue(
            self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_DIFFUSIONMODE,
            null,
            ', '
        );
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEnvironment()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ENVIRONMENT);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRelatedSoftware()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_RELATEDSOFTWARE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRelatedSoftwareLink()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_RELATEDSOFTWARELINK);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getAppDepositNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_APPDEPOSITNUMBER);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPrerequisites()
    {
        return $this->getValue(
            self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PREREQUISITES,
            null,
            ', '
        );
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSoftwareType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_SOFTWARETYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliation()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliationName()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_NAME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliationAcronym()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_ACRONYM);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliationUnit()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliationUnitName()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT_NAME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliationUnitCode()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT_CODE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerInraAffiliationUnitType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT.self::REL_XPATH_EVENT_ORGANIZER_INRAAFFILIATION_UNIT_TYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliation()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationName()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_NAME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationAcronym()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_ACRONYM);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationSection()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_SECTION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationRnsr()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_RNSR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationCity()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_CITY);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationCountry()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_COUNTRY);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationAffiliationPartners()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationAffiliationPartnersName()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS_NAME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationAffiliationPartnersAcronym()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS_ACRONYM);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventOrganizerExternalAffiliationAffiliationPartnersCountry()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_ORGANIZER.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS.self::REL_XPATH_EVENT_ORGANIZER_EXTERNALAFFILIATION_AFFILIATIONPARTNERS_COUNTRY);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getProceedingPaperType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PROCEEDINGPAPERTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getPaperType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_PAPERTYPE);
    }

    // Fonctions pour les ouvrages (BookInfos)

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitle()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_TITLE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookSubtitle()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_SUBTITLE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookAuthor()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_BOOKAUTHOR,null,', ');
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getRecordBookAuthor()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_BOOKINFOS_BOOKAUTHOR, null, ', ');
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookDirector()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_BOOKDIRECTOR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookLink()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_BOOKINFOS_BOOKLINK);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getChapterAuthor()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_CHAPTERAUTHOR);
    }


    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getSeeAlso()
    {
        $bookLink = $this->getBookLink();
        if (empty($bookLink)) $bookLink = [];

        $journalLink = $this->getJournalLink();
        if (empty($journalLink)) $journalLink = [];

        $recordLink = $this->getRecordLink();
        if (empty($recordLink)) $recordLink = [];

        $bookLink = is_array($bookLink) ? $bookLink : [$bookLink];
        $journalLink = is_array($journalLink) ? $journalLink : [$journalLink];
        $recordLink = is_array($recordLink) ? $recordLink : [$recordLink];

        $arrayLink = $bookLink;
        foreach ($journalLink as $jl){
            if (!in_array($jl,$arrayLink,true)){
                $arrayLink[] = $jl;
            }
        }
        foreach ($recordLink as $rl){
            if (!in_array($rl,$arrayLink,true)){
                $arrayLink[] = $rl;
            }
        }

        $arrayLink = array_unique($arrayLink);


        return $arrayLink;
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getChapterType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_CHAPTERAUTHOR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookPages()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_PAGES);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookPagination()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKINFOS.self::REL_XPATH_BOOKINFOS_PAGINATION);
    }

    //Fonction sur les articles (ArticleInfos)

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticleInfosType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_TYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticleInfosNumber()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_NUMBER);
    }

    public function getRecordPeerReviewed()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_ARTICLEINFOS_PEERREVIEWED);

        return ($meta === 'false' || $meta === 0) ? '0' : '1';
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticleInfosPeerReviewed()
    {
        $meta = $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_PEERREVIEWED);

        return ($meta === 'false' || $meta === 0) ? '0' : '1';
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getArticleInfosPagination()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_ARTICLEINFOS.self::REL_XPATH_ARTICLEINFOS_PAGINATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosTitle()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_TITLE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosSubtitle()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_SUBTITLE);
    }

    public function getBookTitleInfosTitleConcat()
    {
        $title = $this->getBookTitleInfosTitle();
        $subtitle = $this->getBookTitleInfosSubtitle();
        if (is_array($title)) $title = trim(implode(' ',$title));
        if (is_array($subtitle)) $subtitle = trim(implode(' ',$subtitle));

        if (!empty($title) && !empty($subtitle)){
            return $title.' : '.$subtitle;
        }
        else if (empty($title) && !empty($subtitle)){
            return $subtitle;
        }
        else if (!empty($title) && empty($subtitle)){
            return $title;
        }
        else{
            return '';
        }
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosBookLink()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_BOOKLINK);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosChapterAuthor()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_CHAPTERAUTHOR);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosChapterType()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_CHAPTERTYPE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosPagination()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_PAGINATION);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getBookTitleInfosPages()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_BOOKTITLEINFOS.self::REL_XPATH_BOOKTITLEINFOS_PAGES);
    }


    // fonctions pour les events

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventName()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_NAME);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventMeetingCountry()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_MEETING.self::REL_XPATH_MEETING_COUNTRY);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventMeetingCity()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_MEETING.self::REL_XPATH_MEETING_CITY);
    }

    /**
     * @return string
     */

    public function getOtherType()
    {
        return '';
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventMeetingStartDate()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_MEETING.self::REL_XPATH_MEETING_STARTDATE);
    }

    /**
     * @return DOMNodeList|DOMNodeList[]|string
     */
    public function getEventMeetingEndDate()
    {
        return $this->getValue(self::XPATH_ROOT_RECORD.self::REL_XPATH_RECORD_EVENT.self::REL_XPATH_EVENT_MEETING.self::REL_XPATH_MEETING_ENDDATE);
    }




    public function getAttachmentInfos()
    {
        //$attachment = $this->getValue(self::XPATH_ROOT_ATTACHMENT);
        $attachment = $this->getDomPath()->query(self::XPATH_ROOT_ATTACHMENT);
        $result = [];
        if (!empty($attachment)) {

            foreach ($attachment as $node) {

                $domxpathAut = $this->getDomXPath($node);

                $attachmentId = $this->getValue('/ns2:attachment' . self::REL_XPATH_ATTACHMENT_ATTACHMENTID, $domxpathAut);
                $attachmentId = empty($attachmentId) ? '' : $attachmentId;

                $fileName = $this->getValue('/ns2:attachment' . self::REL_XPATH_ATTACHMENT_FILENAME, $domxpathAut);
                $fileName = empty($fileName) ? '' : $fileName;

                $version = $this->getValue('/ns2:attachment' . self::REL_XPATH_ATTACHMENT_VERSION, $domxpathAut);
                $version = empty($version) ? '' : $version;

                $fileMimeType = $this->getValue('/ns2:attachment' . self::REL_XPATH_ATTACHMENT_MIMETYPE, $domxpathAut);
                $fileMimeType = empty($fileMimeType) ? '' : $fileMimeType;

                $original = $this->getValue('/ns2:attachment' . self::REL_XPATH_ATTACHMENT_ORIGINAL, $domxpathAut);
                $original = empty($original) ? '' : $original;

                $accessCondition = $this->getValue('/ns2:attachment' . self::REL_XPATH_ATTACHMENT_ACCESSCONDITION, $domxpathAut);
                $accessCondition = empty($accessCondition) ? '' : $accessCondition;

                $arrayValue = ['attachmentId', 'fileName', 'version', 'fileMimeType', 'original', 'accessCondition'];
                foreach ($arrayValue as $value) {
                    if (is_array($$value)) {
                        $$value = implode(' ', $$value);
                    }
                }

                $result[] = [$attachmentId, $fileName, $version, $fileMimeType, $original, $accessCondition];
            }
            return $result;
        }
        else {
            return '';
        }
    }

     /**
     * @param $value
     * @param $domxpath
     * @param $glue
     * @return DOMNodeList[]|DOMNodeList|string
     */
    protected function getValue($value, $domxpath = null, $glue = null)
    {
        if ($domxpath){
            $children = $domxpath->query($value);
        }
        else {
            $children = $this->getDomPath()->query($value);
        }


        if (isset($children)) {
            // Children : tableau de DOMElements
            // Unique élément : l'élément est une string
            if ($children->length === 1) {
                return Ccsd_Tools::space_clean($children[0]->nodeValue);
                // Multiple éléments : ajoutés dans un tableau
            } else if ($children->length > 1) {
                $values = [];
                foreach ($children as $child) {
                    $values[] = Ccsd_Tools::space_clean($child->nodeValue);
                }

                if (isset($glue)) {
                    $values = implode($glue, $values);
                }

                if (empty($values)) {
                    return '';
                }

                return $values;
            }
        }

        return '';
    }


	/** Création de la classe Ccsd_Referentiels_Journal à partir des paramètres spécifiques
	 *
	 * @param $journaltitle : string
	 * @param $shortname : string
	 * @param $issn : string
	 * @param $eissn : string
	 *
	 * @return  Ccsd_Referentiels_Journal
	 */
	protected function formateJournalOnBdd($journaltitle, $shortname, $issn, $eissn)
	{
		if ( (!isset($journaltitle) || $journaltitle == '')
			//        && (!isset($shortname) || $shortname == '')
			&& (!isset($issn) || $issn == '')
			&& (!isset($eissn) || $eissn == ''))
			return null;
		// Debug cas existant...
		if (is_array($journaltitle)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "journaltitle is an array(first: " .  $journaltitle[0] . ")");
		}
		/**
		if (is_array($shortname)) {
		Ccsd_Tools::panicMsg(__FILE__,__LINE__, "shortname is an array(first: " .  $shortname[0] . ")");
		}
		 **/
		if (is_array($issn)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "issn is an array (first: " .  $issn[0] . ")");
		}
		if (is_array($eissn)) {
			Ccsd_Tools::panicMsg(__FILE__,__LINE__, "eissn is an array (first: " .  $eissn[0] . ")");
		}

		$query = 'select TOP 1 JID from REF_JOURNAL where '."JNAME = '".$journaltitle."' OR ISSN='".$issn."' OR EISSN='".$eissn."'";
		$dbHal= Zend_Db_Table_Abstract::getDefaultAdapter();
		$result=$dbHal->query($query);
		$result=$result->fetchAll();

		if (count($result)===1){
			return new Ccsd_Referentiels_Journal($result[0]['JID']);
		}
		else {
			return new Ccsd_Referentiels_Journal(0, ['VALID' => 'INCOMING', 'JID' => '', 'JNAME' => $journaltitle, 'SHORTNAME' => $shortname, 'ISSN' => $issn, 'EISSN' => $eissn, 'PUBLISHER' => '', 'URL' => '']);
		}
	}
    /**
     *
     */
    public function getHalTypology(){
        return $this->_type;
    }

    /**
     * @param string $string
     * @param string $delimiter
     * @param array  $toReplace
     *
     * @return string|array|null
     */
    public function tryToExplodeString(string $string, string $delimiter = ';', array $toReplace = array(',', '/', '\\'))
    {
        $mixed = null;

        if (isset($string) && !empty($string) && is_string($string)) {
            $items = explode($delimiter, str_replace($toReplace, $delimiter, $string));

            $nbItems = count($items);
            if ($nbItems === 1) {
                $mixed = $items[0];
            }

            if ($nbItems > 1) {
                $mixed = array();
                foreach ($items as $item) {
                    $mixed[] = $item;
                }
            }
        }

        return Ccsd_Tools::space_clean($mixed);
    }

    public function getMetadatas()
    {

        $this->_metas[self::META] = [];
        $this->_metas[self::AUTHORS] = [];

        foreach ($this->_wantedTags as $metakey) {

            $meta = "";



            switch ($metakey) {
                case self::META_TITLE :
                    $meta = $this->getTitle();
                    break;
                case self::META_SUBTITLE :
                    $meta = $this->getSubtitle();
                    break;
                case self::META_ISBN :
                    $meta = $this->getRawIsbn();
                    break;
                case self::META_DATE :
                    $meta = $this->getDate();
                    break;
                case self::META_COMMENT :
                    $meta = $this->getComment();
                    break;
                case self::META_DIRECTOR :
                    $meta = $this->getDirector();
                    break;
                case self::META_VOLUME :
                    $meta = $this->getVolume();
                    break;
                case self::META_ISSUE :
                    $meta = $this->getIssue();
                    break;
                case self::META_LINK :
                    $meta = $this->getLink();
                    break;
                case self::META_PAGE :
                    $meta = $this->getPage();
                    break;
                case self::META_PUBLISHER :
                    $meta = $this->getPublisher();
                    break;
                case self::META_PUBLISHER_CITY :
                    $meta = $this->getPubPlaceCity();
                    break;
                case self::META_PUBLISHER_COUNTRY:
                    $meta = $this->getPubPlaceCountry();
                    break;
                case self::META_DOCUMENTLOCATION :
                    $meta = $this->getDocumentLocation();
                    break;
                case self::META_SERIESEDITOR :
                    $meta = $this->getSeriesEditor();
                    break;
                case self::META_ABSTRACT :
                    $meta = $this->getAbstract();
                    break;
                case self::META_INDEXATION :
                    $meta = $this->getIndexation();
                    break;
                case self::META_KEYWORD :
                    $meta = $this->getKeywords();
                    break;
                case self::META_DOMAIN :
                    $meta = $this->getHalDomain();
                    break;
                case self::META_SENDING:
                    $meta = $this->getHalSending();
                    break;
                case self::META_EXPERIMENTALUNIT:
                    $meta = $this->getExperimentalUnit();
                    break;
                case self::META_TARGETAUDIENCE:
                    $meta = $this->getTargetAudience();
                    break;
                case self::META_SOURCE:
                    $meta = $this->getSource();
                    break;
                case self::META_EUROPEANPROJECT:
                    $meta = $this->getEuropeanProject();
                    break;
                case self::META_FUNDING :
                    $meta = $this->getFunding();
                    break;
                case self::META_JELCODE :
                    $meta = $this->getJelCode();
                    break;
                case self::META_BOOKTITLE :
                    $meta = $this->getBookTitleInfosTitleConcat();
                    break;
                case self::META_VOIRAUSSI :
                    $meta = $this->getSeeAlso();
                    break;
	            case self::META_NOSPECIAL :
	            	$meta = $this->getTypeSpecial();
	            	break;
	            case self::META_VULGARISATION :
	            	$meta = $this->getVulgarisation();
	            	break;
                default:
                    break;
            }

            if (!is_array($meta) && $meta === '0'){
                $this->_metas[self::META][$metakey] = $meta;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }


        //suppression du lien principal des liens annexes
        //puisqu'on risque de l'avoir à plusieurs endroits dans les notices INRA
        if (isset($this->_metas[self::META][self::META_VOIRAUSSI], $this->_metas[self::META][self::META_LINK])) {
            $array_link = $this->_metas[self::META][self::META_VOIRAUSSI];
            $link = $this->_metas[self::META][self::META_LINK];
            if (in_array($link, $array_link, true)) {
                $key = array_search($link, $array_link, true);
                unset($array_link[$key]);
                $this->_metas[self::META][self::META_VOIRAUSSI] = array_values($array_link);
            }
        }

        // Récupération de la langue du premier titre
        $titleLang = isset($this->_metas[self::META][self::META_TITLE]) ? array_keys($this->_metas[self::META][self::META_TITLE])[0] : '';

        // Ajout de la langue
        $this->_metas[self::META][self::META_LANG] = $this->formateLang($this->getDocLang(), $titleLang);


        // Gestion des identifiants en tableau
        if (!empty($this->getDOI())) $this->_metas[self::META][self::META_IDENTIFIER][self::META_DOI] = $this->getDOI();
        if (!empty($this->getIdentifier())) $this->_metas[self::META][self::META_IDENTIFIER][self::META_PRODINRA] = $this->getIdentifier();
        if (!empty($this->getUtKey())) $this->_metas[self::META][self::META_IDENTIFIER][self::META_WOS] = $this->getUtKey();
        if (!empty($this->getIssn())) $this->_metas[self::META][self::META_IDENTIFIER][self::META_ISSN] = $this->getIssn();
        //if (!empty($this->getIsbn())) $this->_metas[self::META][self::META_IDENTIFIER][self::META_ISBN] = $this->getIsbn();
        if (!empty($this->getPubmedId())) $this->_metas[self::META][self::META_IDENTIFIER][self::META_PMID] = $this->getPubmedId();


        // Construction des auteurs avec auteurs externes
        if (!empty($this->getAuthors())) $this->_metas[self::AUTHORS] = $this->getAuthors();
        if (!empty($this->getExtAuthors())) $this->_metas[self::EXTAUTHORS] = $this->getExtAuthors();


        return $this->_metas;
    }

}

foreach (glob(__DIR__.'/Inra/*.php') as $filename)
{
    require_once($filename);
}