<?php

class Episciences_Paper_ProjectsManager
{
    /**
     * @param $projects
     * @return int
     */

    public static function insert($projects): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $values = [];

        $affectedRows = 0;


        if (!($projects instanceof Episciences_Paper_Projects)) {

            $projects = new Episciences_Paper_Projects($projects);
        }

        $values[] = '(' . $db->quote($projects->getFunding()) . ',' . $db->quote($projects->getPaperid()) . ' , '.$db->quote($projects->getSourceId()) . ')';

        $sql = 'INSERT INTO ' . $db->quoteIdentifier(T_PAPER_PROJECTS) . ' (`funding`,`paperid`, `source_id`) VALUES ';

        if (!empty($values)) {
            try {
                //Prepares and executes an SQL
                /** @var Zend_Db_Statement_Interface $result */
                $result = $db->query($sql . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE funding=VALUES(funding)');
                $affectedRows = $result->rowCount();
            } catch (Exception $e) {
                trigger_error($e->getMessage(), E_USER_ERROR);
            }
        }
        return $affectedRows;

    }
    public static function getProjectsByPaperId($paperId): array {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPER_PROJECTS)->where('PAPERID = ?',$paperId); // prevent empty row
        return $db->fetchAssoc($select);
    }
    // faire fonction pour le formatage en <ul> <li>

    public static function formatProjectsForview($paperId){
        $rawInfo = self::getProjectsByPaperId($paperId);
        if (!empty($rawInfo)){
            $rawFunding = "";
            $templateProject = "";
            foreach ($rawInfo as $value) {
                $rawFunding = json_decode($value['funding'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            $templateProject .= "<ul class=''>";
            foreach ($rawFunding as $fundingInfo){
                if ($fundingInfo['projectTitle'] !== "unidentified"){
                    $templateProject.='<li><em>'.htmlspecialchars($fundingInfo['projectTitle'])."</em>";
                    if ($fundingInfo['funderName'] !== "unidentified") {
                        $templateProject.= "; ".Zend_Registry::get('Zend_Translate')->translate("Funder").": ".htmlspecialchars($fundingInfo['funderName']);
                    }
                } elseif ($fundingInfo['funderName'] !== "unidentified"){
                    $templateProject.= "<li>".Zend_Registry::get('Zend_Translate')->translate("Funder").",: ".htmlspecialchars($fundingInfo['funderName']);
                }
                if ($fundingInfo['code'] !== "unidentified" && ($fundingInfo['funderName'] !== "unidentified" || $fundingInfo['projectTitle'] !== "unidentified")) {
                    $templateProject.= "; Code: ".htmlspecialchars($fundingInfo['code']);
                }
                $templateProject.="</li>";
            }
            $templateProject .= "</ul>";
            return ['funding'=>$templateProject];
        }
        return "";
    }
}