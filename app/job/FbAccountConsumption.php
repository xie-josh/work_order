<?php

namespace app\job;

use think\queue\Job;
use think\facade\Db;

class FbAccountConsumption
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue FbAccountConsumption
        sleep(1);

        try {
            $this->accountConsumption($data);
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

    public function accountConsumption($params)
    {
        try {
            $accountId = $params['account_id'];
            $businessId = $params['business_id'];

            $result = (new \app\services\FacebookService())->insights($params);
            
            DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['pull_consumption'=>date('Y-m-d H:i',time())]);
            $accountConsumption = $result['data']['data']??[];
            if(empty($accountConsumption)) return true;

            $dateStartList = array_column($accountConsumption,'date_start');
            DB::table('ba_account_consumption')->where('account_id',$accountId)->whereIn('date_start',$dateStartList)->delete();

            $data = [];
            foreach($accountConsumption as $v){
                $data[] = [
                    'account_id'=>$v['account_id'],
                    'spend'=>$v['spend'],
                    'date_start'=>$v['date_start'],
                    'date_stop'=>$v['date_stop'],
                    'create_time'=>time(),
                ];
            }
            DB::table('ba_account_consumption')->insertAll($data);            
        } catch (\Throwable $th) {
            $logs = '错误info('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$logs]);
        }
        return true;        
    }
}
