<?php
namespace app\services;
use think\facade\Db;
class Basics
{

    public static function dbBatchUpdate(string $table_name,array $data,string $field)
    {
        $sql = 'UPDATE '.$table_name." SET ";
        $fields = $casesSql = [];
        foreach ($data as $key => $value) {
            if (!isset($value[$field])){
                continue;
            }
            $temp = $value[$field];
            if(!in_array($temp,$fields)){
                $fields[]='"'.$temp.'"';
            }
            foreach ($value as $k => $v) {
                if ($k == $field){
                    continue;
                }
                $temp = $value[$field];
                $caseWhen = isset($casesSql[$k]) ? $casesSql[$k] : "`{$k}` = (CASE `{$field}` ";
                $caseSql = sprintf(
                    "%s WHEN '%s' THEN '%s' ",
                    $caseWhen,$temp,$v
                );
                $casesSql[$k] = $caseSql;
            }
        }
        if (!$casesSql){
            return false;
        }
        $endSql = [];
        foreach ($casesSql as $key=>$val){
            $endSql[] = $val." END)";
        }
        $sql .= implode(',',$endSql);
        unset($data,$casesSql,$endSql);
        $str = implode(',',  $fields );
        $sql .=" WHERE `{$field}` IN ({$str})";
        $res = Db::execute($sql);
        return $res;
    }
}