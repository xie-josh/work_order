<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class SettlementTask extends Command
{
    protected function configure()
    {
        $this->setName('SettlementTask')
            ->setDescription('SettlementTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think SettlementTask
        $time = date('H');
        $day = date('d');

        $where = [
            ['admin_group_access.group_id','in',[3]],
            ['admin.status','=',1],
            ['admin.settlement_time','=',$time],
        ];
        $adminList = DB::table('ba_admin')->alias('admin')
        ->field('admin.id,admin.settlement_time,admin.nickname,admin.prepayment_type')
        ->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')
        ->where($where)
        ->select()->toArray();

        if($day <= 5) $startTime = date('Y-m-01',strtotime('-1 month'));
        else $startTime = date('Y-m-01',time());
        $endTime = date('Y-m-d',time());

        foreach($adminList as $v)
        {
            $v['start_time'] = $startTime;
            $v['end_time'] = $endTime;
            $jobHandlerClassName = 'app\job\Settlement';
            $jobQueueName = 'Settlement';
            Queue::later(1800, $jobHandlerClassName, $v, $jobQueueName);
        }
        
        $SETTLEMENT_DAYS = config('basics.SETTLEMENT_DAYS');
        if(in_array($time,$SETTLEMENT_DAYS)){
            $data = [];
            $data['start_time'] = $startTime;
            $data['end_time'] = $endTime;
            $data['settlement_time'] = $time;
            $jobHandlerClassName = 'app\job\SettlementSummary';
            $jobQueueName = 'SettlementSummary';
            Queue::later(1800, $jobHandlerClassName, $data, $jobQueueName);
        }

        // 在这里编写你的定时任务逻辑
        $output->writeln('SettlementTask: Scheduled task executed!');
    }
}
