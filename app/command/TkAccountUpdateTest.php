<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class TkAccountUpdateTest extends Command
{
    protected function configure()
    {
        $this->setName('TkAccountUpdateTest')
            ->setDescription('TkAccountUpdateTest: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think TkAccountUpdateTest
        
        $notConsumptionStatus = config('basics.NOT_consumption_status');
        $notConsumptionStatus = array_values(array_diff($notConsumptionStatus, [94]));
        $result = DB::table('ba_accountrequest_proposal')
        ->field('account_id')
        ->where('type',2)//tk
        ->whereNotIn('status',$notConsumptionStatus)
        ->select()->toArray();

        foreach($result as  $k => $v)
        {
            $jobHandlerClassName = 'app\job\TkAccountUpdate';
            $jobQueueName = 'TkAccountUpdate';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('TkAccountUpdateTask: Scheduled task executed!');
    }
}
