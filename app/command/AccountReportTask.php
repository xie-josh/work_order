<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class AccountReportTask extends Command
{
    protected function configure()
    {
        $this->setName('AccountReportTask')->setDescription('AccountReportTask: Run scheduled tasks');
    }
    //php think AccountReportTask
    protected function execute(Input $input, Output $output)
    {
           $result = DB::table('ba_account_report')->where('status',1)->select()->toArray();
           if(!empty($result))foreach($result as $kk => $vv)
           {
                $Id = $vv['id'];
                $c_time = $vv['create_report_time'];
                $n_time = $vv['end_report_time'];
                $accountids = explode(',',$vv['account_ids']);
                $notConsumptionStatus = config('basics.NOT_consumption_status');
                $notConsumptionStatus = array_values(array_diff($notConsumptionStatus, [94]));
                $result2 = DB::table('ba_accountrequest_proposal')
                ->alias('accountrequest_proposal')
                ->field('detali.report_id,detali.id self_id,fb_bm_token.is_token,accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids,accountrequest_proposal.currency')
                ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
                ->leftJoin('ba_account_report_detali detali','detali.account_id=accountrequest_proposal.account_id')
                ->where('detali.report_id',$Id)
                ->where('detali.status',1)
                ->whereNotIn('accountrequest_proposal.status',$notConsumptionStatus)
                ->whereNotNull('accountrequest_proposal.bm_token_id')
                ->select()->toArray();
                foreach($result2 as $k => $v)
                {
                    $v['create_report_time'] = $vv['create_report_time'];
                    $v['end_report_time']    = $vv['end_report_time'];
                    $jobHandlerClassName     = 'app\job\AccountReport';
                    $jobQueueName = 'AccountReport';
                    Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
                }
           }


           // 在这里编写你的定时任务逻辑
           $output->writeln("完毕!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!！");
       
    }

}
