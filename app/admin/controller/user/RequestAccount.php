<?php

namespace app\admin\controller\user;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;

/**
 * 账户列管理
 */
class RequestAccount extends Backend
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

    protected array $noNeedPermission = ['index','userIndex','accountRefresh','gitIndexCount','batchRecycle'];

    protected bool|string|int $dataLimit = 'parent';

    protected $currencyRate = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountrequestProposal();
    }

    public function index():void 
    {
        $result = $this->userIndex();
        $this->success('', $result);
    }

    public function userIndex()
    {
        $this->withJoinTable = [];
        if ($this->request->param('select')) {
            $this->select();
        }

        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        // $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        // if($groupsId != 2 && !$this->auth->isSuperAdmin()) { 
            
        // }
       
        // if(!in_array($this->auth->type,[2,3,4]))throw new \Exception("角色有误!请联系管理员");
        
        array_push($where,['account.company_id','=',$this->auth->company_id]);
        if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);
        

        //客户后台的余额和闲置时间 需要添加升序降序的选项
        //order     create_time,desc
        // dd($order);
        

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
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 333){
                array_push($where,['account.idle_time','>',config('basics.ACCOUNT_RECYCLE_DAYS')*86400]);
                unset($where[$k]);
                continue;
            }
            if($v[0] == 'accountrequest_proposal.account_status' && $v[2] == 94){
                array_push($where,['accountrequest_proposal.status','=',94]);
                unset($where[$k]);
                continue;
            }
        }

        // if($is_) array_push($where,['accountrequest_proposal.account_status','IN',[1,3]]);


        array_push($where,['account.account_id','<>','']);
        // dd($where);
       
        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.*,accountrequest_proposal.type,accountrequest_proposal.serial_name,accountrequest_proposal.account_status,accountrequest_proposal.currency,accountrequest_proposal.status accountrequest_proposal_status,accountrequest_proposal.spend_cap,accountrequest_proposal.amount_spent,accountrequest_proposal.bm,accountrequest_proposal.bm_token_id')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        // // ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        // ->leftJoin('ba_admin admin','admin.company_id=account.company_id')
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
        })//->find(); dd(DB::table('ba_account')->getLastSql());  
        ->paginate($limit);
        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $fbBmTokenList = DB::table('ba_fb_bm_token')->field('business_id,id')->select()->toArray();
            $fbBmTokenList = array_column($fbBmTokenList,'business_id','id');

            foreach($dataList as &$v){

                $seconds = $v['idle_time'];
                if($seconds > 86400){
                    $days = floor($seconds / 86400);
                    $hours = floor(($seconds % 86400) / 3600);
                }else{
                    $days = 0;
                    $hours = floor($seconds / 3600);
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
                $v['bm_count'] = $this->getBmCount($v['account_id']);
                $v['business_id'] = $fbBmTokenList[$v['bm_token_id']]??'';
            }
        }

        return [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ];
    }

    function gitIndexCount()
    {
        $data = [
            'total_count'=>0,        //全部
            'total_active'=>0,       //活跃
            'total_sealing'=>0,      //封户
            'total_unavailable'=>0,  //不可用
            'total_idle'=>0,         //闲置
            'total_pause'=>0,        //暂停
            'total_to_recycle'=>0,   //待回收
            'total_recycle'=>0,      //已回收
        ];
        $where = [
            ['account.company_id','=',$this->auth->company_id],
            ['account.account_id','<>',''],
        ];

        if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);

        $data['total_count'] = DB::table('ba_account')->where($where)
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->count();

        $data['total_active'] = DB::table('ba_account')->where($where)
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where([
            ['accountrequest_proposal.account_status','in',[1,3]],
            ['accountrequest_proposal.status','NOT IN',[96,97]]
        ])        
        ->count();

        $data['total_sealing'] = DB::table('ba_account')->where($where)
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where([
            ['accountrequest_proposal.account_status','=',2],
            ['accountrequest_proposal.status','NOT IN',[96,97]]
        ])
        ->count();

        $data['total_idle'] = DB::table('ba_account')->where($where)
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where([
            ['account.idle_time','>',config('basics.ACCOUNT_RECYCLE_DAYS')*86400]
        ])
        ->count();

        $data['total_unavailable'] = DB::table('ba_account')->where($where)
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where(function ($query){
            $query->whereOr([
                    ['accountrequest_proposal.account_status','=',0],
                    ['accountrequest_proposal.status','=',96]
            ]);
        })
        ->count();

        $data['total_pause'] = DB::table('ba_account')->where($where)
        ->alias('account')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where([
            ['accountrequest_proposal.status','=',97]
        ])
        ->count();

        $data['total_to_recycle'] = DB::table('ba_account')->where($where)
        ->alias('account')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->where(function($query) {       
            $query->where(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.is_keep', '=', '0');
            })->whereOr(function ($q) {
                $q->where('account.status', '=', '4')
                ->where('account.keep_succeed', '=', '1');
            });
        })
        ->where([
            ['accountrequest_proposal.status','=',94]
        ])
        ->count();

        if($this->auth->type != 4){
            $data['total_recycle'] = DB::table('ba_account_recycle')->where(
                [
                    ['company_id','=',$this->auth->company_id],
                ]
            )->count();
        }else{
            $data['total_recycle'] = '';
        }


        $this->success('',$data);
    }


    function getBmCount($accountId)
    {
        $result =  DB::table('ba_bm')
            ->where('account_id',$accountId)
            ->whereIn('demand_type',[1,4])
            ->where('dispose_type',1)
            ->where('new_status',1)
            ->group('account_id,bm')
            ->count();
        return $result;
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
        // if($groupsId != 2 && !$this->auth->isSuperAdmin()) {
        //     array_push($where,['account.admin_id','=',$this->auth->id]);
        // }
        if(!in_array($this->auth->type,[2,3,4]))throw new \Exception("角色有误!请联系管理员");

        array_push($where,['account.company_id','=',$this->auth->company_id]);
        if($this->auth->type == 4) array_push($where,['account.team_id','=',$this->auth->team_id]);
        

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
        ->leftJoin('ba_admin admin','admin.company_id=account.company_id')
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

    public function accountRefresh()
    {
        $accountId = $this->request->param('account_id');
        $type = $this->request->param('type');
        $result = false;
        $resultData = [];
        try {
            //实时刷新状态与消耗
            $where = [
                ['account_id','=',$accountId],
                ['company_id','=',$this->auth->company_id]
            ];
            // if(!$this->auth->isSuperAdmin()) array_push($where,['admin_id','=',$this->auth->id]);
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

    //用户回收账户
    public function batchRecycle(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));
    
            $accountIds  = $data['account_ids'];
            $recycleType = $data['recycle_type'];  //确认回收   //94    2317148228700024
            if(empty($accountIds)) $this->error('参数错误');
            if(empty($recycleType) || !in_array($recycleType,[1,2])) $this->error('确认信息有误');
            if(count($accountIds) > 30) $this->error('批量不能超过30条');
            // foreach($accountIds as $id){ }
            $result = DB::table('ba_accountrequest_proposal')->where('account_status',1)->whereIn('account_id',$accountIds)->update(['status'=>94,'recycle_type'=>$recycleType]);
           
            if ($result !== false) {
                 $this->success(__('Update successful'));
            } else {
                 $this->error(__('No rows updated'));
            }
        }
        $this->error(__('Parameter error'));
    }

}