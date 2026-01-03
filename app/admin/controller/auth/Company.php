<?php

namespace app\admin\controller\auth;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\user\Company as CompanyModel;
use app\admin\model\Admin as AdminModel;
use app\admin\model\CompanyJoinAccountCard;
use think\facade\Cache;
use app\common\controller\Backend;

class Company extends Backend
{
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index','add','edit'];

    protected string|array $quickSearchField = 'company_name';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new CompanyModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        $type  = $this->request->get('type');
        $sortField = $this->request->get('sort_field')??''; 
        $sortType  = $this->request->get('sort_type')??'';  //SORT_DESC  SORT_ASC
        // ID  rate  kyMoney  totalMoney totalconsume accountCount closepercent  idleCount
        //     费率  可用余额  总金额     总消耗         账户数量     封户率         闲置率
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder(); //dd($sortField,$sortType,$order);
        if(!in_array(array_keys($order)[0],['rate','id','account_number'])){
            $sortField = array_keys($order)[0];
            $sortType  = array_values($order)[0] == 'asc'?SORT_ASC:SORT_DESC;
            $order = ['id'=>'asc'];
        }

        foreach($where as $k => $v){
            if($v[0] == 'company.nickname'){
                $adminIds = Db::table('ba_admin')->where('nickname','like','%'.$v[2].'%')->column('company_id');          
                array_push($where,['company.id','IN',$adminIds]);
                unset($where[$k]);
            }
        }

        $res = $this->model
                    ->field('*,ROUND((money - used_money),2) usableMoney')
                    ->withJoin($this->withJoinTable, $this->withJoinType)
                    ->alias($alias)
                    ->where($where)
                    ->order($order)
                    ->paginate($limit);
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $dataList = $res->toArray()['data'];

        if($type == 1) $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
        
       $comapnyids = array_column($dataList,'id');

        $rateArr  = DB::table('ba_rate')->whereIn('company_id',$comapnyids)->order('create_time asc')->column('rate','company_id');//费率
        $adminArr = DB::table('ba_admin')->whereIn('company_id',$comapnyids)->where('type',2)->column('username,nickname','company_id');//主用户
        $moneyArr = DB::table('ba_admin_money_log')->where('status',1)->whereIn('company_id',$comapnyids)->whereIn('type',[1,4])->group('company_id')->column('sum(money)','company_id');//付款总金额
        $key = 'consumption_1';
        $redisValue = Cache::store('redis')->get($key);
        $consumptionArr = [];

        // if(empty($redisValue))
        // {
            $consumptionArr  = DB::table('ba_account_consumption')->whereIn('company_id',$comapnyids)->whereRaw("company_id IS NOT NULL")->group('company_id')->column('sum(dollar)','company_id');//总消耗
            // $consumptionArr = DB::query("SELECT company_id, SUM(dollar) AS dollar FROM ba_account_consumption WHERE company_id IS NOT NULL GROUP BY company_id");
            // $consumptionArr = array_column($consumptionArr,'dollar','company_id');
        //     Cache::store('redis')->set($key, $consumptionArr, 240);
        // }else{
        //     $consumptionArr = Cache::store('redis')->get($key);
        // }
        $AccountCountArr = DB::table('ba_account')->whereIn('company_id',$comapnyids)->where(['status'=>4])->group('company_id')->column('count(*)','company_id');//账户数量
        $AccountcloseArr = DB::table('ba_accountrequest_proposal')->alias('p')->leftJoin('ba_account account','account.account_id=p.account_id')
                               ->where(['p.account_status'=>2])->where([['p.status','<>',99]])->group('account.company_id')->column('count(*)','company_id');//封户数量
        $idleCountArr = DB::table('ba_account')->whereIn('company_id',$comapnyids)->where(['status'=>4])->where('idle_time','>',604800)->group('company_id')->column('count(*)','company_id');//闲置

        
        if($dataList)foreach($dataList as &$v)
        {
                $v['alias'] = $v['company_name'];
                $id = $v['id'] ?? null;
                $rate = 0;
                if ($id !== null && isset($rateArr[$id]) && is_numeric($rateArr[$id])) {
                    $rate = $rateArr[$id];
                }
                $v['rate'] = $rate * 100;
                $v['username'] =  $adminArr[$v['id']]['username']??'';    
                $v['nickname'] =  $adminArr[$v['id']]['nickname']??'';     
                $v['totalconsume'] = ROUND($consumptionArr[$v['id']]??0, 2);//总消耗
                if($v['prepayment_type'] == 1){
                    $v['totalMoney']   = ROUND($moneyArr[$v['id']]??0, 2);      //总金额
                    $v['kyMoney'] = bcsub($v['totalMoney'], $v['totalconsume']);//可用余额
                }else{
                    $v['totalMoney']   = $v['money'];                           //总金额
                    $v['kyMoney']      = bcsub((string)$v['money'],(string)$v['used_money'],2);
                }
                $v['accountCount'] = $AccountCountArr[$v['id']]??0;         //账户数量
                $v['closeCount']   = $AccountcloseArr[$v['id']]??0;         //封户数量
                $v['idleCount']    = $idleCountArr[$v['id']]??0;            //闲置数量
                if($v['closeCount'] && $v['accountCount']) 
                     $v['closepercent'] = (int)($v['closeCount']/$v['accountCount']*100);//封户率
                else 
                     $v['closepercent'] = 0;
        
                if($v['accountCount'] && $v['idleCount']) 
                     $v['idleCount']   =  (int)($v['idleCount']/$v['accountCount']*100); //闲置率
                else 
                     $v['idleCount'] = 0;
        }
        
