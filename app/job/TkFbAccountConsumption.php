<?php

namespace app\job;

use think\queue\Job;
use think\facade\Db;
use app\services\CardService;
use app\admin\services\TkService;
use think\facade\Queue;

class TkFbAccountConsumption
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue TkFbAccountConsumption
        //sleep(1);
        try {
            $this->accountConsumption($data);
            $job->delete();
        } catch (\Throwable $th) {

                $job->delete();
        }
    }

    public function accountConsumption($params)
    {   
        try {
            $accountId = $params['account_id'];
            $params['stort_time'] = date('Y-m-d', strtotime('-7 days'));
            $params['stop_time'] = date('Y-m-d',time());
            
            $sSTimeList = $this->generateTimeArray($params['stort_time'],$params['stop_time']);
            
            $report = (new TkService())->TikTokReport([]);
            $items = [];
            foreach ($report->iterateReport(
                $accountId,
                $params['stort_time'],
                $params['stop_time']
            ) as $dailyData) {
                if(!empty($dailyData)){
                    $time = date('Y-m-d', strtotime($dailyData['dimensions']['stat_time_day']??0));
                    $items[$time] = $dailyData['metrics']??[];
                } 
            }
            // if(empty($items)){
            //     // DB::table('ba_fb_logs')->insert(
            //     //     ['log_id'=>$accountId??'','type'=>'job_TKAccountConsumption','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            //     // );                
            //     return true;
            // }
            
            DB::table('ba_account_consumption_tk')->where('account_id',$accountId)->whereIn('report_date',$sSTimeList)->delete();
            $company_account_column =  DB::table('ba_account')->where('account_id',$accountId)->column('company_id','account_id');
            $data = [];
            foreach($sSTimeList as $v){
                $consumption = $items[$v]??[];
                if(empty($consumption)){
                    $data[] = [
                        'account_id'=>$accountId,
                        'spend'=>$consumption['spend']??0,
                        'impressions'=>$consumption['impressions']??0,
                        'clicks'=>$consumption['clicks']??0,
                        'report_date'=>$v,
                        'company_id'=>$company_account_column[$accountId]??0,
                        'create_time'=>time(),
                    ];
                }else{
                    $data[] = [
                        'account_id'=>$accountId,
                        'spend'=>$consumption['spend']??0,
                        'impressions'=>$consumption['impressions']??0,
                        'clicks'=>$consumption['clicks']??0,
                        'report_date'=>$v,
                        'company_id'=>$company_account_column[$accountId]??0,
                        'create_time'=>time(),
                    ];
                }
            }
            DB::table('ba_account_consumption_tk')->insertAll($data);
        } catch (\Throwable $th) {
            $logs = '错误info:('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId??'','type'=>'job_TkFbAccountConsumption','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
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
