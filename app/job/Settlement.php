<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Queue;

set_time_limit(600);

class Settlement
{
    public function fire(Job $job, $data)
    {
        try {
            //php think queue:listen --queue Settlement --timeout=600

            $taskCount =  Cache::store('redis')->handler()->llen('{queues:FbAccountConsumption}');
            if($taskCount >0){
            
                $jobHandlerClassName = 'app\job\Settlement';
                $jobQueueName = 'Settlement';
                Queue::later(1200, $jobHandlerClassName, $data, $jobQueueName);
                $job->delete();
            }else{
               $this->exportExcel($data);
               $job->delete();
            }
        } catch (\Throwable $th) {
            (new \app\services\Basics())->logs('settlementJobError',$data,$th->getMessage());
        }finally {
            if ($job->attempts() >= 3) $job->delete();
        }
    }    

    function exportExcel($params)
    {

        $batchSize = 5000;
        $processedCount = 0;

        $nickname = $params['nickname'];

        $where = [
            ['consumption.admin_id','=',$params['id']],
            ['consumption.date_start','>=',$params['start_time']],
            ['consumption.date_start','<=',$params['end_time']],
        ];
        $query = DB::table('ba_account_consumption')
            ->field('consumption.account_id,consumption.date_start,consumption.spend,accountrequest_proposal.account_status,accountrequest_proposal.currency,accountrequest_proposal.serial_name')
            ->alias('consumption')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id = consumption.account_id')
            ->order('consumption.date_start','desc')
            ->where($where);

        $query2 = clone $query;
        $total = $query2->count();


        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];

        if($params['prepayment_type'] == 1){
            $prepaymentName ='预付实销';
        }else{
            $prepaymentName = '预付';
        }

        $resultPath = "excel/".date('Ym').'/settlement'.date('d').'/'.$prepaymentName;
        // if(file_exists($resultPath)) unlink($resultPath);

        $folders = (new \app\common\service\Utils)->getExcelFolders($resultPath,0);
        $header = [
            '账户状态',
            '账户名称',
            '账户ID',
            '货币',
            '消耗',
            '开始时间',
            '结束时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $month = date('m',strtotime($params['end_time']));
        $day  = date('d',strtotime($params['end_time']));
        $excelName = $nickname."-{$month}月日消耗-{$month}{$day}";

        $name = $excelName.'.xlsx';

        if($total == 0) return true;

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();
            $dataList = [];
             foreach($data as $v){
                $dataList[]  = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $v['serial_name'],
                    $v['account_id'],
                    $v['currency'],
                    (float)$v['spend'],
                    $v['date_start'],
                    $v['date_start'],
                ];
                $processedCount++;
            }
            $filePath = $excel->fileName($excelName.'.xlsx', 'sheet1')
            ->header($header)
            ->data($dataList);        
        }

        $filePath->setColumn('A:A', 13)
                    ->setColumn('B:B', 55)
                    ->setColumn('C:C', 20)
                    ->setColumn('D:D', 5)
                    ->setColumn('E:E', 10)
                    ->setColumn('F:F', 12)
                    ->setColumn('G:G', 12);

        $excel->output();

        $path = $folders['filePath'].'/'.$name;

        $result = DB::table('ba_settlement')->where(
            [
                ['admin_id','=',$params['id']],
                ['date','=',$params['end_time']],
                ['settlement_time','=',$params['settlement_time']]
            ]
        )->find();
        
        if($result) return true;

        DB::table('ba_settlement')->insert([
            'admin_id'=>$params['id'],
            'date'=>$params['end_time'],
            'settlement_time'=>$params['settlement_time'],
            'url'=>$path,
            'create_time'=>time(),
        ]);
        return true;
    }

}
