<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class BmBindingTask extends Command
{
    protected function configure()
    {
        $this->setName('BmBindingTask')
            ->setDescription('BmBindingTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think BmBinding
        $recharge = Db::table('ba_bm')
        ->whereIn('is_apply',[2,3])
        ->whereIn('status',[0,1])
        ->whereIn('dispose_type',[0])
        ->select()->toArray();
        foreach($recharge as  $k => $v)
        {
            $jobHandlerClassName = 'app\job\BmBinding';
            $jobQueueName = 'BmBinding';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('BmBindingTask: Scheduled task executed!');
    }
}
