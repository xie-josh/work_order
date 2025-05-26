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

    protected array $noNeedPermission = ['index','accountRefresh','bmList','editStatusAll','Export','getAccountrequestProposal','getExportProgress',"accountCardBind","accountCardBindDel","accountCardList","accountCardHandoff"];

    protected bool|string|int $dataLimit = 'parent';

    protected $currencyRate = ["EUR"=>"0.8","ARS"=>"940","PEN"=>"3.6","IDR"=>"16000","VND"=>"23500"];

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

        //dd($this->request->get());
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
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username','nickname'],'cards'=>['card_no'],'account'=>['id','is_']]);

        return [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ];
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
                unset($where[$k]);
                continue;
            }
        }

        // if($is_) array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);

        // dd($where);

        array_push($where,['account.account_id','<>','']);
        array_push($where,['account.status','in',[4]]);

        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.*,accountrequest_proposal.type,accountrequest_proposal.serial_name,accountrequest_proposal.account_status,accountrequest_proposal.currency,admin.nickname')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->order($order)
        ->where($where)
        ->paginate($limit);


        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $currencyRate = config('basics.currency');

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
                if(!empty($openTime) && !empty($endTime)){
                    array_push($consumptionWhere,['date_start','>=',$openTime]);
                    array_push($consumptionWhere,['date_start','<=',$endTime]);
                }
                $accountSpent = DB::table('ba_account_consumption')->where($consumptionWhere)->sum('spend');


                $accountAmount = bcadd((string)$totalRecharge , (string)$openMoney,2);
                $accountAmount = bcsub((string)$accountAmount , (string)$totalDeductions,2) ;
                $accountAmount = bcsub((string)$accountAmount , (string)$totalReset,2) ;

                if(!empty($currencyRate[$v['currency']])){
                    $accountAmount = bcmul((string)$accountAmount, $currencyRate[$v['currency']],2);
                }

                if($accountAmount < $accountSpent) $balance = 0;
                else $balance = bcsub((string)$accountAmount, (string)$accountSpent,2);
                
                $v['fb_balance'] = $balance;
                $v['fb_spand'] = bcadd( (string)$accountSpent,'0',2);
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
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $bm = $data['bm'];
                $affiliationBm = $data['affiliationBm'];
                $timeZone = $data['timeZone'];
                $adminId = $data['adminId'];
                $isCards = $data['is_cards']??0;
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
        $ids = $this->request->post('ids');
        if($ids) array_push($where,['accountrequest_proposal.id','in',$ids]);
        
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
        ->field('accountrequest_proposal.*,account.name account_name,account.bm account_bm,admin.nickname,cards_info.card_no')
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->leftJoin('ba_admin admin','admin.id=accountrequest_proposal.admin_id')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        ->order('accountrequest_proposal.id','asc')
        ->where($where);
        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $statusValue = [0=>'未分配',1=>'已分配',2=>'绑卡挂户',3=>'大BM挂',4=>'其他币种',5=>'丢失账户',6=>'开户异常',98=>'回收',99=>'终止使用'];

        // $cardsList = DB::table('ba_account_card')->select()->toArray();

        $maxCountList = DB::table('ba_account_card')
        ->field('COUNT(id) as cnt')
        ->group('account_id')
        ->order('cnt')->select()->toArray();
        $maxCount = 0;
        if(!empty(array_column($maxCountList,'cnt'))) $maxCount = max(array_column($maxCountList,'cnt'));

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'bm',
            'time_zone',
            'account_id',
            'account_name',
            'affiliation_bm',
            'affiliation_admin_name',
            'account_bm',
            'status',
            'nickname',
            'FB_account_status',
            'currency',
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
                    'bm'=>$v['bm'],
                    'time_zone'=>$v['time_zone'],
                    'account_id'=>$v['account_id'],
                    'account_name'=>$v['serial_name'],
                    'affiliation_bm'=>$v['affiliation_bm'],
                    'affiliation_admin_name'=> $adminList[$v['affiliation_admin_id']]??'',
                    'account_bm'=> $v['account_bm'],
                    'status'=> $statusValue[$v['status']]??'未知的状态',
                    'nickname'=>$v['nickname'],
                    'FB_account_status'=>$accountStatus[$v['account_status']]??'未找到状态',
                    'currency'=>$v['currency'],
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
        $currency = $accountrequestProposal['currency'];    
        $account = DB::table('ba_account')->field('money,account_id,open_time')->where('account_id',$accountrequestProposal['account_id'])->find();

        $accountAmount = $account['money']??0;
        $openTime = date('Y-m-d',$account['open_time']);                        

        $accountSpent = DB::table('ba_account_consumption')->where('account_id',$accountrequestProposal['account_id'])->where('date_start','>=',$openTime)->sum('spend');

        $currencyRate = config('basics.currency');

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

                $param = [];
                $param['card_id'] = $cards['card_id'];
                $param['nickname'] = $this->getNickname($accountrequestProposal['account_id']);
                switch($typr){
                    case 1:
                        $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                        $param['transaction_limit_type'] = 'limited';
                        $param['transaction_limit_change_type'] = 'increase';
                        $param['transaction_limit'] = env('CARD.LIMIT_AMOUNT',2);
                        $param['transaction_is'] = 1;

                        $resultCards =  $cardModel->updateCard($cards,$param);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        
                        break;
                    case 2:
                        $resultCards =  $cardModel->updateCard($cards,$param);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);

                        break;
                    case 3:
                        $param['max_on_percent'] = env('CARD.MAX_ON_PERCENT',901);
                        $param['transaction_limit_type'] = 'limited';
                        $param['transaction_limit_change_type'] = 'increase';
                        $param['transaction_limit'] = $limited;
                        $param['transaction_is'] = 1;

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

                    // $cardResult = $cardInfoModel->field('id,card_id,cards_id,card_status,account_id')->where('cards_id',$accountrequestProposal['cards_id'])->find();
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
        ->field('cards_info.card_no,cards_info.cards_id,card_platform.name card_platform_name')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        ->leftJoin('ba_card_account card_account','card_account.id=cards_info.account_id')
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('accountrequest_proposal.account_id',$data['account_id'])->find();

        
        $cardList = DB::table('ba_account_card')
        ->alias('account_card')
        ->field('cards_info.card_no,cards_info.cards_id,card_platform.name card_platform_name')
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
                    if($v['cards_id'] == $accountCardsId && $v['card_status'] == 'normal'){
                        $cardService = new \app\services\CardService($v['account_id']);
                        $result = $cardService->cardFreeze(['card_id'=>$v['card_id']]);
                        if($result['code'] != 1) throw new \Exception($result['msg']);
                        if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$v['id'])->update(['card_status'=>$result['data']['cardStatus']]);
                        continue;
                    }
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

        $result = DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['status'=>$status]);
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

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}