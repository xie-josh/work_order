<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class LampayCardListTask extends Command
{
    protected function configure()
    {
        $this->setName('LampayCardListTask')
            ->setDescription('LampayCardListTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think LampayCardListTask
        for ($i=1; $i < 100; $i++) { 
            $jobHandlerClassName = 'app\job\CardList';
            $jobQueueName = 'CardList';
            $data = [
                'platform'=>'lampay',
                'id'=>2,
                'pageIndex'=>$i
            ];
            Queue::later(1, $jobHandlerClassName, $data, $jobQueueName);     
        }

    }
}
