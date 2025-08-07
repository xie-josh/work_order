<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class FbAccountConsumptionTask extends Command
{
    protected function configure()
    {
        $this->setName('FbAccountConsumptionTask')
            ->setDescription('FbAccountConsumptionTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think FbAccountConsumptionTask
        //$result = DB::table('ba_fb_bm_token')->where('status',1)->select()->toArray();

        $notConsumptionStatus = config('basics.NOT_consumption_status');
        $result = DB::table('ba_accountrequest_proposal')
        ->alias('accountrequest_proposal')
        ->field('fb_bm_token.is_token,accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids,accountrequest_proposal.currency')
        ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
        ->whereNotIn('accountrequest_proposal.status',$notConsumptionStatus)
        ->whereNotNull('accountrequest_proposal.bm_token_id')
        ->select()->toArray();

        foreach($result as  $v){
            $jobHandlerClassName = 'app\job\FbAccountConsumption';
            $jobQueueName = 'FbAccountConsumption';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('FbAccountConsumptionTask: Scheduled task executed!');
    }


    // protected function execute(Input $input, Output $output)
    // {
    //     //php think FbAccountConsumptionTask
    //     //$result = DB::table('ba_fb_bm_token')->where('status',1)->select()->toArray();

    //     $result = DB::table('ba_accountrequest_proposal')
    //     ->alias('accountrequest_proposal')
    //     ->field('accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type')
    //     ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
    //     ->whereNotIn('accountrequest_proposal.status',[0,99])
    //     ->select()->toArray();

    //     $queues = [
    //         'FbAccountConsumption',
    //         'FbAccountConsumption1',
    //         'FbAccountConsumption2',
    //         'FbAccountConsumption3',
    //     ];

    //     foreach($result as $k => $v){
    //         $jobHandlerClassName = 'app\job\FbAccountConsumption';
    //         $jobQueueName = $queues[$k % 4];
    //         Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
    //     }
    //     // 在这里编写你的定时任务逻辑
    //     $output->writeln('FbAccountConsumptionTask: Scheduled task executed!');
    // }
}
