<?php

namespace app\job;

use think\queue\Job;
use think\facade\Db;
use think\facade\Log;
use app\admin\services\TkService;
set_time_limit(3600);

class TkRecharge
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue TkRecharge
        try {
            $id = $data['id'];
            // $type = $data['type'];
            $account_id = $data['account_id'];
            $apply_id = $data['apply_id'];
            $number = $data['number'];//清零专用
            $appApi = (new TkService())->ApplicationApi([]);
            $TikTokApi = (new TkService())->TikTokAccount([]);
            // 1 额度管理category
            // 2 更名
            // 3 绑定bm
            // 4 绑定像素
            // 5 接受mcc邀请
            // 6 授权个⼈账⼾
            // 7 绑定bc_id
            // 8 绑定邮箱
            $resultTk = $appApi->operateRecords([
                'apply_ids'=>$apply_id,
                'category' => 1,
                'medium' => '5', //0:facebook、18:google、5:tiktok
                'current_page' => 1,
                'page_size' => 100,
                 ]);
            // if(empty($resultTk['data'])) 
            $list = $resultTk['data']['list']??[];
            foreach($list as $k=>$v)
            {
                // $change_amount = $v['change_amount']; //更改金额
                if($v['operate_status'] ==2)
                {
                  if($v['type'] ==1)//充值
                  {
                     DB::table('ba_account')->where('account_id',$account_id)->inc('money',$number)->update(['update_time'=>time(),'is_'=>1]);
                  }elseif($v['type'] ==2)//扣款
                  {
                      DB::table('ba_account')->where('account_id',$account_id)->dec('money',$number)->update(['update_time'=>time()]);
                      $this->teamUsedMoney($number,$account_id);
                  }elseif($v['type'] ==3){ //清零
                    $result = $TikTokApi->getCampaign($account_id);
                    if(isset($result['data']['list'])){
                        $campaignIds = array_column($result['data']['list'],'campaign_id');   
                        $advertiserId = $account_id;   
                        $operationStatus = 'DISABLE';   
                        // $operationStatus = 'ENABLE';   
                        $param = [
                            'advertiser_id'=>$account_id,
                            'campaign_ids'=>$campaignIds,
                            'operation_status'=>$operationStatus,
                        ];
                        $result = $TikTokApi->updateCampaignStatus($param);
                     }
                     $money = DB::table('ba_account')->where('account_id',$account_id)->value('money');
                     if($money <= 0) Db::table('ba_recharge')->where('id',$id)->update(['status'=>1]);

                      DB::table('ba_account')->where('account_id',$account_id)->update(['money'=>0,'is_'=>2,'update_time'=>time()]);
                      $this->teamUsedMoney($v['change_amount'],$account_id);
                  }
                  Db::table('ba_recharge')->where('id',$id)->update(['status'=>1,'is_apply'=>1]);
               }elseif($v['operate_status'] ==3)
               {
                   Db::table('ba_recharge')->where('id',$id)->update(['is_apply'=>1,'comment'=>"操作失败！"]);
               }
            }
            $job->delete();
        } catch (\Throwable $th) 
        {
            (new \app\services\Basics())->logs('TkRechargeJob',$data,$th->getMessage());
            $job->delete();
            // DB::table('ba_account')->where('account_id',$accountId)->update(['comment'=>$th->getMessage()]);
        }
        return true;     
    }


    public function teamUsedMoney($amount,$accountId='')
    {
        $account = DB::table('ba_account')->where('account_id',$accountId)->field('team_id,company_id')->find();
        $companyId =  $account['company_id'];
        $teamId = $account['team_id'];

        if(!empty($companyId)){
            $company = Db::table('ba_company')->where('id',$companyId)->find();
            if($company['prepayment_type'] == 2){
                $usableMoney2 = bcsub((string)$company['used_money'],(string)$amount,2);
                DB::table('ba_company')->where('id',$companyId)->where('used_money',$company['used_money'])->update(['used_money'=>$usableMoney2]);
            }
        }

        if(!empty($teamId))
        {
            $team = Db::table('ba_team')->where('id',$teamId)->find();
            $usableMoney1 = bcsub((string)$team['team_used_money'],(string)$amount,2);
            DB::table('ba_team')->where('id',$teamId)->where('team_used_money',$team['team_used_money'])->update(['team_used_money'=>$usableMoney1]);
        }        
        return ['code'=>1,'msg'=>''];
    }

}
