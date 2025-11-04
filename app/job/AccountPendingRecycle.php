<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
set_time_limit(3600);

class AccountPendingRecycle
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountPendingRecycle
        try {

            $accountId = $data['account_id'];
            $openTime = $data['open_time'];

            $openDate = date('Y-m-d H:i:s',$openTime);
            $where = [
                ['date_start','>=',$openDate],
                ['spend','>',0],
                ['account_id','=',$accountId],
            ];
            $consumption = DB::table('ba_account_consumption')->where($where)->order('date_start','desc')->find();
            $consumptionDate = isset($consumption['date_start'])?$consumption['date_start']:$openDate;

            $ts1 = time();
            $ts2 = strtotime($consumptionDate);

            $seconds = $ts1 - $ts2;


            if($seconds > floor(config('basics.ACCOUNT_RECYCLE_DAYS') * 86400))
            {

                $result = DB::table('ba_account_recycle_pending')->where('account_id',$accountId)->where('status',0)->find();
                if(empty($result)) DB::table('ba_account_recycle_pending')->insert(
                    [
                        'account_id'=>$accountId,
                        'status'=>0,
                        'create_time'=>time()
                    ]
                );                
            }

            DB::table('ba_account')->where('account_id',$accountId)->update(['idle_time'=>$seconds]);

            $accountSpent = DB::table('ba_account_consumption')->where('account_id',$accountId)->sum('dollar');            
            $totalConsumption = bcadd((string)$accountSpent,"0",2);
            DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['total_consumption'=>$totalConsumption]);
                       
            $job->delete();
        } catch (\Throwable $th) {
            (new \app\services\Basics())->logs('AccountPendingRecycleJobError',$data,$th->getMessage());
        } finally {
            if ($job->attempts() >= 3) $job->delete();
        }
    }

}
