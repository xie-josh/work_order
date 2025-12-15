<?php

namespace app\job;

use think\queue\Job;
use think\facade\Db;
use app\services\CardService;
use think\facade\Queue;

class FbAccountConsumptionTest2
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue FbAccountConsumptionTest2
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
            $currency  =  $params['currency']??'';
            $params['stort_time'] = date('Y-m-d', strtotime('-7 days'));
            // $params['stort_time'] = '2024-11-01';
            $params['stop_time'] = date('Y-m-d',time());

            $sSTimeList = $this->generateTimeArray($params['stort_time'],$params['stop_time']);
            $this->fbSpendCap($params);

            // if($params['type'] == 1) $token = DB::table('ba_fb_personalbm_token')->where('type',1)->value('token');
            // else $token = DB::table('ba_fb_personalbm_token')->where('type',2)->value('token');

            // $token = DB::table('ba_fb_personalbm_token')->where('type',1)->value('token');
            // if($params['type'] == 2) $token = DB::table('ba_fb_personalbm_token')->where('type',2)->value('token');

            // $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(1,'',$params['id']);
            // if($params['type'] == 2) $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(2,'',$params['id']);

            $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);            
            
            if(!empty($token)) $params['token'] = $token;
            
            $result = (new \app\services\FacebookService())->insights3($params);
            if(!empty($result) && $result['code'] == 4){
                $jobHandlerClassName = 'app\job\FbAccountConsumption';
                $jobQueueName = 'FbAccountConsumption';
                Queue::later(1200, $jobHandlerClassName, $params, $jobQueueName);
                return true;
            }

            if(empty($result) || $result['code'] == 0){
                //DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['account_status'=>0,'processing_status'=>0,'pull_account_status'=>date('Y-m-d H:i',time())]);
                return true;
            }
            
            if(empty($result) || $result['code'] == 5){
                $accountrequestProposal = DB::table('ba_accountrequest_proposal')
                ->field('accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,cards_info.card_id,cards_info.account_id cards_account_id,cards_info.card_status')
                ->alias('accountrequest_proposal')
                ->where('accountrequest_proposal.account_id',$accountId)
                ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                ->find();
                DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['account_status'=>0,'processing_status'=>0,'pull_account_status'=>date('Y-m-d H:i',time())]);
                if(!empty($accountrequestProposal) && $accountrequestProposal['is_cards'] == 0 && $accountrequestProposal['card_id'] && $accountrequestProposal['card_status'] != 'frozen'){
                    $result = (new CardService($accountrequestProposal['cards_account_id']))->cardFreeze(['card_id'=>$accountrequestProposal['card_id']]);
                    if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('card_id',$accountrequestProposal['card_id'])->update(['card_status'=>$result['data']['cardStatus']]);
                }
                (new \app\admin\services\card\Cards())->allCardFreeze($accountId);
                return true;
            }
            
            // DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['pull_consumption'=>date('Y-m-d H:i',time())]);
            $accountConsumption = $result['data']['data']??[];
            //if(empty($accountConsumption)) return true;
            // $accountConsumption = array_column($accountConsumption,null,'date_start');

            DB::table('ba_account_consumption_test')->where('account_id',$accountId)->whereIn('date_start',$sSTimeList)->delete();


            $accountList = DB::table('ba_account')->where('account_id',$accountId)->field('account_id,open_time,admin_id,is_keep,keep_succeed,company_id')->where('status',4)->select()->toArray();
            $accountRecycleList = DB::table('ba_account_recycle')->where('account_id',$accountId)->field('account_id,open_time,admin_id,company_id')->where('status',4)->order('open_time','asc')->select()->toArray();

            $accountInfo = $accountList[0]??[];

            $accountList = array_merge($accountRecycleList,$accountList);

            $accountTimeList = [];
            foreach($accountList as $k => &$item)
            {                    
                $item['strat_open_time'] = date('Y-m-d',$item['open_time']);
                $item['end_open_time'] = '';
                if(isset($accountList[$k+1])) $item['end_open_time'] = date('Y-m-d',$accountList[$k+1]['open_time']);
                else $item['end_open_time'] = date('Y-m-d',strtotime('+1 day',time()));
                $accountTimeList[] = $item;
            }
            $accountTimeList = array_reverse($accountList);    
            
            $exchangeRate = [];
            if(!empty($currency) && $currency != 'USD' && $currency != '其他')
            {
                $exchangeRate = DB::table('ba_exchange_rate')->whereIn('time',$sSTimeList)->where('currency',$currency)->field('time,rate')->select()->toArray();
                $exchangeRate = array_column($exchangeRate,'rate','time');
            }

            $data = [];
         

            // foreach($sSTimeList as $v){
            foreach($accountConsumption as $consumption)
            {

                $date_start = $consumption['date_start'];
                $companyId = '';
                $dollar = 0;
                foreach($accountTimeList as $v1)
                {
                    if($date_start >= $v1['strat_open_time'] && $date_start <= $v1['end_open_time']){
                        $companyId = $v1['company_id'];
                        break;
                    }
                }

                if($currency == 'USD')
                {
                    $dollar = $consumption['spend'];
                }else{
                    if(empty($currency) || $currency == '其他')
                    {
                        $dollar = 0;
                    }elseif(isset($exchangeRate[$date_start]) && $consumption['spend'] != 0){
                        $dollar =  bcdiv((string)$consumption['spend'],(string)$exchangeRate[$date_start],4);
                    }
                }               
                

                $actions = $consumption["actions"] ?? [];
                $costs = $consumption["cost_per_action_type"] ?? [];
                $av = $consumption["action_values"] ?? [];

                // 常用指标
                $purchase = $this->extract_action($actions, "purchase");
                $reg = $this->extract_action($actions, "complete_registration");
                $clicks = $this->extract_action($actions, "link_click");

                $cost_per_purchase = $this->extract_cost($costs, "purchase");
                $cost_per_reg = $this->extract_cost($costs, "complete_registration");

                $roas = $this->extract_action_value($av, "purchase");                                        

                $data[] = [
                    'account_id'=>$accountId,
                    'spend'=>$consumption['spend'],
                    'dollar'=>$dollar,
                    'date_start'=>$consumption['date_start'],
                    'date_stop'=>$consumption['date_stop'],
                    'company_id'=>$companyId,
                    'create_time'=>time(),
                    'campaign_name'=>$consumption["campaign_name"] ?? null,
                    'campaign_id'=>$consumption["campaign_id"] ?? null,
                    'reach'=>intval($consumption["reach"] ?? 0),
                    'impressions'=>intval($consumption["impressions"] ?? 0),
                    'frequency'=>floatval($consumption["frequency"] ?? 0),
                    'clicks'=>intval($clicks),
                    'cpc'=>floatval($consumption["cpc"] ?? 0),
                    'purchase'=>intval($purchase),
                    'cost_per_purchase'=>$cost_per_purchase,
                    'roas'=>$roas,
                    'reg'=>intval($reg),
                    'cost_per_reg'=>$cost_per_reg,
                    'tt'=>json_encode($consumption)
                ];

            
                // $consumption = $accountConsumption[$v]??[];
                
                // if(empty($consumption)){
                //     $data[] = [
                //         'account_id'=>$accountId,
                //         'spend'=>0,
                //         'dollar'=>$dollar,
                //         'date_start'=>$v,
                //         'date_stop'=>$v,
                //         'company_id'=>$companyId,
                //         'create_time'=>time(),
                //         'campaign_name'=>'',
                //         'campaign_id'=>'',
                //         'reach'=>'',
                //         'impressions'=>'',
                //         'frequency'=>'',
                //         'clicks'=>'',
                //         'cpc'=>'',
                //         'purchase'=>'',
                //         'cost_per_purchase'=>'',
                //         'roas'=>'',
                //         'reg'=>'',
                //         'cost_per_reg'=>'',
                //     ];
                // }else{
                //     if($currency == 'USD')
                //     {
                //         $dollar = $consumption['spend'];
                //     }else{
                //         if(empty($currency) || $currency == '其他')
                //         {
                //             $dollar = 0;
                //         }elseif(isset($exchangeRate[$v]) && $consumption['spend'] != 0){
                //            $dollar =  bcdiv((string)$consumption['spend'],(string)$exchangeRate[$v],4);
                //         }
                //     }               
                    

                //     $actions = $consumption["actions"] ?? [];
                //     $costs = $consumption["cost_per_action_type"] ?? [];
                //     $av = $consumption["action_values"] ?? [];

                //     // 常用指标
                //     $purchase = $this->extract_action($actions, "purchase");
                //     $reg = $this->extract_action($actions, "complete_registration");
                //     $clicks = $this->extract_action($actions, "link_click");

                //     $cost_per_purchase = $this->extract_cost($costs, "purchase");
                //     $cost_per_reg = $this->extract_cost($costs, "complete_registration");

                //     $roas = $this->extract_action_value($av, "purchase");                                        


                //     $data[] = [
                //         'account_id'=>$accountId,
                //         'spend'=>$consumption['spend'],
                //         'dollar'=>$dollar,
                //         'date_start'=>$consumption['date_start'],
                //         'date_stop'=>$consumption['date_stop'],
                //         'company_id'=>$companyId,
                //         'create_time'=>time(),
                //         'campaign_name'=>$r["campaign_name"] ?? null,
                //         'campaign_id'=>$r["campaign_id"] ?? null,
                //         'reach'=>intval($r["reach"] ?? 0),
                //         'impressions'=>intval($r["impressions"] ?? 0),
                //         'frequency'=>floatval($r["frequency"] ?? 0),
                //         'clicks'=>intval($clicks),
                //         'cpc'=>floatval($r["cpc"] ?? 0),
                //         'purchase'=>intval($purchase),
                //         'cost_per_purchase'=>$cost_per_purchase,
                //         'roas'=>$roas,
                //         'reg'=>intval($reg),
                //         'cost_per_reg'=>$cost_per_reg,
                //     ];
                // }
            }
            // dd($data);
            // $this->fbSpendCap($params);
            DB::table('ba_account_consumption_test')->insertAll($data);
            
            // if(!empty($accountInfo)){
            //     if($accountInfo['is_keep'] == 1 && $accountInfo['keep_succeed'] == 0) $this->addDelete($accountId,$currency,$accountInfo['open_time'],$accountInfo['admin_id']);                
            // }
            
        } catch (\Throwable $th) {
            // dd($th->getMessage(),$th->getLine());
            $logs = '错误info('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId??'','type'=>'job_FbAccountConsumption_test2','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
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

    function fbSpendCap($params)
    {
        try {
            $accountrequestProposal = $params;
            
            $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($accountrequestProposal['personalbm_token_ids']);
            if(!empty($token)) $accountrequestProposal['token'] = $token;

            $FacebookService = new \app\services\FacebookService();
            $result = $FacebookService->adAccounts($accountrequestProposal);
            if($result['code'] != 1) throw new \Exception($result['msg']);
            DB::table('ba_accountrequest_proposal')->where('account_id', $accountrequestProposal['account_id'])->update(
                [
                    'spend_cap'=>$result['data']['spend_cap'],
                    'amount_spent'=>$result['data']['amount_spent'],
                    'pull_spend_time'=>date('Y-m-d H:i:s',time())
                ]
            );

            //code...
        } catch (\Throwable $th) {
            $logs = json_encode($th->getMessage());
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountrequestProposal['account_id'],'type'=>'job_fb_spend_cap','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
        }
        return true;
    }

    function addDelete($accountId,$currency,$stratOpenTime,$adminId)
    {
        try {
            $spend = DB::table('ba_account_consumption_test')->where('account_id',$accountId)->where('date_start','>=',$stratOpenTime)->sum('spend');
            if($spend > 0){
                $currencyRate = config('basics.currencyRate');
                if(!empty($currencyRate[$currency])){
                    $spend = bcmul((string)$spend, $currencyRate[$currency],2);
                }

                if($spend > 11){
                    $id = DB::table('ba_recharge')->insertGetId(
                        [
                            'account_id'=>$accountId,
                            'type'=>4,
                            'admin_id'=>$adminId,
                            'create_time'=>time()
                        ]
                    );
                    DB::table('ba_account_return')->insert([
                        'account_id'=>$accountId,
                        'type'=>6,
                        'create_time'=>time()
                    ]);
                    $this->addDeleteJob($id);
                }
            }
        } catch (\Throwable $th) {
            $params = [
                $accountId,$currency,$stratOpenTime,$adminId
            ];
            $logs = json_encode($th->getMessage());
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId,'type'=>'job_fb_add_delete','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
        }
        return true;
    }

    public function addDeleteJob($id)
    {
        $jobHandlerClassName = 'app\job\AccountSpendDelete';
        $jobQueueName = 'AccountSpendDelete';
        Queue::later(10, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }
    

    function extract_action($actions, $t)
    {
        if (empty($actions)) {
            return 0;
        }

        foreach ($actions as $a) {
            if (($a["action_type"] ?? null) === $t) {
                return floatval($a["value"] ?? 0);
            }
        }
        return 0;
    }


    /**
     * 获取 cost_per_action
     */
    function extract_cost($costs, $t)
    {
        if (empty($costs)) {
            return 0;
        }

        foreach ($costs as $c) {
            if (($c["action_type"] ?? null) === $t) {
                return floatval($c["value"] ?? 0);
            }
        }
        return 0;
    }


    /**
     * 获取 action_value（ROAS）
     */
    function extract_action_value($values, $t)
    {
        if (empty($values)) {
            return 0;
        }

        foreach ($values as $v) {
            if (($v["action_type"] ?? null) === $t) {
                return floatval($v["value"] ?? 0);
            }
        }
        return 0;
    }
}
