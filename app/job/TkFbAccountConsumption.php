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

            
            DB::table('ba_account_consumption_tk')->where('account_id',$accountId)->whereIn('report_date',$sSTimeList)->delete();
            $company_account_column =  DB::table('ba_account')->where('account_id',$accountId)->column('company_id','account_id');
            $data = [];
            foreach($sSTimeList as $v){
                $companyId = '';
                foreach($accountTimeList as $v1)
                {
                    if($v >= $v1['strat_open_time'] && $v <= $v1['end_open_time']){
                        $companyId = $v1['company_id'];
                        break;
                    }
                }

                $consumption = $items[$v]??[];
                if(empty($consumption)){
                    $data[] = [
                        'account_id'=>$accountId,
                        'spend'=>$consumption['spend']??0,
                        'impressions'=>$consumption['impressions']??0,
                        'clicks'=>$consumption['clicks']??0,
                        'report_date'=>$v,
                        'company_id'=>$companyId,
                        'create_time'=>time(),
                    ];
                }else{
                    $data[] = [
                        'account_id'=>$accountId,
                        'spend'=>$consumption['spend']??0,
                        'impressions'=>$consumption['impressions']??0,
                        'clicks'=>$consumption['clicks']??0,
                        'report_date'=>$v,
                        'company_id'=>$companyId,
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
        }finally{
            if(!empty($accountTimeList) && isset($accountTimeList[0]))
            {
                $first = $accountTimeList[0];
                $spend = DB::table('ba_account_consumption_tk')->where('account_id',$accountId)
                ->whereBetween('report_date', [$first['strat_open_time'], $first['end_open_time']])->sum('spend');
                DB::table('ba_accountrequest_proposal')->where('account_id', $accountId)->update(
                    [
                        'total_consumption'=>$spend,
                        'pull_spend_time'=>date('Y-m-d H:i:s',time())
                    ]
                );
            }
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
