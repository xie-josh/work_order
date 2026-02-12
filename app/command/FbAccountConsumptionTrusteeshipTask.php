<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class FbAccountConsumptionTrusteeshipTask extends Command
{
    protected function configure()
    {
        $this->setName('FbAccountConsumptionTrusteeshipTask')
            ->setDescription('FbAccountConsumptionTrusteeshipTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think FbAccountConsumptionTrusteeshipTask
        //$result = DB::table('ba_fb_bm_token')->where('status',1)->select()->toArray();
        $notConsumptionStatus = config('basics.NOT_consumption_status');
        $notConsumptionStatus = array_values(array_diff($notConsumptionStatus, [94]));
        $result = DB::table('ba_accountrequest_proposal_trusteeship')
        ->alias('accountrequest_proposal_trusteeship')
        ->distinct(true)
        ->field('fb_bm_token.is_token,accountrequest_proposal_trusteeship.id,accountrequest_proposal_trusteeship.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids,accountrequest_proposal_trusteeship.currency,accountrequest_proposal_trusteeship.trusteeship_user')
        ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal_trusteeship.bm_token_id')
        // ->whereNotIn('accountrequest_proposal_trusteeship.status',$notConsumptionStatus)
        ->whereNotNull('accountrequest_proposal_trusteeship.bm_token_id')
        ->where('accountrequest_proposal_trusteeship.trusteeship_type',1)
        ->select()->toArray();
        // dd(DB::table('ba_accountrequest_proposal_trusteeship')->getLastSql());
        foreach($result as  $v){
            $jobHandlerClassName = 'app\job\FbAccountConsumptionTrusteeship';
            $jobQueueName = 'FbAccountConsumptionTrusteeship';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('accountrequest_proposal_trusteeship: Scheduled task executed!');
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
