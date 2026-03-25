<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;
use app\admin\services\Backend;
use app\admin\services\TkService;

class TkRechargeHSTask extends Command
{
    protected function configure()
    {
        $this->setName('TkRechargeHSTask')
            ->setDescription('TkRechargeHSTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think TkRechargeHSTask
        $list = DB::table('ba_recharge')->where('is_hs',1)->field('account_id')->where('status',1)->select()->toArray();
        foreach($list as $v)
        {
            $jobHandlerClassName = 'app\job\AccountRecycle';
            $jobQueueName = 'AccountRecycle';
            Queue::later(1, $jobHandlerClassName, ['account_id'=>$v['account_id'],'status'=>98], $jobQueueName);
        }
    }
}
