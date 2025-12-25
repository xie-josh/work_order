<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class MonthConsumptionTotalTask extends Command
{
    protected function configure()
    {
        $this->setName('MonthConsumptionTotalTask')->setDescription('MonthConsumptionTotalTask: Run scheduled tasks');
    }
    //上月消耗费率区间计算
    protected function execute(Input $input, Output $output)
    {
        //php think MonthConsumptionTotalTask
        //$month = date("Y-m", strtotime("-1 month"));   //测试
        $day = date('d');
        if($day < '10') return true;

        $month = date("Y-m", strtotime("-1 month"));//正式
        $start = date('Y-m-01', strtotime($month));
        $end   = date('Y-m-01', strtotime($month . ' +1 month'));
        $output->writeln("统计月份". $month . PHP_EOL);
        //用户组
        $companyAll = DB::table('ba_company')->where(['status'=>1])->column('id');
        try{
            foreach($companyAll as $company_id)
            {
                $consumptionResult = DB::table('ba_account_consumption')
                ->field('dollar,date_start')
                ->where('company_id',$company_id)
                ->where('date_start', '>=', $start)
                ->where('date_start', '<', $end)
                ->select()->toArray();
        
                // $result = DB::table('ba_rate')->where('company_id',$adminId)->order('create_time asc')->select()->toArray();
                $result = DB::table('ba_rate')->where('company_id',$company_id)->order('create_time asc')->select()->toArray();
                $section = [];
                if(!empty($result))foreach($result as $k => $v)
                {
                    $section[] =  ['start_tmie' => $v['create_time'],'end_tmie' => isset($result[$k+1]['create_time'])?$result[$k+1]['create_time']:'','rate'=>$v['rate']];
                }
                $section = array_reverse($section);
                $total_dollar = 0;
                $total_dollar_rate = 0;
                foreach($consumptionResult AS $k => $v)
                {
                    $total_dollar += $v['dollar'];
                    //服务费率
                    if(!empty($section))foreach($section as $kk => $vv)
                    {
                        $start_tmie = strtotime($vv['start_tmie']);
                        $end_tmie   = strtotime($vv['end_tmie']);
                        //大于设定日期,设定当日不生效
                        // $total_dollar_rate = 0; // 一定要初始化

                        $dollar = (float)($v['dollar'] ?? 0);
                        $rate   = (float)($vv['rate'] ?? 0);

                        if (strtotime($v['date_start']) > $start_tmie && strtotime($v['date_start']) <= $end_tmie) {
                            $total_dollar_rate += $dollar * $rate;
                        }

                        if (strtotime($v['date_start']) > $start_tmie && empty($end_tmie)) {
                            $total_dollar_rate += $dollar * $rate;
                        }

                        // if (strtotime($v['date_start']) > $start_tmie && strtotime($v['date_start']) <= $end_tmie) {
                        // //  $dd[] =   $v['start_tmie'] .">$thsiTime 在区间内".$v['rate']."--".$v['end_tmie']."\n";
                        // $total_dollar_rate +=  $v['dollar']*$vv['rate'];
                        // // $output->writeln($vv['start_tmie'].">--"."命中区间".$v['date_start']."费率为".$vv['rate']."<=--".$vv['end_tmie']);
                        // }
                        // if (strtotime($v['date_start']) > $start_tmie && empty($end_tmie)) {
                        // //  $dd[] =  $v['start_tmie'] .">$thsiTime 没有结束时间".$v['rate']."--".$v['end_tmie']."\n";
                        // $total_dollar_rate +=  $v['dollar']*$vv['rate'];
                        // // $output->writeln($vv['start_tmie']."-->"."命中区间".$v['date_start']."费率为".$vv['rate']."No Null--".$vv['end_tmie']);
                        // }
                    }
                }
    
                $archivedId = DB::table('ba_archived')->where([['month','=',$month],['company_id','=',$company_id]])->value('id');
                if($archivedId) DB::table('ba_archived')->where('id',$archivedId)->delete();
    
                DB::table('ba_archived')->insert(['create_time'=>date("Y-m-d", time()),'month'=>$month,'company_id'=>$company_id,'month_total_dollar'=>round($total_dollar, 2),'rate_total_dollar'=>round($total_dollar_rate, 2)]); 
                // 在这里编写你的定时任务逻辑
                $output->writeln($company_id."用户".$month."总金额统计为$total_dollar"."服务费统计为$total_dollar_rate"."<br/>");
          }
        }catch(\Exception $e){
            $this->error($e->getMessage());
        }

    }
}
