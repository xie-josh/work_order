<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class AccountReportCheckTask extends Command
{
    protected function configure()
    {
        $this->setName('AccountReportCheckTask')->setDescription('AccountReportCheckTask: Run scheduled tasks');
    }
    //php think AccountReportCheckTask
    protected function execute(Input $input, Output $output)
    {
           $result = DB::table('ba_account_report')->where('status',1)->select()->toArray();
           if(!empty($result))foreach($result as $k => $v)
           {
                    $jobHandlerClassName     = 'app\job\AccountReportCheck';
                    $jobQueueName = 'AccountReportCheck';
                    Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
           }
           // 在这里编写你的定时任务逻辑
           $output->writeln("完毕!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!！");
       
    }

}
