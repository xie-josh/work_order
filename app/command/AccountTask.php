<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class AccountTask extends Command
{
    protected function configure()
    {
        $this->setName('AccountTask')
            ->setDescription('AccountTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think AccountTask
        $account = Db::table('ba_admin')->where([['id','<>',1]])->update(['account_number'=>0,'is_account'=>2]);

        // 在这里编写你的定时任务逻辑
        $output->writeln('AccountTask: Scheduled task executed!');
    }
}
