<?php

namespace app\admin\controller;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户管理
 */
class Account extends Backend
{
    /**
     * Account模型对象
     * @var object
     * @phpstan-var \app\admin\model\Account
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'account_id', 'admin_id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];
    protected array $noNeedPermission = ['accountCountMoney','editIs_','audit','index','getAccountNumber','allAudit'];
    protected string|array $quickSearchField = ['id'];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\Account();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        //$this->quickSearchField = 'account_id';
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $status = $this->request->get('status');

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();


        array_push($this->withJoinTable,'accountrequestProposal');
        
        foreach($where as $k => &$v){
            if($v[0] == 'account.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
            }
        }

        if($status == 1){
            array_push($where,['account.status','in',[1,3,4,5]]);
        }elseif($status == 3){
            array_push($where,['account.status','in',[3,4]]);
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $dataList = $res->toArray()['data'];
        // dd($dataList);
        if($dataList){
            
            $bmList = [];
            if($status == 3){
                $accountIds = array_column($dataList,'account_id');
                $resultBm = DB::table('ba_bm')->where('status',1)->whereIn('account_id',$accountIds)->select()->toArray();
                foreach($resultBm as $v){
                    $bmList[$v['account_id']][] = $v['bm'];
                }
            }
            
            foreach($dataList as &$v){
                $v['bm_list'] = $bmList[$v['account_id']]??[];
            }
        }
        $res->visible(['admin' => ['username']]);

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

                $admin = Db::table('ba_admin')->where('id',$this->auth->id)->find();
                $accountNumber = $admin['account_number'];
                $isAccount = $admin['is_account'];
                $usableMoney = ($admin['money'] - $admin['used_money']);
                if($isAccount != 1) throw new \Exception("未调整可开户数量,请联系管理员添加！");
                if($usableMoney <= 0 || $usableMoney < $data['money']) throw new \Exception("余额不足,请联系管理员！");

                $time = date('Y-m-d',time());
                $openAccountNumber = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
                if($openAccountNumber >= $accountNumber) throw new \Exception("今.开户数量已经不足，不能再提交开户需求,请联系管理员！");

                // DB::table('ba_account')->where('id',$account['id'])->inc('money',$data['number'])->update(['update_time'=>time()]);
                DB::table('ba_admin')->where('id',$this->auth->id)->inc('used_money',$data['money'])->update();

                $data['admin_id'] = $this->auth->id;
                // $data['account_id'] = $this->generateUniqueNumber();
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


    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if($row['status'] != 0){
            $this->error('已经审核不可在修改');
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
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
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }
                $result = $row->save($data);
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



    function generateUniqueNumber() {
        // 获取当前的微秒时间戳
        $microtime = microtime(true);
        
        // 提取整数部分（秒）和小数部分（微秒）
        $seconds = floor($microtime);
        $milliseconds = ($microtime - $seconds) * 1000;
        
        // 将秒和毫秒组合成一个整数
        $timestamp = $seconds . sprintf('%03d', $milliseconds);
        
        // 生成一个随机数来填充剩余的位数
        $randomNumber = mt_rand(1000, 9999); // 生成一个4位的随机数
        
        // 合并时间戳和随机数
        $uniqueNumber = $timestamp . $randomNumber;
        
        // 截取前16位
        return substr($uniqueNumber, 0, 16);
    }


    public function audit(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $accountId = $data['account_id']??0;
                $status = $data['status'];

                if($status == 1){

                    $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time()]);
                    
                }elseif($status == 2){
                    $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
                    foreach($ids as $v){
                        DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['money'])->update();
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time()]);
                }elseif($status == 3){
                    $ids = $this->model->whereIn('id',$ids)->where('status',1)->select()->toArray();

                    foreach($ids as $v){
                        $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->where('status',0)->find();

                        $data = [
                            'account_admin_id'=>$accountrequestProposal['admin_id'],
                            'status'=>3,
                            'account_id'=>$accountId,
                            'is_'=>1,
                            'update_time'=>time()
                        ];
                        if(!empty($accountrequestProposal['time_zone'])) $data['time_zone'] = $accountrequestProposal['time_zone'];
                        $this->model->where('id',$v['id'])->update($data);
                        DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update(['status'=>1,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time()]);
                        
                    //     $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('admin_id',$adminId)->where('status',0)->find();
                    //     if(empty($accountrequestProposal))  continue;//throw new \Exception("该渠道暂时没有账号可以分配");
                    //     $accountId = $accountrequestProposal['account_id'];
                        
                    //     $result = $this->model->where('id',$v['id'])->update(['account_admin_id'=>$adminId,'status'=>$status,'account_id'=>$accountId]);
    
                    //     DB::table('ba_accountrequest_proposal')->where('id',$accountrequestProposal['id'])->update(['status'=>1,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time()]);

                    //     //if(!empty($v['money'])) DB::table('ba_recharge')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'type'=>1,'number'=>$v['money'],'status'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                         if(!empty($v['bm'])) DB::table('ba_bm')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'bm'=>$v['bm'],'demand_type'=>4,'status'=>0,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                    }
                }elseif($status == 4){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    foreach($ids as $v){
                        //$this->model->where('id',$v['id'])->update(['status'=>4,'update_time'=>time()]);
                        if(!empty($v['bm'])){
                            //DB::table('ba_bm')->insert(['account_name'=>$v['name'],'account_id'=>$v['account_id'],'bm'=>$v['bm'],'demand_type'=>1,'status'=>1,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                        }else{
                            $this->model->whereIn('id',$v['id'])->update(['dispose_status'=>1]);
                        }
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>4,'update_time'=>time()]);
                }elseif($status == 5){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    $accountIds = array_column($ids,'account_id');
                    foreach($ids as $v){
                        DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['money'])->update();
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>5,'money'=>0,'update_time'=>time()]);
                    DB::table('ba_bm')->whereIn('account_id',$accountIds)->update(['dispose_type'=>2]);
                }elseif($status == 6){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    $accountIds = array_column($ids,'account_id');
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>1,'account_id'=>'','update_time'=>time()]);
                    DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['status'=>2,'affiliation_admin_id'=>null]);

                    DB::table('ba_bm')->whereIn('account_id',$accountIds)->update(['status'=>2]);

                }
                //$this->model->whereIn('id',array_column($ids,'id'))->update(['money'=>0,'is_'=>1]);
                $result = true;
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
    }

    public function disposeStatus(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->column('id'); 

                $result = $this->model->whereIn('id',$ids)->update(['dispose_status'=>$status]);

                $result = true;
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
    }

    public function editIs_(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];

                $ids = $this->model->whereIn('id',$ids)->where('status',1)->column('id'); 

                $result = $this->model->whereIn('id',$ids)->update(['is_'=>$status]);

                $result = true;
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
    }


    function accountCountMoney()
    {
        $data = [
            'totalMoney'=>0,
            'usedMoney'=>0,
            'usableMoney'=>0
        ];
        $admin = DB::table('ba_admin')->where('id',$this->auth->id)->find();
        $data['totalMoney'] = $admin['money'];
        $data['usedMoney'] = $admin['used_money'];
        $data['usableMoney'] = ($admin['money'] - $admin['used_money']);

        // if($this->auth->isSuperAdmin()){
        //     $money = $this->model->where('is_',1)->sum('money');
        // }else{
        //     $money = $this->model->where('is_',1)->where('admin_id',$this->auth->id)->sum('money');
        // }
        $this->success('',$data);
    }



    function allAudit()
    {

        /**
         * 1.选择开户需求【多选】
         * 2.选择渠道
         *      1.没有选择账户（随机分配该渠道下的账户到对应开户需求）    
         *      2.选择了账户（把选择的账户分配给选择的开户需求下）【多选】
         *      3.如果账户不够自动跳过
         * 
         * 
         */


