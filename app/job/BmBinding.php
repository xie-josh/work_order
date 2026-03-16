<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use app\admin\services\TkService;

class BmBinding
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue BmBinding
        // sleep(1);

        // is_apply  1=已处理，2=处理中，3=待处理
        //is_apply = [2,3]

        try {
            $id = $data['id'];
            $bmData = DB::table('ba_bm')->whereIn('status',[0,1])->whereIn('dispose_type',[0])->where('id',$id)->find();
            if(empty($bmData)) return ;
            $bm = $bmData['bm'];
            $accountId = $bmData['account_id'];
            $bmType = $bmData['bm_type']; //绑定类型：1=BM，2=邮箱
            $demandType = $bmData['demand_type']; //需求类型:1=绑定,2=解绑,3=全部解绑,4=开户绑定
            
            $appApi = (new TkService())->ApplicationApi([]);
            
            switch ($bmData['is_apply']) {
                case 3:
                    if(in_array($demandType,[1,4]))
                    {
                        //绑定
                        if($bmType == 1)
                        {
                            $result = $appApi->tiktokBindBc([
                                'applications' => [
                                    [
                                        'account_id' => $bmData['account_id'],
                                        'bind_bc' => [
                                            [
                                                'role'=>1,
                                                'bc'=>$bm
                                            ]
                                        ]
                                    ]
                                ],
                            ]);

                            $applyId = $result['data']['apply_id']??'';
                        }elseif($bmType == 2)
                        {
                            $result = $appApi->tiktokBindEmail([
                                'applications' => [
                                    [
                                        'account_id' => $bmData['account_id'],
                                        'bind_email' => [
                                            [
                                                'role'=>1,
                                                'email'=>$bm
                                            ]
                                        ],
                                        // 'unbind_email'=>'' //解绑邮箱
                                    ]
                                ],
                            ]);

                            $applyId = $result['data']['apply_id']??'';
                        }
                    }elseif($demandType == 2)
                    {
                        //解绑
                        if($bmType == 1)
                        {

                            $result = $appApi->tiktokBindBc([
                                'applications' => [
                                    [
                                        'account_id' => $bmData['account_id'],
                                        "unbind_bc"=>[$bm]
                                    ]
                                ],
                            ]);

                            $applyId = $result['data']['apply_id']??'';
                        }elseif($bmType == 2)
                        {
                            $result = $appApi->tiktokBindEmail([
                                'applications' => [
                                    [
                                        'account_id' => $bmData['account_id'],
                                        'unbind_email'=>[$bm] //解绑邮箱
                                    ]
                                ],
                            ]);

                            $applyId = $result['data']['apply_id']??'';
                        }
                    }
                    if(empty($applyId)){
                        DB::table('ba_bm')->where('id',$id)->update(['is_apply'=>1,'comment'=>'1-2：绑定失败！']); 
                        return ;
                    }
                    DB::table('ba_bm')->where('id',$id)->update(['is_apply'=>2,'apply_id'=>$applyId]);
                    break;
                case 2:
                        
                        $demandType = $bmData['demand_type'];

                        // 需求申请分类（category）
                        // 1 额度管理
                        // 2 更名
                        // 3 绑定bm
                        // 4 绑定像素
                        // 5 接受mcc邀请
                        // 6 授权个⼈账⼾
                        // 7 绑定bc_id
                        // 8 绑定邮箱

                        $applyId = $bmData['apply_id'];                        
                        $demandType = $bmData['demand_type']; //需求类型:1=绑定,2=解绑,3=全部解绑,4=开户绑定
                        $category = $bmType == 1?7:8;

                        $result = $appApi->operateRecords([
                            'apply_ids'=>$applyId,
                            'category' => $category,
                            'medium' => '5', //0:facebook、18:google、5:tiktok
                            'current_page' => 1,
                            'page_size' => 100,
                        ]);

                        $status = $result['data']['list'][0]['operate_status']??0;
                                        
                        //1:处理中、2:成功、3:失败
                        if($status == 2){
                            if($demandType == 2) DB::table('ba_bm')->where('account_id',$accountId)->where('bm',$bm)->update(['new_status'=>2]);                            
                            DB::table('ba_bm')->where('id',$id)->update(['status'=>1,'dispose_type'=>1,'is_apply'=>1]);
                        }elseif($status == 3)
                        {
                            DB::table('ba_bm')->where('id',$id)->update(['status'=>2,'dispose_type'=>2,'is_apply'=>1]);
                        }

                        if($status == 2 && $demandType == 4)
                        {
                            $this->spendUp($accountId);
                        }elseif($status == 3 && $demandType == 4){
                            DB::table('ba_account')->where('account_id',$accountId)->where('status',3)->update(['comment'=>"绑定失败！"]);
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

    public function spendUp($accountId)
    {
        $account =  DB::table('ba_account')->where('account_id',$accountId)->where('status',3)->find();
        if(empty($account)) return ;
        if($account['money'] > 0)
        {
            $result = (new TkService())->ApplicationApi([])->tiktokAmountSpend([
                'applications' => [
                    [
                        'account_id' => $accountId,
                        'amount' => $account['money'],
                        'currency' => '0',
                        'type' => '1'
                    ]
                ],
                'is_prepay'    => false,
            ]);

            $applyId = $result['data']['apply_id']??0;

            if(!empty($applyId)) DB::table('ba_account')->where('account_id',$accountId)->update(['apply_id'=>$applyId,'is_apply'=>2,'comment'=>""]);
        }else{
            DB::table('ba_account')->where('account_id',$accountId)->update(['status'=>4,'is_apply'=>1,'comment'=>""]);

            $serialName = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->value('serial_name');
            (new TkService())->ApplicationApi([])->tiktokRename([
                'applications' => [
                    [
                        'account_id' => $accountId,
                        'new_account_name' => $serialName
                    ]
                ]
            ]);
        }



    }
}
