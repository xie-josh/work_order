<?php

namespace app\job;

use think\queue\Job;
use think\facade\Db;
use app\services\CardService;

class FbAccountConsumption
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue FbAccountConsumption
        //sleep(1);

        try {
            $this->accountConsumption($data);
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

    public function accountConsumption($params)
    {
        try {
            $accountId = $params['account_id'];
            $businessId = $params['business_id']??'';
            $params['stort_time'] = date('Y-m-d', strtotime('-30 days'));
            // $params['stort_time'] = '2024-11-01';
            $params['stop_time'] = date('Y-m-d',time());

            $sSTimeList = $this->generateTimeArray($params['stort_time'],$params['stop_time']);

            if($params['type'] == 1) $token = DB::table('ba_fb_personalbm_token')->where('type',1)->value('token');
            else $token = DB::table('ba_fb_personalbm_token')->where('type',2)->value('token');

            $token = DB::table('ba_fb_personalbm_token')->where('type',1)->value('token');
            if($params['type'] == 2) $token = DB::table('ba_fb_personalbm_token')->where('type',2)->value('token');
            
            if(!empty($token)) $params['token'] = $token;
            
            $result = (new \app\services\FacebookService())->insights($params);
            if(empty($result) || $result['code'] == 0){
                $accountrequestProposal = DB::table('ba_accountrequest_proposal')
                ->field('accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,cards_info.card_id,cards_info.account_id cards_account_id')
                ->alias('accountrequest_proposal')
                ->where('accountrequest_proposal.account_id',$accountId)
                ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                ->find();
                if(!empty($accountrequestProposal) && $accountrequestProposal['is_cards'] == 0 && $accountrequestProposal['card_id']){
                    $result = (new CardService($accountrequestProposal['cards_account_id']))->cardFreeze(['card_id'=>$accountrequestProposal['card_id']]);
                }
                return true;
            }
            
            DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['pull_consumption'=>date('Y-m-d H:i',time())]);
            $accountConsumption = $result['data']['data']??[];
            //if(empty($accountConsumption)) return true;
            $accountConsumption = array_column($accountConsumption,null,'date_start');

            DB::table('ba_account_consumption')->where('account_id',$accountId)->whereIn('date_start',$sSTimeList)->delete();

            $data = [];

            foreach($sSTimeList as $v){
                $consumption = $accountConsumption[$v]??[];
                if(empty($consumption)){
                    $data[] = [
                        'account_id'=>$accountId,
                        'spend'=>0,
                        'date_start'=>$v,
                        'date_stop'=>$v,
                        'create_time'=>time(),
                    ];
                }else{
                    $data[] = [
                        'account_id'=>$accountId,
                        'spend'=>$consumption['spend'],
                        'date_start'=>$consumption['date_start'],
                        'date_stop'=>$consumption['date_stop'],
                        'create_time'=>time(),
                    ];
                }
            }
            DB::table('ba_account_consumption')->insertAll($data);            
        } catch (\Throwable $th) {
            $logs = '错误info('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId??'','type'=>'job_FbAccountConsumption','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
            //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$logs]);
        }
        return true;        
    }

    
    function generateTimeArray($startDate, $endDate) {
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $timeArray = [];
        for ($currentTimestamp = $startTimestamp; $currentTimestamp <= $endTimestamp; $currentTimestamp += 86400) {
            $timeArray[] = date('Y-m-d', $currentTimestamp);
        }
        return $timeArray;
    }
}
