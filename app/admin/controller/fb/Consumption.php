<?php

namespace app\admin\controller\fb;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use think\facade\Cache;
use think\facade\Db;

class Consumption extends Backend
{
    /**
     * @var object
     * @phpstan-var BmToken
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = ['last_login_time', 'login_failure', 'password', 'salt'];

    protected string|array $quickSearchField = ['username', 'nickname', 'id'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\fb\ConsumptionModel();
    }

    public function export()
    {
        set_time_limit(600);

        $data = $this->request->post();

        // if(empty($data['status']) || $data['status'] != '99') $this->error('导出功能维护中!');

        $isCount = $data['is_count']??2;
        $startTime = $data['start_time']??'';
        $endTime = $data['end_time']??'';

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 3000;
        $processedCount = 0;
        $redisKey = 'export_consumpotion'.'_'.$this->auth->id;
        
        $accountRecycleWhere = [];
        if(!empty($startTime) && !empty($endTime)){
            array_push($where,['date_start','>=',$startTime]);
            array_push($where,['date_stop','<=',$endTime]);
            $accountRecycleWhere = [
                ['account_recycle.account_recycle_time','>=',$startTime],
                ['account_recycle.account_recycle_time','<=',$endTime." 23:59:59"]
            ];
        }

        // $accountRecycleList = $this->accountRecycle($accountRecycleWhere);
        //$accountRecycleList = $this->accountRecycle2($accountRecycleWhere);

        // array_push($where,['account_consumption.account_id','=','1168825717882895']);


        $query =  $this->model
        ->alias('account_consumption')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->where($where);

        if($isCount == 1){
            $query->field('account.open_time,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend');
            $query->group('account_id');
            $accountRecycleList = $this->accountRecycle2($accountRecycleWhere);
        }else{
            $accountRecycleWhere = [];
            $query->field('account.open_time,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm');
            $accountRecycleList = $this->accountRecycle($accountRecycleWhere);
        }
        // dd($accountRecycleList['579613691644460'],$isCount);

        $total = $query->count();

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '账户状态',
            '账户名称',
            '账户ID',
            '货币',
            '消耗',
            '开始时间',
            '结束时间',
            '归属用户',
            '管理BM',
            '归属BM',
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();
            $dataList=[];
            foreach($data as $v){
                $nickname = '';
                $spend = $v['spend']??0;
                $dateStart =  $v['date_start'];
                $accountRecycle = $accountRecycleList[$v['account_id']]??[];

                if(!empty($v['open_time'])){
                    $openTimeDate = date('Y-m-d',$v['open_time']);
                    if($v['date_start'] >= $openTimeDate) $nickname = $v['nickname'];
                }
                
                if(!empty($accountRecycle)){
                    if($isCount == 1){
                        foreach($accountRecycle as $recycle){
                            $dataList[] = [
                                $accountStatus[$v['account_status']]??'未找到状态',
                                $v['serial_name'],                    
                                $v['account_id'],
                                $v['currency'],
                                (float)$recycle['spend'],
                                $recycle['date_start'],
                                $recycle['date_stop'],
                                $recycle['nickname'],
                                $v['bm'],
                                $v['affiliation_bm'],
                            ];
                            $spend = bcsub((string)$spend ,(string)$recycle['spend'],2);
                            $dateStart = $recycle['date_stop'];
                        }

                    }else{
                        // if(empty($v['nickname']) && !empty($accountRecycle)){
                        //     $accountRecycleCount = count($accountRecycle) - 1;
                        //     $nickname = $accountRecycle[$accountRecycleCount]['nickname'];
                        // }else{
                        //     foreach($accountRecycle as $recycle){
                        //         if($v['date_start'] >= $recycle['strat_open_time'] && $v['date_start'] < $recycle['end_open_time']){
                        //             $nickname = $recycle['nickname'];
                        //             break;
                        //         }
                        //     }
                        // }                       

                        foreach($accountRecycle as $recycle){
                            if($v['date_start'] >= $recycle['strat_open_time'] && $v['date_start'] < $recycle['end_open_time']){
                                $nickname = $recycle['nickname'];
                                break;
                            }
                        }

                        if(empty($nickname) && !empty($accountRecycle)){
                            $accountRecycleCount = count($accountRecycle) - 1;
                            if($v['date_start'] >= $accountRecycle[$accountRecycleCount]['strat_open_time']){
                                $nickname = $accountRecycle[$accountRecycleCount]['nickname'];
                            }
                        }

                    }
                }
                
                $dataList[] = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $v['serial_name'],                    
                    $v['account_id'],
                    $v['currency'],
                    (float)$spend,
                    $dateStart,
                    $v['date_stop'],
                    $nickname,
                    $v['bm'],
                    $v['affiliation_bm'],
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

    public function export2()
    {
        set_time_limit(300);

        $data = $this->request->post();

        $isCount = $data['is_count']??2;
        $startTime = $data['start_time']??'';
        $endTime = $data['end_time']??'';

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_consumpotion'.'_'.$this->auth->id;
        

        if(!empty($startTime) && !empty($endTime)){
            array_push($where,['date_start','>=',$startTime]);
            array_push($where,['date_stop','<=',$endTime]);
        }

        $query =  $this->model
        ->alias('account_consumption')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->where($where);

        if($isCount == 1){
            $query->field('admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend');
            $query->group('account_id');
        }else{
            $query->field('admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm');
        }

        $total = $query->count();

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '账户状态',
            '账户名称',
            '账户ID',
            '货币',
            '消耗',
            '开始时间',
            '结束时间',
            '归属用户',
            '管理BM',
            '归属BM'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $v['serial_name'],                    
                    $v['account_id'],
                    $v['currency'],
                    $v['spend'],
                    $v['date_start'],
                    $v['date_stop'],
                    $v['nickname'],
                    $v['bm'],
                    $v['affiliation_bm'],
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


    public function accountRecycle($accountRecycleWhere)
    {   
        $accountRecycleListResult = DB::table('ba_account_recycle')
        ->alias('account_recycle')
        ->field('account_recycle.id,account_recycle.open_time,account_recycle.account_id,account_recycle.account_recycle_time,admin.nickname')
        ->leftJoin('ba_admin admin','admin.id=account_recycle.admin_id')
        ->where($accountRecycleWhere)
        ->where('account_recycle.status',4)
        ->select()->toArray();

        $accountIds = array_unique(array_column($accountRecycleListResult,'account_id'));
        $accountListResult = DB::table('ba_account')->field('open_time,account_id')->whereIn('account_id',$accountIds)->where('status',4)->select()->toArray();
        $accountList = [];
        foreach ($accountListResult as $key => $item) {
            $accountList[$item['account_id']] = date('Y-m-d',$item['open_time']);
        }


        //$seenAccounts = [];
        $accountRecycleList = [];
        foreach ($accountRecycleListResult as $key => $item) {
            // if (isset($seenAccounts[$item['account_id']])) {
            //     $item['start_time'] = $seenAccounts[$item['account_id']];
            //     $seenAccounts[$item['account_id']] = $item['account_recycle_time'];
            // } else {
            //     $seenAccounts[$item['account_id']] = $item['account_recycle_time'];
            // }
            //$item['account_recycle_time'] = date('Y-m-d',strtotime($item['account_recycle_time']));
            // $stratOpenTime = date('Y-m-d',strtotime($item['open_time']));
            // $endOpenTime = date('Y-m-d',strtotime($item['account_recycle_time']));
            $item['open_time'] = date('Y-m-d',$item['open_time']);
            $accountRecycleList[$item['account_id']][] = $item;
        }
        foreach($accountRecycleList as $key => &$value){
            foreach ($value as $key2 => &$value2) {
                $value2['strat_open_time'] = $value2['open_time'];
                $value2['end_open_time'] = '';
                if(isset($value[$key2+1])) $value2['end_open_time'] = $value[$key2+1]['open_time'];
                if(!isset($value[$key2+1]) && isset($accountList[$value2['account_id']])) $value2['end_open_time'] = $accountList[$value2['account_id']];
            }
        }
        // $a = '2025-02-20';
        // $a1 = '2025-02-20';
        // $name = '1111';




        // $a2 = '2025-02-20';

        // if($a2 >= $a && $a2 < $a1 || $name){
        //     dd(1);
        // }


        // dd($accountRecycleList,$accountList);
        
        return $accountRecycleList;
    }

    public function accountRecycle2($accountRecycleWhere)
    {   
        $accountConsumptionResults = [];
        $accountRecycleListResult = DB::table('ba_account_recycle')
        ->alias('account_recycle')
        ->field('account_recycle.account_id,account_recycle.account_recycle_time,admin.nickname')
        ->leftJoin('ba_admin admin','admin.id=account_recycle.admin_id')
        ->where($accountRecycleWhere)
        ->where('account_recycle.status',4)
        ->select()->toArray();

        $seenAccounts = [];
        $accountRecycleList = [];
        foreach ($accountRecycleListResult as $key => $item) {
            $item['account_recycle_time'] = date('Y-m-d',strtotime($item['account_recycle_time']));
            if (isset($seenAccounts[$item['account_id']])) {
                if($item['account_recycle_time']  == $seenAccounts[$item['account_id']]) continue;
                $item['start_time'] = $seenAccounts[$item['account_id']];
                $seenAccounts[$item['account_id']] = $item['account_recycle_time'];
            } else {
                if(!empty($accountRecycleWhere)) $item['start_time'] = $accountRecycleWhere[0][2];
                else $item['start_time'] = '2024-01-01';
                
                $seenAccounts[$item['account_id']] = $item['account_recycle_time'];
            }
            $accountRecycleList[] = [
                'nickname' => $item['nickname'],
                ['account_id' ,'=', $item['account_id']],
                ['date_start','<',$item['account_recycle_time']],
                ['date_start','>=', $item['start_time']]
            ];
        }
        //dd($accountRecycleList);
        
        foreach ($accountRecycleList as $conditions) {
            $nickname = $conditions['nickname'];
            unset($conditions['nickname']);
            $result = Db::table('ba_account_consumption')
                ->field('sum(spend) as spend, account_id')
                ->where($conditions)->find();
            if(!empty($result['account_id'])){
                $accountConsumptionResults[$result['account_id']][] = [
                    'spend' => $result['spend'],
                    'account_id' => $result['account_id'],
                    'date_start' => $conditions[2][2],
                    'date_stop' => $conditions[1][2],
                    'nickname' => $nickname,
                ];
            }
        }
            
            // dd($accountConsumptionResults,$value);

            // $accountConsumption = DB::table('ba_account_consumption')->field('sum(spend) as spend,account_id')->where(function($query) use ($value){
            //     $query->whereOr($value);
            // })->group('account_id')->select()->toArray();
            // dd($accountConsumption,$value);
        

        // dd($accountConsumptionResults);
        return $accountConsumptionResults;
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_consumpotion'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

}