        if($sortField && $sortType)array_multisort(array_column($dataList, $sortField), $sortType, $dataList);
        //ID
        //comapny_name公司  
        //username用户名  
        //nickname昵称
        //status 状态：0=禁用,1=启用
        //prepayment_type 预付类型：1=预付实销，2=预付
        //is_account 是否可以开户：1=可，2=不可
        //totalMoney付款总金额   
        //account_number可开户   
        //totalconsume 总消耗
        //rate 服务费率
        //kyMoney 可用余额
        //accountCount 账户数量
        //closeCount 封户数量
        //idleCount 闲置数量
        //create_time注册时间

        // if($dataList)foreach($dataList as &$v)
        // {
        //     $v['kyMoney'] ='$'.$v['kyMoney'];
        //     $v['totalMoney'] ='$'.$v['totalMoney'];
        //     $v['closepercent'] =$v['closepercent'].'%';
        //     $v['idleCount'] =$v['idleCount'].'%';
        // }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 添加公司
     * @throws Throwable
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            // $user['company_name'] = $data['company_name']??'';
            $user['password'] = $data['password']??'';
            $user['username'] = $data['username']??'';
            $user['nickname'] = $data['nickname']??'';
            $user['email'] = $data['email']??'';
            $list = $data['list']??[];
            $rate = $data['rate']??0;
            $billRate = $data['bill_rate']??0;
            unset($data['password'],$data['username'],$data['nickname'],$data['rate'],$data['bill_rate']);
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('add')->check($data);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }

            $salt   = Random::build('alnum', 16);
            if(empty($user['password'])) $this->error('请输入用户密码！');
            $user['password'] = encrypt_password($user['password'], $salt);
            $this->model->startTrans();
            try {
                $result             = $this->model->save($data);
                $company_id = $user['company_id'] = $this->model->id;
                $validate = new \app\admin\validate\user\Admin;
                $validate->scene('add')->check($user);
                $user['salt']       = $salt;
                $user['type']       = 2; //公司主账号类型

                $uid = DB::table('ba_admin')->insertGetId($user);

                $groupAccess = [
                    'uid'      => $uid,
                    'group_id' => 7,
                ];
                Db::name('admin_group_access')->insert($groupAccess);
                $CardModel = new CompanyJoinAccountCard();

                //处理卡片关联
                $dataList = [];
                if(!empty($list))foreach($list as $k => $v)
                {
                    $dataList[] = [
                        'company_id'=>$company_id,
                        'card_id'=>$v['card_id'],
                        'is_show_card'=>$v['is_show_card'],
                        'is_open_card'=>$v['is_open_card']
                    ];
                }
                $CardModel->saveAll($dataList);

                //费率处理
                if(isset($rate)&&!empty($rate))
                {
                    $inserData  = ['create_time'=>date('Y-m-d',time()),'company_id'=>$this->model->id,'rate'=>$rate];
                    DB::table('ba_rate')->insert($inserData);
                }

                //入账手续费处理
                if(isset($billRate)&&!empty($billRate))
                {
                    $inserData  = ['create_time'=>date('Y-m-d',time()),'company_id'=>$this->model->id,'bill_rate'=>$billRate];
                    DB::table('ba_bill_rate')->insert($inserData);
                }

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

    /**
     * 编辑
     * @throws Throwable
     */
    public function edit($id = null): void
    {
        if($this->request->isGet())$info = $this->request->get();
        if($this->request->isPost())$info = $this->request->post();
        $row = $this->model->find($info['id']);
        if (!$row) {
            $this->error(__('Record not found'));
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

            /**
             * 由于有密码字段-对方法进行重写
             * 数据验证
             */
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('edit')->check($data);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }
    
            $user['username'] = $data['username']??'';
            $user['nickname'] = $data['nickname']??'';
            if($user){
                DB::table('ba_admin')->where('company_id',$info['id'])->where('type',2)->update($user);
                unset($data['nickname'],$data['username']);
            }

            $openAccountNumber = $this->openAccountNumber($row['id']);


            if(isset($data['account_number'])) if($data['account_number'] < $openAccountNumber) $this->error('调整后的数量不能小于已经使用的数量！');

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();

            try {
                $rate = $data['rate']??0;
                $billRate = $data['bill_rate']??0;
                $list = $data['list']??[];
                unset($data['rate'],$data['bill_rate'],$data['list']);
                $result = $row->save($data);
                 
                if($result){
                    if($data['status'] == 1){
                        DB::table('ba_admin')->where('company_id',$id)->update(['status'=>1]);
                    }else{
                        DB::table('ba_admin')->where('company_id',$id)->update(['status'=>0]);
                    }
                }
                $CardModel = new CompanyJoinAccountCard();
                  //处理卡片关联
                  $dataList = [];
                  if(!empty($list))foreach($list as $k => $v)
                  {
                       $arr = [
                          'company_id'=>$info['id'],
                          'card_id'=>$v['card_id'],
                          'is_show_card'=>$v['is_show_card'],
                          'is_open_card'=>$v['is_open_card'],
                      ];
                      if(!empty($v['id'])) $arr['id'] = $v['id'];
                      $dataList[] =$arr;
                  }
                  $CardModel->saveAll($dataList);
               

                //费率处理
                $rateResult = DB::table('ba_rate')->where('company_id',$id)->order('create_time desc')->find();
                $inserData  = ['create_time'=>date('Y-m-d',time()),'company_id'=>$id,'rate'=>$rate];
                $rateResult['rate']        = $rateResult['rate']??0;
                $rateResult['create_time'] = $rateResult['create_time']??0;
                if($rateResult['rate'] != $rate)
                {
                     if(!empty($rateResult))
                     {
                         if($rateResult['create_time'] == date('Y-m-d',time()))
                             DB::table('ba_rate')->where(['company_id'=>$id,'create_time'=>date('Y-m-d',time())])->update(['rate'=>$rate]);
                         else
                             DB::table('ba_rate')->insert($inserData);
                     }else{
                             DB::table('ba_rate')->insert($inserData);
                     }
                }

                //入账手续费处理
                $billrateRes = DB::table('ba_bill_rate')->where('company_id',$id)->order('create_time desc')->find();
                $inserData  = ['create_time'=>date('Y-m-d',time()),'company_id'=>$id,'bill_rate'=>$billRate];
                $billrateRes['bill_rate']   = $billrateRes['bill_rate']??0;
                $billrateRes['create_time'] = $billrateRes['create_time']??0;
                if($billrateRes['bill_rate'] != $billRate)
                {
                     if(!empty($billrateRes))
                     {
                         if($billrateRes['create_time'] == date('Y-m-d',time()))
                             DB::table('ba_bill_rate')->where(['company_id'=>$id,'create_time'=>date('Y-m-d',time())])->update(['bill_rate'=>$billRate]);
                         else
                             DB::table('ba_bill_rate')->insert($inserData);
                     }else{
                             DB::table('ba_bill_rate')->insert($inserData);
                     }
                }

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

        unset($row['salt']);
        $rateArr  = DB::table('ba_rate')->where('company_id',$info['id'])->order('create_time asc')->column('rate','company_id');//费率
        $billRateArr  = DB::table('ba_bill_rate')->where('company_id',$info['id'])->order('create_time asc')->column('bill_rate','company_id');//费率
        $adminUser  = DB::table('ba_admin')->where('company_id',$info['id'])->where('type',2)->find();//admin
        $listArr  = DB::table('ba_company_join_account_card')->where('company_id',$info['id'])->order('create_time asc')->select()->toArray();//公司关联的卡片
        $listArrcount  = DB::table('ba_company_join_account_card')->where('company_id',$info['id'])->count();//公司关联的卡片

        $row['list'] = $listArr??[];
        $row['list_count'] = $listArrcount??0;
        $row['rate'] = $rateArr[$info['id']]??0;
        $row['bill_rate'] = $billRateArr[$info['id']]??0;
        $row['username']  = $adminUser['username']??'';
        $row['nickname'] = $adminUser['nickname']??'';
        $row['password'] = '';
        $this->success('', [
            'row' => $row
        ]);
    }

    public function openAccountNumber($id = null)
    {
        $time = date('Y-m-d',time());
        $openAccountNumber = Db::table('ba_account')->where('company_id',$id)->whereDay('create_time',$time)->count();
        return $openAccountNumber;
    }

    /**
     * 删除
     * @param null $ids
     * @throws Throwable
     */
    public function del($ids = null): void
    {
        $this->error('功能暂停使用，请联系管理员！');
        if (!$this->request->isDelete() || !$ids) {
            $this->error(__('Parameter error'));
        }

        $where             = [];
        $pk      = $this->model->getPk();
        $where[] = [$pk, 'in', $ids];

        $count = 0;
        $data  = $this->model->where($where)->select();
        $this->model->startTrans();
        try {
            foreach ($data as $v) 
            {
                $count = Db::name('company')->where('id', $v->id)->delete();
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success(__('Deleted successfully'));
        } else {
            $this->error(__('No rows were deleted'));
        }
    }


    /**
     * 删除
     * @param null $ids
     * @throws Throwable
     */
    public function delUser($ids = null): void
    {
        $this->error('功能暂停使用，请联系管理员！');
    }


}


