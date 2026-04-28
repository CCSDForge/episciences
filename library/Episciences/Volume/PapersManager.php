<?php

// volume/paper relation manager
class Episciences_Volume_PapersManager
{
    // return an array of volume/paper relations
    public static function findVolumePapers($vid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_VOLUME_PAPER)->where('vid = ?', $vid);
        $volume_papers = array();
        foreach ($db->fetchAs($sql) as $data) {
            $volume_papers[$data['ID']] = new Episciences_Volume_Paper($data);
        }
        return $volume_papers;
    }

    // return an array of volume/paper relations
    public static function findPaperVolumes($docid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(T_VOLUME_PAPER)->where('docid = ?', $docid);
        $volume_papers = array();
        foreach ($db->fetchAll($sql) as $data) {
            $volume_papers[$data['ID']] = new Episciences_Volume_Paper($data);
        }
        return $volume_papers;
    }

    public static function updatePaperVolumes($docid, array $paper_volumes): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $vids = array();
        foreach ($paper_volumes as $paper_volume) {
            $vids[] = $paper_volume->getVid();
        }

        if (!empty($vids)) {
            // delete previous paper/volume relations, when not in $vids
            $db->delete(T_VOLUME_PAPER, 'DOCID = ' . $docid . ' AND VID NOT IN (' . implode(',', $vids) . ')');

            // insert new paper/volume relations
            foreach ($vids as $vid) {
                $sql = 'INSERT INTO ' . T_VOLUME_PAPER . ' (DOCID, VID) VALUES (:docid, :vid)
                    ON DUPLICATE KEY UPDATE VID = VID';
                $query = $db->prepare($sql);
                $query->execute(['docid' => $docid, 'vid' => $vid]);
            }
        } else {
            // delete all previous paper/volume relations
            $db->delete(T_VOLUME_PAPER, 'DOCID = ' . $docid);
        }

    }

    public static function deletePaperVolume($docid, $vid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->delete(T_VOLUME_PAPER, 'DOCID = ' . $docid . ' AND VID = ' . $vid);
    }
}
