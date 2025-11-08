<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;

/**
 * 账户列管理
 */
class AccountrequestProposal extends Backend
{
    /**
     * AccountrequestProposal模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\AccountrequestProposal
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'accountrequest_id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = ['index','accountRefresh','bmList','editStatusAll','Export','getAccountrequestProposal','getExportProgress',"accountCardBind","accountCardBindDel","accountCardList","accountCardHandoff","allEditLabelRelevance","manageExport","getManageExportProgress","getCountBalance"];

    protected bool|string|int $dataLimit = 'parent';

    protected $currencyRate = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountrequestProposal();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        $type = $this->request->param('type');
        $status = $this->request->param('status');
        if($type == 2) $result = $this->userIndex3();
        else if($type == 3) $result = $this->userIndex3();
        else $result = $this->adminIndex($status);
        $this->success('', $result);
    }

    public function adminIndex($status)
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        
        array_push($this->withJoinTable,'affiliationAdmin');
        array_push($this->withJoinTable,'cards');
        array_push($this->withJoinTable,'account');

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder(); 
        if($status === "0") array_push($where,['accountrequest_proposal.status','in',config('basics.FH_status')]);
        if($limit == 999) $limit = 2500;
        
        $res = $this->model
            ->distinct(true)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username','nickname'],'cards'=>['card_no'],'account'=>['id','is_']]);

        // return [
        //     'list'   => $res->items(),
        //     'total'  => $res->total(),
        //     'remark' => get_route_remark(),
        // ];

        $result = $res->toArray();
        $dataList = [];
        $account_easy = config('basics.account_easy');

        if(!empty($result['data'])) {
            $dataList = $result['data'];
            foreach($dataList as &$v){
                $v['label_name'] = '';
                if(!empty($v['label_ids'])){ //标签处理
                  $arr =  $v['label_ids']??[];
                  $label_arr = [];
                  foreach($arr as $vv){ 
                     $label_arr[] =  $account_easy[$vv];
                  }
                  $v['label_name'] = implode(',',$label_arr);
                }
           }
       }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function userIndex3()
    {
        $this->withJoinTable = [];
        if ($this->request->param('select')) {
            $this->select();
        }

        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId != 2 && !$this->auth->isSuperAdmin()) {
            array_push($where,['account.admin_id','=',$this->auth->id]);
        }

        $openTime = '';
        $endTime = '';
        $is_  = true;
        $whereOr = [];
        $noWhere = [];
        foreach($where as $k => $v){
            if($v[0] == 'accountrequest_proposal.account_status') $is_ = false;
            
            if($v[0] == 'account.open_time'){
                $openTime = date('Y-m-d H:i:s',$v[2][0]);
                $endTime = date('Y-m-d H:i:s',$v[2][1]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 1){
                array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);
                array_push($where,['accountrequest_proposal.status','NOT IN',[96,97]]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 2){
                array_push($where,['accountrequest_proposal.status','NOT IN',[96,97]]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 0){
                array_push($whereOr,['accountrequest_proposal.account_status','=',0]);
                array_push($whereOr,['accountrequest_proposal.status','=',96]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 97){
                array_push($where,['accountrequest_proposal.status','=',97]);
                unset($where[$k]);
                continue;
            }
        }

        // if($is_) array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);


        array_push($where,['account.account_id','<>','']);

        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.*,accountrequest_proposal.type,accountrequest_proposal.serial_name,accountrequest_proposal.account_status,accountrequest_proposal.currency,admin.nickname,accountrequest_proposal.status accountrequest_proposal_status,accountrequest_proposal.spend_cap,accountrequest_proposal.amount_spent')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->order($order)
        ->where($where)
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where(function($query) use($whereOr){
            if(!empty($whereOr)){
                $query->whereOr($whereOr);
            }
        })  
        ->paginate($limit);
        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            foreach($dataList as &$v){

                if($v['idle_time'] > 86400){
                    $days = floor($v['idle_time'] / 86400);
                    $hours = floor(($v['idle_time'] % 86400) / 3600);
                }else{
                    $days = 0;
                    $hours = 0;
                }                            

                $spendCap = $v['spend_cap'] == 0.01?0:$v['spend_cap'];
                $amountSpent = $v['amount_spent'];
                $balance = bcsub((string)$spendCap,(string)$amountSpent,'2');
                
                $openAccountTime = date('Y-m-d',$v['open_time']);
                
                $consumptionWhere = [
                    ['account_id','=',$v['account_id']],
                    ['date_start','>=',$openAccountTime]
                ];

                if(!empty($openTime) && !empty($endTime)){
                    array_push($consumptionWhere,['date_start','>=',$openTime]);
                    array_push($consumptionWhere,['date_start','<=',$endTime]);
                }
                $accountSpent2 = DB::table('ba_account_consumption')->where($consumptionWhere)->sum('spend');
                
                $v['fb_balance'] = $balance;
                $v['fb_spand'] = bcadd( (string)$accountSpent2,'0',2);
                $v['consumption_date'] = [
                    'days'=>$days,
                    'hours'=>$hours
                ];
            }
        }

        return [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ];
    }

    public function userIndex3_test()
    {
        $this->withJoinTable = [];
        if ($this->request->param('select')) {
            $this->select();
        }

        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId != 2 && !$this->auth->isSuperAdmin()) {
            array_push($where,['account.admin_id','=',$this->auth->id]);
        }

        $openTime = '';
        $endTime = '';
        $is_  = true;
        $whereOr = [];
        $noWhere = [];
        foreach($where as $k => $v){
            if($v[0] == 'accountrequest_proposal.account_status') $is_ = false;
            
            if($v[0] == 'account.open_time'){
                $openTime = date('Y-m-d H:i:s',$v[2][0]);
                $endTime = date('Y-m-d H:i:s',$v[2][1]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 1){
                array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);
                array_push($where,['accountrequest_proposal.status','NOT IN',[96,97]]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 2){
                array_push($where,['accountrequest_proposal.status','NOT IN',[96,97]]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 0){
                array_push($whereOr,['accountrequest_proposal.account_status','=',0]);
                array_push($whereOr,['accountrequest_proposal.status','=',96]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 97){
                array_push($where,['accountrequest_proposal.status','=',97]);
                unset($where[$k]);
                continue;
            }
        }

        // if($is_) array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);


        array_push($where,['account.account_id','<>','']);

        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.*,accountrequest_proposal.type,accountrequest_proposal.serial_name,accountrequest_proposal.account_status,accountrequest_proposal.currency,admin.nickname,accountrequest_proposal.status accountrequest_proposal_status')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->order($order)
        ->where($where)
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where(function($query) use($whereOr){
            if(!empty($whereOr)){
                $query->whereOr($whereOr);
            }
        })  
        ->paginate($limit);
        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $currencyRate = json_decode(env('APP.currency'),true);

            $accountListIds = array_column($dataList,'account_id');

            $recharge = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->where('type',1)->where('status',1)->group('account_id')->select()->toArray();
            $recharge1 = DB::table('ba_account')->field('open_money,account_id')->whereIn('account_id',$accountListIds)->select()->toArray();
            $recharge2 = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->where('type',2)->where('status',1)->group('account_id')->select()->toArray();
            $recharge4 = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->whereIn('type',[3,4])->where('status',1)->group('account_id')->select()->toArray();

            $recharge = array_column($recharge,'number','account_id');
            $recharge1 = array_column($recharge1,'open_money','account_id');
            $recharge2 = array_column($recharge2,'number','account_id');
            $recharge4 = array_column($recharge4,'number','account_id');

            foreach($dataList as &$v){

                $totalRecharge = $recharge[$v['account_id']]??0;
                $openMoney = $v['open_money'];
                $totalDeductions = $recharge2[$v['account_id']]??0;
                $totalReset = $recharge4[$v['account_id']]??0;

                // 余额：
                //    1.账户总充值 - 花费 （充值比消耗小余额默认0）

                // 花费：
                //     1.fb花费（总消费账单）

                $balance = '';
//                $accountAmount = $v['money']??0;
                $accountAmount = "0";
                $openAccountTime = date('Y-m-d',$v['open_time']);
                
                $consumptionWhere = [
                    ['account_id','=',$v['account_id']],
                    ['date_start','>=',$openAccountTime]
                ];
                $accountSpent = DB::table('ba_account_consumption')->where($consumptionWhere)->sum('spend');
                if(!empty($openTime) && !empty($endTime)){
                    array_push($consumptionWhere,['date_start','>=',$openTime]);
                    array_push($consumptionWhere,['date_start','<=',$endTime]);
                }
                $accountSpent2 = DB::table('ba_account_consumption')->where($consumptionWhere)->sum('spend');


                $accountAmount = bcadd((string)$totalRecharge , (string)$openMoney,2);
                $accountAmount = bcsub((string)$accountAmount , (string)$totalDeductions,2) ;
                $accountAmount = bcsub((string)$accountAmount , (string)$totalReset,2) ;

                if(!empty($currencyRate[$v['currency']])){
                    $accountAmount = bcmul((string)$accountAmount, $currencyRate[$v['currency']],2);
                }

                if($accountAmount < $accountSpent) $balance = 0;
                else $balance = bcsub((string)$accountAmount, (string)$accountSpent,2);
                
                $v['fb_balance'] = $balance;
                $v['fb_spand'] = bcadd( (string)$accountSpent2,'0',2);
            }
        }

        return [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ];
    }

    public function userIndex()
    {
        $this->withJoinTable = [];
        if ($this->request->param('select')) {
            $this->select();
        }

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['account.account_id','<>','']);

        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.*,accountrequest_proposal.serial_name,accountrequest_proposal.account_status,accountrequest_proposal.currency,admin.nickname')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->order($order)
        ->where($where)
        ->paginate($limit);


        // $result = $res->toArray();
        // $dataList = [];
        // if(!empty($result['data'])) {
        //     $dataList = $result['data'];
        //     $accountListIds = array_column($dataList,'account_id');

        //     // 余额：
        //     //     1.fb余额(总充值 - 总扣款 - 总消费账单 - 总清零)
                
        //     // 花费：
        //     //     1.fb花费（总消费账单）

        //     $recharge = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->where('type',1)->where('status',1)->group('account_id')->select()->toArray();
        //     DB::table('ba_account')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->select()->toArray();
        //     dd($recharge);



        //     foreach($dataList as &$v){
                
        //     }
        // }



        // $res = $this->model
        //     ->withJoin($this->withJoinTable, $this->withJoinType)
        //     ->alias($alias)
        //     ->where($where)
        //     ->order($order)
        //     ->paginate($limit);
        // $res->visible(['admin' => ['username','nickname'],'cards'=>['card_no']]);

        return [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ];
    }

    public function audit(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $affiliationBm = $this->request->param(false)['affiliationBm'];
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $bm = $data['bm'];
                // $affiliationBm = $data['affiliationBm'];
                $timeZone = $data['timeZone'];
                $adminId = $data['adminId'];
                $isCards = $data['is_cards']??0;
                $labelIds = $data['label_ids']??[];
                $type = $data['type']??1;
                $currency = $data['currency']??'';
                $nameList = $data['name_list']??[];
                $dataList = [];

                if(empty($ids)) throw new \Exception("账户为空！");

                $accountCount = $this->model->where('admin_id',$adminId)->count();

                $accountList = $this->model->whereIn('account_id',$ids)->column('account_id');

                $bmTokenResult = DB::table('ba_fb_bm_token')->where('id',$bm)->find();
                if(empty($bmTokenResult)) throw new \Exception("管理BM必选！");
                
                $bm = $bmTokenResult['name'];
                $bmTokenId = $bmTokenResult['id'];

                foreach($ids as $k =>$v){
                    if(in_array($v,$accountList)) continue;
                    $v = filter_var($v, FILTER_SANITIZE_NUMBER_INT);
                    $accountCount++;
                    $dataList[] = [
                        'bm'=>$bm,
                        'affiliation_bm'=>$affiliationBm,
                        'admin_id'=>$adminId,
                        'status'=>0,
                        'time_zone'=>$timeZone,
                        'currency'=>$currency,
                        'account_id'=>$v,
                        'is_cards'=>$isCards,
                        'label_ids'=>implode(',', $labelIds),
                        'name'=>$nameList[$k]??'',
                        'type'=>$type,
                        'serial_number'=>$accountCount,
                        'bm_token_id'=>$bmTokenResult['id'],
                        'account_status'=>1,
                        'create_time'=>time()
                    ];
                    $this->assignedUsersJob($v,$bmTokenId);
                }
                Db::table('ba_accountrequest_proposal')->insertAll($dataList);

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

    public function Export()
    {
        set_time_limit(600);
        $where = [];
        $ids = $this->request->get('ids');

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }
   
        if($ids) array_push($where,['accountrequest_proposal.id','in',$ids]);
        else list($where, $alias, $limit, $order) = $this->queryBuilder(); 
        // $data = DB::table('ba_accountrequest_proposal')
        // ->alias('accountrequest_proposal')
        // ->field('accountrequest_proposal.*,account.name account_name,account.bm account_bm,admin.nickname,cards_info.card_no')
        // ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        // ->leftJoin('ba_admin admin','admin.id=accountrequest_proposal.admin_id')
        // ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        // ->where($where)->select()->toArray();

        $batchSize = 2000;
        $processedCount = 0;

        $query = $this->model
        ->alias('accountrequest_proposal')
        ->field('accountrequest_proposal.*,account.name account_name,account.bm account_bm,admin.nickname,cards_info.card_no,account.status open_account_status')
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->leftJoin('ba_admin admin','admin.id=accountrequest_proposal.admin_id')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        ->order('accountrequest_proposal.id','asc')
        ->where($where);
        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $statusValue = config('basics.ACCOUNT_STATUS');
        $openAccountStatusValue = config('basics.OPEN_ACCOUNT_STATUS');

        // $cardsList = DB::table('ba_account_card')->select()->toArray();

        $maxCountList = DB::table('ba_account_card')
        ->field('COUNT(id) as cnt')
        ->group('account_id')
        ->order('cnt')->select()->toArray();
        $maxCount = 0;
        if(!empty(array_column($maxCountList,'cnt'))) $maxCount = max(array_column($maxCountList,'cnt'));

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'account_id',
            'bm',
            'time_zone',
            'account_name',
            'affiliation_bm',
            'affiliation_admin_name',
            'account_bm',
            'status',
            'open_account_status',
            'nickname',
            'FB_account_status',
            'currency',
            'country_code',
            'country_name',
            'card_no',
        ];

        for($i = 0; $i < $maxCount; $i++){
            $header[] = 'card_no'.($i+1);
        }

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        
        
        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();

            $cardsList = DB::table('ba_account_card')->whereIn('account_card.account_id',array_column($data,'account_id'))
            ->alias('account_card')
            ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=account_card.cards_id')
            ->field('account_card.account_id,cards_info.card_no')->select()->toArray();

            $groupedCards = [];
            foreach ($cardsList as $v) {
                $groupedCards[$v['account_id']][] = $v['card_no'];
            }


            $dataList=[];
            $excelData = [];
            foreach($data as $v){
                $excelData  = [
                    'account_id'=>$v['account_id'],
                    'bm'=>$v['bm'],
                    'time_zone'=>$v['time_zone'],
                    'account_name'=>$v['serial_name'],
                    'affiliation_bm'=>$v['affiliation_bm'],
                    'affiliation_admin_name'=> $adminList[$v['affiliation_admin_id']]??'',
                    'account_bm'=> $v['account_bm'],
                    'status'=> $statusValue[$v['status']]??'未知的状态',
                    'open_account_status'=> $openAccountStatusValue[$v['open_account_status']]??'未知的状态',
                    'nickname'=>$v['nickname'],
                    'FB_account_status'=>$accountStatus[$v['account_status']]??'未找到状态',
                    'currency'=>$v['currency'],
                    'country_code'=>$v['country_code'],
                    'country_name'=>$v['country_name'],
                    'card_no'=>$v['card_no'],
                ];
                if(!empty($groupedCards[$v['account_id']])){
                    $cardNoList = $groupedCards[$v['account_id']];
                    foreach($cardNoList as $k => $cardNo){
                        $excelData['card_no'.($k+1)] = $cardNo;
                    }
                }
                $dataList[] = $excelData ;  
                $processedCount++;
            }
            $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
            ->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set('export_progress', $progress, 300);
            // 刷新缓冲区
            //ob_flush();
            //flush();
        }   
        $excel->output();
        Cache::store('redis')->delete('export_progress');

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);        
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_progress', 0); // 获取进度
        return $this->success('',['progress' => $progress]);
    }


    public function getAccountrequestProposal(): void
    {
        $accountId  = $this->request->param('account_id');
        $row = $this->model->where('affiliation_admin_id',$this->auth->id)->where('account_id',$accountId)->find();
        
        $this->success('', [
            'row' => $row
        ]);
    }


    public function assignedUsersJob($accountId,$bmTokenId)
    {
        $jobHandlerClassName = 'app\job\AccountAssignedUsers';
        $jobQueueName = 'AccountAssignedUsers';
        Queue::later(1, $jobHandlerClassName, ['account_id'=>$accountId,'bm_token_id'=>$bmTokenId], $jobQueueName);
        return true;
    }

    public function accountRefresh()
    {
        $accountId = $this->request->param('account_id');
        $type = $this->request->param('type');
        $result = false;
        $resultData = [];
        try {
            //实时刷新状态与消耗
            $where = [
                ['account_id','=',$accountId]
            ];
            if(!$this->auth->isSuperAdmin()) array_push($where,['admin_id','=',$this->auth->id]);
            $accountResult = DB::table('ba_account')->where($where)->find();

            if(empty($accountResult)) throw new \Exception("未找到账户!");

            $FHStatus2 = config('basics.NOT_consumption_status');

            $accountrequestProposal = DB::table('ba_accountrequest_proposal')
            ->alias('accountrequest_proposal')
            ->field('accountrequest_proposal.account_id,fb_bm_token.pull_status,accountrequest_proposal.id,accountrequest_proposal.currency,accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids')
            ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
            ->where('fb_bm_token.pull_status',1)
            ->whereNotIn('accountrequest_proposal.status',$FHStatus2)
            // ->whereNotNull('fb_bm_token.token')
            ->where('accountrequest_proposal.account_id',$accountId)
            ->find();
            if(empty($accountrequestProposal['account_id'])) throw new \Exception("无法查询该账户，请联系管理员1-1!");
            
            if($type == 1){
                $accountStatus = $this->refreshStatus($accountrequestProposal);
                $resultData = [
                    'account_status'=>$accountStatus
                ];
            }else if($type == 2){
                $accountStatus = $this->refreshConsumption($accountrequestProposal);
                $resultData = [
                    'fb_balance'=>$accountStatus['fb_balance'],
                    'fb_spand'=>bcadd((string)$accountStatus['fb_spand'],'0',2)
                ];
            }

            $result = true;
        } catch (Throwable $th) {
            $this->error($th->getMessage());
        }

        if ($result !== false) {
            $this->success(__('Update successful'),['row'=>$resultData]);
        } else {
            $this->error(__('No rows updated'));
        }
    }

    public function refreshStatus($accountrequestProposal)
    {
        $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($accountrequestProposal['personalbm_token_ids']);
        if(!empty($token)) $accountrequestProposal['token'] = $token;

        $FacebookService = new \app\services\FacebookService();
        $result1 = $FacebookService->adAccounts($accountrequestProposal);
        if($result1['code'] != 1 || empty($result1['data']['account_status'])) throw new \Exception('无法查询该账户，请联系管理员1-2!');
        DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountrequestProposal['account_id'])->update(['account_status'=>$result1['data']['account_status'],'pull_account_status'=>date('Y-m-d H:i',time())]);
        return $result1['data']['account_status'];
    }

    public function refreshConsumption($accountrequestProposal)
    {
        $result = $this->accountConsumption($accountrequestProposal);
        if($result['code'] != 1) throw new \Exception($result['msg']);

        $account = DB::table('ba_account')->field('money,open_money,account_id,open_time')->where('account_id',$accountrequestProposal['account_id'])->find();

        $openTime = date('Y-m-d',$account['open_time']);                        

        $accountSpent = DB::table('ba_account_consumption')->where('account_id',$accountrequestProposal['account_id'])->where('date_start','>=',$openTime)->sum('spend');


        $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($accountrequestProposal['personalbm_token_ids']);
        if(!empty($token)) $accountrequestProposal['token'] = $token;
        
        $FacebookService = new \app\services\FacebookService();
        $result = $FacebookService->adAccounts($accountrequestProposal);
        if($result['code'] != 1) throw new \Exception('无法查询该账户，请联系管理员1-2！');
        DB::table('ba_accountrequest_proposal')->where('account_id', $accountrequestProposal['account_id'])->update(
            [
                'spend_cap'=>$result['data']['spend_cap'],
                'amount_spent'=>$result['data']['amount_spent'],
                'pull_spend_time'=>date('Y-m-d H:i:s',time())
            ]
        );

        $spendCap = $result['data']['spend_cap'] == 0.01?0:$result['data']['spend_cap'];
        $amountSpent = $result['data']['amount_spent'];
        $balance = bcsub((string)$spendCap,(string)$amountSpent,'2');

        $resultData = [
            'fb_balance'=>$balance,
            'fb_spand'=>bcadd((string)$accountSpent,'0',2)
        ];
        return $resultData;
    }

    public function refreshConsumption_test($accountrequestProposal)
    {
        $result = $this->accountConsumption($accountrequestProposal);
        if($result['code'] != 1) throw new \Exception($result['msg']);
        $currency = $accountrequestProposal['currency'];    
        $account = DB::table('ba_account')->field('money,open_money,account_id,open_time')->where('account_id',$accountrequestProposal['account_id'])->find();

        $openMoney = $account['open_money']??0;
        $totalRecharge = DB::table('ba_recharge')->where('account_id',$accountrequestProposal['account_id'])->where('type',1)->where('status',1)->sum('number');
        $totalDeductions = DB::table('ba_recharge')->where('account_id',$accountrequestProposal['account_id'])->where('type',2)->where('status',1)->sum('number');
        $totalReset = DB::table('ba_recharge')->where('account_id',$accountrequestProposal['account_id'])->whereIn('type',[3,4])->where('status',1)->sum('number');
        
        $accountAmount = "0";
        $accountAmount = bcadd((string)$totalRecharge , (string)$openMoney,2);
        $accountAmount = bcsub((string)$accountAmount , (string)$totalDeductions,2) ;
        $accountAmount = bcsub((string)$accountAmount , (string)$totalReset,2) ;

        // $accountAmount = $account['money']??0;
        $openTime = date('Y-m-d',$account['open_time']);                        

        $accountSpent = DB::table('ba_account_consumption')->where('account_id',$accountrequestProposal['account_id'])->where('date_start','>=',$openTime)->sum('spend');

        $currencyRate = json_decode(env('APP.currency'),true);

        $balance = '';

        if(!empty($currencyRate[$currency])){
            $accountAmount = bcmul((string)$accountAmount, $currencyRate[$currency],2);
        }

        if($accountAmount < $accountSpent) $balance = 0;
        else $balance = bcsub((string)$accountAmount, (string)$accountSpent,2);

        $resultData = [
            'fb_balance'=>$balance,
            'fb_spand'=>bcadd((string)$accountSpent,'0',2)
        ];
        return $resultData;
    }


    public function accountConsumption($params)
    {
        $accountId = $params['account_id'];
        $params['stort_time'] = date('Y-m-d', strtotime('-30 days'));
        $params['stop_time'] = date('Y-m-d',time());

        // $params['stort_time'] = '2025-01-02';
        // $params['stop_time'] = '2025-01-13';

        $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);
        
        if(!empty($token)) $params['token'] = $token;
        
        $result = (new \app\services\FacebookService())->insights($params);
        if(empty($result) || $result['code'] != 1) return ['code'=>0,'msg'=>'无法查询该账户，请联系管理员1-3！'];
        
        $accountConsumption = $result['data']['data']??[];
        $accountConsumption = array_column($accountConsumption,null,'date_start');

        $accountConsumptionList = DB::table('ba_account_consumption')->field('account_id,date_start')->where('account_id',$accountId)->select()->toArray();
        $accountConsumptionList = array_column($accountConsumptionList,'account_id','date_start');

        $data = [];

        foreach($accountConsumption as $v){
            if(empty($accountConsumptionList[$v['date_start']])){
                $data[] = [
                    'account_id'=>$accountId,
                    'spend'=>$v['spend'],
                    'date_start'=>$v['date_start'],
                    'date_stop'=>$v['date_stop'],
                    'create_time'=>time(),
                ];
            }else{
                $result = DB::table('ba_account_consumption')->where('account_id',$accountId)->where('date_start',$v['date_start'])->update(['spend'=>$v['spend']]);
            }
        }

        if(!empty($data))  DB::table('ba_account_consumption')->insertAll($data);

        return ['code'=>1,'msg'=>'success'];
    }

    function generateTimeArray($startDate, $endDate) {
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $timeArray = [];
        for ($currentTimestamp = $startTimestamp; $currentTimestamp <= $endTimestamp; $currentTimestamp += 86400) {
            $timeArray[] = date('Y-m-d', $currentTimestamp);
        }
        return $timeArray;
    }

    public function batchReset()
    {
        $accountIds = $this->request->param('account_list');
        $accountListResult = DB::table('ba_account')
        ->alias('account')
        ->field('accountrequest_proposal.account_id,accountrequest_proposal.serial_name,account.admin_id,account.money')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where('account.status',4)
        ->whereIn('accountrequest_proposal.account_id',$accountIds)->select()->toArray();

        $accountrequestProposalIds = array_column($accountListResult,'account_id');

        $rechargeList = DB::table('ba_recharge')->whereIn('id',function($query) use($accountrequestProposalIds){
            $query->table('ba_recharge')->field('max(id)')->whereIn('account_id',$accountrequestProposalIds)->group('account_id');
        })->whereIn('type',[3,4])->where('status','<>',"2")->column('account_id');

        $data = [];
        foreach($accountListResult as $v)
        {
            if(in_array($v['account_id'],$rechargeList) || $v['money'] < 1) continue;
            
            $data = [
                "type" => "3",
                "number" => 0,
                "account_id" => $v['account_id'],
                "admin_id" => $v['admin_id'],
                "account_name" => $v['serial_name'],
                'add_operate_user'=>$this->auth->id,
                'create_time'=>time()
            ];
            
            $id = DB::table('ba_recharge')->insertGetId($data);
            $jobHandlerClassName = 'app\job\AccountSpendDelete';
            $jobQueueName = 'AccountSpendDelete';
            Queue::later(1, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        }
        $this->success('',[]);
    }

    public function getFbAccountConsumptionTaskCount()
    {
        $taskCount =  Cache::store('redis')->handler()->llen('{queues:FbAccountConsumption}');
        $comment = '';
        if($taskCount >0){
            $comment = "消耗查询任务正在执行中！( $taskCount )";
        }
        $this->success('', ['comment' => $comment]);
    }


    function bmList()
    {  

        $accountId = $this->request->param('account_id');

        $resultBm = DB::table('ba_bm')->where('status',1)
        ->whereIn('account_id',$accountId)
        ->whereIn('demand_type',[1,4])
        ->where('dispose_type',1)
        ->where('new_status',1)
        ->column('bm');

        $this->success('',['row'=>$resultBm]);

        //dd($resultBm);

        // $accountId = $this->request->param('account_id');

        // $accountrequestProposal = DB::table('ba_accountrequest_proposal')
        // ->alias('accountrequest_proposal')
        // ->field('accountrequest_proposal.currency,accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type')
        // ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
        // ->where('fb_bm_token.status',1)
        // ->whereNotNull('fb_bm_token.token')
        // ->where('accountrequest_proposal.account_id',$accountId)
        // ->find();

        
        
        // $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(1);
        // if($accountrequestProposal['type'] == 2) $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(2);
        
        // if(!empty($token)) $params['token'] = $token;
        
        // //$result = (new \app\services\FacebookService())->businessesList($accountrequestProposal);
        // $result = (new \app\services\FacebookService())->businessesAdaccountsList($accountrequestProposal);
        // dd($accountrequestProposal);
        // if(empty($result) || $result['code'] == 0) throw new \Exception("消耗查询异常！");

    }


    public function accountCardBind()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            //$this->model->startTrans();
            try {
                //数据校验
                $accountId = $data['account_id'];
                $typr = $data['type'];
                $cardNo = $data['card_no'];
                $timeZone = $data['time_zone'];
                $limited = $data['limited'];

                $accountrequestProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$accountId)->find();
                if(empty($accountrequestProposal)) throw new \Exception('错误：未找到账户！'); 

                $cards = DB::table('ba_cards_info')->where('card_no',$cardNo)->where('is_use',0)->find();
                if(empty($cards)) throw new \Exception('错误：[未找到卡]或[卡已经被使用]或[卡不可使用]！');
                $cardsId = $cards['cards_id'];

                $cardModel = new \app\admin\model\card\CardsModel();
                $cardInfoModel = new \app\admin\model\card\CardsInfoModel();
                $accountCard = new \app\admin\model\addaccountrequest\AccountCard();

                $proposalData = [
                    // 'time_zone'=>$timeZone,
                    'is_cards'=>0,
                    // 'cards_id'=>$cardsId
                ];
                if(!empty($timeZone)) $proposalData['time_zone'] = $timeZone;

                $adminId = DB::table('ba_account')->where('account_id',$accountId)->value('admin_id');
                
                $param = [];
                $param['card_id'] = $cards['card_id'];
                $param['nickname'] = $this->getNickname($accountrequestProposal['account_id']);
                switch($typr){
                    case 1:
                    //    //SX-用户不改限额
                       if(env('APP.IS_QUOTA'))
                       {
                            $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                            $param['transaction_limit_type'] = 'limited';
                            $param['transaction_limit_change_type'] = 'increase';
                            $param['transaction_limit'] = env('CARD.LIMIT_AMOUNT',2);
                            $param['transaction_is'] = 1;
                        }
                            $resultCards =  $cardModel->updateCard($cards,$param);
                            if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        break;
                    case 2:
                            $resultCards =  $cardModel->updateCard($cards,$param);
                            if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        break;
                    case 3:
                        //SX-用户不改限额
                       if(env('APP.IS_QUOTA'))
                       {
                            $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                            $param['transaction_limit_type'] = 'limited';
                            $param['transaction_limit_change_type'] = 'increase';
                            $param['transaction_limit'] = $limited;
                            $param['transaction_is'] = 1;
                       }
                        $resultCards =  $cardModel->updateCard($cards,$param);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);

                        break;
                    default:
                        throw new \Exception("参数错误！");
                }

                $cardInfoModel->where('cards_id',$cards['cards_id'])->update(['is_use'=>1]);

                if(!empty($accountrequestProposal['cards_id'])){                    
                    $accountCard->insert([
                        'account_id'=>$accountrequestProposal['account_id'],
                        'cards_id'=>$cardsId
                    ]);

                    // $cardResult = $cardInfoModel->field('id,card_id,cards_id,card_status,account_id')->where('cards_id',$cardsId)->find();
                    // if($cardResult['card_status'] == 'normal'){
                    //     $cardService = new \app\services\CardService($cardResult['account_id']);
                    //     $result = $cardService->cardFreeze(['card_id'=>$cardResult['card_id']]);
                    //     if($result['code'] != 1) throw new \Exception($result['msg']);
                    //     if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cardResult['id'])->update(['card_status'=>$result['data']['cardStatus']]);
                    // }
                }else{
                    $proposalData['cards_id'] = $cardsId;
                }
                
                $result = $this->model->where('account_id',$accountId)->update($proposalData); 

                // $this->model->commit();
            } catch (Throwable $e) {
                // $this->model->rollback();
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
    
    public function accountCardBindDel()
    {
        if ($this->request->post()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            try {
                //数据校验
                $cardsId = $data['cards_id'];

                if(empty($cardsId)) throw new \Exception('参数错误！');

                $cards = DB::table('ba_cards_info')->where('cards_id',$cardsId)->find();

                $param = [];
                $param['card_id'] = $cards['card_id'];
                $param['nickname'] = 'remove';

                $cardModel = new \app\admin\model\card\CardsModel();
                $resultCards =  $cardModel->updateCard($cards,$param);
                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);

                $accountCard = new \app\admin\model\addaccountrequest\AccountCard();
                $result = $accountCard->where('cards_id',$cardsId)->delete();
                if(empty($result)) throw new \Exception('该户下未找到该卡号！');

                DB::table('ba_cards_info')->where('cards_id',$cardsId)->update(['is_use'=>0]);

            } catch (Throwable $e) {
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

    public function accountCardList()
    {
        $data = $this->request->get();
        if(empty($data['account_id'])) $this->error('参数错误！');
        $card = DB::table('ba_accountrequest_proposal')
        ->alias('accountrequest_proposal')
        ->field('cards_info.card_no,cards_info.cards_id,card_platform.name card_platform_name,cards_info.card_status')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        ->leftJoin('ba_card_account card_account','card_account.id=cards_info.account_id')
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('accountrequest_proposal.account_id',$data['account_id'])->find();

        
        $cardList = DB::table('ba_account_card')
        ->alias('account_card')
        ->field('cards_info.card_no,cards_info.cards_id,card_platform.name card_platform_name,cards_info.card_status')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=account_card.cards_id')
        ->leftJoin('ba_card_account card_account','card_account.id=cards_info.account_id')
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('account_card.account_id',$data['account_id'])->select()->toArray();

        if(!empty($card['cards_id'])){
            $card['type'] = 1;
            array_push($cardList,$card);
        }else{
            $cardList = [];
        }
        

        return $this->success('', $cardList);
    }

    public function accountCardHandoff()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
               

                if(empty($data['account_id']) || empty($data['cards_id'])) $this->error('参数错误！');    
                $accountId = $data['account_id'];
                $cardsId = $data['cards_id'];
                
                $accountCardsId = $this->model->where('account_id',$accountId)->value('cards_id');
                if(empty($accountCardsId)) throw new \Exception('未找到该账户的卡号！');

                $accountCard = new \app\admin\model\addaccountrequest\AccountCard();

                $result = $accountCard->where('account_id',$accountId)->where('cards_id',$cardsId)->delete();
                if(empty($result)) throw new \Exception('该户下未找到该卡号！');

                $accountCard->insert([
                    'account_id'=>$accountId,
                    'cards_id'=>$accountCardsId
                ]);

                $this->model->where('account_id',$accountId)->update(['cards_id'=>$cardsId]);


                $cardResultList = DB::table('ba_cards_info')->field('id,card_id,cards_id,card_status,account_id')->whereIn('cards_id',[$cardsId,$accountCardsId])->select()->toArray();                
                foreach($cardResultList as $v)
                {
                    if($v['cards_id'] == $cardsId && $v['card_status'] != 'normal') {
                        $cardService = new \app\services\CardService($v['account_id']);
                        $result = $cardService->cardUnfreeze(['card_id'=>$v['card_id']]);
                        if($result['code'] != 1) throw new \Exception($result['msg']);
                        if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$v['id'])->update(['card_status'=>$result['data']['cardStatus']]);
                        continue;
                    }
                    // if($v['cards_id'] == $accountCardsId && $v['card_status'] == 'normal'){
                    //     $cardService = new \app\services\CardService($v['account_id']);
                    //     $result = $cardService->cardFreeze(['card_id'=>$v['card_id']]);
                    //     if($result['code'] != 1) throw new \Exception($result['msg']);
                    //     if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$v['id'])->update(['card_status'=>$result['data']['cardStatus']]);
                    //     continue;
                    // }
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('edit successfully'));
            } else {
                $this->error(__('No rows edit'));
            }
        }

        $this->error(__('Parameter error'));
    }

    public function editStatusAll()
    {
        // if(!$this->auth->isSuperAdmin()) $this->error('没有权限！');
        $data = $this->request->post();
        if(empty($data['account_ids']) || !isset($data['status'])) $this->error('参数错误！');
        $accountIds = $data['account_ids'];
        $status = $data['status'];


        $d = [
            'status'=>$status
        ];
        if($status == 99) $d['account_status'] = 0;
        
        $result = DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update($d);
        if($result) return $this->success(__('Update successful'));
        return $this->error(__('No rows updated'));
    }

    function getNickname($nickname)
    {
        $nickname = (string)$nickname;
        if(in_array($nickname[0],[1,4]) && strlen($nickname) >= 16) $nickname = substr($nickname,0,15);
        return $nickname;
    }
    

    function allEditBmRelevance()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $accountIds = $data['account_ids'] ?? [];
            $bmId = $data['bm_id'] ?? 0;
            if (empty($accountIds) || empty($bmId)) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            $bmToken = DB::table('ba_fb_bm_token')->where('id', $bmId)->find();
            if (empty($bmToken)) $this->error(__('BM not found'));
            $this->model->whereIn('account_id',$accountIds)->update(['bm'=>$bmToken['name'],'bm_token_id'=>$bmToken['id']]);
        }
        return $this->success(__('Update successful'));
    }

    function allEditLabelRelevance()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $accountIds = $data['account_ids'] ?? [];
            $labelIds = $data['label_ids'] ?? [];
            if (empty($accountIds) || empty($labelIds)) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            $this->model->whereIn('account_id',$accountIds)->update(['label_ids'=>implode(',', $labelIds)]);
        }
        return $this->success(__('Update successful'));
    }

    

    public function manageExport()
    {
        set_time_limit(600);
        $batchSize = 2000;
        $processedCount = 0;

        $this->withJoinTable = [];
        if ($this->request->param('select')) {
            $this->select();
        }

        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId != 2 && !$this->auth->isSuperAdmin()) {
            array_push($where,['account.admin_id','=',$this->auth->id]);
        }

        $openTime = '';
        $endTime = '';
        $is_  = true;
        $isStatus = 99;
        $whereOr = [];
        $noWhere = [];
        foreach($where as $k => $v){
            if($v[0] == 'accountrequest_proposal.account_status') $is_ = false;
            
            if($v[0] == 'account.open_time'){
                $openTime = date('Y-m-d H:i:s',$v[2][0]);
                $endTime = date('Y-m-d H:i:s',$v[2][1]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 1){
                array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);
                array_push($where,['accountrequest_proposal.status','NOT IN',[96,97]]);
                $isStatus = 1;
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 2){
                array_push($where,['accountrequest_proposal.status','NOT IN',[96,97]]);
                $isStatus = 2;
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 0){
                array_push($whereOr,['accountrequest_proposal.account_status','=',0]);
                array_push($whereOr,['accountrequest_proposal.status','=',96]);
                $isStatus = 0;
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 97){
                array_push($where,['accountrequest_proposal.status','=',97]);
                $isStatus = 97;
                unset($where[$k]);
                continue;
            }
        }
        array_push($where,['account.account_id','<>','']);

        $query = DB::table('ba_account')
        ->alias('account')
        ->field('accountrequest_proposal.spend_cap,accountrequest_proposal.amount_spent,account.account_id,account.open_money,account.open_time,accountrequest_proposal.type,accountrequest_proposal.serial_name,accountrequest_proposal.account_status,accountrequest_proposal.currency,admin.nickname,accountrequest_proposal.status accountrequest_proposal_status')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->order('account.id','desc')
        ->where($where)
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where(function($query) use($whereOr){
            if(!empty($whereOr)){
                $query->whereOr($whereOr);
            }
        });
        $query2 = clone $query;
        $total = $query2->count(); 

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '账号ID',
            '账户名称',
            '状态',
            '币种',
            '余额',
            '历史花费',
            '下户时间'
        ];
        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();
            // dd($where,$data);
            if(!empty($data)) {
                $dataList = $data;
                $List = [];

                foreach($dataList as &$v){

                    $spendCap = $v['spend_cap'] == 0.01?0:$v['spend_cap'];
                    $amountSpent = $v['amount_spent'];
                    $balance = bcsub((string)$spendCap,(string)$amountSpent,'2');

                    $openAccountTime = date('Y-m-d',$v['open_time']);

                    $consumptionWhere = [
                        ['account_id','=',$v['account_id']],
                        ['date_start','>=',$openAccountTime]
                    ];
                    if(!empty($openTime) && !empty($endTime)){
                        array_push($consumptionWhere,['date_start','>=',$openTime]);
                        array_push($consumptionWhere,['date_start','<=',$endTime]);
                    }
                    $accountSpent2 = DB::table('ba_account_consumption')->where($consumptionWhere)->sum('spend');                  
                    
                    $accountStatus = '';
                    if(in_array($v['account_status'],[1,3]) && !in_array($v['accountrequest_proposal_status'],[96,97])) $accountStatus = '活跃';
                    if(in_array($v['account_status'],[2]) && !in_array($v['accountrequest_proposal_status'],[96,97])) $accountStatus = '封户';
                    if($v['account_status'] == 0 || $v['accountrequest_proposal_status']  == 96)
                    {
                        $accountStatus = '不可用';
                        $balance = '***';
                    }
                    if($v['accountrequest_proposal_status']  == 97) $accountStatus = '暂停使用';

                    $List[] = [
                        $v['account_id'],
                        $v['serial_name'],
                        $accountStatus,
                        $v['currency'],
                        $balance,
                        bcadd( (string)$accountSpent2,'0',2),
                        date('Y-m-d H:i:s',$v['open_time'])
                    ];

                    $processedCount++;
                }

                $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
                ->header($header)
                ->data($List);
                $progress = min(100, ceil($processedCount / $total * 100));
                Cache::store('redis')->set('export_manage', $progress, 300);
            }
        }

        $excel->output();
        Cache::store('redis')->delete('export_manage');

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);  
    }


    public function getManageExportProgress()
    {
        $progress = Cache::store('redis')->get('export_manage', 0); // 获取进度
        return $this->success('',['progress' => $progress]);
    }


    public function getCountBalance()
    {
        // if($this->request->get('cc') != 1)  $this->success('',['balance'=>0]);

        $where = [];
        // if(!$this->auth->isSuperAdmin()){
            // array_push($where,['admin_id','=',$this->auth->id]);
            array_push($where,['spend_cap','<>','0.01']);
            array_push($where,['account_status','<>',0]);
            array_push($where,['status','<>',96]);
        // }

        $result = DB::table('ba_accountrequest_proposal')
        ->where($where)
        ->whereIn('account_id',function($query){
            $query->table('ba_account')
            ->where('admin_id',$this->auth->id)
            ->where('status',4)
            ->where(function($query){
                $query->whereOr(
                    [
                        ['is_keep','=','0'],
                        ['keep_succeed','=','1'],
                    ]
                );
            })
            ->field('account_id');
        })
        ->field('sum(spend_cap) spend_cap,sum(amount_spent) amount_spent,currency')->group('currency')->select()->toArray(); 

        $currencyRate = json_decode(env('APP.currency'),true);

        $tokenCapSpent = 0;
        $tokenAmountSpent = 0;
        foreach($result as $v)
        {
            if(isset($currencyRate[$v['currency']]))
            {
                $rate = $currencyRate[$v['currency']];
                $spent = bcdiv((string)$v['spend_cap'],(string)$rate,4);
                $spent2 = bcdiv((string)$v['amount_spent'],(string)$rate,4);
                $tokenCapSpent += $spent;
                $tokenAmountSpent += $spent2;
            }else{
                $tokenCapSpent += $v['spend_cap'];
                $tokenAmountSpent += $v['amount_spent'];
            }
        }

        // if($this->request->get('cc') == 1){
        //         $result = DB::table('ba_accountrequest_proposal')
        //     ->where($where)
        //     ->whereIn('account_id',function($query){
        //         $query->table('ba_account')
        //         ->where('admin_id',$this->auth->id)
        //         ->where(function($query) {       
        //             $query->where(function ($q) {
        //                 $q->where('status', '=', '4')
        //                 ->where('is_keep', '=', '0');
        //             })->whereOr(function ($q) {
        //                 $q->where('status', '=', '4')
        //                 ->where('keep_succeed', '=', '1');
        //             });
        //         })->field('account_id');
        //     })
        //     ->field('sum(spend_cap) spend_cap,sum(amount_spent) amount_spent')->fetchSql()->find();

        // dd($result);
        // }

        
        $balance = bcsub((string)$tokenCapSpent,(string)$tokenAmountSpent,'2');
        // dd($result,$tokenCapSpent,$tokenAmountSpent,$balance);
        $this->success('',['balance'=>$balance]);
    }

    public function allEdit()
    {
        $data = $this->request->param();
        $accountIds = $data['account_ids'];        

        $item = [];
        if(!empty($data['status'])){
            $item['status'] = $data['status'];
            if($data['status'] == 99) $item['account_status'] = 0;
        }
        if(!empty($data['time_zone'])) $item['time_zone'] = $data['time_zone'];
        if(!empty($data['bm_id'])){
            $item['bm_token_id'] = $data['bm_id'];
            $name = DB::table('ba_fb_bm_token')->where('id',$data['bm_id'])->value('name');
            $item['bm'] = $name;
        }
        if(!empty($data['affiliation_bm'])) $item['affiliation_bm'] = $data['affiliation_bm'];
        if(!empty($data['admin_id'])) $item['admin_id'] = $data['admin_id'];
        if(!empty($data['label_ids'])) $item['label_ids'] = implode(',',$data['label_ids']);

        $result = DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update($item);

        if($result) $this->success('更新成功！');
        else $this->error('未找到可以更新的！');
    }
    


    function editAccountName()
    {
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
                
                $params = [];
                $params['account_id'] = $data['account_id'];
                $params['name'] = $data['serial_name'];
                $result = (new \app\admin\services\fb\FbService())->editAdAccounts($params);
                if($result['code'] != 1) throw new \Exception($result['msg']);
                DB::table('ba_accountrequest_proposal')->where('account_id',$data['account_id'])->update(['serial_name'=>$data['serial_name']]);

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

    function allEditAccountName()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $errorList = [];
            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证



                $errorList = [];
                foreach($data['list'] as $v)
                {
                    $v =  preg_replace('/\s+/', '', $v);
                    $item = explode('|',$v);
                    $params = [];
                    $params['account_id'] = $item[0];
                    $params['name'] = $item[1];
                    $result = (new \app\admin\services\fb\FbService())->editAdAccounts($params);
                    if($result['code'] != 1) $errorList[] = $item[0].':'.$result['msg'];
                    DB::table('ba_accountrequest_proposal')->where('account_id',$params['account_id'])->update(['serial_name'=>$item[1]]);
                }                
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'),['errrorList'=>$errorList]);
            } else {
                $this->error(__('No rows updated'),['errrorList'=>$errorList]);
            }
        }
    }


    
    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}