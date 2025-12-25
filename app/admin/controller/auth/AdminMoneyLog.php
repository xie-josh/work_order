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

    protected array $noNeedPermission = ['index'];

    protected array $withJoinTable = ['company'];

    protected string|array $quickSearchField = ['id'];

    protected bool|string|int $dataLimit = false;

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
        foreach($where as $k => $v){
            if($v[0] == 'admin_money_log.nickname'){
                $adminIds = Db::table('ba_admin')->where('nickname','like','%'.$v[2].'%')->column('company_id');          
                array_push($where,['company.id','IN',$adminIds]);
                unset($where[$k]);
            }
        }


        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order('create_time','desc')
            ->paginate($limit);
        $res->visible(['company' => ['company_name']]);
        $dataList = $res->toArray()['data'];
        
        $naickArr = Db::table('ba_admin')->where('type',2)->column('nickname','company_id');
        foreach($dataList as &$v){
            $v['nickname'] = $naickArr[$v['company_id']]??'';
        }

        $this->success('', [
            'list'   => $dataList,
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
            if(empty($data['company_id'])) throw new \Exception("数据有误！ 请联系管理员检查！");
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
                $pay_type = $data['pay_type']??'';
                $sub_pay_type = $data['sub_pay_type']??'';

                $companyData = Db::table('ba_company')->where('id',$data['company_id'])->find();

                $rate = DB::table('ba_rate')->where('company_id',$data['company_id'])->order('create_time desc')->find();
                $billRate = DB::table('ba_bill_rate')->where('company_id',$data['company_id'])->order('create_time desc')->find();
                if(empty($billRate)) $billRate['bill_rate'] = '0.0';
                if(empty($rate)) $rate['rate'] = '0.0';
                if(!is_numeric($data['money'])) throw new \Exception("输入金额异常！请检查");
                $type = 0;
                if (is_numeric($data['money']) && $data['money'] < 0) 
                {   //金额负为充正类型
                    $type = 4;
                    $rechargeMoney =  $data['money'];
                    $billRate['bill_rate'] = '0.0';
                    $rate['rate'] = '0.0';
                }else{
                    $type = 1;
                    if($companyData['prepayment_type'] == 2)
                    {
                        $totalRate = 0;
                        if($pay_type == 1)
                        {
                            $billRate['bill_rate'] = '0.0';
                            $totalRate =  (string)$rate['rate']; //usd
                        }
                        else
                        {
                            $totalRate =  bcadd((string)$rate['rate'],(string)$billRate['bill_rate'],4); //usdt
                        }

                        $rateNumber    =  bcadd('1',(string)($totalRate),4);
                        $rechargeMoney =  round(bcdiv((string)($data['money']), (string)$rateNumber,3), 2); //入账金额
                        // $creditMoney   =  bcsub((string)($data['money']) ,(string)$rechargeMoney,2);
                    }else
                    {
                        $rate['rate'] = '0.0';
                        if($pay_type == 1)
                        {
                            $billRate['bill_rate'] = '0.0';
                            $rechargeMoney = $data['money'];
                        }else{
                            $rateNumber    = bcadd('1',(string)($billRate['bill_rate']),4);
                            $rechargeMoney = round(bcdiv((string)($data['money']), (string)$rateNumber,3),2);
                            // $creditMoney   = bcsub((string)($data['money']) ,(string)$rechargeMoney,2);
                        }
                    }
                }
                
                //$money = bcadd((string)$money,(string)$rechargeMoney,2);
                //$money = floor(($money + $rechargeMoney) * 100) / 100;
                //$money = Db::table('ba_company')->where('id',$data['company_id'])->update(['money'=>$money]);
                $createTime = $data['create_time']??'';                
                $data = [
                    'company_id'=>$data['company_id'],
                    'money'=>$rechargeMoney,               //充值金额
                    'raw_money'=>$data['money'],           //原金额
                    'comment'=>$comment,
                    'rate'=>$rate['rate']??0,              //税率
                    'bill_rate'=>$billRate['bill_rate']??0,//入账手续费
                    'credit_money'=>0,                     //入账金额
                    'images'=>implode(',', $data['images']??[]),
                    'status'=>2,
                    'type'=>$type,
                    'pay_type'=>$pay_type,
                    'sub_pay_type'=>$sub_pay_type,
                    // 'recharge_channel_name'=>'',
                ];
                if(!empty($createTime)) $data['create_time'] = strtotime($createTime);
                 
                $result = DB::table('ba_admin_money_log')->insert($data);
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

    public function audit()
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
    
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            DB::startTrans();
            try {

                $companyId = $row->company_id;
                $status = $row->status;
                $adminMoney = $row->money;
                $pay_type = $row->pay_type;
                $sub_pay_type = $row->sub_pay_type;
                $rate = $row->rate;
                $type = $row->type;
                $bill_rate = $row->bill_rate;
                $rechargeMoney = $row->money;
                $raw_money = $row->raw_money;
                $create_time = $row->create_time;

                $companyData = Db::table('ba_company')->where('id',$companyId)->find();
                $result = false;
                if($status == 2 && $data['status'] == 1)
                {
                    $money = bcadd((string)$adminMoney,(string)$companyData['money'],'2');
                    if($companyData['prepayment_type'] == 2) if($money < 0) throw new \Exception("金额异常！请联系管理员！");
                    $money = Db::table('ba_company')->where('id',$companyId)->where('money',$companyData['money'])->update(['money'=>$money]);
                    if($money) DB::table('ba_admin_money_log')->where('id',$row->id)->update(['status'=>$data['status']]);
                    $result = true;
                    if($companyData['prepayment_type'] == 2)
                    {
                        if($pay_type == 1)
                        {
                            $addRate = round(bcmul((string)$rechargeMoney, (string)$rate, 3),2);
                        }
                        else
                        {
                            $addRate = round(bcmul((string)$rechargeMoney, (string)$rate, 3),2);
                            $addBillRate = round(bcmul((string)$rechargeMoney, (string)$bill_rate, 3),2);
                        }
                    }else
                    {
                        $addBillRate = 0;
                        if($pay_type == 2)
                        {
                            $addBillRate = round(bcmul((string)$rechargeMoney, (string)$bill_rate, 3),2);
                        }    
                    }
                    //金额负为充正类型
                    if ($type == 4) 
                    {
                        $addRate = '0';
                        $addBillRate = '0';
                    }

                    if(!empty($addRate))
                    {
                        $data = [
                            'company_id'=>$companyId,
                            // 'money'=>$rechargeMoney,
                            'money'=>0,
                            'raw_money'=>$addRate,
                            'pid'=>$id,
                            'rate'=>$rate??0,
                            'credit_money'=>$addRate,
                            'status'=>1,
                            'type'=>2,
                            'pay_type'=>$pay_type,
                            'sub_pay_type'=>$sub_pay_type,
                            'create_time'=>$create_time-1
                        ];
                        $result = DB::table('ba_admin_money_log')->insertGetId($data);
                    }

                    if(!empty($addBillRate))
                    {
                        $data = [
                            'company_id'=>$companyId,
                            // 'money'=>$rechargeMoney,
                            'money'=>0,
                            'raw_money'=>$addBillRate,
                            'pid'=>$id,
                            'bill_rate'=>$bill_rate??0,
                            'credit_money'=>$addBillRate,
                            'status'=>1,
                            'type'=>3,
                            'pay_type'=>$pay_type,
                            'sub_pay_type'=>$sub_pay_type,
                            'create_time'=>$create_time-1
                        ];
                        $result = DB::table('ba_admin_money_log')->insertGetId($data);
                    }
                }

                if($status == 1 && $data['status'] == 3)
                {
                    $money = bcsub((string)$companyData['money'],(string)$adminMoney,'2');
                    $money = Db::table('ba_company')->where('id',$companyId)->where('money',$companyData['money'])->update(['money'=>$money]);
                    if($money) DB::table('ba_admin_money_log')->where('id',$row->id)->update(['status'=>$data['status']]);
                    if($money) DB::table('ba_admin_money_log')->where('pid',$row->id)->update(['status'=>$data['status']]);
                    $result = true;
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
    }

    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
    
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if(empty($data['images'])) throw new \Exception("请上传凭证");
                
                $result = DB::table('ba_admin_money_log')->where('id',$row['id'])->update([
                    'images'=>implode(',', $data['images']??[]),
                ]);
                // $result = $row->save(['images'=>$data['images']??[]]);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
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
        array_push($where,['company_id','=',$adminId]);
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