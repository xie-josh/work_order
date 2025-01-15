<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class FbAccountUnUpdateTask extends Command
{
    protected function configure()
    {
        $this->setName('FbAccountUnUpdateTask')
            ->setDescription('FbAccountUnUpdateTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think FbAccountUnUpdateTask
        $result = DB::table('ba_fb_bm_token')->where('pull_status',1)->select()->toArray();
        $result['account_status'] = 1;
        foreach($result as  $v){
            $jobHandlerClassName = 'app\job\FbAccountUnUpdate';
            $jobQueueName = 'FbAccountUnUpdate';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('FbAccountUnUpdateTask: Scheduled task executed!');
    }
}
