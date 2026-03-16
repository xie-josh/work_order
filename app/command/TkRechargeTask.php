<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class TkRechargeTask extends Command
{
    protected function configure()
    {
        $this->setName('TkRechargeTask')
            ->setDescription('TkRechargeTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think TkRechargeTask
        $recharge = Db::table('ba_recharge')
        ->where('is_apply',2)
        ->where('status',0)
        ->whereNotNull('apply_id')
        ->select()->toArray();
        foreach($recharge as  $k => $v)
        {
            $jobHandlerClassName = 'app\job\TkRecharge';
            $jobQueueName = 'TkRecharge';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('TkRechargeTask: Scheduled task executed!');
    }
}
