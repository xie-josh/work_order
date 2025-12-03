<?php

namespace app\admin\controller;

use app\admin\model\card\CardsModel;
use think\facade\Cache;
use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use app\services\CardService;
use app\common\service\QYWXService;
use think\facade\Queue;

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
    protected array $noNeedPermission = ['accountCountMoney','editIs_','audit','index','getAccountNumber','allAudit','distribution','inDistribution','export','getExportProgress','importTemplate','exportAccountDealWith','getExportProgressWeal','updateStatus'];
    protected string|array $quickSearchField = ['id'];

    // protected bool|string|int $dataLimit = 'parent';

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
        $conserve = $this->request->get('conserve');
        $isConserve = $this->request->get('is_conserve');

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $whereOr = [];

        array_push($this->withJoinTable,'accountrequestProposal');

        $adminChannel = Db::table('ba_admin')->column('nickname','id');
        foreach($where as $k => &$v){
            if($v[0] == 'accountrequestProposal.admin_id' && $v[1] == 'LIKE'){
                $v[1] = '=';
                $v[2] = array_flip($adminChannel)[substr($v[2], 1, -1)]??'';
                continue;
                // unset($where[$k]);
            }
            if($v[0] == 'account.id' && $v[1] == 'IN'){
                foreach($v[2] as &$item){
                    if (preg_match('/\d+/', $item, $matches)) {
                        $number = ltrim($matches[0], '0'); // 移除开头的零
                        $item = $number;
                    } else {
                        //$v[2] = $number;
                    }
                    continue;
                }
                continue;
            }
            if($v[0] == 'account.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
                continue;
            }
            if($v[0] == 'account.uuid'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[0] = 'account.id';
                    $v[2] = $number;
                } else {
                    //$v[2] = $number;
                }
                continue;
            }
            if($v[0] == 'account.time_zone'){
                $whereOr[] = ['account.time_zone',$v[1],$v[2]];
                $whereOr[] = ['accountrequestProposal.time_zone',$v[1],$v[2]];
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'account.is_conserve'){
                switch ($v[2]) {
                    case '1':
                        array_push($where,['account.is_keep','=',1]);
                        break;
                    case '2':
                        array_push($where,['account.is_keep','=',1]);
                        array_push($where,['account.keep_succeed','=',0]);
                        break;            
                    case '3':
                        array_push($where,['account.is_keep','=',1]);
                        array_push($where,['account.keep_succeed','=',1]);
                        break;       
                }

                // $whereOr[] = ['account.time_zone',$v[1],$v[2]];
                // $whereOr[] = ['accountrequestProposal.time_zone',$v[1],$v[2]];
                unset($where[$k]);
                continue;
            }
        }
        if($status == 1){
            array_push($where,['account.status','in',[1,3,4,5,6]]);
        }elseif($status == 3){
            //array_push($where,['account.status','in',[3,4]]);
            array_push($where,['account.status','in',[4]]);
        }
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where(function($query) use($whereOr){
                $query->whereOr($whereOr);
            })
            ->order('id','desc')//->find(); dd($this->model->getLastSql());
            ->paginate($limit);
        $dataList = $res->toArray()['data'];
        if($dataList){
            

            $cardsIds = array_filter(array_map(function($dataList) {
                return $dataList['accountrequestProposal']['cards_id'] ?? null;
            }, $dataList));

            $cardNo = DB::table('ba_cards_info')->whereIn('cards_id',$cardsIds)->column('card_no','cards_id');
            $cardNo = array_map(function($cardNo) {
                return substr($cardNo, -4);
            }, $cardNo);

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
                if(empty($v['bes'])){
                    $bes =[];
                    if(!empty($v['email'])) $bes[]=$v['email'];
                    if(!empty($v['bm'])) $bes[]=$v['bm'];                    
                    $v['bes'] = $bes;
                }else{
                    $v['bes'] = json_decode($v['bes']??'',true);
                }
                
                if($conserve && $v['is_keep'] == 1 && $v['keep_succeed'] != 1 && $v['status'] == 4){
                    //养护未完成账户id不显示&&状态是3分配账户
                    $v['account_id'] = '';
                    $v['status'] = 3;
                }
                $v['admin'] = [
                    'username'=>$v['admin']['username']??"",
                    'nickname'=>$v['admin']['nickname']??""
                ];
                if(isset($v['accountrequestProposal']['admin_id']) && $adminChannel[$v['accountrequestProposal']['admin_id']]){
                    $v['channelName'] = $adminChannel[$v['accountrequestProposal']['admin_id']];
                }
                $v['card_no_c'] = '';
                if(!empty($v['accountrequestProposal']['cards_id'])) $v['card_no_c'] = $cardNo[$v['accountrequestProposal']['cards_id']]??'';
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
                if($this->auth->type == 4) throw new \Exception("不可添加！");
                
                $money = $data['money']??0;
                if(empty($data['time_zone']) || empty($data['type'])) throw new \Exception("时区与投放类型不能为空!");
                if(empty($data['is_keep'])){
                    if($money < 200) throw new \Exception("开户金额不能小于200！");
                }else{
                    if($money != 10) throw new \Exception("养户开户金额必须是10！");
                }

                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['name'])) {
                    throw new \Exception("账户名称不能包含中文!");
                }

                $bmList = $data['bes']??[];
                if($data['bm_type'] == 1){
                    if(count($bmList) != 1) throw new \Exception("BM只能填一个！");
                    // if(empty($data['bm'])) throw new \Exception("BM不能为空！");
                    // if(strlen($data['bm']) > 20) throw new \Exception("BM长度不能超过20位！");
                    // if(filter_var($data['bm'], FILTER_VALIDATE_EMAIL) !== false) throw new \Exception("BM类型不能填写邮箱！");
                    // $data['email'] = '';
                }

                // if($data['bm_type'] == 2){
                //     if(empty($data['email'])) throw new \Exception("email不能为空！");
                //     $data['bm'] = '';
                // }
                
                if(!empty($bmList))foreach($bmList as $v){
                    if(filter_var($v, FILTER_VALIDATE_EMAIL) || preg_match('/^\d+$/', $v)){
                    }else throw new \Exception("BM格式错误,请填写正确的BM或邮箱!");    
                    
                    if(filter_var($v, FILTER_VALIDATE_EMAIL))
                    {
                        $isEmail = (new \app\services\Basics())->isEmail($v);
                        if($isEmail['code'] != 1) throw new \Exception($isEmail['msg']);
                    }
                }else throw new \Exception("BM|email不能为空!");
                $data['bes'] = json_encode($bmList??[], true);
                // if($data['bm_type'] == 3 && (empty($data['email']) || empty($data['bm'])))  throw new \Exception("BM 与 Email不能为空！");

                $admin = Db::table('ba_admin')->where('id',$this->auth->id)->find();
                $accountNumber = $admin['account_number'];
                $isAccount = $admin['is_account'];
                $usableMoney = ($admin['money'] - $admin['used_money']);
                if($isAccount != 1) throw new \Exception("未调整可开户数量,请联系管理员添加！");

                if($admin['prepayment_type'] == 2){
                    if($usableMoney <= 0 || $usableMoney < $data['money']) throw new \Exception("余额不足,请联系管理员！");
                    DB::table('ba_admin')->where('id',$this->auth->id)->inc('used_money',$data['money'])->update();
                }


                $time = date('Y-m-d',time());
                $openAccountNumber = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
                if($openAccountNumber >= $accountNumber) throw new \Exception("今.开户数量已经不足，不能再提交开户需求,请联系管理员！");

                // DB::table('ba_account')->where('id',$account['id'])->inc('money',$data['number'])->update(['update_time'=>time()]);
                
                if(isset($data['is_keep']) && in_array($data['type'],[1,3]) && $data['is_keep'] == 1) $data['is_keep'] = 1;
                else $data['is_keep'] = 0;

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

        if(!$this->auth->isSuperAdmin() && $row['status'] != 0){
            $this->error('已经审核不可在修改！');
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
                
                unset($data['status']);
                unset($data['money']);

                if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['name'])) {
                    throw new \Exception("账户名称不能包含中文!");
                }
                
                if(!$this->auth->isSuperAdmin()){
                    unset($data['dispose_status']);
                }
               
                if($data['bm_type'] == 1 ) if(count($data['bes']) != 1)   throw new \Exception("BM只能填一个！");
                
                 if(!empty($data['bes']))foreach($data['bes'] as $v){
                    if(filter_var($v, FILTER_VALIDATE_EMAIL) || preg_match('/^\d+$/', $v)){
                    }else throw new \Exception("BM格式错误,请填写正确的BM或邮箱!");

                    if(filter_var($v, FILTER_VALIDATE_EMAIL))
                    {
                        $isEmail = (new \app\services\Basics())->isEmail($v);
                        if($isEmail['code'] != 1) throw new \Exception($isEmail['msg']);
                    }
                }else throw new \Exception("email不能为空!");
                $data['bes'] = json_encode($data['bes']??[], true);

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
        $row['bes'] =json_decode($row['bes']??'', true)??[];
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
            
            $redisLock = new \app\services\RedisLock();
            $redisLockList = $data['ids'];

            foreach($redisLockList as $v){
                $key = 'account_audit_'.$v;
                $acquired = $redisLock->acquire($key, 'audit', 180);
                if(!$acquired) $this->error($v.":该需求被锁定，在处理中！");               
            }

            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $accountId = $data['account_id']??0;
                $status = $data['status'];
                $accountrequestProposalStatus = $data['accountrequest_proposal_status']??2;
                $timeZone = $data['time_zone']??'';
                                
                if($status == 1){
                    $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                    $list = $this->model->whereIn('id',$data['ids'])->field('admin_id,id,money')->whereIn('status',[2,5])->select()->append([])->toArray();
                    if(!empty($list)){
                        // $amount = $this->model->whereIn('id',array_column($list,'id'))->whereIn('status',[2,5])->sum('money');
                        foreach($list as $v){
                            if($v['money'] <= 0) continue;
                            $admin = Db::table('ba_admin')->where('id',$v['admin_id'])->find();
                            $usableMoney = bcsub((string)$admin['money'], (string)$admin['used_money'],2);
                            
                            if($usableMoney <= 0 || $usableMoney < $v['money']) throw new \Exception($admin['nickname'].":余额不足,不足以支持该开户需求,请联系管理员！");

                            DB::table('ba_admin')->where('id',$v['admin_id'])->inc('used_money',$v['money'])->update();
                            $this->model->where('id',$v['id'])->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                        }
                        // if(!empty($list)) $this->model->whereIn('id',array_column($list,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                    }
                    
                }elseif($status == 2){
                    $ids = $this->model->whereIn('id',$ids)->whereIn('status',[0,1])->select()->toArray();
                    foreach($ids as $v){
                        if($v['money'] <= 0) continue;
                        DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['money'])->update();
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                }elseif($status == 3){
                    $ids = $this->model->whereIn('id',$ids)->where('status',1)->select()->toArray();

                    foreach($ids as $v){
                        $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->whereIn('status',config('basics.FH_status'))->find();
                        if(empty($accountrequestProposal)) throw new \Exception("请选择分配的账户！");

                        if(!empty($v['type'])){
                            $lableIds = explode(',',$accountrequestProposal['label_ids']??'');
                            if(!empty($lableIds) && !in_array($v['type'],$lableIds)) throw new \Exception("标签映射错误，请调整！");
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

                        if(!in_array($v['admin_id'],config('basics.NOT_SERIAL_NAME'))) $getSerialName = (new \app\admin\services\addaccountrequest\AccountrequestProposal())->getSerialName($accountrequestProposal);
                        else $getSerialName = $v['name'];


                        $data = ['status'=>1,'allocate_time'=>$allocateTime,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time(),'serial_name'=>$getSerialName,'currency'=>$v['currency']];
                        if(empty($accountrequestProposal['time_zone']) && !empty($v['time_zone'])) $data['time_zone'] = $v['time_zone'];
                        DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->update($data);
                        
                    //     $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('admin_id',$adminId)->where('status',0)->find();
                    //     if(empty($accountrequestProposal))  continue;//throw new \Exception("该渠道暂时没有账号可以分配");
                    //     $accountId = $accountrequestProposal['account_id'];
                        
                    //     $result = $this->model->where('id',$v['id'])->update(['account_admin_id'=>$adminId,'status'=>$status,'account_id'=>$accountId]);
    
                    //     DB::table('ba_accountrequest_proposal')->where('id',$accountrequestProposal['id'])->update(['status'=>1,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time()]);

                    //     //if(!empty($v['money'])) DB::table('ba_recharge')->insert(['account_name'=>$v['name'],'account_id'=>$accountId,'type'=>1,'number'=>$v['money'],'status'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()]);

                        // if(!empty($v['bm']) || !empty($v['email'])){
                        //     $bmDataList = [];
                        //     if($v['bm_type'] == 3){
                        //         $bmDataList[] = ['account_name'=>$v['name'],'account_id'=>$accountId,'bm'=>$v['bm'],'bm_type'=>1,'demand_type'=>4,'status'=>0,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()];
                        //         $bmDataList[] = ['account_name'=>$v['name'],'account_id'=>$accountId,'bm'=>$v['email'],'bm_type'=>2,'demand_type'=>4,'status'=>0,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()];
                        //     }else{
                        //         $bm = empty($v['bm'])?$v['email']:$v['bm'];
                        //         $bmDataList[] = ['account_name'=>$v['name'],'account_id'=>$accountId,'bm'=>$bm,'bm_type'=>$v['bm_type'],'demand_type'=>4,'status'=>0,'dispose_type'=>0,'admin_id'=>$v['admin_id'],'create_time'=>time()];
                        //     }
                        //     DB::table('ba_bm')->insertAll($bmDataList);
                        //     if(env('IS_ENV',false)) (new QYWXService())->bmSend(['account_id'=>$accountId],4);
                        // }
                    }
                }elseif($status == 4){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    $keepStatus = 6; 
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
                        if(empty($v['bm']) && empty($v['email'])) $accountData['dispose_status'] = 1;
                        if(!empty($accountData)) $this->model->where('id',$v['id'])->update($accountData);
                        DB::table('ba_bm')->where('account_id',$v['account_id'])->update(['account_is'=>1]);

                        $this->model->whereIn('id',$v['id'])->update(['open_money'=>$v['money']]);
                        
                        //修改名称
                        $editAdAccounts = [];
                        $editAdAccounts['account_id'] = $resultProposal['account_id'];
                        $editAdAccounts['name'] = $resultProposal['serial_name'];
                        (new \app\admin\services\fb\FbService())->assignedUsers($editAdAccounts);
                        (new \app\admin\services\fb\FbService())->editAdAccounts($editAdAccounts);


                        //根据bes生成对等个数的开户绑定条数
                        $besArr =  json_decode($v['bes']??'', true)??[];
                        $bmDataList = [];
                        if(!empty($besArr))foreach($besArr as $be){
                            if(filter_var($be, FILTER_VALIDATE_EMAIL)){
                                $strBes = $be;
                                $bmType = 2;
                            }else if (preg_match('/^\d+$/', $be)){
                                $strBes = $be;
                                $bmType = 1;
                            }
                            $bmDataList[] = [
                                'account_name'=>$v['name'],
                                'account_id'=>$v['account_id'],
                                'bm'=>$strBes,
                                'demand_type'=>4,
                                'bm_type'=>$bmType,
                                'status'=>0,
                                'dispose_type'=>0,
                                'admin_id'=>$v['admin_id'],
                                'create_time'=>time(),
                            ];
                        }else{
                            //旧数据
                            if(!empty($v['bm']) || !empty($v['email'])){
                                    
                                    if($v['bm_type'] == 3){
                                        $bmDataList[] = [
                                            'account_name'=>$v['name'],
                                            'account_id'=>$v['account_id'],
                                            'bm'=>$v['bm'],
                                            'bm_type'=>1,
                                            'demand_type'=>4,
                                            'status'=>0,
                                            'dispose_type'=>0,
                                            'admin_id'=>$v['admin_id'],
                                            'create_time'=>time(),
                                        ];
                                        $bmDataList[] = [
                                            'account_name'=>$v['name'],
                                            'account_id'=>$v['account_id'],
                                            'bm'=>$v['email'],
                                            'bm_type'=>2,
                                            'demand_type'=>4,
                                            'status'=>0,
                                            'dispose_type'=>0,
                                            'admin_id'=>$v['admin_id'],
                                            'create_time'=>time(),
                                        ];
                                    }else{
                                        $bmDataList[] = [
                                            'account_name'=>$v['name'],
                                            'account_id'=>$v['account_id'],
                                            'bm'=>empty($v['bm'])?$v['email']:$v['bm'],   //$resultAccount['bm'],
                                            'bm_type'=>$v['bm_type'],
                                            'demand_type'=>4,
                                            'status'=>0,
                                            'dispose_type'=>0,
                                            'admin_id'=>$v['admin_id'],
                                            'create_time'=>time(),
                                        ];
                                    }
                                }

                        }
                        //养护类型完成不生成bm需求&&原本完成到待绑定的状态直接到完成
                        if($v['is_keep'] == 1){
                            $keepStatus = 4; 
                        }else{
                            if(!empty($bmDataList)) DB::table('ba_bm')->insertAll($bmDataList);
                            $keepStatus = 6; 
                        } 
                      
                        if($resultProposal['is_cards'] == 2) continue;
                        $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();

                        // $key = 'account_audit_'.$v['id'];
                        // $redisValue = Cache::store('redis')->get($key);
                        if(!empty($redisValue)) throw new \Exception("该数据在处理中，不需要重复点击！");
                                                    
                        if(empty($cards)) {
                            //TODO...
                                throw new \Exception("未找到分配的卡或把账户设置成无卡！");
                        }else if($v['money'] > 0){
                            $param = [
                                //'max_on_percent'=>env('CARD.MAX_ON_PERCENT',901),
                                'transaction_limit_type'=>'limited',
                                'transaction_limit_change_type'=>'increase',
                                'transaction_limit'=>$v['money'],
                            ];

                            // Cache::store('redis')->set($key, '1', 180);
                              //SX-用户不改限额
                              if(env('APP.IS_QUOTA'))
                              {
                                  $resultCards = (new CardsModel())->updateCard($cards,$param);
                                  if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                              }
                            // Cache::store('redis')->delete($key);
                        }

                    }                                                                     //4开户完成->6待开户绑定
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$keepStatus,'update_time'=>time(),'open_time'=>time(),'operate_admin_id'=>$this->auth->id,'is_'=>1]);
                }elseif($status == 5){
                    $ids = $this->model->whereIn('id',$ids)->where('status',3)->select()->toArray();
                    $accountIds = array_column($ids,'account_id');
                    foreach($ids as $v){
                        DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['money'])->update();
                    }
                    $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>5,'money'=>0,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                    DB::table('ba_bm')->whereIn('account_id',$accountIds)->update(['dispose_type'=>2,'status'=>2]);
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

                    if($accountrequestProposalStatus != 0)
                    {
                        $cardsList = DB::table('ba_accountrequest_proposal')
                        ->field('accountrequest_proposal.account_id accountrequest_proposal_account_id,cards_info.id,cards_info.card_status,cards_info.card_id,cards_info.account_id')
                        ->alias('accountrequest_proposal')
                        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                        ->whereIn('accountrequest_proposal.account_id',$accountIds)
                        ->select()->toArray();
    
                        foreach($cardsList as $cards)
                        {
                            if(!empty($cards) && $cards['card_status'] == 'normal') {
                                $resultCards = (new CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                                if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                            }
                            (new \app\admin\services\card\Cards())->allCardFreeze($cards['accountrequest_proposal_account_id']);
                        }
                    }
                    //$cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                }else if($status == 7){
                
                    //养护完成生成bm需求
                    $keep['status']=4;
                    $keep['keep_succeed']=0;
                    $keep['is_keep']=1;
                    $ids = $this->model->whereIn('id',$ids)->where($keep)->select()->toArray();
                    foreach($ids as $v){

                            $personalbmTokenIds = DB::table('ba_accountrequest_proposal')
                            ->alias('a')
                            ->where('a.account_id',$v['account_id'])
                            ->leftJoin('ba_fb_bm_token b','b.id=a.bm_token_id')
                            ->value('b.personalbm_token_ids');

                            if(empty($personalbmTokenIds)) throw new \Exception($v['account_id'].":该账户未配置个人BM！");

                            $params = [
                                'account_id'=>$v['account_id'],
                                'effective_status'=>['ACTIVE'],
                            ];

                            $FacebookService = new \app\services\FacebookService();

                            $params['token'] = (new \app\admin\services\fb\FbService())->getPersonalbmToken($personalbmTokenIds);

                            $result = $FacebookService->getAdsCampaignsList($params);

                            if($result['code'] != 1) throw new \Exception($v['account_id'].":".$result['msg']);
                            if($result['code'] == 1 && !empty($result['data']['data'])) throw new \Exception($v['account_id'].":该账户中找到有效广告系列，请关闭！");

                            $result = $FacebookService->adAccounts($params);
                            if($result['code'] != 1) throw new \Exception('无法查询该账户消耗！');
                            $spend = $result['data']['amount_spent'];
                            $currency = $result['data']['currency'];

                            $currencyRate = config('basics.currencyRate');
                            if(!empty($currencyRate[$currency])){
                                $spend = bcmul((string)$spend, $currencyRate[$currency],2);
                            }
                            if($spend > 11) throw new \Exception($v['account_id'].":该账户消耗大于11刀，请联系管理员处理！(当前消耗:{$spend})");

                            //根据bes生成对等个数的开户绑定条数
                            $besArr =  json_decode($v['bes']??'', true)??[];
                            $bmDataList = [];
                            if(!empty($besArr))foreach($besArr as $be){
                                if(filter_var($be, FILTER_VALIDATE_EMAIL)){
                                    $strBes = $be;
                                    $bmType = 2;
                                }else if (preg_match('/^\d+$/', $be)){
                                    $strBes = $be;
                                    $bmType = 1;
                                }
                                $bmDataList[] = [
                                    'account_name'=>$v['name'],
                                    'account_id'=>$v['account_id'],
                                    'bm'=>$strBes,
                                    'demand_type'=>4,
                                    'bm_type'=>$bmType,
                                    'status'=>0,
                                    'dispose_type'=>0,
                                    'admin_id'=>$v['admin_id'],
                                    'create_time'=>time(),
                                ];
                            }
                            if(!empty($bmDataList)) DB::table('ba_bm')->insertAll($bmDataList);
                    }
                    if(!empty($ids)) $this->model->whereIn('id',array_column($ids,'id'))->update(['keep_succeed'=>1,'keep_time'=>time(),'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                } //
                //$this->model->whereIn('id',array_column($ids,'id'))->update(['money'=>0,'is_'=>1]);
                $result = true;
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }finally {
               foreach($redisLockList as $v){
                    $key = 'account_audit_'.$v;
                    $redisLock->release($key, 'audit');
                }
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
        if($admin['prepayment_type'] == 1)
        {
            $consumptionService = new \app\admin\services\fb\Consumption();
            $totalDollar = $consumptionService->getTotalDollar($this->auth->id);
            $money = $admin['money'] ?? 0;
            $data['usableMoney'] = bcsub((string)$money,(string)$totalDollar,'2');
        }else{
            $data['usableMoney'] = floor((($admin['money'] - $admin['used_money'])) * 100) / 100;
        }

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
                    $accountIds = DB::table('ba_accountrequest_proposal')->where('admin_id',$accountrequestProposalId)->whereIn('status',config('basics.FH_status'))->select()->toArray();
                }else{
                    $accountIds = DB::table('ba_accountrequest_proposal')->where('admin_id',$accountrequestProposalId)->whereIn('account_id',$accountIds)->whereIn('status',config('basics.FH_status'))->select()->toArray();
                }
                $resultAccountList = DB::table('ba_account')->whereIn('id',$ids)->where('status',1)->select()->toArray();                
                // $bmDataList = [];
                foreach($accountIds as $k => $v)
                {
                    $resultAccount = $resultAccountList[$k]??[];
                    if(empty($resultAccount)) continue;
                    
                    $lableIds = explode(',',$v['label_ids']??'');
                    if(!empty($lableIds) && !empty($resultAccount['type']) && !in_array($resultAccount['type'],$lableIds)) continue;
                   
                    $data = [
                        'account_admin_id'=>$v['admin_id'],
                        'status'=>3,
                        'account_id'=>$v['account_id'],
                        'is_'=>1,
                        'update_time'=>time()
                    ];                    

                    // if(!empty($v['bm']) || !empty($v['email'])){
                        
                    //     if($resultAccount['bm_type'] == 3){
                    //         $bmDataList[] = [
                    //             'account_name'=>$resultAccount['name'],
                    //             'account_id'=>$v['account_id'],
                    //             'bm'=>$resultAccount['bm'],
                    //             'bm_type'=>1,
                    //             'demand_type'=>4,
                    //             'status'=>0,
                    //             'dispose_type'=>0,
                    //             'admin_id'=>$resultAccount['admin_id'],
                    //             'create_time'=>time(),
                    //         ];
                    //         $bmDataList[] = [
                    //             'account_name'=>$resultAccount['name'],
                    //             'account_id'=>$v['account_id'],
                    //             'bm'=>$resultAccount['email'],
                    //             'bm_type'=>2,
                    //             'demand_type'=>4,
                    //             'status'=>0,
                    //             'dispose_type'=>0,
                    //             'admin_id'=>$resultAccount['admin_id'],
                    //             'create_time'=>time(),
                    //         ];
                    //     }else{
                    //         $bmDataList[] = [
                    //             'account_name'=>$resultAccount['name'],
                    //             'account_id'=>$v['account_id'],
                    //             'bm'=>empty($resultAccount['bm'])?$resultAccount['email']:$resultAccount['bm'],   //$resultAccount['bm'],
                    //             'bm_type'=>$resultAccount['bm_type'],
                    //             'demand_type'=>4,
                    //             'status'=>0,
                    //             'dispose_type'=>0,
                    //             'admin_id'=>$resultAccount['admin_id'],
                    //             'create_time'=>time(),
                    //         ];
                    //     }
                    // }
                   
                    if(!empty($v['time_zone'])) $data['time_zone'] = $v['time_zone'];
                    DB::table('ba_account')->where('id',$resultAccount['id'])->update($data);
                    $allocateTime = date('md',time());

                    if(!in_array($resultAccount['admin_id'],config('basics.NOT_SERIAL_NAME'))) $getSerialName = (new \app\admin\services\addaccountrequest\AccountrequestProposal())->getSerialName($v);
                    else $getSerialName = $resultAccount['name'];
                    
                    $data = ['status'=>1,'affiliation_admin_id'=>$resultAccount['admin_id'],'allocate_time'=>$allocateTime,'update_time'=>time(),'serial_name'=>$getSerialName,'currency'=>$resultAccount['currency']];
                    if(empty($v['time_zone']) && !empty($resultAccount['time_zone'])) $data['time_zone'] = $resultAccount['time_zone'];
                    DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->update($data);
                }
                // if(!empty($bmDataList)) DB::table('ba_bm')->insertAll($bmDataList);

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
                $proposalData['is_cards'] = 0;
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

    // public function getAccountRecycleJobErrorCount()
    // {
    //     $taskCount =  Cache::store('redis')->handler()->llen('{queues:AccountPendingRecycle}');
    //     $comment = '';
    //     if($taskCount >0){
    //         $comment = "消耗查询任务正在执行中！( $taskCount )";
    //     }
    //     $this->success('', ['comment' => $comment]);
    // }

    public function errAccount()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            try {             
                $accountList = $data['account_list'];
                $accountStatus = $data['account_status']??0;

                $taskCount =  Cache::store('redis')->handler()->llen('{queues:AccountRecycle}');

                if($taskCount > 0) throw new \Exception('在回收，请回收完成后在加入新的任务!');
                
                if(empty($accountList)) throw new \Exception("Error Processing Request");

                $bmDataList = DB::table('ba_bm')->whereIn('account_id',$accountList)->where('demand_type',2)->where(function($query){
                    $query->whereOr(
                        [
                            ['status','=',0],
                            ['dispose_type','=',0]
                        ]   
                        );
                })->where([
                    ['status','<>',2],
                    ['dispose_type','<>',2]
                ])->find();
                if(!empty($bmDataList)) throw new \Exception("BM解绑未处理完成，请先处理BM解绑！".$bmDataList['account_id']);

                $rechargeDataList = DB::table('ba_recharge')->whereIn('account_id',$accountList)
                ->whereIn('type',[3,4])->where('status',0)->find();

                if(!empty($rechargeDataList)) throw new \Exception("充值需求还有未处理的，请先处理完成！".$rechargeDataList['account_id']);
              
                $accountDataList = DB::table('ba_account')->whereIn('account_id',$accountList)->select()->toArray();
                if(empty($accountDataList)) throw new \Exception(implode(',',$accountList)."账户ID没有可回收的账户！");

                foreach ($accountList as $value) {
                    $accountId = $value;

                    $jobHandlerClassName = 'app\job\AccountRecycle';
                    $jobQueueName = 'AccountRecycle';
                    Queue::later(1, $jobHandlerClassName, ['account_id'=>$accountId,'status'=>$accountStatus], $jobQueueName);
                }

                $result = true;
                // if(!empty($diffData)) throw new \Exception(implode(',',$diffData)."账户ID回收失败！");
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }


    public function errAccount2()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            Db::startTrans();
            try {             
                $accountList = $data['account_list'];
                $accountStatus = $data['account_status']??0;

                if(empty($accountList)) throw new \Exception("Error Processing Request");

                $bmDataList = DB::table('ba_bm')->whereIn('account_id',$accountList)->where('demand_type',2)->where(function($query){
                    $query->whereOr(
                        [
                            ['status','=',0],
                            ['dispose_type','=',0]
                        ]   
                        );
                })->where([
                    ['status','<>',2],
                    ['dispose_type','<>',2]
                ])->find();
                if(!empty($bmDataList)) throw new \Exception("BM解绑未处理完成，请先处理BM解绑！".$bmDataList['account_id']);

                $rechargeDataList = DB::table('ba_recharge')->whereIn('account_id',$accountList)
                ->whereIn('type',[3,4])->where('status',0)->find();

                 if(!empty($rechargeDataList)) throw new \Exception("充值需求还有未处理的，请先处理完成！".$rechargeDataList['account_id']);
              
                $accountDataList = DB::table('ba_account')->whereIn('account_id',$accountList)->select()->toArray();
                $bmDataList = DB::table('ba_bm')->whereIn('account_id',$accountList)->select()->toArray();;
                $rechargeDataList = DB::table('ba_recharge')->whereIn('account_id',$accountList)->select()->toArray();
                if(empty($accountDataList)) throw new \Exception(implode(',',$accountList)."账户ID没有可回收的账户！");
                $diffData = array_diff($accountList,array_column($accountDataList,'account_id'));
                foreach ($accountList as $value) {
                    $accountId = $value;
                    DB::table('ba_account')->where('account_id',$accountId)->update(['account_id'=>'','status'=>2,'dispose_status'=>0,'open_money'=>0,'money'=>0]);
                    DB::table('ba_bm')->where('account_id',$accountId)->delete();
                    DB::table('ba_recharge')->where('account_id',$accountId)->delete();
                }

                DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountList)->update(
                    ['status'=>$accountStatus,'affiliation_admin_id'=>'']
                ); 

                $accountDataList = array_map(function($v){
                    $v['account_recycle_time'] = date('Y-m-d H:i:s',time());
                    unset($v['id']);
                    return $v;
                },$accountDataList);

                DB::table('ba_account_recycle')->insertAll($accountDataList);
                DB::table('ba_bm_recycle')->insertAll($bmDataList);
                DB::table('ba_recharge_recycle')->insertAll($rechargeDataList);

                //dd($accountList,$accountStatus);
                $result = true;
                Db::commit();
                if(!empty($diffData)) throw new \Exception(implode(',',$diffData)."账户ID回收失败！");
            } catch (Throwable $e) {
                if(empty($diffData)) Db::rollback();
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
        ->field('account.account_type,account.open_time,account.id,account.admin_id,account.name,account.account_id,account.time_zone,account.bm,account.open_money,account.dispose_status,account.status,account.create_time,account.update_time,accountrequest_proposal.id accountrequest_proposal_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm accountrequest_proposal_bm')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        //->withJoin(['accountrequestProposal'], 'LEFT')
        ->order('account.id','desc')
        ->where($where);

        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $accountTypeList = DB::table('ba_account_type')->field('id,name')->select()->toArray();
        $accountTypeListValue = array_column($accountTypeList,'name','id');

        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $statusValue = config('basics.OPEN_ACCOUNT_STATUS');
        // $disposeStatusValue = [0=>'待处理',1=>'处理完成',2=>'已提交',3=>'提交异常',4=>'处理异常'];

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'ID',
            // '管理BM',
            '用户名',
            '账户名称',
            '账户ID',
            '时区',
            '绑定BM',
            '首充金额',
            // 'BM绑定',
            '开户状态',
            '账户类型',
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
                    // $v['accountrequest_proposal_id']?$v['accountrequest_proposal_bm']:'',
                    //($v['accountrequestProposal']['bm'])??'',
                    ($adminList[$v['admin_id']])??'',
                    $v['accountrequest_proposal_id']?$v['serial_name']:$v['name'],
                    //$v['name'],
                    $v['status'] != 4 ? "" : $v['account_id'],
                    $v['time_zone'],
                    $v['bm'],
                    $v['open_money'],
                    // $disposeStatusValue[$v['dispose_status']],
                    $statusValue[$v['status']],
                    $accountTypeListValue[$v['account_type']]??'',
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


            $accountType = config('basics.account_type');
            $accountTypeList = array_flip($accountType);


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
                "3.5"=>'GMT +3:30',
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

            unset($fileObject[0],$fileObject[1],$fileObject[2]);
            $authAdminId = $this->auth->id;
            if($this->auth->type == 4){
                // $adminId = 0;
                throw new \Exception("不可申请！");
            }else{
                $adminId = $this->auth->id;
            }

            /**
             * 1.计算总开户数量
             * 2.计算总金额
             * 
             * 
             * 
             */
            $column = '0';
            $filteredArray = array_filter($fileObject, function($row) use ($column) {
                return !empty($row[$column]);
            });


            $countNumber = count($filteredArray);
            if($countNumber < 1) throw new \Exception("Error Processing Request");
            

            // dd($fileObject,$filteredArray);
            // $countAmout = array_sum(array_column($fileObject, '4'));

            $admin = Db::table('ba_admin')->where('id',$this->auth->id)->find();
            $accountNumber = $admin['account_number'];
            $prepaymentType = $admin['prepayment_type'];
            // $isAccount = $admin['is_account'];
            // $usableMoney = ($admin['money'] - $admin['used_money']);
            // if($isAccount != 1) throw new \Exception("未调整可开户数量,请联系管理员添加！");
            // if($usableMoney <= 0 || $usableMoney < $countAmout) throw new \Exception("余额不足,请联系管理员！");

            $time = date('Y-m-d',time());
            $openAccountNumber = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
            if($accountNumber < ($countNumber + $openAccountNumber) && $this->auth->id != 1) throw new \Exception("今.开户数量已经不足，不足你提交表格里面申请的开户需求,请联系管理员或减少申请数量！");
 
            $notMoneyAdminList = explode(',',env('CACHE.NOT_MONEY_admin',''));
            $data = [];
            $isKeepCount = 0;
            foreach($filteredArray as $v){
                $accountTypeId = $accountTypeList[$v[0]]??'';
                $time = $timeList[(String)$v[1]]??'';
                $name = $v[2];
                 $isKeep = 0;
                // $adminId = empty($adminId)?($v[5]??0):$adminId;

                if(in_array($adminId,$notMoneyAdminList) && !empty($v[3])) $money = $v[3];
                else $money = 0;

                if(in_array($accountTypeId,[1,3]) && $v[5] == 1)
                {
                    $isKeep = 1;//---养户充值
                    $isKeepCount++;
                } 
                if(!in_array($accountTypeId,[1,3]) && $v[5] == 1)
                {
                    throw new \Exception("养户只针对于【电商/游戏】类型  请修改账户名称为".$v[2]."的投放类型,或改成非养户类型后重新导入");
                } 

                $currency = $v[4];
                // $isKeep = $v[5];
                if($isKeep)$open_money =10;
                else$open_money = 0;
                
                $bes = [];
                $i=6;
                while ($i <= 100) {
                    if(!empty($v[$i])){
                        if(filter_var($v[$i], FILTER_VALIDATE_EMAIL)) 
                        $bes[] = $v[$i];
                        else throw new \Exception($v[$i]."邮箱格式错误,请填写正确的邮箱后重新导入！");
                        $i++;  
                    }else{ break; }
                }
           
                if(empty($accountTypeId) || empty($time) || empty($name) || empty($bes) ) continue;

                $d = [
                    'name'=>$name,
                    'time_zone'=>$time,
                    // 'email'=>'',
                    // 'bm'=>$bm,
                    'bes'=>json_encode($bes??[]),
                    'bm_type'=>2,
                    'money'=>$open_money,
                    // 'open_money'=>$open_money, //---养户充值10
                    'company_id'=>$this->auth->company_id,
                    'admin_id'=>$adminId,
                    'status'=>$authAdminId==1?1:0,
                    'currency'=>$currency,
                    'type'=>$accountTypeId,
                    'is_keep'=>$isKeep, 
                    'create_time'=>time()
                ];
                $data[] = $d;
            }
            if($isKeepCount>0 && $prepaymentType != 1)
            {
                $amount =  bcmul($isKeepCount, '10');//---养户充值*10
                $where['id'] = $this->auth->id;
                $result =  DB::table('ba_admin')
                            ->whereRaw("money - used_money > $amount")
                            ->where($where)
                            ->inc('used_money', $amount)                   
                            ->update();
                if(!$result) throw new \Exception("养户所需余额不足请充值！");
            }
            DB::table('ba_account')->insertAll($data);
            $result = true;
            //$fileObject->closeSheet();  
            //code...
        } catch (Throwable $th) {
            $this->error($th->getMessage());
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

    public function updateStatus()
    {
        $data = $this->request->param();
        // $status = $data['status'];
        $ids = $data['ids'];

        //开户时间是3天内
        //且只能是养户
        //未充值的

        $where = [
            // ['is_keep','=',1],
            ['status','IN',[4,6]],
            ['account_id','IN',$ids],
            ['open_time','>',strtotime('-3 days')]
        ];

        $accountIds = DB::table('ba_account')->where($where)->column('account_id');
        if(empty($accountIds)) $this->error('未找到可以修改的数据，请先确实在条件内[(待绑定/完成) + 开户三天内]');

        $accountRechargeCIds = DB::table('ba_recharge')->whereIn('account_id',$accountIds)->column('account_id');
        if(!empty($accountRechargeCIds)) $this->error('你选择的数据有充值需求，不可变更!'.implode(',',$accountRechargeCIds));
        
        $result = DB::table('ba_account')->whereIn('account_id',$accountIds)->update(['status'=>3,'operate_admin_id'=>$this->auth->id]);
        if ($result !== false) {
            $this->success(__('Update successful'));
        } else {
            $this->error(__('No rows updated'));
        }
    }


    public function exportAccountDealWith()
    {
        $where = [];
        set_time_limit(300);
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_progress_weal'.'_'.$this->auth->id;

        array_push($where,['account.status','in',[1,3,4,5,6]]);
        
        $query =  $this->model
        ->alias('account')
        ->field('account.is_keep,account.operate_admin_id,account.account_type,account.open_time,account.id,account.admin_id,account.name,account.account_id,account.time_zone,account.bm,account.open_money,account.dispose_status,account.status,account.create_time,account.update_time,accountrequest_proposal.id accountrequest_proposal_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm accountrequest_proposal_bm,accountrequest_proposal.admin_id accountrequest_proposal_admin_id')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        //->withJoin(['accountrequestProposal'], 'LEFT')
        ->order('account.id','desc')
        ->where($where);

        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $accountTypeList = DB::table('ba_account_type')->field('id,name')->select()->toArray();
        $accountTypeListValue = array_column($accountTypeList,'name','id');

        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $statusValue = config('basics.OPEN_ACCOUNT_STATUS');
        // $disposeStatusValue = [0=>'待处理',1=>'处理完成',2=>'已提交',3=>'提交异常',4=>'处理异常'];

        $isKeepValue = [0=>'否',1=>'是'];

        //管理 bm，渠道，账户名称，id，开户时间，处理人

        // dd($query->fetchSql()->find());

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '管理BM',
            '渠道',
            '账户名称',
            '账户ID',
            '开户时间',
            '处理人',
            '开户状态',
            '是否养户',
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
                    $v['accountrequest_proposal_id']?$v['accountrequest_proposal_bm']:'',
                    ($adminList[$v['accountrequest_proposal_admin_id']])??'',
                    $v['accountrequest_proposal_id']?$v['serial_name']:$v['name'],
                    $v['account_id'],
                    $v['open_time']?date('Y-m-d H:i',$v['open_time']):'',
                    ($adminList[$v['operate_admin_id']])??'',
                    $statusValue[$v['status']],
                    $isKeepValue[$v['is_keep']],
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

    public function getExportProgressWeal()
    {
        $progress = Cache::store('redis')->get('export_progress_weal'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}