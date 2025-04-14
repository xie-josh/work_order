<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class AccountListBackupTask extends Command
{
    protected function configure()
    {
        $this->setName('AccountListBackupTask')
            ->setDescription('AccountListBackupTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think AccountListBackupTask
        $dateTime = date('Y-m-d');
        $batchSize = 2000;

        $query =  DB::table('ba_accountrequest_proposal');
        $total = $query->count(); 

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();

            $dataList = [];
            foreach($data as $v) {
                $v['backup_time'] = $dateTime;
                unset($v['id']);
                $dataList[] = $v;
            }
                                
            DB::table('ba_accountrequest_proposal_backup')->whereIn('account_id',array_column($data,'account_id'))->where('backup_time', $dateTime)->delete();
            DB::table('ba_accountrequest_proposal_backup')->insertAll($dataList);       
        }         
        
        $output->writeln('AccountListBackupTask: Scheduled task executed!');
    }
}