<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\facade\Db;

class Announcement extends Backend
{
    /**
     * @var object
     * @phpstan-var Announcement
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];
    protected array $noNeedPermission = ['index','unprocessed'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\AnnouncementModel();
    }


    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate(10);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function unprocessed()
    {
        $data = [
            'open_dispose'=>0,   //开户处理
            'idle_quantity'=>0,  //待回收
            'bm_account'=>0,     //开户绑定
            'bm_to_submit'=>0,   //待提交
            'bm_submit'=>0,      //已提交
            'account_status_modify'=>0,  //账号状态变更
            'recharge'=>0,       //充值需求
            'bm_up'=>0,           //绑定需求
            'pending_payment_audit'=>0,           //待支付
            'fb_bm_expired'=>0,           //个号过期
            'account_preheating'=>0,           //预热账户
        ];


        $data['open_dispose']  = DB::table('ba_account')->whereIn('status',[0,1,3])->count();
        $data['idle_quantity'] = DB::table('ba_account')->where('status',4)->where('idle_time','>',(31 * 86400))->count();
        $data['bm_account']    = DB::table('ba_bm')->where('status',0)->where('demand_type',4)->count();
        $data['bm_to_submit']    = DB::table('ba_bm')->where('status',0)->where('demand_type','<>',4)->count();
        $data['bm_submit']    = DB::table('ba_bm')->where('status',1)->where('dispose_type',0)->count();
        $data['account_status_modify']    = DB::table('ba_account_return')->where('status',0)->where('type','<>',7)->count();
        $data['recharge']    = DB::table('ba_recharge')->where('status',0)->whereIn('type',[1,2])->count();
        $data['bm_up']    = DB::table('ba_bm')->whereIn('status',[0,1])->where('dispose_type',0)->count();
        $data['pending_payment_audit']    = DB::table('ba_account_return')->where('status',0)->where('type','=',7)->count();
        $data['fb_bm_expired']    = DB::table('ba_fb_personalbm_token')->where([
            ['expired_time','<',date("Y-m-d",strtotime("+6 day"))],
            ['status','=',1]
        ])->count();
        $data['account_preheating']    = DB::table('ba_account')->whereIn('status',[4,6])->where('is_keep',1)->where('keep_succeed','<>',1)->count();

        $this->success('', [
            'list'   => $data
        ]);
    }

}