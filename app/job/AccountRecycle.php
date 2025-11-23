<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
set_time_limit(3600);

class AccountRecycle
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountRecycle
        try {

            $accountId = $data['account_id'];
            $status = $data['status'];

            $accountDataList = DB::table('ba_account')->where('account_id',$accountId)->select()->toArray();
            $bmDataList = DB::table('ba_bm')->where('account_id',$accountId)->select()->toArray();;
            $rechargeDataList = DB::table('ba_recharge')->where('account_id',$accountId)->select()->toArray();
                      
            

            $accountDataList = array_map(function($v){
                $openTime = date('Y-m-d',$v['open_time']);
                $accountId = $v['account_id'];
                $where = [
                    ['account_id','=',$accountId],
                    ['date_start','>=',$openTime]
                ];
                $serialName = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->value('serial_name');
                $totalConsumption = DB::table('ba_account_consumption')->where($where)->sum('dollar');
                $totalUp = DB::table('ba_recharge')->where('account_id',$accountId)->where('type',1)->where('status',1)->sum('number');
                $totalDelete = DB::table('ba_recharge')->where('account_id',$accountId)->where('type','IN',[3,4])->where('status',1)->sum('number');
                $totalDeductions = DB::table('ba_recharge')->where('account_id',$accountId)->where('type',2)->where('status',1)->sum('number');
                $v['total_up'] = bcadd((string)$totalUp,"0",2);
                $v['total_delete'] = bcadd((string)$totalDelete,"0",2);
                $v['total_deductions'] = bcadd((string)$totalDeductions,"0",2);
                $v['total_consumption'] = bcadd((string)$totalConsumption,"0",2);
                $v['name'] = $serialName;

                $v['account_recycle_time'] = date('Y-m-d H:i:s',time());
                unset($v['id']);
                return $v;
            },$accountDataList);

            DB::table('ba_account_recycle')->insertAll($accountDataList);
            DB::table('ba_bm_recycle')->insertAll($bmDataList);
            DB::table('ba_recharge_recycle')->insertAll($rechargeDataList);

            DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(
                ['status'=>$status,'affiliation_admin_id'=>'']
            ); 
            DB::table('ba_account')->where('account_id',$accountId)->update(['account_id'=>'','status'=>2,'dispose_status'=>0,'open_money'=>0,'money'=>0]);
            DB::table('ba_bm')->where('account_id',$accountId)->delete();
            DB::table('ba_recharge')->where('account_id',$accountId)->delete();

            // DB::table('ba_account_recycle_pending')->where('account_id',$accountId)->update(['status'=>1]);
           
            $job->delete();
        } catch (\Throwable $th) {
            (new \app\services\Basics())->logs('AccountRecycleJobError',$data,$th->getMessage());
        } finally {
            if ($job->attempts() >= 3) $job->delete();
        }
    }

}
