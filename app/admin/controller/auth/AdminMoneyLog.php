<?php

namespace app\admin\controller\auth;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账号充值记录
 */
class AdminMoneyLog extends Backend
{
    /**
     * AdminMoneyLog模型对象
     * @var object
     * @phpstan-var \app\admin\model\auth\AdminMoneyLog
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];

    protected string|array $quickSearchField = ['id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\auth\AdminMoneyLog();
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
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username','nickname']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }

                $comment = $data['comment']??'';

                $rate = DB::table('ba_recharge_channel')->where('id',$data['recharge_channel_id'])->find();
                if(!$rate) throw new \Exception("请选择汇率！");

                $rateNumber = bcadd('1',(string)($rate['rate']),4);
                
                $rechargeMoney = bcdiv((string)($data['money']), (string)$rateNumber,2);

                $creditMoney =  bcsub((string)($data['money']) ,(string)$rechargeMoney,2);

                $money = Db::table('ba_admin')->where('id',$data['admin_id'])->value('money');
                
                $money = bcadd((string)$money,(string)$rechargeMoney,2);
                //$money = floor(($money + $rechargeMoney) * 100) / 100;
                $money = Db::table('ba_admin')->where('id',$data['admin_id'])->update(['money'=>$money]);
                
                $data = [
                    'admin_id'=>$data['admin_id'],
                    'money'=>$rechargeMoney,
                    'raw_money'=>$data['money'],
                    'comment'=>$comment,
                    'rate'=>$rate['rate'],
                    'credit_money'=>$creditMoney,
                    'recharge_channel_name'=>$rate['name'],
                ];


                $applicationData = [
                    'status'=>1,
                    'type_id'=>1,
                    'admin_id'=>$data['admin_id'],
                    'amount'=>$rechargeMoney,
                    'create_time'=>time(),
                    
                ];
                DB::table('ba_wallet_account_application')->insert($applicationData);

                $result = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }


    public function moneyLogList()
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index

        $this->withJoinTable = [];
        if ($this->request->param('select')) {
            $this->select();
        }
        $adminId = $this->request->get('id');
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $limit = 999;
        array_push($where,['admin_id','=',$adminId]);
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        // $res->visible(['admin' => ['username']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function moneyLogItem()
    {

    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}