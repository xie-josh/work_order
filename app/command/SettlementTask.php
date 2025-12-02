<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class SettlementTask extends Command
{
    protected function configure()
    {
        $this->setName('SettlementTask')
            ->setDescription('SettlementTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think SettlementTask
        $time = date('H');
        $day = date('d');

        $where = [
            ['status','=',1],
            ['settlement_time','=',$time],
        ];
        $companyList = DB::table('ba_company')
        ->field('id,settlement_time,company_name,prepayment_type')
        ->where($where)
        ->select()->toArray();

        $adminCompanyList = DB::table('ba_admin')->where('type',2)->field('company_id,nickname')->select()->toArray();
        $adminCompanyList = array_column($adminCompanyList,'nickname','company_id');

        if($day <= 5) $startTime = date('Y-m-01',strtotime('-1 month'));
        else $startTime = date('Y-m-01',time());
        $endTime = date('Y-m-d',time());

        foreach($companyList as $v)
        {
            $v['start_time'] = $startTime;
            $v['end_time'] = $endTime;
            $v['admin_nickname'] = $adminCompanyList[$v['id']]??'未找到用户';
            $jobHandlerClassName = 'app\job\Settlement';
            $jobQueueName = 'Settlement';
            Queue::later(1800, $jobHandlerClassName, $v, $jobQueueName);
        }
        
        $SETTLEMENT_DAYS = config('basics.SETTLEMENT_DAYS');
        if(in_array($time,$SETTLEMENT_DAYS)){
            $data = [];
            $data['start_time'] = $startTime;
            $data['end_time'] = $endTime;
            $data['settlement_time'] = $time;
            $jobHandlerClassName = 'app\job\SettlementSummary';
            $jobQueueName = 'SettlementSummary';
            Queue::later(1800, $jobHandlerClassName, $data, $jobQueueName);
        }

        // 在这里编写你的定时任务逻辑
        $output->writeln('SettlementTask: Scheduled task executed!');
    }
}
