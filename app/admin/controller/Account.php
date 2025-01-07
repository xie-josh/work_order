<?php

namespace app\admin\controller;

use app\admin\model\card\CardsModel;
use think\facade\Cache;
use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use app\services\CardService;
use app\common\service\QYWXService;

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
    protected array $noNeedPermission = ['accountCountMoney','editIs_','audit','index','getAccountNumber','allAudit','distribution','inDistribution','export','getExportProgress'];
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
            //array_push($where,['account.status','in',[3,4]]);
            array_push($where,['account.status','in',[4]]);
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $dataList = $res->toArray()['data'];
        if($dataList){
            
            $resultTypeList = DB::table('ba_account_type')->select()->toArray();
            $typeList = array_column($resultTypeList,'name','id');

            $bmList = [];
            if($status == 3){
                $accountIds = array_column($dataList,'account_id');
                $resultBm = DB::table('ba_bm')->where('status',1)
                ->whereIn('account_id',$accountIds)
                ->whereIn('demand_type',[1,4])
                ->where('dispose_type',1)
                ->where('new_status',1)
                ->select()->toArray();
                foreach($resultBm as $v){
                    $bmList[$v['account_id']][] = $v['bm'];
                }
            }
            
            foreach($dataList as &$v){
                $v['account_type_name'] = '';
                if($v['status'] != 4 && $status != 1) $v['account_id'] = '';
                if(!empty($typeList[$v['account_type']])) $v['account_type_name'] = $typeList[$v['account_type']];
                $v['bm_list'] = $bmList[$v['account_id']]??[];
                $v['admin'] = [
                    'username'=>$v['admin']['username'],
                    'nickname'=>$v['admin']['nickname']
                ];
            }
        }
        //$res->visible(['admin' => ['username']]);

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
                $accountrequestProposalStatus = $data['accountrequest_proposal_status']??2;
                $timeZone = $data['time_zone']??'';

                // foreach($ids as $k => $v){
                //     $key = 'account_audit_'.$v['id'];
                //     $redisValue = Cache::store('redis')->get($key);
                //     if(!empty($redisValue)) unset($ids[$k]);
                //     Cache::store('redis')->set($key, '1', 120);
                // }

                if($status == 1){
                    $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                }elseif($status == 2){
                    $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
                    foreach($ids as $v){
                        DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['money'])->update();
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                }elseif($status == 3){
                    $ids = $this->model->whereIn('id',$ids)->where('status',1)->select()->toArray();

                    foreach($ids as $v){
                        $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->where('status',0)->find();
                        if(empty($accountrequestProposal)) throw new \Exception("请选择分配的账户！");

                        if(!empty($v['account_type'])){
                            $accountTypeList = DB::table('ba_association_account_type')->whereIn('admin_id',$accountrequestProposal['admin_id'])->column('account_type_id');
                            if(!empty($accountTypeList) && !in_array($v['account_type'],$accountTypeList)) throw new \Exception("账户类型错误，请联系管理员！");
                        }

                        $data = [
                            'account_admin_id'=>$accountrequestProposal['admin_id'],
                            'status'=>3,
                            'account_id'=>$accountId,
                            'is_'=>1,
                            'update_time'=>time(),
                            'operate_admin_id'=>$this->auth->id
                        ];
                        if(!empty($accountrequestProposal['time_zone'])) $data['time_zone'] = $accountrequestProposal['time_zone'];
                        $this->model->where('id',$v['id'])->update($data);
                        $allocateTime = date('md',time());

                        $getSerialName = (new \app\admin\services\addaccountrequest\AccountrequestProposal())->getSerialName($accountrequestProposal);


                        $data = ['status'=>1,'allocate_time'=>$allocateTime,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time(),'serial_name'=>$getSerialName,'currency'=>$v['currency']];
                        if(empty($accountrequestProposal['time_zone']) && !empty($v['time_zone'])) $data['time_zone'] = $v['time_zone'];
                        DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update($data);
                        
                    //     $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('admin_id',$adminId)->where('status',0)->find();
                    //     if(empty($accountrequestProposal))  continue;//throw new \Exception("该渠道暂时没有账号可以分配");
                    //     $accountId = $accountrequestProposal['account_id'];
                        
                    //     $result = $this->model->where('id',$v['id'])->update(['account_admin_id'=>$adminId,'status'=>$status,'account_id'=>$accountId]);
    
                    //     DB::table('ba_accountrequest_proposal')->where('id',$accountrequestProposal['id'])->update(['status'=>1,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time()]);

                    //     //if(!empty($v['money'])) DB::table('ba_recharge')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'type'=>1,'number'=>$v['money'],'status'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);

                        if(!empty($v['bm'])){
                            DB::table('ba_bm')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'bm'=>$v['bm'],'bm_type'=>$v['bm_type'],'demand_type'=>4,'status'=>0,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                            if(env('IS_ENV',false)) (new QYWXService())->bmSend(['account_id'=>$accountId],4);
                        }
                    }
                }elseif($status == 4){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    foreach($ids as $v){
                        //$this->model->where('id',$v['id'])->update(['status'=>4,'update_time'=>time()]);
                        // if(!empty($v['bm'])){
                        //     //DB::table('ba_bm')->insert(['account_name'=>$v['name'],'account_id'=>$v['account_id'],'bm'=>$v['bm'],'demand_type'=>1,'status'=>1,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);
                        // }else{
                        //     //$this->model->where('id',$v['id'])->update(['dispose_status'=>1]);
                        // }

                        $accountData = [];
                        $resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->find();
                        if(empty($v['name']) && !empty($resultProposal['name'])) $accountData['name'] = $resultProposal['name'];
                        if(empty($v['bm'])) $accountData['dispose_status'] = 1;
                        if(!empty($accountData)) $this->model->where('id',$v['id'])->update($accountData);


                        if($v['money'] > 0){
                            $this->model->whereIn('id',$v['id'])->update(['open_money'=>$v['money']]);
                            $param = [
                                //'max_on_percent'=>env('CARD.MAX_ON_PERCENT',901),
                                'transaction_limit_type'=>'limited',
                                'transaction_limit_change_type'=>'increase',
                                'transaction_limit'=>$v['money'],
                            ];
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();

                            $key = 'account_audit_'.$v['id'];
                            $redisValue = Cache::store('redis')->get($key);
                            if(!empty($redisValue)) throw new \Exception("该数据在处理中，不需要重复点击！");
                                                        
                            if(empty($cards)) {
                                //TODO...
                                throw new \Exception("未找到分配的卡");
                            }else{
                                Cache::store('redis')->set($key, '1', 180);
                                $resultCards = (new CardsModel())->updateCard($cards,$param);
                                Cache::store('redis')->delete($key);
                                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                            }
                        }
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>4,'update_time'=>time(),'open_time'=>time(),'operate_admin_id'=>$this->auth->id,'is_'=>1]);


                }elseif($status == 5){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    $accountIds = array_column($ids,'account_id');
                    foreach($ids as $v){
                        DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['money'])->update();
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>5,'money'=>0,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                    DB::table('ba_bm')->whereIn('account_id',$accountIds)->update(['dispose_type'=>2]);
                }elseif($status == 6){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    $accountIds = array_column($ids,'account_id');
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>1,'account_id'=>'','update_time'=>time(),'operate_admin_id'=>$this->auth->id]);

                    $AccountrequestProposalValue = [
                        'status'=>$accountrequestProposalStatus,
                        'affiliation_admin_id'=>null,
                        'time_zone'=>$timeZone
                    ];
                    
                    DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update($AccountrequestProposalValue);
                    //DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['status'=>2,'affiliation_admin_id'=>null]);

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

                $ids = $this->model->whereIn('id',$ids)->column('id');

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
        $data['totalMoney'] = floor($admin['money'] * 100) / 100;
        $data['usedMoney'] = floor($admin['used_money'] * 100) / 100;
        $data['usableMoney'] = floor((($admin['money'] - $admin['used_money'])) * 100) / 100;

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


                $associationAccountTypeList = DB::table('ba_association_account_type')->where('admin_id',$accountrequestProposalId)->column('account_type_id');
                
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
                    
                    if(!empty($associationAccountTypeList) && !empty($resultAccount['account_type']) && !in_array($resultAccount['account_type'],$associationAccountTypeList)) continue;

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
                    $allocateTime = date('md',time());

                    $getSerialName = (new \app\admin\services\addaccountrequest\AccountrequestProposal())->getSerialName($v);
                    $data = ['status'=>1,'affiliation_admin_id'=>$resultAccount['admin_id'],'allocate_time'=>$allocateTime,'update_time'=>time(),'serial_name'=>$getSerialName,'currency'=>$resultAccount['currency']];
                    if(empty($v['time_zone']) && !empty($resultAccount['time_zone'])) $data['time_zone'] = $resultAccount['time_zone'];
                    DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->update($data);
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

    function distribution()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {                                
                $id = $data['id'];
                $status = $data['status'];
                //$cardsId = $data['cards_id'];
                $cardNo = $data['card_no'];
                $timeZone = $data['time_zone'];
                $cardStatus = $data['card_status']??0;
                $accountStatus = $data['account_status']??0;
                $cardLimitedStatus = $data['card_limited_status']??0;

                $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('id',$id)->find();
                if(empty($accountrequestProposal) || !empty($accountrequestProposal['cards_id'])) throw new \Exception('错误：未找到账户或已经分配了卡！'); 

                $cards = DB::table('ba_cards_info')->where('card_no',$cardNo)->where('is_use',0)->find();
                if(empty($cards)) throw new \Exception('错误：[未找到卡]或[卡已经被使用]或[卡不可使用]！');

                $accountId = $cards['account_id'];
                $cardsId = $cards['cards_id'];

                $param = [];
                $param['card_id'] = $cards['card_id'];
                $param['nickname'] = $this->getNickname($accountrequestProposal['account_id']);
                if($cardLimitedStatus == 1){
                    $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                    $param['transaction_limit_type'] = 'limited';
                    $param['transaction_limit_change_type'] = 'increase';
                    $param['transaction_limit'] = env('CARD.LIMIT_AMOUNT',2);
                    $param['transaction_is'] = 1;
                }
                
                $proposalData = [
                    // 'status'=>$accountStatus,
                    'time_zone'=>$timeZone,
                ];

                if(!empty($accountStatus)) $proposalData['status'] = $accountStatus;
                
                if($status == 1){
                    //1.成功（卡状态（已使用）+ 备注 + 限额$2）
                    $cardsInfo = DB::table('ba_cards_info')->where('cards_id',$cards['cards_id'])->where('is_use',0)->update(['is_use'=>1]);

                    if(!$cardsInfo) throw new \Exception("请刷新,卡已经被占用！");

                    $resultCards = (new CardsModel())->updateCard($cards,$param);

                    if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                    $proposalData['cards_id'] = $cardsId;
                    // $result = (new CardService($accountId))->updateCard($param);
                    // if($result['code'] == 1){
                    //     (new CardsModel())->updateCardsInfo($cards,$param);
                    //     $proposalData['cards_id'] = $cardsId;
                    // }else{
                    //     throw new \Exception($result['msg']);
                    // }
                }else if($status == 2){
                    //2.失败（卡状态列表[已使用/未使用]，账户状态列表[大BM挂/绑卡挂户/其他币种]）
                    if($cardStatus == 1){
                        $cardsInfo = DB::table('ba_cards_info')->where('cards_id',$cards['cards_id'])->where('is_use',0)->update(['is_use'=>1]);

                        if(!$cardsInfo) throw new \Exception("请刷新,卡已经被占用！");

                        $resultCards = (new CardsModel())->updateCard($cards,$param);

                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        
                        $proposalData['cards_id'] = $cardsId;
                        // $result = (new CardService($accountId))->updateCard($param);
                        // if($result['code'] == 1){
                        //     (new CardsModel())->updateCardsInfo($cards,$param);
                        //     $proposalData['cards_id'] = $cardsId;
                        // }else{
                        //     throw new \Exception($result['msg']);
                        // }  
                    }
                }        
                DB::table('ba_accountrequest_proposal')->where('id',$id)->update($proposalData);

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

    function inDistribution()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {                                
                $id = $data['id'];
                $cardNo = $data['card_no'];                
                $limited = $data['limited'];
                
                if(empty($id) || empty($cardNo) || empty($limited)) throw new \Exception('Params Required');

                $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('id',$id)->find();
                if(empty($accountrequestProposal)) throw new \Exception('错误：未找到账户！'); 

                $cards = DB::table('ba_cards_info')->where('card_no',$cardNo)->where('is_use',0)->find();
                if(empty($cards)) throw new \Exception('错误：[未找到卡]或[卡已经被使用]或[卡不可使用]！');

                $cardsId = $cards['cards_id'];

                $param = [];
                $param['card_id'] = $cards['card_id'];
                $param['nickname'] = $this->getNickname($accountrequestProposal['account_id']);
                $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                $param['transaction_limit_type'] = 'limited';
                $param['transaction_limit_change_type'] = 'increase';
                $param['transaction_limit'] = $limited;
                $param['transaction_is'] = 1;
                
                $proposalData = [];

                $cardsInfo = DB::table('ba_cards_info')->where('cards_id',$cards['cards_id'])->where('is_use',0)->update(['is_use'=>1]);

                if(!$cardsInfo) throw new \Exception("请刷新,卡已经被占用！");

                $resultCards = (new CardsModel())->updateCard($cards,$param);

                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                $proposalData['cards_id'] = $cardsId;
               
                DB::table('ba_accountrequest_proposal')->where('id',$id)->update($proposalData);

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


    function getNickname($nickname)
    {
        $nickname = (string)$nickname;
        if(in_array($nickname[0],[1,4]) && strlen($nickname) >= 16) $nickname = substr($nickname,0,15);
        return $nickname;
    }


    public function errAccount()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {             
                $accountList = $data['account_list'];
                $accountStatus = $data['account_status']??0;

                if(empty($accountList)) throw new \Exception("Error Processing Request");
                
                foreach ($accountList as $value) {
                    $accountId = $value;
                    DB::table('ba_account')->where('account_id',$accountId)->update(['account_id'=>'','status'=>0,'dispose_status'=>0,'open_money'=>0]);
                    DB::table('ba_bm')->where('account_id',$accountId)->delete();
                    DB::table('ba_recharge')->where('account_id',$accountId)->delete();
                }

                DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountList)->update(
                    ['status'=>$accountStatus,'affiliation_admin_id'=>'']
                ); 

                //dd($accountList,$accountStatus);
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


    public function export()
    {
        $where = [];
        set_time_limit(300);
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        // array_push($this->withJoinTable,'accountrequestProposal');
        // $res = $this->model
        // ->withJoin($this->withJoinTable, $this->withJoinType)
        // ->alias($alias)
        // ->where($where)
        // ->order($order)
        // ->limit(100)
        // ->select();
        //$data = $res->toArray();

        //DB::table(table: 'ba_account')

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_progress'.'_'.$this->auth->id;
        
        $query =  $this->model
        ->alias('account')
        ->field('account.open_time,account.id,account.admin_id,account.name,account.account_id,account.time_zone,account.bm,account.open_money,account.dispose_status,account.status,account.create_time,account.update_time,accountrequest_proposal.id accountrequest_proposal_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm accountrequest_proposal_bm')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        //->withJoin(['accountrequestProposal'], 'LEFT')
        ->order('account.id','desc')
        ->where($where);

        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $statusValue = [0=>'待审核',1=>'审核通过',2=>'审核拒绝',3=>'分配账户',4=>'完成',5=>'开户失败'];
        $disposeStatusValue = [0=>'待处理',1=>'处理完成',2=>'已提交',3=>'提交异常',4=>'处理异常'];

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'ID',
            '管理BM',
            '用户名',
            '账户名称',
            '账户ID',
            '时区',
            '绑定BM',
            '首充金额',
            'BM绑定',
            '开户状态',
            '创建时间',
            '修改时间',
            '开户时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['id'],
                    $v['accountrequest_proposal_id']?$v['accountrequest_proposal_bm']:'',
                    //($v['accountrequestProposal']['bm'])??'',
                    ($adminList[$v['admin_id']])??'',
                    $v['accountrequest_proposal_id']?$v['serial_name']:$v['name'],
                    //$v['name'],
                    $v['account_id'],
                    $v['time_zone'],
                    $v['bm'],
                    $v['open_money'],
                    $disposeStatusValue[$v['dispose_status']],
                    $statusValue[$v['status']],
                    $v['create_time']?date('Y-m-d H:i',$v['create_time']):'',
                    $v['update_time']?date('Y-m-d H:i',$v['update_time']):'',
                    $v['open_time']?date('Y-m-d H:i',$v['open_time']):'',
                ];  
                $processedCount++;
            }
            $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
            ->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_progress'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }


    public function import(){

        $result = false;
        try {
            $file = $this->request->file('file');
            
            //$path = '/www/wwwroot/workOrder.test/public/storage/excel';
            $path = $_SERVER['DOCUMENT_ROOT'].'/storage/excel';
            $fineName = 'accountImport.xlsx';
            $info = $file->move($path,$fineName);
    
            $config = [
                'path' => $path
            ];

            $excel = new \Vtiful\Kernel\Excel($config);

            $fileObject = $excel->openFile($fineName)->openSheet()->getSheetData();


            $accountType = DB::table('ba_account_type')->select()->toArray();
            $accountTypeList = array_column($accountType,'id','name');


            $timeList = [
                "-12"=>'GMT -12:00',
                "-11"=>'GMT -11:00',
                "-10"=>'GMT -10:00',
                "-9"=>'GMT -9:00',
                "-8"=>'GMT -8:00',
                "-7"=>'GMT -7:00',
                "-6"=>'GMT -6:00',
                "-5"=>'GMT -5:00',
                "-4"=>'GMT -4:00',
                "-3"=>'GMT -3:00',
                "-2"=>'GMT -2:00',
                "-1"=>'GMT -1:00',
                "0"=>'GMT 0:00',
                "1"=>'GMT +1:00',
                "2"=>'GMT +2:00',
                "3"=>'GMT +3:00',
                "4"=>'GMT +4:00',
                "5"=>'GMT +5:00',
                "5.5"=>'GMT +5:30',
                "6"=>'GMT +6:00',
                "7"=>'GMT +7:00',
                "8"=>'GMT +8:00',
                "9"=>'GMT +9:00',
                "10"=>'GMT +10:00',
                "11"=>'GMT +11:00',
                "12"=>'GMT +12:00',
            ];

            unset($fileObject[0],$fileObject[1]);
            $authAdminId = $this->auth->id;
            if($this->auth->isSuperAdmin()){
                $adminId = 0;
            }else{
                $adminId = $this->auth->id;
            }
 
            $data = [];
            foreach($fileObject as $v){
                $accountTypeId = $accountTypeList[$v[0]]??'';
                $time = $timeList[$v[1]]??'';
                $name = $v[2];
                $bm = $v[3];
                $money = $v[4];
                $adminId = empty($adminId)?($v[5]??0):$adminId;
                
                if(empty($accountTypeId) || empty($time) || empty($name) || empty($bm) || empty($money) || empty($adminId)) continue;

                $data[] = [
                    'name'=>$name,
                    'time_zone'=>$time,
                    'bm'=>$bm,
                    'money'=>$money,
                    'admin_id'=>$adminId,
                    'status'=>$authAdminId==1?1:0,
                    'account_type'=>$accountTypeId,
                    'create_time'=>time()
                ];
            }

            DB::table('ba_account')->insertAll($data);
            $result = true;
            $fileObject->closeSheet();  
            //code...
        } catch (\Throwable $th) {
            //throw $th;
        }
        if ($result !== false) {
            $this->success(__('Added successfully'));
        } else {
            $this->error(__('No rows were added'));
        }
    }

    public function importTemplate()
    {
        $this->success('',['row'=>['path'=>'/storage/default/申请账户模板.xlsx']]);
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}