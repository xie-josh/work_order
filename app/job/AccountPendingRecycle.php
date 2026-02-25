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
            $companyId = $data['company_id'];
            $recycleStart = $data['recycle_start'];

            $openDate = date('Y-m-d',$openTime);
            $where = [
                ['date_start','>=',$openDate],
                ['spend','>',0],
                ['account_id','=',$accountId],
            ];
            $consumption = DB::table('ba_account_consumption')->where($where)->order('date_start','desc')->find();
            $consumptionDate = isset($consumption['date_start'])?$consumption['date_start']:$openDate;

            $ts1 = time();
            $ts2 = strtotime($consumptionDate);

            $recyclingGracePeriodList = DB::table('ba_recycling_grace_period')->field('id,star_time,end_time')->where([['star_time','<=',date('Y-m-d',$ts1)],['end_time','>=',date('Y-m-d',$ts2)]])->where('status',1)->order(['star_time'=>'asc'])->select()->toArray();

            $pDays = $this->overlapDays($ts2, $ts1, $recyclingGracePeriodList);

            $seconds = $ts1 - $ts2;

            $pIdleTime = $seconds;
            if($pDays > 0) $pIdleTime =  ($seconds - ($pDays * 86400));
            
            // if($seconds > floor(30 * 86400)) 
            // {

            //     $result = DB::table('ba_account_recycle_pending')->where('account_id',$accountId)->where('status',0)->find();
            //     if(empty($result)) DB::table('ba_account_recycle_pending')->insert(
            //         [
            //             'account_id'=>$accountId,
            //             'status'=>0,
            //             'create_time'=>time()
            //         ]
            //     );                
            // }

            DB::table('ba_account')->where('account_id',$accountId)->update(['idle_time'=>$seconds,'p_idle_time'=>$pIdleTime]);

            $seconds = $pIdleTime;

            $accountSpent = DB::table('ba_account_consumption')->where('account_id',$accountId)->sum('dollar');            
            $totalConsumption = bcadd((string)$accountSpent,"0",2);
            $accountrequestProposalData = [
                'total_consumption'=>$totalConsumption
            ];

            $where = [
                ['account_id','=',$accountId],
            ];
            DB::table('ba_accountrequest_proposal')->where($where)->update($accountrequestProposalData);
            
            $companyIsopen = DB::table('ba_company')->where('id',$companyId)->value('isopen');
            if($seconds > floor(31 * 86400) && $companyIsopen == 1) {
                // if(empty($recycleStart)) $accountrequestProposalData = ['status'=>94];
                // elseif(!empty($recycleStart) && (time() - strtotime($recycleStart)) > 7 * 86400) $accountrequestProposalData = ['status'=>94];
            }else{
                $where[] = ['status','=',94];
                $where[] = ['recycle_type','=',3];
                $accountrequestProposalData = ['status'=>1];
            }
            $where[] = ['admin_id','<>',368];
            $where[] = ['admin_id','<>',400];
            if(!empty($accountrequestProposalData)) DB::table('ba_accountrequest_proposal')->where($where)->update($accountrequestProposalData);
                       
            $job->delete();
        } catch (\Throwable $th) {
            (new \app\services\Basics())->logs('AccountPendingRecycleJobError',$data,$th->getMessage());
        } finally {
            if ($job->attempts() >= 3) $job->delete();
        }
    }


    function overlapDays($mainStart, $mainEnd, $ranges)
    {
        $mainStart =  $mainStart;
        $mainEnd   =  $mainEnd;

        $result = [];

        $d = 0;
        foreach ($ranges as $range) {

            $start =  strtotime($range['star_time']);
            $end   =  strtotime($range['end_time']);

            // 取最大开始时间
            $overlapStart = max($mainStart, $start);
            
            // 取最小结束时间
            $overlapEnd = min($mainEnd, $end);

            if ($overlapStart <= $overlapEnd) {
                $days = floor(($overlapEnd - $overlapStart) / 86400) + 1;
            } else {
                $days = 0;
            }

            $d += $days;
        }

        return $d;
    }

}
