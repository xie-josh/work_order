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
            $params['stort_time'] = $data['create_report_time'];
            // $params['stort_time'] = '2024-11-01';
            $params['stop_time'] = $data['end_report_time'];

            $sSTimeList = $this->generateTimeArray($params['stort_time'],$params['stop_time']);

            $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);            
            
            if(!empty($token)) $params['token'] = $token;
            
            $result = (new \app\services\FacebookService())->insights3($params);
            if(!empty($result) && $result['code'] == 4){
                $jobHandlerClassName = 'app\job\AccountReport';
                $jobQueueName = 'AccountReport';
                Queue::later(1200, $jobHandlerClassName, $params, $jobQueueName);
                return true;
            }
            if(empty($result) || $result['code'] == 0){
                return true;
            }
            
            if(empty($result) || $result['code'] == 5){
                return true;
            }
            $accountConsumption = $result['data']['data']??[];
            $exchangeRate = [];
            if(!empty($currency) && $currency != 'USD' && $currency != '其他')
            {
                $exchangeRate = DB::table('ba_exchange_rate')->whereIn('time',$sSTimeList)->where('currency',$currency)->field('time,rate')->select()->toArray();
                $exchangeRate = array_column($exchangeRate,'rate','time');
            }
            $data = [];
            foreach($accountConsumption as $consumption)
            {
                $date_start = $consumption['date_start'];

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

            if($reportId)
            {
                DB::table('ba_account_report_detali')->where('id',$reportId)->update(
                    [
                        'status'=>2
                    ]
                );
            }
        } catch (\Throwable $th) 
        {
            (new \app\services\Basics())->logs('AccountReportJobError',$data,$th->getMessage());
            $logs = '错误info('.$reportId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId??'','type'=>'job_AccountReport','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
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