         if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {                                
                $ids = $data['ids'];
                $accountrequestProposalId = $data['admin_id'];
                $accountIds = $data['account_ids'];
                
                if(empty($accountIds)){
                    $accountIds = DB::table('ba_accountrequest_proposal')->where('admin_id',$accountrequestProposalId)->where('status',0)->select()->toArray();
                }else{
                    $accountIds = DB::table('ba_accountrequest_proposal')->where('admin_id',$accountrequestProposalId)->whereIn('account_id',$accountIds)->where('status',0)->select()->toArray();
                }

                $resultAccountList = DB::table('ba_account')->whereIn('id',$ids)->where('status',1)->select()->toArray();

                $bmDataList = [];
                foreach($accountIds as $k => $v)
                {
                    $resultAccount = $resultAccountList[$k]??[];
                    if(empty($resultAccount)) continue;

                    $data = [
                        'account_admin_id'=>$v['admin_id'],
                        'status'=>3,
                        'account_id'=>$v['account_id'],
                        'is_'=>1,
                        'update_time'=>time()
                    ];

                    if(!empty($v['bm'])){
                        $bmDataList[] = [
                            'account_name'=>$resultAccount['name'],
                            'account_id'=>$v['account_id'],
                            'bm'=>$resultAccount['bm'],
                            'demand_type'=>4,
                            'status'=>0,
                            'dispose_type'=>0,
                            'admin_id'=>$resultAccount['admin_id'],
                            'create_time'=>time(),
                        ];
                    }
                    
                    if(!empty($v['time_zone'])) $data['time_zone'] = $v['time_zone'];
                    DB::table('ba_account')->where('id',$resultAccount['id'])->update($data);
                    DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->update(['status'=>1,'affiliation_admin_id'=>$resultAccount['admin_id'],'update_time'=>time()]);
                }

                if(!empty($bmDataList)) DB::table('ba_bm')->insertAll($bmDataList);

                $result = true;
                Db::commit();
            } catch (Throwable $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

    }


    function getAccountNumber()
    {
        $accountNumber = DB::table('ba_admin')->field('account_number,is_account')->where('id',$this->auth->id)->find();
        $time = date('Y-m-d',time());
        $number = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
        $accountNumber['residue_account_number'] =  $accountNumber['account_number'] - $number;
        return $this->success('',[$accountNumber]);
    }


    public function excel()
    {
        $config = [
            'path' => '/www/wwwroot/workOrder/public/storage/excel' // xlsx文件保存路径
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);
        
        // fileName 会自动创建一个工作表，你可以自定义该工作表名称，工作表名称为可选参数
        $filePath = $excel->fileName('tutorial01.xlsx', 'sheet1')
            ->header(['Item', 'Cost'])
            ->data([
                ['Rent', 1000],
                ['Gas',  100],
                ['Food', 300],
                ['Gym',  50],
            ])
            ->output();

        dd($filePath);

    }



    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}