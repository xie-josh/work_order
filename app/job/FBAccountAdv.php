<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
set_time_limit(3600);

class FBAccountAdv
{
    public function fire(Job $job, $data)
    {
        try {
            //php think queue:listen --queue FBAccountAdv
            $type = $data['type']??0;
            $accountId = $data['account_id'];
            if($type == 1)
            {
                $params = DB::table('ba_accountrequest_proposal')
                ->alias('accountrequest_proposal')
                ->field('accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids')
                ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
                //->where('accountrequest_proposal.account_status',1)
                ->where('accountrequest_proposal.account_id=526969330278713')
                ->find();
                $params['token'] = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);

                $facebookService = new \app\services\FacebookService();

                $params['effective_status'] = ['ACTIVE'];                        
                $campaignsList = $facebookService->getAdsCampaignsList($params);
                if($campaignsList['code'] != 1){
                    DB::table('ba_fb_logs')->insert(
                        ['log_id'=>$accountId,'type'=>'FB_get_ads_campaigns','data'=>json_encode($params),'logs'=>$campaignsList['msg'],'create_time'=>date('Y-m-d H:i:s',time())]
                    );
                }
                $campaignsListData = $campaignsList['data']??[];
                $list = $campaignsListData['data']??[];
                foreach($list as $v)
                {
                    $params['status'] = 'PAUSED';
                    $params['campaigns_id'] = $v['id'];

                    $postAdsCampaigns = $facebookService->postAdsCampaigns($params);
                    if($postAdsCampaigns['code'] != 1){
                        DB::table('ba_fb_logs')->insert(
                            ['log_id'=>$accountId,'type'=>'FB_post_ads_campaigns','data'=>json_encode($params),'logs'=>$postAdsCampaigns['msg'],'create_time'=>date('Y-m-d H:i:s',time())]
                        );
                    }
                }
                
            }
            $job->delete();

        } catch (\Throwable $th) {
            if ($job->attempts() >= 3) $job->delete();
        }
        if ($job->attempts() >= 3)  $job->delete();
        
    }

}
