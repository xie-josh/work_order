<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class AccountSpendDeductions
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountSpendDeductions
        sleep(1);

        try {
            $account_id = DB::table('ba_recharge')->where('id',$data['id'])->value('account_id');
            $type = DB::table('ba_accountrequest_proposal')->where('account_id',$account_id)->value('type');
            if($type == 1)
            {
                (new \app\admin\services\demand\Recharge())->spendDeductions(['id'=>$data['id']]);
            }else{
                (new \app\admin\services\demand\Recharge())->tkspendDeductions(['id'=>$data['id']]);
            }
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
