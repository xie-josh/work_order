<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Db;

class AccountRecyclePending extends Backend
{
    /**
     * @var object
     * @phpstan-var AccountChannel
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = ['id'];
    protected array $noNeedPermission = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountRecyclePending();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = DB::table('ba_account_recycle_pending')
            ->field('account_recycle_pending.*,accountrequest_proposal.bm,account.idle_time,COALESCE(CAST(accountrequest_proposal.total_consumption AS DECIMAL(15,2)), 0) AS total_consumption_num')
            ->alias('account_recycle_pending')
            // ->withJoin($this->withJoinTable, $this->withJoinType)
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_recycle_pending.account_id')
            ->leftJoin('ba_account account','account.account_id=account_recycle_pending.account_id')
            ->where($where)
            ->order('total_consumption_num desc')
            ->paginate($limit);

         $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            foreach($dataList as &$v){
                if($v['idle_time'] > 86400){
                    $days = floor($v['idle_time'] / 86400) - 1;
                    $hours = floor(($v['idle_time'] % 86400) / 3600);
                }else{
                    $days = 0;
                    $hours = 0;
                }
                $v['consumption_date'] = [
                    'days'=>$days,
                    'hours'=>$hours
                ];
            }
        }

             
        // ->fetchSql()->find();

        // dd($res);

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

}