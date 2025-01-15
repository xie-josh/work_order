<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class FbAccountUpdateTask extends Command
{
    protected function configure()
    {
        $this->setName('FbAccountUpdateTask')
            ->setDescription('FbAccountUpdateTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think FbAccountUpdateTask
        $result = DB::table('ba_fb_bm_token')->select()->toArray();
        $result['account_status'] = 2;
        foreach($result as  $v){
            $jobHandlerClassName = 'app\job\FbAccountUpdate';
            $jobQueueName = 'FbAccountUpdate';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('FbAccountUpdateTask: Scheduled task executed!');
    }
}
