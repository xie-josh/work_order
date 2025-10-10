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

    protected function execute(Input $input, Output $output)
    {
        //php think MonthConsumptionTotalTask
        // $month = date("Y-m", strtotime("-1 month"));   //测试

        $day = date('d');
        if($day < '10') return true;

        $month = date("Y-m", strtotime("-1 month"));//正式
        $start = date('Y-m-01', strtotime($month));
        $end   = date('Y-m-01', strtotime($month . ' +1 month'));
        $output->writeln("统计月份". $month . PHP_EOL);
        //用户组
        $adminIds = DB::table('ba_admin')->alias('admin')
        ->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')
        ->where(['admin_group_access.group_id'=>3])
        // ->where(['admin.id'=>122])//测试
        ->column('admin.id');
        foreach($adminIds as $admin_id)
        {
            $consumptionResult = DB::table('ba_account_consumption')
            ->field('dollar,date_start')
            ->where('admin_id',$admin_id)
            ->where('date_start', '>=', $start)
            ->where('date_start', '<', $end)
            ->select()->toArray();
    
            // $result = DB::table('ba_rate')->where('admin_id',$adminId)->order('create_time asc')->select()->toArray();
            $result = DB::table('ba_rate')->where('admin_id',$admin_id)->order('create_time asc')->select()->toArray();
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
                    if (strtotime($v['date_start']) > $start_tmie && strtotime($v['date_start']) <= $end_tmie) {
                    //  $dd[] =   $v['start_tmie'] .">$thsiTime 在区间内".$v['rate']."--".$v['end_tmie']."\n";
                    $total_dollar_rate +=  $v['dollar']*$vv['rate'];
                    $output->writeln($vv['start_tmie'].">--"."命中区间".$v['date_start']."费率为".$vv['rate']."<=--".$vv['end_tmie']);
                    }
                    if (strtotime($v['date_start']) > $start_tmie && empty($end_tmie)) {
                    //  $dd[] =  $v['start_tmie'] .">$thsiTime 没有结束时间".$v['rate']."--".$v['end_tmie']."\n";
                    $total_dollar_rate +=  $v['dollar']*$vv['rate'];
                    $output->writeln($vv['start_tmie']."-->"."命中区间".$v['date_start']."费率为".$vv['rate']."No Null--".$vv['end_tmie']);
                    }
                }
            }

            $archivedId = DB::table('ba_archived')->where([['month','=',$month],['admin_id','=',$admin_id]])->value('id');
            if($archivedId) DB::table('ba_archived')->where('id',$archivedId)->delete();

            DB::table('ba_archived')->insert(['create_time'=>date("Y-m-d", time()),'month'=>$month,'admin_id'=>$admin_id,'month_total_dollar'=>round($total_dollar, 2),'rate_total_dollar'=>round($total_dollar_rate, 2)]); 
            // 在这里编写你的定时任务逻辑
            $output->writeln($admin_id."用户".$month."总金额统计为$total_dollar"."服务费统计为$total_dollar_rate"."<br/>");
      }
    }
}
