<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class CardListTask extends Command
{
    protected function configure()
    {
        $this->setName('CardListTast')
            ->setDescription('CardListTast: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think CardListTast
        $account = Db::table('ba_card_account')
        ->alias('card_account')
        ->field('card_account.*,card_platform.name,card_platform.platform')
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('card_account.status',1)
        ->whereNotIn('card_account.card_platform_id',2)
        ->select()->toArray();

        foreach($account as  $v){
            $jobHandlerClassName = 'app\job\CardList';
            $jobQueueName = 'CardList';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('CardListTast: Scheduled task executed!');
    }
}
