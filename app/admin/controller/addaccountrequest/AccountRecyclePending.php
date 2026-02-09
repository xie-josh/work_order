<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use Exception;
use think\facade\Db;
use think\facade\Cache;

class AccountRecyclePending extends Backend
{
    /**
     * @var object
     * @phpstan-var AccountChannel
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = ['id'];
    protected array $noNeedPermission = ['index','getExportProgress','export','batchTurnDownRecycle','idleExport','getIdleExportProgress'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountRecyclePending();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();    
        $type =  $this->request->get('type');
        if($type==1) 
                $where[] = ['accountrequest_proposal.recycle_type','=',3];
        else 
                $where[] = ['accountrequest_proposal.recycle_type','<>',3];

        $where[] = ['account.status','=',4];
        $totalConsumption = 0;
        $whereOr = [];
        foreach($where as $k => &$v){
            if($v[0] == 'account.idle_time'){                
                $v[2] = floor((int)$v[2] * 86400);
            }
            if($v[0] == 'account_recycle_pending.account_id' && $v[1] == 'LIKE'){                
                $v[0] = 'account.account_id';
            }
            if($v[0] == 'account_recycle_pending.status'){                
                $v[0] = 'accountrequest_proposal.status';
            }
            if($v[0] == 'account_recycle_pending.account_status'){                
                $v[0] = 'accountrequest_proposal.account_status';
            }
            if($v[0] == 'accountrequest_proposal.total_consumption'){                
                // $v[2] = 1000;
                $totalConsumption = floor($v[2]);
            }
            if($v[0] == 'account_recycle_pending.admin_a_nickname'){      
                $adminIds = Db::table('ba_admin')->where('nickname','like','%'.$v[2].'%')->column('id');          
                array_push($where,['accountrequest_proposal.admin_id','IN',$adminIds]);
                unset($where[$k]);
            }
            if($v[0] == 'account_recycle_pending.account_id' && $v[1] == 'IN'){      
                array_push($where,['account.account_id','IN',$v[2]]);
                unset($where[$k]);
            }
            if($v[0] == 'account_recycle_pending.requeire')
            {      
                $recycleTypeAccountIds = DB::table('ba_accountrequest_proposal')->where('recycle_type','in',[1,2])->column('account_id');
                $rechargeNumIds = DB::table('ba_recharge')->whereIn('account_id',$recycleTypeAccountIds)->where('status',0)->group('account_id')->column('account_id');
                $bmNumIds = DB::table('ba_bm')->whereIn('account_id',$recycleTypeAccountIds)->where('status','IN',[0,1])->where('dispose_type',0)->group('account_id')->column('account_id');
                // dd($rechargeNum);

                if($v[2] == 1)
                {
                    array_push($where,['account.account_id','NOT IN', $rechargeNumIds]);
                    array_push($where,['account.account_id','NOT IN', $bmNumIds]);
                }else{
                    array_push($whereOr,['account.account_id','IN', $rechargeNumIds]);
                    array_push($whereOr,['account.account_id','IN', $bmNumIds]);
                }
                // dd($where,$whereOr);
                // array_push($where,['account.account_id','IN',$v[2]]);
                unset($where[$k]);
            }
        }
        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.admin_id account_admin_id,account.company_id,accountrequest_proposal.recycle_date,accountrequest_proposal.status,account.comment,account.account_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm,admin_a.nickname admin_a_nickname,account.idle_time,accountrequest_proposal.total_consumption,admin_b.nickname admin_b_nickname,accountrequest_proposal.account_status,accountrequest_proposal.time_zone,account.open_time,accountrequest_proposal.recycle_type')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin_a','admin_a.id=accountrequest_proposal.admin_id')
        ->leftJoin('ba_admin admin_b','admin_b.id=account.admin_id')
        ->where($where);

        if(!empty($whereOr)) $res = $res->where(function($query) use ($whereOr){ $query->whereOr($whereOr); });
        
        if(!empty($totalConsumption)) $res = $res->whereRaw("COALESCE(accountrequest_proposal.total_consumption, 0) + 0 > $totalConsumption");

        $res = $res->paginate($limit);
        // dd($where);

        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $accountIds = array_column($dataList,'account_id');
            $rechargeNum = DB::table('ba_recharge')->field('account_id,count(account_id) as num')->whereIn('account_id',$accountIds)->where('status',0)->group('account_id')->select()->toArray();
            $bmNum = DB::table('ba_bm')->field('account_id,count(account_id) as num')->whereIn('account_id',$accountIds)->where('status','IN',[0,1])->where('dispose_type',0)->group('account_id')->select()->toArray();
            $rechargeList = array_column($rechargeNum,'num','account_id');
            $bmList = array_column($bmNum,'num','account_id');

            $companyAdminNameArr = DB::table('ba_admin')->field('company_id,nickname,id')->where('type',2)->select()->toArray();
            $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');
            $adminNameArr = DB::table('ba_admin')->field('nickname,id')->select()->toArray();
            $adminNameArr = array_column($adminNameArr,'nickname','id');

            

            foreach($dataList as &$v){
                $companyId = $v['company_id'];
                if($v['account_admin_id'] == $companyAdminNameArr[$companyId]['id']) $nickname_b = $companyAdminNameArr[$companyId]['nickname'];
                else $nickname_b = $companyAdminNameArr[$companyId]['nickname']."(".$adminNameArr[$v['account_admin_id']].")";
                

                $v['admin_b_nickname'] = $nickname_b;
                $v['account'] = [
                    'account_id'=>$v['account_id'],
                    'idle_time'=>floor($v['idle_time'] / 86400),
                    'open_time'=>$v['open_time'],
                ];
                $v['accountrequest_proposal'] = [
                    'serial_name'=>$v['serial_name'],
                    'bm'=>$v['bm'],
                    'total_consumption'=>$v['total_consumption'],
                    'account_status'=>$v['account_status'],
                    'time_zone'=>$v['time_zone'],
                    'recycle_type'=>$v['recycle_type'],
                ];
                $v['recharge_num'] = $rechargeList[$v['account_id']]??0;
                $v['bm_num'] = $bmList[$v['account_id']]??0;
                
                $v['requeire'] = 2;
                if($v['recharge_num'] == 0 && $v['bm_num'] == 0) $v['requeire'] = 1;
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);




        // $res = DB::table('ba_account_recycle_pending')
        //     ->field('account_recycle_pending.*,accountrequest_proposal.bm,account.idle_time,COALESCE(CAST(accountrequest_proposal.total_consumption AS DECIMAL(15,2)), 0) AS total_consumption_num')
        //     ->alias('account_recycle_pending')
        //     // ->withJoin($this->withJoinTable, $this->withJoinType)
        //     ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_recycle_pending.account_id')
        //     ->leftJoin('ba_account account','account.account_id=account_recycle_pending.account_id')
        //     ->where($where)
        //     ->order('total_consumption_num desc')
        //     ->paginate($limit);

        //  $result = $res->toArray();
        // $dataList = [];
        // if(!empty($result['data'])) {
        //     $dataList = $result['data'];

        //     foreach($dataList as &$v){
        //         if($v['idle_time'] > 86400){
        //             $days = floor($v['idle_time'] / 86400);
        //             $hours = floor(($v['idle_time'] % 86400) / 3600);
        //         }else{
        //             $days = 0;
        //             $hours = 0;
        //         }
        //         $v['consumption_date'] = [
        //             'days'=>$days,
        //             'hours'=>$hours
        //         ];
        //     }
        // }

             

        // $this->success('', [
        //     'list'   => $dataList,
        //     'total'  => $res->total(),
        //     'remark' => get_route_remark(),
        // ]);
    }

    public function export()
    {
        $where = [];
        set_time_limit(300);
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $type =  $this->request->get('type');
        if($type==1) 
                $where[] = ['accountrequest_proposal.recycle_type','=',3];
        else 
                $where[] = ['accountrequest_proposal.recycle_type','<>',3];
        $where[] = ['account.status','=',4];
        $totalConsumption = 0;
        $whereOr = [];
        foreach($where as $k => &$v){
            if($v[0] == 'account.idle_time'){                
                $v[2] = floor((int)$v[2] * 86400);
            }
            if($v[0] == 'account_recycle_pending.account_id' && $v[1] == 'LIKE'){                
                $v[0] = 'account.account_id';
            }
            if($v[0] == 'account_recycle_pending.status'){                
                $v[0] = 'accountrequest_proposal.status';
            }
            if($v[0] == 'account_recycle_pending.account_status'){                
                $v[0] = 'accountrequest_proposal.account_status';
            }
            if($v[0] == 'accountrequest_proposal.total_consumption'){                
                // $v[2] = 1000;
                $totalConsumption = floor($v[2]);
            }
            if($v[0] == 'account_recycle_pending.admin_a_nickname'){      
                $adminIds = Db::table('ba_admin')->where('nickname','like','%'.$v[2].'%')->column('id');          
                array_push($where,['accountrequest_proposal.admin_id','IN',$adminIds]);
                unset($where[$k]);
            }
            if($v[0] == 'account_recycle_pending.account_id' && $v[1] == 'IN'){      
                array_push($where,['account.account_id','IN',$v[2]]);
                unset($where[$k]);
            }
            if($v[0] == 'account_recycle_pending.requeire')
            {      
                $recycleTypeAccountIds = DB::table('ba_accountrequest_proposal')->where('recycle_type','in',[1,2])->column('account_id');
                $rechargeNumIds = DB::table('ba_recharge')->whereIn('account_id',$recycleTypeAccountIds)->where('status',0)->group('account_id')->column('account_id');
                $bmNumIds = DB::table('ba_bm')->whereIn('account_id',$recycleTypeAccountIds)->where('status','IN',[0,1])->where('dispose_type',0)->group('account_id')->column('account_id');
                // dd($rechargeNum);

                if($v[2] == 1)
                {
                    array_push($where,['account.account_id','NOT IN', $rechargeNumIds]);
                    array_push($where,['account.account_id','NOT IN', $bmNumIds]);
                }else{
                    array_push($whereOr,['account.account_id','IN', $rechargeNumIds]);
                    array_push($whereOr,['account.account_id','IN', $bmNumIds]);
                }
                // dd($where,$whereOr);
                // array_push($where,['account.account_id','IN',$v[2]]);
                unset($where[$k]);
            }
        }

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_progress'.'_'.$this->auth->id;
        
        $query = DB::table('ba_account')
        ->alias('account')
        ->field('account.admin_id account_admin_id,account.company_id,accountrequest_proposal.admin_id accountrequest_proposal_admin_id,accountrequest_proposal.recycle_date,accountrequest_proposal.status,account.account_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm,account.idle_time,accountrequest_proposal.total_consumption,accountrequest_proposal.account_status,accountrequest_proposal.time_zone,account.open_time')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        // ->leftJoin('ba_admin admin_a','admin_a.id=accountrequest_proposal.admin_id')
        // ->leftJoin('ba_admin admin_b','admin_b.id=account.admin_id')
        // ->where('account.id','desc')
        ->where($where);

        if(!empty($whereOr)) $query = $query->where(function($query) use ($whereOr){ $query->whereOr($whereOr); });
        if(!empty($totalConsumption)) $query = $query->whereRaw("COALESCE(accountrequest_proposal.total_consumption, 0) + 0 > $totalConsumption");
        $statusValueList = config('basics.ACCOUNT_STATUS');
        $total = $query->count(); 

        $acountStatusValue = [0=>'异常',1=>'活跃',2=>'封户',3=>'待支付'];

        $companyAdminNameArr = DB::table('ba_admin')->field('company_id,nickname,id')->where('type',2)->select()->toArray();
        $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');
        $adminNameArr = DB::table('ba_admin')->field('nickname,id')->select()->toArray();
        $adminNameArr = array_column($adminNameArr,'nickname','id');

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            "账户ID",
            "账户名称",
            "管理BM",
            "渠道",
            "闲置天数",
            "历史总消耗",
            "归属用户",
            "账户状态",
            "时区",            
            "系统状态",
            "开户时间",
            "回收时间"
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        for ($offset = 0; $offset < $total; $offset += $batchSize) {

            $data = $query->limit($offset, $batchSize)->select()->toArray();
            $dataList=[];
            foreach($data as $v){
                $companyId = $v['company_id'];
                $nickname_b = '';
                
                if($v['account_admin_id'] == $companyAdminNameArr[$companyId]['id']) $nickname_b = $companyAdminNameArr[$companyId]['nickname'];
                else $nickname_b = $companyAdminNameArr[$companyId]['nickname']."(".$adminNameArr[$v['account_admin_id']].")";

                $nickname_a = $adminNameArr[$v['accountrequest_proposal_admin_id']]??'';

                $dataList[] = [
                    $v['account_id'],
                    $v['serial_name'],
                    $v['bm'],
                    $nickname_a,
                    floor($v['idle_time'] / 86400),
                    $v['total_consumption'],
                    $nickname_b,
                    $acountStatusValue[$v['account_status']]??'未知状态',
                    $v['time_zone'],
                    $statusValueList[$v['status']]??'',
                    $v['open_time']?date('Y-m-d H:i',$v['open_time']):'',
                    $v['recycle_date']
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



    public function idleExport()
    {
        set_time_limit(300);

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $type = $this->request->get('type');

        if ($type == 1) {
            $where[] = ['accountrequest_proposal.recycle_type', '=', 3];
        } else {
            $where[] = ['accountrequest_proposal.recycle_type', '<>', 3];
        }

        $where[] = ['account.status', '=', 4];
        $where[] = ['account.idle_time', '>=', (7 * 86400)];
        $where[] = ['accountrequest_proposal.status', '=', 1];

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'idle_export_progress_' . $this->auth->id;

        // 基础查询（后面 clone 用）
        $baseQuery = DB::table('ba_account')
            ->alias('account')
            ->field(
                'account.admin_id account_admin_id,
                account.company_id,
                accountrequest_proposal.admin_id accountrequest_proposal_admin_id,
                accountrequest_proposal.recycle_date,
                accountrequest_proposal.status,
                account.account_id,
                accountrequest_proposal.serial_name,
                accountrequest_proposal.bm,
                account.idle_time,
                accountrequest_proposal.total_consumption,
                accountrequest_proposal.account_status,
                accountrequest_proposal.time_zone,
                account.open_time'
            )
            ->leftJoin(
                'ba_accountrequest_proposal accountrequest_proposal',
                'accountrequest_proposal.account_id = account.account_id'
            )
            ->where($where);

        // 总数（用于进度）
        // $total = (clone $baseQuery)->count();

        // 所有 company_id
        $companyIds = (clone $baseQuery)
            ->distinct(true)
            ->column('account.company_id');

        if(empty($companyIds)) $this->error('未找到匹配的数据！');

        $companyAdminNameArr = DB::table('ba_admin')
            ->field('company_id,nickname,id')
            ->where('type', 2)
            ->select()
            ->toArray();
        $companyAdminNameArr = array_column($companyAdminNameArr, null, 'company_id');

        $adminNameArr = DB::table('ba_admin')
            ->field('nickname,id')
            ->select()
            ->toArray();
        $adminNameArr = array_column($adminNameArr, 'nickname', 'id');

        $companyAdminNameArr = DB::table('ba_admin')->field('company_id,nickname,id')->where('type',2)->select()->toArray();
        $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');

        // Excel 目录
        $folders = (new \app\common\service\Utils)->getExcelFolders();

        $header = [
            "账户ID",
            "账户名称",
            "管理BM",
            "闲置天数",
            "历史总消耗",
            "时区",
            "开户时间"
        ];

        // zip 初始化
        $zipName = 'idle_export_' . date('Ymd_His') . '.zip';
        $zipPath = $folders['path'] . '/' . $zipName;

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $total = count($companyIds);
        foreach ($companyIds as $k => $companyId) {

            $nickname = $companyAdminNameArr[$companyId]['nickname'];

            $query = clone $baseQuery;
            $query->where('account.company_id', $companyId);

            $companyTotal = $query->count();
            if ($companyTotal == 0) continue;

            $excelName = "{$nickname}-闲置超过七天账户-".date('md').".xlsx";
            $excel = new \Vtiful\Kernel\Excel(['path' => $folders['path']]);

            $sheetInit = false;

            for ($offset = 0; $offset < $companyTotal; $offset += $batchSize) {

                $rows = $query->limit($offset, $batchSize)->select()->toArray();
                $dataList = [];

                foreach ($rows as $v) {
                    $dataList[] = [
                        $v['account_id'],
                        $v['serial_name'],
                        $v['bm'],
                        floor($v['idle_time'] / 86400),
                        $v['total_consumption'],
                        $v['time_zone'],
                        $v['open_time']?date('Y-m-d H:i',$v['open_time']):''
                    ];
                    $processedCount++;
                }
                
                if (!$sheetInit) {
                    $filePath = $excel->fileName($excelName, 'sheet1')
                    ->header($header)
                    ->data($dataList);
                    $sheetInit = true;
                } else {
                    $excel->data($dataList);
                }
            }
                
            $excel->output();
            // 进度
            $progress = min(100, ceil($k / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
            // 加入 zip
            $zip->addFile($folders['path'] . '/' . $excelName, $excelName);
        }

        $zip->close();

        Cache::store('redis')->delete($redisKey);
        $this->success('', [
            'path' => $folders['filePath'] . '/' . $zipName
        ]);
    }


    public function getIdleExportProgress()
    {
        $progress = Cache::store('redis')->get('idle_export_progress_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

    public function batchTurnDownRecycle()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            try {

                $accountIds = $data['account_ids'];
                if(empty($accountIds)) throw new Exception('请传正确的参数！');

                // $rechargeNum = DB::table('ba_recharge')->field('account_id,count(account_id) as num')->whereIn('account_id',$accountIds)->where('status',0)->group('account_id')->select()->toArray();
                // $bmNum = DB::table('ba_bm')->field('account_id,count(account_id) as num')->whereIn('account_id',$accountIds)->where('status','IN',[0,1])->where('dispose_type',0)->group('account_id')->select()->toArray();
                // $rechargeList = array_column($rechargeNum,'num','account_id');
                // $bmList = array_column($bmNum,'num','account_id');

                $where = [
                    //预充账户 / 待回收 / 终止使用
                    // ['accountrequest_proposal.recycle_type','<>',3],
                    ['accountrequest_proposal.status','in',[94,99,96]],
                    ['accountrequest_proposal.account_id','IN',$accountIds],
                ];
                $res = DB::table('ba_account')
                ->alias('account')
                ->field('accountrequest_proposal.account_id,account.company_id')
                ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')            
                ->where($where)
                ->select()->toArray();

                $dataAccountIds = [];

                $errorList = [];
                foreach($res as $v)
                {
                    if(empty($v['company_id']))
                    {
                        $errorList[] = ['account_id'=>$v['account_id'],'msg'=>'未找到账户或未分配！'];
                        continue;
                    }
                                       
                    $dataAccountIds[] = $v['account_id'];
                }
                if(!empty($dataAccountIds)){

                    $data = [
                        'recycle_type'=>3,
                        'recycle_date'=>'',
                        'status'=>1,
                        'recycle_start'=>date('Y-m-d H:i:s',time())
                    ];
                    DB::table('ba_accountrequest_proposal')->whereIn('account_id',$dataAccountIds)->update($data);

                    DB::table('ba_recharge')->whereIn('account_id',$dataAccountIds)->whereIn('type',[3,4])->where('status',0)->update(['status'=>2]);
                    DB::table('ba_bm')->whereIn('account_id',$dataAccountIds)->where('demand_type',2)->whereIn('status',[0,1])->where('dispose_type',0)->update(['status'=>0,'dispose_type'=>0]);
                }
               
                $result = true;
            } catch (Throwable $e) {
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'),['error_list'=>$errorList]);
            } else {
                $this->error(__('No rows updated'),['error_list'=>$errorList]);
            }
        }
    }

}