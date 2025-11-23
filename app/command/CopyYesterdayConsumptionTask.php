<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class CopyYesterdayConsumptionTask extends Command
{
    protected function configure()
    {
        $this->setName('CopyYesterdayConsumptionTask')->setDescription('CopyYesterdayConsumptionTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think CopyYesterdayConsumptionTask
        //$month = date("Y-m", strtotime("-1 month"));   //测试
        $params['stort_time'] = date('Y-m-d', strtotime('-15 days'));
        // $params['stort_time'] = '2024-11-01';
        $params['stop_time'] = date('Y-m-d',time());
        $sSTimeList = $this->generateTimeArray($params['stort_time'],$params['stop_time']);
        DB::table('ba_account_consumption_yesterday')->whereIn('date_start',$sSTimeList)->delete();
        $result =  DB::table('ba_account_consumption')->field('account_id,spend,dollar,date_start,date_stop,company_id,create_time')->whereIn('date_start',$sSTimeList)->select()->toArray();
        $chunkResult = array_chunk($result, 2000);
        if(!empty($chunkResult))foreach($chunkResult as $k => $v)
        {
            $count = count($v);
            DB::table('ba_account_consumption_yesterday')->insertAll($v);
            $output->writeln("本次插入".$count."条消耗！！");
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln("消耗插入完毕!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!！");
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
