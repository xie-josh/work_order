<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Queue;

set_time_limit(600);

class SettlementSummary
{
    public function fire(Job $job, $data)
    {
        try {
            //php think queue:listen --queue SettlementSummary --timeout=1800

            $taskCount =  Cache::store('redis')->handler()->llen('{queues:FbAccountConsumption}');
            if($taskCount >0){
            
                $jobHandlerClassName = 'app\job\SettlementSummary';
                $jobQueueName = 'SettlementSummary';
                Queue::later(1200, $jobHandlerClassName, $data, $jobQueueName);
                $job->delete();
            }else{
               $this->exportExcel($data);
               $job->delete();
            }
        } catch (\Throwable $th) {
            (new \app\services\Basics())->logs('settlementSummaryJobError',$data,$th->getMessage());
        }finally {
            if ($job->attempts() >= 3) $job->delete();
        }
    }    

    function exportExcel($params)
    {

        $batchSize = 5000;
        $processedCount = 0;

        $where = [
            ['consumption.date_start','>=',$params['start_time']],
            ['consumption.date_start','<=',$params['end_time']],
        ];
        $query = DB::table('ba_account_consumption')
            ->field('consumption.admin_id,consumption.account_id,consumption.date_start,consumption.spend,accountrequest_proposal.account_status,accountrequest_proposal.currency,accountrequest_proposal.serial_name')
            ->alias('consumption')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id = consumption.account_id')
            ->where($where);

        $query2 = clone $query;
        $total = $query2->count();

        $adminList = Db::table('ba_admin')->where('status',1)->field('id,nickname')->select()->toArray();
        $adminList = array_column($adminList,'nickname','id');

        $prepaymentName ='预付实销';

        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];
        $folders = (new \app\common\service\Utils)->getExcelFolders("excel/".date('Ym').'/settlement'.date('d').'/'.$prepaymentName,0);
        $header = [
            '账户状态',
            '账户名称',
            '账户ID',
            '货币',
            '消耗',
            '归属用户',
            '开始时间',
            '结束时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);


        $month = date('m',strtotime($params['end_time']));
        $day  = date('d',strtotime($params['end_time']));
        $excelName = "{$month}月{$day}日消耗-{$params['settlement_time']}";

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
                    $adminList[$v['admin_id']]??'未找到用户',
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
                    ->setColumn('F:F', 13)
                    ->setColumn('G:G', 12)
                    ->setColumn('H:H', 12);

        $excel->output();

        $path = $folders['filePath'].'/'.$name;

        $result = DB::table('ba_settlement')->where(
            [
                ['admin_id','null','NULL'],
                ['date','=',$params['end_time']],
                ['settlement_time','=',$params['settlement_time']]
            ]
        )->find();
        
        if($result) return true;

        DB::table('ba_settlement')->insert([
            'date'=>$params['end_time'],
            'settlement_time'=>$params['settlement_time'],
            'url'=>$path,
            'create_time'=>time(),
        ]);
        return true;
    }

}
