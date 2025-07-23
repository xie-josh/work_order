<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\services\RatesService;

class GetRateTask extends Command
{
    protected function configure()
    {
        $this->setName('GetRateTask')
            ->setDescription('GetRateTask: Run scheduled tasks');
    }

    protected function execute(Input $input, Output $output)
    {
        //php think GetRateTask
        $result = (new RatesService())->list();
        $list = $result['data'] ?? [];
        $data = [];
        foreach($list as $v)
        {
            $id = DB::table('ba_exchange_rate')->field('id')->where('currency', $v['currency'])
                ->where('time', $v['time'])
                ->find();
            if(empty($id)) {
                $data[] = [
                    'time' => $v['time'],
                    'currency' => $v['currency'],
                    'rate' => $v['rate']
                ];                
            } else {
                DB::table('ba_exchange_rate')->where('id', $id['id'])->update([
                    'rate' => $v['rate']
                ]);
            }
        }
        DB::table('ba_exchange_rate')->insertAll($data);

        // 在这里编写你的定时任务逻辑
        $output->writeln('GetRateTask: Scheduled task executed!');
    }
}
