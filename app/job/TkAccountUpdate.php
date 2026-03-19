<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Queue;
use app\admin\services\TkService;
set_time_limit(600);

class TkAccountUpdate
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue TkAccountUpdate
        //sleep(1);

        try {
            $accountId = $data['account_id'];
            $map = [
                // 异常
                'STATUS_CONFIRM_FAIL' => 0,
                'STATUS_CONFIRM_FAIL_END' => 0,
                'STATUS_CONFIRM_MODIFY_FAIL' => 0,
                'STATUS_LIMIT' => 0,

                // 活跃
                'STATUS_ENABLE' => 1,

                // 封户
                'STATUS_DISABLE' => 2,

                // 待处理
                'STATUS_PENDING_CONFIRM' => 3,
                'STATUS_PENDING_VERIFIED' => 3,
                'STATUS_PENDING_CONFIRM_MODIFY' => 3,
                'STATUS_WAIT_FOR_BPM_AUDIT' => 3,
                'STATUS_WAIT_FOR_PUBLIC_AUTH' => 3,
                'STATUS_SELF_SERVICE_UNAUDITED' => 3,
                'STATUS_CONTRACT_PENDING' => 3,
            ];

             $appApi = (new TkService())->TikTokAccount([]);

            $result = $appApi->getAdvertiser($accountId);

            $status = $map[$result['status']??'STATUS_CONFIRM_FAIL']??'STATUS_CONFIRM_FAIL';
            $balance = $result['balance']??0;

            $spendCap = 1000000;
            $amountSpent = bcsub((string)$spendCap,(string)$balance,'2');

            DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update([
                'spend_cap'=>$spendCap,
                'amount_spent'=>$amountSpent,
                'account_status'=>$status
            ]);

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


}
