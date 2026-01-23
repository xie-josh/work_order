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
        // $cc = ["165","171","194","220","221","225","233","256","257","268","278"];
        // 4小时更新一次

        $where = [
            ['account.status','=',4],
            ['accountrequest_proposal.status','IN',[1]],
            // ['accountrequest_proposal.admin_id','IN',["165","171","194","220","221","225","233","256","257","268","278","292","368","374","364","403"]],
            ['accountrequest_proposal.account_status','IN',[1,3]],
        ];

        $accountList = DB::table('ba_account')
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where($where)
        ->field('account.id,account.account_id,account.open_time,account.company_id,accountrequest_proposal.recycle_start')
        ->select()->toArray();

        foreach($accountList as $v){
            $jobHandlerClassName = 'app\job\AccountPendingRecycle';
            $jobQueueName = 'AccountPendingRecycle';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }

        
        // 在这里编写你的定时任务逻辑
        $output->writeln('AccountPendingRecycleTask: Scheduled task executed!');
    }
}
