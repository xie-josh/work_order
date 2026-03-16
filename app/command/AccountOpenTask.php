<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class AccountOpenTask extends Command
{
    protected function configure()
    {
        $this->setName('AccountOpenTask')
            ->setDescription('AccountOpenTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think AccountOpen
        $recharge = Db::table('ba_account')
        ->whereIn('is_apply',[2])
        ->where('status',3)
        ->select()->toArray();
        foreach($recharge as  $k => $v)
        {
            $jobHandlerClassName = 'app\job\AccountOpen';
            $jobQueueName = 'AccountOpen';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('AccountOpenTask: Scheduled task executed!');
    }
}
