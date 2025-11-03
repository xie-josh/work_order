<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class AccountPendingRecycleTask extends Command
{
    protected function configure()
    {
        $this->setName('AccountPendingRecycleTask')
            ->setDescription('AccountPendingRecycleTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think AccountPendingRecycleTask
        // 4小时更新一次
        $accountList = DB::table('ba_account')->where('status',4)->field('id,account_id,open_time')->select()->toArray();

        foreach($accountList as $v){
            $jobHandlerClassName = 'app\job\AccountPendingRecycle';
            $jobQueueName = 'AccountPendingRecycle';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }

        
        // 在这里编写你的定时任务逻辑
        $output->writeln('AccountPendingRecycleTask: Scheduled task executed!');
    }
}
