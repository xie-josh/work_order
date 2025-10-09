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
        $time = date("Y-m-d", time());
        $result =  DB::table('ba_account_consumption')->field('account_id,spend,dollar,date_start,date_stop,admin_id,create_time')->where(['date_start'=>$time])->select()->toArray();
        $chunkResult = array_chunk($result, 1000);
        if(!empty($chunkResult))foreach($chunkResult as $k => $v)
        {
            $count = count($v);
            DB::table('ba_account_consumption_yesterday')->insertAll($v);
            $output->writeln("本次插入".$count."条消耗！！");
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln("消耗插入完毕!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!！");

    }
}
