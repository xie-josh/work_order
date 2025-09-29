<?php

namespace app\admin\model\fb;

use ba\Random;
use think\Model;
use think\model\relation\BelongsTo;

/**
 * User 模型
 */
class ConsumptionModel extends Model
{
    protected $name = 'account_consumption';
    protected $autoWriteTimestamp = true;
    protected $time = '-1';


    function dayConsumption($adminId,string $startDate = '',string $endDate = '')
    {
        $where = [];
        array_push($where, ['consumption.admin_id', '=', $adminId]);
        if (!empty($startDate))  array_push($where, ['consumption.date_start', '>=', $startDate]);
        else{
            $start1 = date('Y-m-d', time());
            $start =  date('Y-m-10', time());
            // $end   = date('Y-m-01', strtotime($month . ' +1 month'));
            if (strtotime($start1) < strtotime($start)) {
                $startDate = date('Y-m-01', strtotime("-1 month"));
            } elseif (strtotime($start1) >= strtotime($start)) {
                $startDate = date('Y-m-01', time());
            }
            // $startDate = date('Y-m-01', strtotime("$this->time month"));
            array_push($where, ['consumption.date_start', '>=', $startDate]);
        }

        if (!empty($endDate)) array_push($where, ['consumption.date_start', '<=', $endDate]);
        else {
            $endDate = date('Y-m-d');
            array_push($where, ['consumption.date_start', '<=',$endDate]);
        }

        $list = $this->where($where)
        ->alias('consumption')
        ->field('sum(consumption.dollar) total_dollar,consumption.admin_id,consumption.date_start')
        ->group('consumption.admin_id,consumption.date_start')
        ->select()->toArray();

        $listData = array_column($list,null,'date_start');

        $start = strtotime($startDate);
        $end = strtotime($endDate); // 默认为今天

        $list = [];
        for ($ts = $start; $ts <= $end; $ts += 86400) {
            $currentDate = date('Y-m-d', $ts);
            if(isset($listData[$currentDate]))
            {
                $totalDollar = bcadd((string)$listData[$currentDate]['total_dollar'],'0',4);
                $list[] = [
                    "total_dollar" => $totalDollar,
                    "admin_id" => $adminId,
                    "date_start" => $currentDate
                ];
            }else{
                $list[] = [
                    "total_dollar" => "0.0000",
                    "admin_id" => $adminId,
                    "date_start" => $currentDate
                ];
            }
            
        }
        return $list;
    }

    function monthConsumption($adminId,string $startDate = '',string $endDate = '')
    {
        $where = [];
        array_push($where, ['consumption.admin_id', '=', $adminId]);
       if (!empty($startDate))  array_push($where, ['consumption.date_start', '>=', $startDate]);
        else{
            $startDate = date('Y-m-01', strtotime("$this->time month"));
            array_push($where, ['consumption.date_start', '>=', $startDate]);
        }

        if (!empty($endDate)) array_push($where, ['consumption.date_start', '<=', $endDate]);
        else {
            $endDate = date('Y-m-d');
            array_push($where, ['consumption.date_start', '<=',$endDate]);
        }

        $list = $this->where($where)
        ->alias('consumption')
        ->field("sum(consumption.dollar) total_dollar, DATE_FORMAT(consumption.date_start, '%Y-%m') AS month,consumption.admin_id,consumption.date_start")
        // ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=consumption.account_id')
        ->group('consumption.admin_id,month')->select()->toArray();


        $listData = array_column($list,null,'month');

        $start = strtotime($startDate);
        $end = strtotime($endDate); // 默认为今天

        $list = [];
        for ($ts = $start; $ts <= $end; $ts = strtotime('+1 month', $ts)) {
            $currentMonth = date('Y-m', $ts);
            if(isset($listData[$currentMonth]))
            {
                $totalDollar = bcadd((string)$listData[$currentMonth]['total_dollar'],'0',4);
                $list[] = [
                    "total_dollar" => $totalDollar,
                    "admin_id" => $adminId,
                    "date_start" => $currentMonth
                ];
            }else{
                $list[] = [
                    "total_dollar" => "0.0000",
                    "admin_id" => $adminId,
                    "date_start" => $currentMonth
                ];
            }
        }

        return $list;
    }
}