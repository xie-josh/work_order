<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class AuditExceptionTask extends Command
{
    protected function configure()
    {
        $this->setName('AuditExceptionTask')
            ->setDescription('AuditExceptionTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think AuditExceptionTask
        $list = (new \app\admin\services\auth\AuditException())->consumptionReconciliation();
        foreach ($list as $item) {
            if($item['used_money'] -  $item['amount'] > 10 || $item['used_money'] -  $item['amount'] < -10) DB::table('ba_audit_exception')->insert([
                    'admin_id'=>$item['admin_id'],
                    'nickname'=>$item['nickname'],
                    'money'=>$item['money'],
                    'used_money'=>$item['used_money'],
                    'amount'=>$item['amount'],
                    'fb_consume'=>$item['fb_consume'],
                    'open_money'=>$item['open_money'],
                    'recharge'=>$item['recharge'],
                    'withdraw'=>$item['withdraw'],
                    'transfer'=>$item['transfer'], 
                    'recycle_open_money'=>$item['recycle_open_money'],
                    'recycle_recharge'=>$item['recycle_recharge'],
                    'recycle_withdraw'=>$item['recycle_withdraw'],
                    'recycle_transfer'=>$item['recycle_transfer'],
                    'create_time'=>time()
                ]
            );                                    
        }

        // 在这里编写你的定时任务逻辑
        $output->writeln('AuditExceptionTask: Scheduled task executed!');
    }
}
