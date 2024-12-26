<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class AccountAssignedUsers
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountAssignedUsers
        sleep(1);

        try {
            $accountId = $data['account_id'];
            $bmTokenId = $data['bm_token_id'];
            $params = DB::table('ba_fb_bm_token')->where('status',1)->where('id',$bmTokenId)->find();
            if(empty($params) || empty($params['user_id'])) $job->delete();
            $params['account_id'] = $accountId;

            (new \app\services\FacebookService())->assignedUsers($params);
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
