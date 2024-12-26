<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class AccountSpendUp
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountSpendUp
        sleep(1);

        try {
            (new \app\admin\services\demand\Recharge())->spendUp(['id'=>$data['id']]);
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
