<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
set_time_limit(3600);

class AccountReport
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountReport
        try {
            $reportId = $data['report_id'];
            $accountId = $data['account_id'];
            $params['business_id'] = $data['business_id'];
            $params['account_id'] = $data['account_id'];
            $params['personalbm_token_ids'] = $data['personalbm_token_ids'];
            $currency  =  $data['currency']??'';
            $self_id  =  $data['self_id']??0;
            $params['stort_time'] = $data['create_report_time'];
            // $params['stort_time'] = '2024-11-01';
            $params['stop_time'] = $data['end_report_time'];

            $sSTimeList = $this->generateTimeArray($params['stort_time'],$params['stop_time']);

            $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);
            
            if(!empty($token)) $params['token'] = $token;
            
            $result = (new \app\services\FacebookService())->insights3($params);
            if(!empty($result) && in_array($result['code'],[0,4,5]))
            {
                DB::table('ba_account_consumption_test2')->insert(['account_id'=>$accountId,'report_id'=>$reportId,'self_status'=>"异常045"]);
                DB::table('ba_account_report_detali')->where('id',$self_id)->update(['status'=>2]);
                $job->delete();
                return true;
            }
            $accountConsumption = $result['data']['data']??[];
            if(empty($accountConsumption))
            {
                DB::table('ba_account_consumption_test2')->insert(['account_id'=>$accountId,'report_id'=>$reportId]);
                DB::table('ba_account_report_detali')->where('id',$self_id)->update(['status'=>2]);
                $job->delete();
                return true;
            }
            $exchangeRate = [];
            if(!empty($currency) && $currency != 'USD' && $currency != '其他')
            {
                $exchangeRate = DB::table('ba_exchange_rate')->whereIn('time',$sSTimeList)->where('currency',$currency)->field('time,rate')->select()->toArray();
                $exchangeRate = array_column($exchangeRate,'rate','time');
            }

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

            $data = [];
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
                    'report_id'=>$reportId,
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
            }

            DB::table('ba_account_consumption_test2')->insertAll($data);

            if($self_id)
            {
                DB::table('ba_account_report_detali')->where('id',$self_id)->update(
                    [
                        'status'=>2
                    ]
                );
            } 
            $job->delete();
        } catch (\Throwable $th) 
        {
            (new \app\services\Basics())->logs('AccountReportJobError',$data,$th->getMessage());
            $logs = '错误info('.$reportId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId??'','type'=>'job_AccountReport','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );$job->delete();
            // DB::table('ba_account')->where('account_id',$accountId)->update(['comment'=>$th->getMessage()]);
        }
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

    function generateTimeArray($startDate, $endDate) {
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $timeArray = [];
        for ($currentTimestamp = $startTimestamp; $currentTimestamp <= $endTimestamp; $currentTimestamp += 86400) {
            $timeArray[] = date('Y-m-d', $currentTimestamp);
        }
        return $timeArray;
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
