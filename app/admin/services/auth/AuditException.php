<?php
namespace app\admin\services\auth;

use think\facade\Db;


class AuditException
{


    public function consumptionReconciliation()
    {
        $accountResult1 = Db::table('ba_account')->field('admin_id,sum(open_money) open_money')->where('status',4)->group('admin_id')->select()->toArray();
        $accountResult2 = Db::table('ba_account')->field('admin_id,sum(money) open_money')->where('status','NOT IN',['2','4','5'])->group('admin_id')->select()->toArray();
        $rechargeResult = Db::table('ba_recharge')->field('admin_id,sum(number) number')->where('type','1')->where('status','IN',['0','1'])->group('admin_id')->select()->toArray();
        $withdrawResult = Db::table('ba_recharge')->field('admin_id,sum(number) number')->where('type','IN',['3','4'])->where('status','1')->group('admin_id')->select()->toArray();
        $transferResult = Db::table('ba_recharge')->field('admin_id,sum(number) number')->where('type','2')->where('status','1')->group('admin_id')->select()->toArray();
        $accountResult1 = array_column($accountResult1,null,'admin_id');
        $accountResult2 = array_column($accountResult2,null,'admin_id');
        $rechargeResult = array_column($rechargeResult,null,'admin_id');
        $withdrawResult = array_column($withdrawResult,null,'admin_id');
        $transferResult = array_column($transferResult,null,'admin_id');
        
        
        $accountResult11 = Db::table('ba_account_recycle')->field('admin_id,sum(open_money) open_money')->where('status',4)->group('admin_id')->select()->toArray();
        $accountResult22 = Db::table('ba_account_recycle')->field('admin_id,sum(money) open_money')->where('status','NOT IN',['2','4','5'])->group('admin_id')->select()->toArray();
        $rechargeResult1 = Db::table('ba_recharge_recycle')->field('admin_id,sum(number) number')->where('type','1')->where('status','IN',['0','1'])->group('admin_id')->select()->toArray();
        $withdrawResult1 = Db::table('ba_recharge_recycle')->field('admin_id,sum(number) number')->where('type','IN',['3','4'])->where('status','1')->group('admin_id')->select()->toArray();
        $transferResult1 = Db::table('ba_recharge_recycle')->field('admin_id,sum(number) number')->where('type','2')->where('status','1')->group('admin_id')->select()->toArray();
        $accountResult11 = array_column($accountResult11,null,'admin_id');
        $accountResult22 = array_column($accountResult22,null,'admin_id');
        $rechargeResult1 = array_column($rechargeResult1,null,'admin_id');
        $withdrawResult1 = array_column($withdrawResult1,null,'admin_id');
        $transferResult1 = array_column($transferResult1,null,'admin_id');

        $consumption = Db::table('ba_account_consumption')->field('admin_id,sum(spend) number')->group('admin_id')->select()->toArray();
        $consumptionResult = array_column($consumption,null,'admin_id');

        $admin = DB::table('ba_admin')->alias('admin')
        ->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid=admin.id')
        ->where('group_id',3)
        ->field('admin.id,admin.nickname,admin.money,admin.used_money')->select()->toArray();
        
        $list = [];
        foreach($admin as $k => $v){

            //使用金额
            $openMoney1 = bcadd((string)($accountResult1[$v['id']]['open_money']??0),(string)($accountResult2[$v['id']]['open_money']??0),2);
            $openMoney2 = bcadd((string)($accountResult11[$v['id']]['open_money']??0),(string)($accountResult22[$v['id']]['open_money']??0),2);
                        
            $money = $v['money'];
            $usedMoney = $v['used_money'];

            $openMoney = $openMoney1;
            $recharge = $rechargeResult[$v['id']]['number']??0;
            $withdraw = $withdrawResult[$v['id']]['number']??0;
            $transfer = $transferResult[$v['id']]['number']??0;

            $recycleOpenMoney = $openMoney2;
            $recycleRecharge = $rechargeResult1[$v['id']]['number']??0;
            $recycleWithdraw = $withdrawResult1[$v['id']]['number']??0;
            $recycleTransfer = $transferResult1[$v['id']]['number']??0;

            $fb_consume = $consumptionResult[$v['id']]['number']??0;

            //首充
            $openMoney = bcadd((string)$openMoney1,(string)$openMoney2,2);
            //充值
            $number1 = bcadd((string)$recharge,(string)$recycleRecharge,2);
            //清零
            $number2 = bcadd((string)$withdraw,(string)$recycleWithdraw,2);
            //扣款
            $number3 = bcadd((string)$transfer,(string)$recycleTransfer,2);

            $amount = $openMoney + $number1 - $number2 - $number3;
            // if($usedMoney -  $amount > 10 || $usedMoney -  $amount < -10){
                $list[] = [
                    'admin_id'=>$v['id'],
                    'nickname'=>$v['nickname'],
                    'money'=>$money,
                    'used_money'=>$usedMoney,
                    'amount'=>$amount,

                    'open_money'=>$openMoney,
                    'recharge'=>$recharge,
                    'withdraw'=>$withdraw,
                    'transfer'=>$transfer,

                    'fb_consume'=>$fb_consume,
                
                    'recycle_open_money'=>$recycleOpenMoney,
                    'recycle_recharge'=>$recycleRecharge,
                    'recycle_withdraw'=>$recycleWithdraw,
                    'recycle_transfer'=>$recycleTransfer,
                ];
            // }
        }
        return $list;
    }

}