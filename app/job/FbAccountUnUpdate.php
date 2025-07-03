<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class FbAccountUnUpdate
{
    public function fire(Job $job, $data)
    {
        set_time_limit(300);
        //php think queue:listen --queue FbAccountUnUpdate
        sleep(1);

        try {
            $this->accountUpdate($data);
            $job->delete();
        } catch (\Throwable $th) {

            if ($job->attempts() >= 3) {
                $job->delete();
            }
        }
        if ($job->attempts() >= 3) {
            $job->delete();
        }
    }

    public function accountUpdate($params)
    {
        try {
            $businessId = $params['business_id'];
            $id = $params['id'];

            $_is = true;
            // if($params['type'] == 1){
            //     $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(1,$id);
            //     $params['token'] = $token;
            // }else if($params['type'] == 2){
            //     $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(2,$id);
            //     $params['token'] = $token;
            // }
            $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);
            $params['token'] = $token;
            
            while ($_is) {
                $params['account_status'] = 1;
                $result = (new \app\services\FacebookService())->list($params);
                if(empty($result) || $result['code'] == 0) return true;

                $params['after'] = $result['data']['after']??'';
                $params['pageSize'] = $result['data']['pageSize']??0;
                $params['total'] = $result['data']['total']??0;
                
                if(empty($params['after']) || $params['total'] < $params['pageSize']) $_is = false;
                
                if(empty($result['data']['data'])){
                    $_is = false;
                    return true;
                } 

                $accountList = [];
                $currencyAccountList = [];
                $accountStatusList = [];
                $accountNameList = [];
                $accountTimeZoneList = [];
                $accountCountryCodeList = [];
                foreach($result['data']['data'] as $item)
                {  
                    $item['id'] = str_replace('act_', '', $item['id']);
                    $accountNameList[$item['id']] = [
                        'account_id'=>$item['id'],
                        'serial_name'=>$item['name']
                    ];
                    $accountTimeZoneList[(string)$item['timezone_offset_hours_utc']][] = $item['id'];
                    if(isset($item['business_country_code'])) $accountCountryCodeList[(string)$item['business_country_code']][] = $item['id'];
                    $accountStatusList[$item['account_status']][] = $item['id'];
                    if(!in_array($item['account_status'],[1,3])) continue;
                    $accountList[] = $item;
                    $currencyAccountList[$item['currency']][] = $item['id'];                    
                }
                
                $accountIds = array_column($accountList,'id');
                // $cardList = DB::table('ba_accountrequest_proposal')
                // ->alias('accountrequest_proposal')
                // ->field('accountrequest_proposal.close_time,accountrequest_proposal.account_id,cards_info.card_no,cards_info.card_status,cards_info.card_id,cards_info.account_id cards_account_id,cards_info.cards_id')
                // ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                // ->whereIn('accountrequest_proposal.account_id',$accountIds)
                // ->select()->toArray();

                // foreach($cardList as $v){
                //     if(empty($v['card_status']) || $v['card_status'] != 'frozen') continue;
                    //$result = (new CardService($v['cards_account_id']))->cardUnfreeze(['card_id'=>$v['card_id']]);
                    // if(isset($result['data']['cardStatus'])){
                    //     DB::table('ba_cards_info')->where('cards_id',$v['cards_id'])->update(['card_status'=>$result['data']['cardStatus']]);
                    // }else{
                    //     DB::table('ba_cards_logs')->insert([
                    //         'type'=>'FB_cardUnfreeze',
                    //         'data'=>json_encode($v),
                    //         'logs'=>$result['msg']??'',
                    //         'create_time'=>date('Y-m-d H:i:s',time())
                    //     ]);
                    // }
                // }

                if(!empty($accountTimeZoneList)) $this->updateTimeZone($accountTimeZoneList);
                
                if(!empty($accountCountryCodeList)) $this->updateCountryCode($accountCountryCodeList);
                
                if(!empty($accountNameList)) $this->updateSerialName($accountIds,$accountNameList);                

                if(!empty($accountStatusList)) $this->accountReturn($accountStatusList);
                

                foreach($currencyAccountList as $k => $v){
                    $where = [
                        ['accountrequest_proposal.account_id','IN',$v],
                        ['accountrequest_proposal.status','=',1],
                        ['account.status','=',4],
                    ];
                    DB::table('ba_accountrequest_proposal')
                    ->alias('accountrequest_proposal')
                    ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')->where($where)->update(['accountrequest_proposal.currency'=>$k]);
                }
                foreach($accountStatusList as $k => $v){
                    // if($k == 2) continue;
                    //DB::table('ba_accountrequest_proposal')->whereIn('account_id',$v)->update(['account_status'=>$k,'bm_token_id'=>$id,'close_time'=>'','pull_status'=>1,'pull_account_status'=>date('Y-m-d H:i',time())]);
                    DB::table('ba_accountrequest_proposal')->whereIn('account_id',$v)->update(['account_status'=>$k,'close_time'=>'','pull_status'=>1,'pull_account_status'=>date('Y-m-d H:i',time())]);
                }
            }
        } catch (\Throwable $th) {
            $logs = '错误info_cardUnfreeze_('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$id??'','type'=>'job_FbAccountUnUpdate','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
        }
        return true;   
    }

    public function updateSerialName($accountIds,$accountNameList)
    {
        $where = [
            ['accountrequest_proposal.account_id','IN',$accountIds],
            ['account.status','=',4],
        ];
        $resultList = DB::table('ba_accountrequest_proposal')
        ->alias('accountrequest_proposal')                    
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->where($where)->column('accountrequest_proposal.account_id');
        $params = [];
        foreach($resultList as &$v){
            $serialName = $accountNameList[$v]['serial_name']??'';
            $params[] = [
                'account_id'=>$v,
                'serial_name'=>$serialName
            ];
        }
        $this->dbBatchUpdate('ba_accountrequest_proposal',$params,'account_id');
        return true;
    }
    public function updateTimeZone($timeZoneList)
    {
        $TIME_ZONE = config('basics.TIME_ZONE');
        foreach($timeZoneList as $k => $v){
            $timeZone = $TIME_ZONE[$k]??$k;
            $where = [
                ['accountrequest_proposal.account_id','IN',$v],
                ['account.status','=',4],
            ];
            DB::table('ba_accountrequest_proposal')
            ->alias('accountrequest_proposal')
            ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')->where($where)->update(['accountrequest_proposal.time_zone'=>$timeZone]);
        }
        return true;
    }

    public function updateCountryCode($accountCountryCodeList)
    {
        $countryCode = config('basics.country_code');
        foreach($accountCountryCodeList as $k => $v){
            $where = [
                ['accountrequest_proposal.account_id','IN',$v],
                ['account.status','=',4],
            ];
            DB::table('ba_accountrequest_proposal')
            ->alias('accountrequest_proposal')
            ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')->where($where)->update(['accountrequest_proposal.country_code'=>$k,'accountrequest_proposal.country_name'=>$countryCode[$k]??'']);
        }
        return true;
    }
    public static function dbBatchUpdate(string $table_name,array $data,string $field)
    {
        // 生成SQL
        $sql = 'UPDATE '.$table_name." SET ";
        $fields = $casesSql = [];
        foreach ($data as $key => $value) {
            // 指定更新字段不存在
            if (!isset($value[$field])){
                continue;
            }
            // 记录更新字段
            $temp = $value[$field];
            if(!in_array($temp,$fields)){
                $fields[]='"'.$temp.'"';
            }
            // 拼接更新字段条件
            foreach ($value as $k => $v) {
                // 更新条件字段不更新
                if ($k == $field){
                    continue;
                }
                $temp = $value[$field];
                // 拼接CASE，默认CASE头
                $caseWhen = isset($casesSql[$k]) ? $casesSql[$k] : "`{$k}` = (CASE `{$field}` ";
                // 拼接WHEN
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
        // 拼接结束END
        foreach ($casesSql as $key=>$val){
            $endSql[] = $val." END)";
        }
        $sql .= implode(',',$endSql);
        unset($data,$casesSql,$endSql);
        // 拼接WHERE
        $str = implode(',',  $fields );
        $sql .=" WHERE `{$field}` IN ({$str})";
        // 创建并执行完整SQL
        $res = Db::execute($sql);
        return $res;
    }

    public function accountReturn($params)
    {
        $dataList = [];
        foreach($params as $k => $v)
        {
            if($k == 1){
                $resultList = DB::table('ba_accountrequest_proposal')->where([['status','<>',0]])->whereIn('account_id',$v)->where('account_status',2)->column('account_id');
                $resultList2 = DB::table('ba_accountrequest_proposal')->where([['status','<>',0]])->whereIn('account_id',$v)->where('account_status',0)->whereNotNull('pull_account_status')->column('account_id');
                
                foreach($resultList as $v){
                    $dataList[] = [
                        'account_id'=>$v,
                        'type'=>1,
                        'create_time'=>time()
                    ];
                }
                foreach($resultList2 as $v){
                    $dataList[] = [
                        'account_id'=>$v,
                        'type'=>3,
                        'create_time'=>time()
                    ];
                }
                // if(!empty($dataList)) DB::table('ba_account_return')->insertAll($dataList);
            }else if($k == 2){
                $resultList = DB::table('ba_accountrequest_proposal')->where([['status','<>',0]])->whereIn('account_id',$v)->where('account_status',0)->whereNotNull('pull_account_status')->column('account_id');
                foreach($resultList as $v){
                    $dataList[] = [
                        'account_id'=>$v,
                        'type'=>4,
                        'create_time'=>time()
                    ];
                }
                // if(!empty($dataList)) DB::table('ba_account_return')->insertAll($dataList);
            }else if($k == 3){
                $resultList = DB::table('ba_accountrequest_proposal')->where([['status','<>',0]])->whereIn('account_id',$v)->where('account_status',2)->column('account_id');
                $resultList2 = DB::table('ba_accountrequest_proposal')->where([['status','<>',0]])->whereIn('account_id',$v)->where('account_status',0)->whereNotNull('pull_account_status')->column('account_id');
                foreach($resultList as $v){
                    $dataList[] = [
                        'account_id'=>$v,
                        'type'=>2,
                        'create_time'=>time()
                    ];
                }
                foreach($resultList2 as $v){
                    $dataList[] = [
                        'account_id'=>$v,
                        'type'=>5,
                        'create_time'=>time()
                    ];
                }
                // if(!empty($dataList)) DB::table('ba_account_return')->insertAll($dataList);
            }
        }
        if(!empty($dataList)) DB::table('ba_account_return')->insertAll($dataList);

        return true;
    }

    public function accountInsights(){

    }
}
