<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class CardTransactionsTask extends Command
{
    protected function configure()
    {
        $this->setName('CardTransactionsTask')
            ->setDescription('CardTransactionsTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think CardTransactionsTask
        $result = DB::table('ba_cards')
            ->alias('cards')
            ->field('cards.id,cards.account_id,cards.card_id,card_platform.platform')
            ->leftJoin('ba_card_account card_account','card_account.id=cards.account_id')
            ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
            ->where('cards.is_transactions',0)
            ->where('card_account.status',1)
            ->select()->toArray();

        foreach($result as  $v){
            $jobHandlerClassName = 'app\job\CardTransactions';
            $jobQueueName = 'CardTransactions';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('CardTransactionsTask: Scheduled task executed!');
    }
}
