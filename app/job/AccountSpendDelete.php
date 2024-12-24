<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class AccountSpendDelete
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountSpendDelete
        sleep(1);

        try {
            (new \app\admin\services\demand\Recharge())->spendDelete(['id'=>$data['id']]);
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
