<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use app\admin\services\TkService;

class AccountOpen
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountOpen
        // sleep(1);

        // is_apply  1=已处理，2=处理中，3=待处理
        //is_apply = 2 and status = 3

        try {
            $id = $data['id'];
            $account = DB::table('ba_account')->where('status',3)->where('id',$id)->find();
            $accountId = $account['account_id'];
            if(empty($account)) return ;

            $appApi = (new TkService())->ApplicationApi([]);

            switch ($account['is_apply']) {
                case 3:
                    # code...
                    break;
                case 2:
                    $applyId = $account['apply_id']; 

                    $result = $appApi->operateRecords([
                        'apply_ids'=>$applyId,
                        'category' => 1,
                        'medium' => '5', //0:facebook、18:google、5:tiktok
                        'current_page' => 1,
                        'page_size' => 100,
                    ]);
                    $status = $result['data']['list'][0]['operate_status']??0;
                    //1:处理中、2:成功、3:失败
                    if($status == 2)
                    {
                        DB::table('ba_account')->where('id',$id)->update(['status'=>4,'is_apply'=>1,'open_money'=>$account['money'],'money'=>0,'comment'=>'']);
                        $serialName = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->value('serial_name');
                        $appApi->tiktokRename([
                            'applications' => [
                                [
                                    'account_id' => $accountId,
                                    'new_account_name' => $serialName
                                ]
                            ]
                        ]);
                    }elseif($status == 3)
                    {
                        DB::table('ba_account')->where('id',$id)->update(['is_apply'=>1,'comment'=>'首充失败']);
                    }

                    # code...
                    break;

                default:
                    # code...
                    break;
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
