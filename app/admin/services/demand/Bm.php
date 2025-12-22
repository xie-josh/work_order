<?php

namespace app\admin\services\demand;

use think\facade\Db;

class Bm
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\demand\Bm();
        $this->auth = $auth ?? new \stdClass();
    }


    public function bmAllUnbinding($accountIds)
    {
        $bmList = Db::table('ba_bm')
        ->alias('b')
        ->field('id, admin_id, account_id, bm,bm_type,team_id')
        ->whereIn('b.account_id', $accountIds)
        ->whereIn('b.demand_type', [1, 4])
        ->where('b.new_status', 1)
        ->where('b.dispose_type', 1)
        ->whereNotExists(function ($subQuery) use ($accountIds) {
            $subQuery->table('ba_bm')
                ->alias('n')
                ->whereColumn('n.account_id', 'b.account_id')
                ->whereColumn('n.bm', 'b.bm')
                ->whereIn('n.account_id', $accountIds)
                ->where('n.demand_type', 2)
                ->where('n.new_status', 1)
                ->where('n.status', '<>', 2)
                ->where('n.dispose_type', '<>', 2);
        })->select()->toArray();


        $dataList = [];
        foreach($bmList as $item){
            $dataList[] = [
                'demand_type'=>2,
                'account_id'=>$item['account_id'],
                'bm'=>$item['bm'],
                'bm_type'=>$item['bm_type'],
                'account_name'=>'',
                'admin_id'=>$item['admin_id'],
                'add_operate_user'=>$this->auth->id,
                'create_time'=>time()
            ];
        }
        $result = DB::table('ba_bm')->insertAll($dataList);
        
        if($result) return ['code'=>1,'msg'=>''];
        else return ['code'=>0,'msg'=>'没有找到可以解绑的需求！'];
    }
      

}