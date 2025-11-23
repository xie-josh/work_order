<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Db;

class AccountChannel extends Backend
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
    protected array $noNeedPermission = ['index','channel','add','edit','del'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountChannel();
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
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function channel(): void
    {
        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        if ($this->request->param('select')) {
            $this->select();
        }

        $accountType = $this->request->get('account_type');

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res = $this->model
            ->withoutField('login_failure,password,salt')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate(999);

        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $proposal = Db::table('ba_accountrequest_proposal')->whereIn('status',config('basics.FH_status'))->field('count(*) countNumber,channel_id')->group('channel_id')->select()->toArray();
            $proposalList = array_combine(array_column($proposal,'channel_id'),array_column($proposal,'countNumber'));

            foreach($dataList as &$v){
                $v['username'] = $v['name'].'('.($proposalList[$v['id']]??0).')';
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function del(array $ids = []): void
    {
        $this->error('账户渠道不可删除');
    }

}