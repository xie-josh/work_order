<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class CardInfoTask extends Command
{
    protected function configure()
    {
        $this->setName('CardInfoTask')
            ->setDescription('CardInfoTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think CardInfoTask
        $result = DB::table('ba_cards')
            ->alias('cards')
            ->field('cards.id,cards.account_id,cards.card_id')
            ->leftJoin('ba_card_account card_account','card_account.id=cards.account_id')
            ->where('cards.is_info',0)
            ->where('card_account.status',1)
            ->select()->toArray();

        foreach($result as  $v){
            $jobHandlerClassName = 'app\job\CardInfo';
            $jobQueueName = 'CardInfo';
            Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
        }
        // 在这里编写你的定时任务逻辑
        $output->writeln('CardInfoTask: Scheduled task executed!');
    }
}
