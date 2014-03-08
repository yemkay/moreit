<?php


class DataHelper {

    static function getKeywords($active = true)  //assumes unique usernames across the system
    {
        global $log;
        global $DB;
        $cond = array();

        if ($active) $cond[] = 'is_active=1';

        $cond = implode(' AND ', $cond);
        if (!empty($cond)) $cond = ' WHERE '.$cond;

        $query = 'select tw_keywords.*, keyword as ARRAY_KEY from tw_keywords'.$cond;

        $log->logDebug('getKeywords Query = '.$query);
        $res = $DB->query($query);
        $log->logDebug('getKeywords results: ', $res);

        return $res;
    }

}
?>