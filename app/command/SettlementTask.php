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

        foreach($adminList as $v)
        {
            $v['start_time'] = date('Y-m-01',time());
            $v['end_time'] = date('Y-m-d',time());
            $jobHandlerClassName = 'app\job\Settlement';
            $jobQueueName = 'Settlement';
            Queue::later(1800, $jobHandlerClassName, $v, $jobQueueName);
        }
        
        $SETTLEMENT_DAYS = config('basics.SETTLEMENT_DAYS');
        if(in_array($time,$SETTLEMENT_DAYS)){
            $data = [];
            $data['start_time'] = date('Y-m-01',time());
            $data['end_time'] = date('Y-m-d',time());
            $data['settlement_time'] = $time;
            $jobHandlerClassName = 'app\job\SettlementSummary';
            $jobQueueName = 'SettlementSummary';
            Queue::later(1800, $jobHandlerClassName, $data, $jobQueueName);
        }

        // 在这里编写你的定时任务逻辑
        $output->writeln('SettlementTask: Scheduled task executed!');
    }
}
