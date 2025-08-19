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
    public function returnError(String $msg='error',Array $data = [])
    {
        return [
            'code'=>0,
            'msg'=>$msg,
            'data'=>$data
        ];
    }
     public function returnSucceed(Array $data = [])
    {
        return [
            'code'=>1,
            'msg'=>'succeed',
            'data'=>$data
        ];
    }

    public function isEmail($email)
    {
        $domain = substr(strrchr($email, "@"), 1);
        $main = explode('.', $domain)[0];
        $allowList = config('basics.BM_EMAIL');
        if (in_array(strtolower($main), $allowList)) {
            return $this->returnSucceed();
        }else{
            return $this->returnError('请提供@outlook或@hotmail后缀邮箱!');
        }
    }

    static function logs(String $type = '',array $data = [], String $logs = '')
    {
        DB::table('ba_logs')->insert([
            'type' => $type,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'logs' => $logs,
            'create_time' => date('Y-m-d H:i:s')
        ]);
        return [
            'code'=>1,
            'msg'=>'succeed',
            'data'=>$data
        ];
    }

}