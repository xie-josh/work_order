<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use think\facade\Cache;
use app\common\controller\Backend;

class Account extends Backend
{
    /**
     * @var object
     * @phpstan-var UserGroup
     */
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];

    protected string|array $quickSearchField = 'name';
    protected array $noNeedPermission = ['index','add','edit','addAccountTeam','delAccountTeam','accountCountMoney','getAccountNumber','getExportProgress','export','importTemplate','import','needTotal','batchAdd','getComapnyCard'];


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
        $teamType    = $this->request->get('team_type');
        $teamId =   $this->request->get('team_id');

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $whereOr = [];
        
        array_push($this->withJoinTable,'accountrequestProposal');

        $adminChannel = Db::table('ba_admin')->column('nickname','id');
        foreach($where as $k => $v){
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
            // if($v[0] == 'account.team_id'){
            //     unset($where[$k]);
            //     if($v[1] == "=") array_push($where,['account.team_id','exp',Db::raw('IS NULL')]);
            //     if($v[1] == "<>") array_push($where,['account.team_id','exp',Db::raw('IS NOT NULL')]);
            // }
        }
        if($teamType==1) 
        {
            array_push($where,['account.team_id','exp',Db::raw('IS NULL')]);
            array_push($where,['account.status','in',[4]]);
        }
        if(!empty($teamId)) array_push($where,['account.team_id','=',$teamId]);
       
        array_push($where,['account.company_id','=',$this->auth->company_id]);
        // array_push($where,['account.status','in',[4]]);
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where(function($query) use($whereOr){
                $query->whereOr($whereOr);
            })
            ->order('id','desc')//->find();
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
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function batchAdd(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            $errorList = [];
            DB::startTrans();
            try {

                if($this->auth->type == 4) throw new \Exception("不可添加！");

                $company = Db::table('ba_company')->where('id',$this->auth->company_id)->find();
                $isAccount = $company['is_account'];
                $accountNumber = $company['account_number'];
                if($isAccount != 1) throw new \Exception("未调整可开户数量,请联系管理员添加！");
                if(empty($company)) throw new \Exception("您没有归属公司，请联系管理员！");

                $time = date('Y-m-d',time());
                $account_count = count($data['list'])??0;
                $openAccountNumber = Db::table('ba_account')->where('company_id',$this->auth->company_id)->whereDay('create_time',$time)->count();
                if(($openAccountNumber + $account_count) > $accountNumber) throw new \Exception("今.开户数量已经不足，不能再提交开户需求,请联系管理员！");

                $list = $data['list'];
                $listData = [];
                if(empty($list)) throw new \Exception("请提交数据！");
                foreach($list as $v)
                {            
                    $money = $v['money']??0;
                    if(empty($v['time_zone']) || empty($v['type'])){
                        $errorList[] = ['name'=>$v['name'],'msg'=>'时区与投放类型不能为空!'];
                        continue;
                    }
                    if(empty($v['is_keep'])){
                        if($money < 200){
                            $errorList[] = ['name'=>$v['name'],'msg'=>'开户金额不能小于200！'];
                            continue;
                        } 
                    }else{
                        if($money != 10){
                            $errorList[] = ['name'=>$v['name'],'msg'=>'养户开户金额必须是10！'];
                            continue;
                        }
                    }

                    if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $v['name'])){
                        $errorList[] = ['name'=>$v['name'],'msg'=>'账户名称不能包含中文!'];
                        continue;
                    }

                    if(empty($v['currency']))
                    {
                        $v['currency'] = 'USD';
                        // $errorList[] = ['name'=>$v['name'],'msg'=>'账户名称不能包含中文!'];
                        // continue;

                    }

                    $bmList = $v['bes']??[];
                    if($v['bm_type'] == 1 && count($bmList) != 1){
                        $errorList[] = ['name'=>$v['name'],'msg'=>'BM只能填一个！'];   
                        continue;
                    }

                    if(!empty($bmList))foreach($bmList as $value){
                        if(filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/^\d+$/', $value)){
                        }else{
                            $errorList[] = ['name'=>$value['name'],'msg'=>'BM格式错误,请填写正确的BM或邮箱!'];   
                            continue;
                        } 
                        
                        if(filter_var($value, FILTER_VALIDATE_EMAIL))
                        {
                            $isEmail = (new \app\services\Basics())->isEmail($value);
                            if($isEmail['code'] != 1){
                                $errorList[] = ['name'=>$v['name'],'msg'=>$isEmail['msg']];   
                                continue;
                            }
                        }
                    }else {
                        $errorList[] = ['name'=>$v['name'],'msg'=>'BM|email不能为空!'];   
                            continue;
                    }
                    $v['bes'] = json_encode($bmList??[], true);

                    // if(isset($data['is_keep']) && in_array($data['type'],[1,3]) && $data['is_keep'] == 1) $data['is_keep'] = 1;
                    // else $data['is_keep'] = 0;
                    $v['admin_id'] = $this->auth->id;
                    $v['company_id']  = $company['id'];
                    $v['aoam_id']     = $v['aoam_id']??0;
                    $v['create_time']  = time();

                    if($company['prepayment_type'] == 2){
                        $companyUsedMoney = $this->companyUsedMoney($v['money']);
                        if($companyUsedMoney['code'] != 1){
                            $errorList[] = ['name'=>$v['name'],'msg'=>$companyUsedMoney['msg']];
                            continue;
                        }
                    }
                    $listData[] = $v;
                }
                // dd($listData);
                if(empty($listData)) $result = false;
                else $result = DB::table('ba_account')->insertAll($listData);

                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
                // dd($e->getMessage(),$e->getLine());
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'),['errorList'=>$errorList]);
            } else {
                $this->error(__('No rows were added'),['errorList'=>$errorList]);
            }
        }

    }

    /**
     * 添加账户到团队
     * @throws Throwable
     */
    public function addAccountTeam()
    {
        if ($this->request->isPost()) 
        {
            $data = $this->request->post();
            if(empty($data['account_ids'])||empty($data['team_id']))
            {
               $this->error(__('Parameter %s can not be empty', ['']));
            }
            $ids = $data['account_ids'];
            $this->model->startTrans();
            $res = false;
            try {
                foreach($ids as $v)
                {
                    $arr['id'] = $v;
                    $result =  Db::name('account')->whereNull('team_id')->where($arr)->find();
                    if(empty($result)){
                        continue;
                    }else{
                        $res = Db::name('account')->where(['id'=>$v])->update(['team_id'=>$data['team_id']]);
                    }
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }

            if ($res !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }

    /**
     * 从团队移除账户
     * @throws Throwable
     */
    public function delAccountTeam()
    {
        if ($this->request->isPost()) 
        {
            $data = $this->request->post();
            if(empty($data['account_ids'])||empty($data['team_id']))
            {
               $this->error(__('Parameter %s can not be empty', ['']));
            }
            $ids = $data['account_ids'];
            $this->model->startTrans();
            $res = false;
            try {
                foreach($ids as $v)
                {
                    $arr['id'] = $v;
                    $result =  Db::name('account')->whereNotNull('team_id')->where($arr)->find();
                    if(empty($result)){
                        continue;
                    }else{
                        $res = Db::name('account')->where(['id'=>$v])->update(['team_id'=> Db::raw('NULL')]);
                    }
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }

            if ($res !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }

    /**
     * 回收列表
     * @throws Throwable
     */
    function recycleList()
    {
 
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['company_id','=',$this->auth->company_id]);
        $res = DB::table('ba_account_recycle')
            ->field('name,account_id,currency,total_consumption,account_recycle_time')
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

    //开户
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            // if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            //     $data[$this->dataLimitField] = $this->auth->id;
            // }
                    // //判断用户层级
            if(!in_array($this->auth->type,[1,2,3]))
            {
                throw new \Exception("您没有操作权限！");
            }

            $result = false;
            DB::startTrans();
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

                $company = Db::table('ba_company')->where('id',$this->auth->company_id)->find();
                $accountNumber = $company['account_number'];
                $isAccount = $company['is_account'];
                $usableMoney = ($company['money'] - $company['used_money']);
                if($isAccount != 1) throw new \Exception("未调整可开户数量,请联系管理员添加！");

                if($company['prepayment_type'] == 2){
                    // if($usableMoney <= 0 || $usableMoney < $data['money']) throw new \Exception("余额不足,请联系管理员！");
                    // DB::table('ba_admin')->where('id',$this->auth->id)->inc('used_money',$data['money'])->update();
                    $companyUsedMoney = $this->companyUsedMoney($data['money']);
                    if($companyUsedMoney['code'] != 1) throw new \Exception($companyUsedMoney['msg']);
                }
                

                $time = date('Y-m-d',time());
                $openAccountNumber = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
                if($openAccountNumber >= $accountNumber) throw new \Exception("今.开户数量已经不足，不能再提交开户需求,请联系管理员！");

                // DB::table('ba_account')->where('id',$account['id'])->inc('money',$data['number'])->update(['update_time'=>time()]);
                
                if(isset($data['is_keep']) && in_array($data['type'],[1,3]) && $data['is_keep'] == 1) $data['is_keep'] = 1;
                else $data['is_keep'] = 0;

                $data['admin_id'] = $this->auth->id;

                if($company['id']) $data['company_id']  = $company['id'];
                else throw new \Exception("您没有归属公司，请联系管理员！");
                     
                // $data['account_id'] = $this->generateUniqueNumber();
                $result = $this->model->save($data);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
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

    //编辑
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

        // $dataLimitAdminIds = $this->getDataLimitAdminIds();
        // if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
        //     $this->error(__('You have no permission'));
        // }

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
                unset($data['is_keep']);

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

    //申请导入
    public function import(){

        $result = false;
        try {
            $file = $this->request->file('file');
            
            //$path = '/www/wwwroot/workOrder.test/public/storage/excel';
            $path = $_SERVER['DOCUMENT_ROOT'].'/storage/excel';
            $fineName = 'accountImport'.time().'.xlsx';
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
            if($this->auth->isSuperAdmin()){
                // $adminId = 0;
                throw new \Exception("管理员不可申请！");
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
            

            //dd($fileObject,$filteredArray);
            // $countAmout = array_sum(array_column($fileObject, '4'));

            $company = Db::table('ba_company')->where('id',$this->auth->company_id)->find();
            $accountNumber = $company['account_number'];
            $prepaymentType = $company['prepayment_type'];
            $usedMoney = $company['used_money'];
            // $isAccount = $admin['is_account'];
            // $usableMoney = ($admin['money'] - $admin['used_money']);
            // if($isAccount != 1) throw new \Exception("未调整可开户数量,请联系管理员添加！");
            // if($usableMoney <= 0 || $usableMoney < $countAmout) throw new \Exception("余额不足,请联系管理员！");

            $time = date('Y-m-d',time());
            $openAccountNumber = Db::table('ba_account')->where('company_id',$this->auth->company_id)->whereDay('create_time',$time)->count();
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
                    throw new \Exception("暂不支持养户！");
                    // $isKeep = 1;//---养户充值
                    // $isKeepCount++;
                } 
                if(!in_array($accountTypeId,[1,3]) && $v[5] == 1)
                {
                    throw new \Exception("养户只针对于【电商/游戏】类型  请修改账户名称为".$v[2]."的投放类型,或改成非养户类型后重新导入");
                } 

                if(!in_array($v[0],['游戏','短剧','工具','电商']))
                {
                    throw new \Exception($v[2].":投放标签只支持【'游戏','短剧','工具','电商'】");
                }

                if(in_array($v[0],['游戏']) && !in_array($v[1],[5.5,7,8,-3,-7,-6]))
                {
                     throw new \Exception($v[2].":【'游戏','短剧','工具'】,只能选择对应时区【5.5,7,8,-3,-7,-6】");
                }

                if(in_array($v[0],['短剧','工具']) && !in_array($v[1],[5.5,7,8,-3,-7]))
                {
                     throw new \Exception($v[2].":【'游戏','短剧','工具'】,只能选择对应时区【5.5,7,8,-3,-7】");
                }

                if(in_array($v[0],['电商']) && !in_array($v[1],[8,5.5,-3,-6,-7,-8,-9]))
                {
                     throw new \Exception($v[2].":【'电商'】,只能选择对应时区【8,5.5,-3,-6,7,-8,-9】");
                }

                $currency = empty($v[4])?"USD":$v[4];
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
                    'admin_id'=>$adminId,
                    'company_id'=>$this->auth->company_id,
                    'status'=>$authAdminId==1?1:0,
                    'currency'=>$currency,
                    'type'=>$accountTypeId,
                    'is_keep'=>$isKeep, 
                    'create_time'=>time()
                ];
                $data[] = $d;
            }
            if($isKeepCount>0)
            {
                $amount =  bcmul($isKeepCount, '10');//---养户充值*10

                if($prepaymentType != 1)
                {
                    if($usedMoney < 0) throw new \Exception("养户所需余额不足请充值！");
                    $where['id'] = $this->auth->company_id;
                    $result =  DB::table('ba_company')
                                ->whereRaw("money - used_money > $amount")
                                ->where($where)
                                ->inc('used_money', $amount)                   
                                ->update();
                    if(!$result) throw new \Exception("养户所需余额不足请充值！");
                }
                
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

    //模版下载
    public function importTemplate()
    {
        $this->success('',['row'=>['path'=>'/storage/default/申请账户模板.xlsx']]);
    }

    //下载数据
    public function export()
    {
        $where = [];
        set_time_limit(300);
        $this->dataLimit = false;
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
        //判断用户层级

        array_push($where,['account.company_id','=',$this->auth->company_id]);
 
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


    public function getAccountNumber()
    {
        $accountNumber = DB::table('ba_company')->field('account_number,is_account')->where('id',$this->auth->company_id)->find();
        $time = date('Y-m-d',time());
        $number = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
        $accountNumber['residue_account_number'] =  $accountNumber['account_number'] - $number;
        return $this->success('',[$accountNumber]);
    }

    function accountCountMoney()
    {
            $data = [
                'totalMoney'=>0,
                'usedMoney'=>0,
                'usableMoney'=>0,
                'accBalance'=>0
            ];
      
            if($this->auth->type != 4)
            {
               $result = DB::table('ba_company')->where('id',$this->auth->company_id)->find();
               $accBalanceArr =  DB::table('ba_accountrequest_proposal')->alias('p')
                ->leftJoin('ba_account a','a.account_id=p.account_id')
                ->field('sum(p.spend_cap) spend_cap,sum(p.amount_spent) amount_spent')
                ->where('a.company_id',$this->auth->company_id)
                ->where('a.status',4)
                ->group('a.company_id')
                ->find();

                if(!empty($accBalanceArr['spend_cap']) && !empty($accBalanceArr['amount_spent']))$data['accBalance'] = bcsub((String)$accBalanceArr['spend_cap'], (String)$accBalanceArr['amount_spent'], 2);
          
                if($result['prepayment_type'] == 1){
                    $consumptionService = new \app\admin\services\fb\Consumption();
                    $totalDollar = $consumptionService->getTotalDollar($result['id']);                
                    // $companyMoney = DB::table('ba_admin_money_log')->whereIn('type',[1,4])->where('status',1)->where('company_id',$result['id'])->sum('money');
                    $companyMoney = DB::table('ba_admin_money_log')->whereIn('type',[1,4])->where('status',1)->where('company_id',$result['id'])->sum('money');

                    $data['usedMoney'] = bcadd((string)$totalDollar,"0",2);
                    $data['usableMoney'] = bcsub((string)$companyMoney,(string)$data['usedMoney'],2);
                }else{
                    $data['totalMoney'] = $result['money'];
                    $data['usedMoney'] = $result['used_money'];
                    $data['usableMoney'] = bcsub((string)$result['money'],(string)$result['used_money'],2);
                }

                // $accountList = DB::table('ba_account')->where('company_id',$this->auth->company_id)->where('status',4)->count();
                // dd($accountList);
            }else{
                // $result = DB::table('ba_team')->where('id',$this->auth->team_id)->field('team_money money,team_used_money used_money')->find();
                // $data['totalMoney'] = $result['money'];
                // $data['usedMoney'] = $result['used_money'];
                // $data['usableMoney'] = bcsub((string)$result['money'],(string)$result['used_money'],2);
            }

            $this->success('',$data);
    }
     //进度条
    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_progress'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

    public function needTotal()
    {
        $data = [
            'account_sum'=>0,
            'bm_sum'=>0,
            'recharge_sum'=>0,
            'version'=>env('VERSION')
        ];

        $where = [];
        
        array_push($where,['account.company_id','=',$this->auth->company_id]);
        if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);
        // dd($where);
        $data['account_sum'] = DB::table('ba_account')
        ->alias('account')
        ->where($where)
        // ->where('status','<>',4)
        ->where('status','in',[0,1,3])
        ->where(function ($quers){
            $quers->where(function ($quers2){
                $quers2->whereOr([
                    ['account.status','=',4],
                    ['account.is_keep','=',1],
                    ['account.keep_succeed','=',0],
                    ]
                    );

            })->where('status','<>',4);
        })
        ->count();
        // dd($data['account_sum']);

        // if($this->auth->type == 4) array_push($where,['bm.team_id','=',$this->auth->team_id]);

        $bmTable = DB::table('ba_bm')
        ->alias('bm')
        ->leftJoin('ba_account account','bm.account_id=account.account_id')
        ->where($where)
        ->where(function ($query){
            $query->where([
                // ['bm.status','=',0],
                ['bm.status','IN',[0,1]],
                ['bm.dispose_type','=',0],
                ['bm.audit_status','<>',3],
            ]);
        });
        
        if($this->auth->type == 4) $bmTable->where('bm.team_id',$this->auth->team_id);

        $data['bm_sum'] = $bmTable->count();

        $rechargeTable = DB::table('ba_recharge')
        ->alias('recharge')
        ->leftJoin('ba_account account','recharge.account_id=account.account_id')
        ->where($where)
        ->where(function ($query){
            $query->where([
                ['recharge.status','=',0],
                ['recharge.audit_status','<>',3],
                // ['bm.audit_status','<>',2],
            ]);
        });
        // ->where('recharge.status',0);

        if($this->auth->type == 4) $rechargeTable->where('recharge.team_id',$this->auth->team_id);

        // dd( $rechargeTable->fetchSql()->find());

        $data['recharge_sum'] = $rechargeTable->count();

        $this->success('',$data);
    }

    // public function recycle()
    // {
    //     $data = $this->request->post();
    //     $accountIds = $data['account_ids'];
        
    //     $where = [['account.account_id','IN',$accountIds],['account.status','=',4]];
    //     $accountList = $this->getAccountPermission($where);
    //     if(empty($accountList)) $this->error('未找到账户');

    //     DB::startTrans();
    //     try{

    //         foreach($accountList as $v)
    //         {
    //             $accountId = $v['account_id'];
    //             $bmDataList = DB::table('ba_bm')->where('account_id',$accountId)->select()->toArray();
    //             $rechargeDataList = DB::table('ba_recharge')->where('account_id',$accountId)->select()->toArray();

    //             DB::table('ba_bm_recycle')->insertAll($bmDataList);
    //             DB::table('ba_recharge_recycle')->insertAll($rechargeDataList);

    //             DB::table('ba_bm')->where('account_id',$accountId)->delete();
    //             DB::table('ba_recharge')->where('account_id',$accountId)->delete();
    //         }

    //         DB::commit();
    //     }catch(\Exception $e){
    //         DB::rollback();
    //     }        
    // }
     //获取客户端公司申请卡列表
     public function getComapnyCard()
     {
            $companyId = $this->auth->company_id;
            if(empty($companyId)) $this->error('公司身份错误');
            $result = DB::table('ba_company_join_account_card')
                        ->alias('b')
                        ->leftJoin('ba_account_opening_application_manage o','o.id=b.card_id')
                        ->where('b.company_id',$companyId)
                        ->where('b.is_show_card',1)
                        ->select()
                        ->toArray();
            $this->success('',$result);
      }
    
}