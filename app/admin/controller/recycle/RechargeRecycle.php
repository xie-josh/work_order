<?php

namespace app\admin\controller\recycle;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;


class RechargeRecycle extends Backend
{
    /**
     * RechargeRecycleModel模型对象
     * @var object
     * @phpstan-var \app\admin\model\recycle\RechargeRecycleModel
     */
    protected object $model;

    protected array|string $preExcludeFields = [];

    protected array $withJoinTable = ['admin','accountrequestProposal'];
    protected array $noNeedPermission = ['index'];

    protected string|array $quickSearchField = [];


    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\recycle\RechargeRecycleModel();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        foreach($where as $k => &$v)
        {
            if($v[0] == 'admin.username'){
                $companyId = DB::table('ba_admin')->where('type',2)->where([['nickname','like',$v[2]]])->column('company_id');
                $adminIds = DB::table('ba_admin')->whereIn('company_id',$companyId)->column('id');
                if(!empty($adminIds)) array_push($where,['recharge_recycle_model.admin_id','IN',$adminIds]);
                unset($where[$k]);
                continue;
            }
        }
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username']]);

        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $companyAdminNameArr = DB::table('ba_admin')->field('company_id,nickname,id')->where('type',2)->select()->toArray();
            $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');

            foreach($dataList as &$v){
                $companyId = $v['company_id']??'';

                $nickname = '';
                if(!empty($companyId)) $nickname = $companyAdminNameArr[$companyId]['nickname'];
                $v['admin']['username'] = $nickname;

            }
       }

        $this->success('', [
            'list'   => $dataList,
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}