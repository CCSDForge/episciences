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
        $select = $db->select()->from(["project"=>T_PAPER_PROJECTS])->joinLeft(["source_paper" => T_PAPER_METADATA_SOURCES],"project.source_id = source_paper.id",["source_id_name"=>'source_paper.name'])->where('PAPERID = ?',$paperId); // prevent empty row
        return $db->fetchAssoc($select);
    }

    public static function getProjectsByPaperIdAndSourceId($paperId,$sourceId): array {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from(T_PAPER_PROJECTS)->where('PAPERID = ?',$paperId)->where('source_id = ?',$sourceId); // prevent empty row
        return $db->fetchAssoc($select);
    }

    /**
     * @throws JsonException
     */
    public static function getProjectWithDuplicateRemoved($paperId) {
        $allProjects = self::getProjectsByPaperId($paperId);
        $rawFunding = [];
        foreach ($allProjects as $project) {
            $decodeProject  = json_decode($project['funding'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $rawFunding[] = $decodeProject;

        }
        $rawFunding = array_unique($rawFunding,SORT_REGULAR);

        //reorganise to simply the array
        $finalFundingArray = [];
        foreach ($rawFunding as $fundings){
            foreach ($fundings as $funding){
                $finalFundingArray[] = $funding;

            }
        }
        return $finalFundingArray;
    }



    public static function update($projects): int {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!($projects instanceof Episciences_Paper_Projects)) {
            $projects = new Episciences_Paper_Projects($projects);
        }
        $where['paperid = ?'] = $projects->getPaperId();
        $where['source_id = ?'] = $projects->getSourceId();
        $values = [
            'funding' => $projects->getFunding()
        ];
        try {
            $resUpdate = $db->update(T_PAPER_PROJECTS, $values, $where);
        } catch (Zend_Db_Adapter_Exception $exception) {
            $resUpdate = 0;
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }
        return $resUpdate;

    }



    public static function formatProjectsForview($paperId){
        $rawInfo = self::getProjectsByPaperId($paperId);
        if (!empty($rawInfo)){
            $rawFunding = [];
            $templateProject = "";
            foreach ($rawInfo as $value) {
                $rawFunding[$value['source_id_name']][] = json_decode($value['funding'], true, 512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }
            foreach ($rawFunding as $source_id_name => $fundingInfo){
                $templateProject .= "<ul class='list-unstyled'>";
                $templateProject .= " <small class='label label-info'>".Zend_Registry::get('Zend_Translate')->translate('Source :') . ' ' .$source_id_name."</small>";
                foreach ($fundingInfo as $counter => $funding){
                    foreach ($funding as $kf => $vfunding){
                        if ($vfunding['projectTitle'] !== "unidentified"){
                            $templateProject.='<li><em>'.htmlspecialchars($vfunding['projectTitle'])."</em>";
                        if ($vfunding['funderName'] !== "unidentified") {
                            $templateProject.= "; ".Zend_Registry::get('Zend_Translate')->translate("Funder").": ".htmlspecialchars($vfunding['funderName']);
                        }
                        } elseif ($vfunding['funderName'] !== "unidentified"){
                            $templateProject.= "<li>".Zend_Registry::get('Zend_Translate')->translate("Funder").": ".htmlspecialchars($vfunding['funderName']);
                        }
                        if ($vfunding['code'] !== "unidentified" && ($vfunding['funderName'] !== "unidentified" || $vfunding['projectTitle'] !== "unidentified")) {
                            $templateProject.= "; Code: ".htmlspecialchars($vfunding['code']);
                        }
                        if (isset($vfunding['callId']) && $vfunding['callId'] !== "unidentified") {
                            $templateProject.= "; ".Zend_Registry::get('Zend_Translate')->translate("callId").": ".htmlspecialchars($vfunding['callId']);
                        }
                        if (isset($vfunding['projectFinancing']) && $vfunding['projectFinancing'] !== "unidentified") {
                            $templateProject.= "; ".Zend_Registry::get('Zend_Translate')->translate("projectFinancing").": ".htmlspecialchars($vfunding['projectFinancing']);
                        }
                        $templateProject.="</li>";
                    }

                }
                $templateProject .= "</ul>";
            }
            return ['funding'=>$templateProject];

        }
        return "";
    }
}