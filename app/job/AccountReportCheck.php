<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
set_time_limit(3600);

class AccountReportCheck
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue AccountReportCheck
        try {
            $id = $data['id'];
            $params['stort_time'] = $data['create_report_time'];
            $params['stop_time'] = $data['end_report_time'];
            $result =DB::table('ba_account_report_detali')->where('report_id',$id)->where('status',1)->find();
            if(!empty($result)){
                $job->delete();
                return true;
            }
            $query =DB::table('ba_account_consumption_test2')->where('report_id',$id);//->select()->toArray();
            $query2 = clone $query;
            $total = $query2->count();
            $resultPath = "excel/".date('Ym').'/reportcheck';
            // if(file_exists($resultPath)) unlink($resultPath);
    
            $folders = (new \app\common\service\Utils)->getExcelFolders($resultPath,0);
            $header = [
                '公司昵称',
                '账户状态',
                '广告账户ID',
                '日期',
                '广告系列名称',
                '广告系列ID',
                '覆盖人数',
                '展示次数',
                '频次',
                '花费金额',
                '链接点击量',
                'CPC',
                '购物次数',
                'CPP',
                'ROAS',
                '完成注册次数',
                '注册成本',
            ];
    
            $config = [
                'path' => $folders['path']
            ];
            $excel  = new \Vtiful\Kernel\Excel($config);
            $month = date('y-m-d',strtotime($params['stort_time']));
            $day  = date('y-m-d',strtotime($params['stop_time']));
            $thistime  = date('Y-m-d H:i:s');
            $excelName = "{$thistime}~广告消耗";
            $name = $excelName.'.xlsx';
            if($total == 0){
                $job->delete();
                return true;
            }
            // $result =DB::table('ba_account_report_detali')->where('report_id',$id)->where('status',1)->find();
            $companyArr =DB::table('ba_admin')->where('type',2)->column('nickname','company_id');
            $accountStatus = [0=>'不可用',1=>'活跃',2=>'封户',3=>'待支付'];
            $batchSize = 2000;
            for ($offset = 0; $offset < $total; $offset += $batchSize) {
                $data = $query->limit($offset, $batchSize)->select()->toArray();
                $accountIdsArr = array_column($data,'account_id');
                $statusArr = DB::table('ba_accountrequest_proposal')->whereIn('account_id',array_unique($accountIdsArr))->column('account_status','account_id');
                $dataList = [];
                foreach($data as $v){
                    $status = $statusArr[$v['account_id']]??'963';
                    $dataList[] = [
                            $companyArr[$v['company_id']]??'无',
                            $accountStatus[$status]??'无',
                            $v['account_id']??'',
                            $v['date_start']??'',
                            $v['campaign_name']??'',
                            $v['campaign_id']??'',
                            $v['reach']??'',
                            $v['impressions']??'',
                            $v['frequency']??'',
                            $v['spend']??'',
                            $v['clicks']??'',
                            $v['cpc']??'',
                            $v['purchase']??'',
                            $v['cost_per_purchase']??'',
                            $v['roas']??'',
                            $v['reg']??'',
                            $v['cost_per_reg']??'',
                        ];
               }
               $filePath = $excel->fileName($excelName.'.xlsx', 'sheet1')
                ->header($header)
                ->data($dataList);   
            }

            $filePath->setColumn('A:A', 13)
            ->setColumn('B:B', 55)
            ->setColumn('C:C', 20)
            ->setColumn('D:D', 60)
            ->setColumn('E:E', 40)
            ->setColumn('F:F', 12)
            ->setColumn('G:G', 12)
            ->setColumn('H:H', 12)
            ->setColumn('I:I', 12)
            ->setColumn('J:J', 12)
            ->setColumn('K:K', 12)
            ->setColumn('L:L', 12)
            ->setColumn('M:M', 12)
            ->setColumn('N:N', 12)
            ->setColumn('O:O', 12)
            ->setColumn('P:P', 12)
            ->setColumn('Q:Q', 12);

            $excel->output();

            $path = $folders['filePath'].'/'.$name;
            
            if($id)
            {
                DB::table('ba_account_report')->where('id',$id)->update(
                    [
                        'status'=>2,
                        'file_url'=>$path,
                    ]
                );
            } 
            $job->delete();
        } catch (\Throwable $th) 
        {
            (new \app\services\Basics())->logs('AccountReportCheckJob',$data,$th->getMessage());
            $logs = '错误info('.$reportId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$accountId??'','type'=>'job_AccountReportCheck','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );$job->delete();
            // DB::table('ba_account')->where('account_id',$accountId)->update(['comment'=>$th->getMessage()]);
        }
        return true;     
    }

}
