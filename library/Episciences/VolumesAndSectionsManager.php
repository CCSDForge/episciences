<?php

class Episciences_VolumesAndSectionsManager
{

    /**
     * Sort volumes and sections
     * @param array $params
     * @param string $colId
     * @return bool
     * @throws Zend_Db_Adapter_Exception
     */
    public static function sort(array $params, string $colId = 'VID'): bool
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $table = T_VOLUMES;
        $cols = ['VID', 'POSITION'];
        $pattern = '#volume_(.*)#';

        if ($colId === 'SID'){
            $table = T_SECTIONS;
            $cols = ['SID', 'POSITION'];
            $pattern = '#section_(.*)#';
        }

        $select = $db
            ->select()
            ->from($table, $cols)
            ->where('RVID = ?', RVID)
            ->order('POSITION ASC');

        $previousSort = $db->fetchCol($select); // previous sort
        $pCount = count($previousSort);


        if ($pCount < 2) { // at least two lines
            echo 0;
            return false;
        }

        $paramsCount = count($params['sorted']);
        $to = 0;

        //sort the block passed in parameter
        foreach ($params['sorted'] as $i => $value) {
            preg_match($pattern, $value, $matches);
            if (empty($matches)) {
                continue;
            }

            $id = $matches[1];
            $to = $i + 1;


            if ($db->update($table, ['POSITION' => $to], ["$colId = ?" => $id])) {

                $key = array_search($id, $previousSort, false);

                if (false !== $key) {
                    unset($previousSort[$key]);
                }
            }
        }

        //re-sort the remaining volumes or sections
        if ($paramsCount !== $pCount) {

            foreach ($previousSort as $id) {
                ++$to;
                $db->update($table, ['POSITION' => $to], ["$colId = ?" => $id]);

            }
        }

        echo count($params['sorted']);

        return true;
    }
}
