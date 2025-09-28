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
                'start_time'=>$startTime,
                'end_time'=>$endTime
            ];
        }
       
        $query =  $this->model
        ->alias('account_consumption')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->leftJoin('ba_admin admin','admin.id=account.admin_id')
        ->where($where)
        ->order('account_consumption.id','asc');

        $statusList = config('basics.ACCOUNT_STATUS');

        if($isCount == 1){
            $query->field('account.status open_account_status,accountrequest_proposal.admin_id admin_channel,account.open_time,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.status,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend');
            $query->group('account_id');
            $accountRecycleList = $this->accountRecycle2($accountRecycleWhere);
        }else{
            $accountRecycleWhere = [];
            $query->field('account.status open_account_status,accountrequest_proposal.admin_id admin_channel,account.open_time,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.status
            ,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop
            ,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm');
            $accountRecycleList = $this->accountRecycle($accountRecycleWhere);
        }

        $adminChannelList = DB::table('ba_admin')->field('id,nickname')->select()->toArray();
        $adminChannelList = array_column($adminChannelList,'nickname','id');
        $openAccountStatusValue = config('basics.OPEN_ACCOUNT_STATUS');
        
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
            '系统状态',
            '开户状态',
            '渠道'
        ];

        if($isCount != 1) $header[] = '开户时间';

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
                
                $adminChannel = $adminChannelList[$v['admin_channel']]??'';
                $nickname = '';
                $openTime = '';
                $serialName = $v['serial_name'];
                $spend = $v['spend']??0;
                $dateStart =  $v['date_start'];
                $accountRecycle = $accountRecycleList[$v['account_id']]??[];
                $statusValue = $statusList[$v['status']]??'未找到状态';
                                
                if(!empty($v['open_time']) && $isCount != 1){
                    $openTimeDate = date('Y-m-d',$v['open_time']);
                    if($v['date_start'] >= $openTimeDate){
                        $nickname = $v['nickname'];
                        $openTime = date('Y-m-d',$v['open_time']);
                    }
                }
                
                if(!empty($v['open_time']) && $isCount == 1)
                {
                    $openTimeDate = date('Y-m-d',$v['open_time']);
                    if($v['date_stop'] >= $openTimeDate) $nickname = $v['nickname'];
                }

                // if($v['account_id'] == '1738696516982083'){
                //     dd($v,$accountRecycle,$openTimeDate,$nickname);
                // }
                
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
                                $statusValue,
                                $openAccountStatusValue[$v['open_account_status']]??'未知的状态',
                                $adminChannel,
                            ];
                            $spend = bcsub((string)$spend ,(string)$recycle['spend'],2);
                            $dateStart = $recycle['date_stop'];
                        }
                        if(empty($v['open_time']) || $v['open_time'] > strtotime($endTime." 23:59:59"))
                        {
                            $processedCount++;
                            continue;
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
                                $openTime = $recycle['open_time'];
                                $serialName = $recycle['name'];
                                break;
                            }
                        }

                        if(empty($nickname) && !empty($accountRecycle)){
                            $accountRecycleCount = count($accountRecycle) - 1;
                            if($v['date_start'] >= $accountRecycle[$accountRecycleCount]['strat_open_time']){
                                $nickname = $accountRecycle[$accountRecycleCount]['nickname'];
                                $openTime = $accountRecycle[$accountRecycleCount]['open_time'];
                                $serialName = $accountRecycle[$accountRecycleCount]['name'];
                            }
                        }

                    }
                }
                
                $dataList[] = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $serialName,                    
                    $v['account_id'],
                    $v['currency'],
                    (float)$spend,
                    $dateStart,
                    $v['date_stop'],
                    $nickname,
                    $v['bm'],
                    $v['affiliation_bm'],
                    $statusValue,
                    $openAccountStatusValue[$v['open_account_status']]??'未知的状态',
                    $adminChannel,
                    $openTime,
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
        ->field('account_recycle.id,account_recycle.open_time,account_recycle.account_id,account_recycle.account_recycle_time,admin.nickname,account_recycle.name')
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
        // dd($accountRecycleList);
        return $accountRecycleList;
    }

    public function accountRecycle2($accountRecycleWhere)
    {   
        $startTime = '';
        $endTime = '';
        $accountRecycleWhere2 = [];
        if(!empty($accountRecycleWhere['start_time']) && !empty($accountRecycleWhere['end_time']))
        {
            $startTime = $accountRecycleWhere['start_time'];
            //$endTime = $accountRecycleWhere['end_time'];
            $endTime = date("Y-m-d",strtotime($accountRecycleWhere['end_time'] . ' +1 day'));
            $accountRecycleWhere2[] = [
                'account_recycle.open_time','<',strtotime($endTime)
            ];
        }

         $accountRecycleListResult = DB::table('ba_account_recycle')
         ->alias('account_recycle')
         ->field('account_recycle.id,account_recycle.open_time,account_recycle.account_id,account_recycle.account_recycle_time,admin.nickname')
         ->leftJoin('ba_admin admin','admin.id=account_recycle.admin_id')
         ->whereNotNull('account_recycle.open_time')
        //  ->where($accountRecycleWhere)
        ->where($accountRecycleWhere2)
         ->where('account_recycle.status',4)
         ->select()->toArray();

         $accountIds = array_unique(array_column($accountRecycleListResult,'account_id'));
         $accountListResult = DB::table('ba_account')->field('open_time,account_id')
         ->whereIn('account_id',$accountIds)->where('status',4)
         ->where('open_time','<',strtotime($endTime))
         ->select()->toArray();
         $accountList = array_column($accountListResult,null,'account_id');
         
 
         $accountRecycleList = [];
        foreach ($accountRecycleListResult as $key => $item) {
            $item['open_time'] = date('Y-m-d',$item['open_time']);
            $accountRecycleList[$item['account_id']][] = $item;
        }
        foreach($accountRecycleList as $k => &$v){
            if(!empty($accountList[$k])){
                $v[] = [
                    // "id" => 25505
                    "open_time" => date('Y-m-d',$accountList[$k]['open_time']),
                    "account_id" => $accountList[$k]['account_id'],
                    "account_recycle_time" => '',
                    "nickname" => ''
                ];
            }
        }



        $stratOpenTime = '';
        $endOpenTime = '';
        $accountConsumptionResults = [];

        if(!empty($startTime))
        {
            $startTime2 = strtotime($startTime . ' +1 day');
            $endTime2 = strtotime($endTime);
            // $startTimeDate = date('Y-m-d', $startTime2);

            $accountListResult2 = DB::table('ba_account')->field('open_time,account_id')->whereNotIn('account_id',$accountIds)->where('status',4)
            ->where([
                ['open_time','>=',$startTime2],
                ['open_time','<',$endTime2],
            ])->select()->toArray();
            
            $dataWhere = [];
            foreach($accountListResult2 as $item)
            {
                $startTimeDate = date('Y-m-d', $item['open_time']);
                // if($item['account_id'] == '579613691644460'){
                //     dd(88,$startTimeDate);
                // }
                $dataWhere = [
                    ['date_start','>=',$startTime],
                    ['date_start','<',$startTimeDate],
                    ['account_id','=',$item['account_id']]
                ];
                $result = Db::table('ba_account_consumption')
                ->field('sum(spend) as spend, account_id')
                ->where($dataWhere)->find();

                $accountConsumptionResults[$result['account_id']][] = [
                    'spend' => $result['spend'],
                    'account_id' => $result['account_id'],
                    'date_start' => $startTime,
                    'date_stop' => $startTimeDate,
                    'nickname' => '',
                ];
            }
        }

        // dd($accountRecycleList['554903870883876'],$endTime);
        // dd($accountConsumptionResults['579613691644460']);

        // dd($accountRecycleList);
        // foreach($accountRecycleList as $key => &$value){
        //     foreach ($value as $key2 => &$value2) {
        //         $value2['strat_open_time'] = $value2['open_time'];
        //         $value2['end_open_time'] = '';
        //         if(isset($value[$key2+1])) $value2['end_open_time'] = $value[$key2+1]['open_time'];
        //         if(!isset($value[$key2+1]) && isset($accountList[$value2['account_id']])) $value2['end_open_time'] = $accountList[$value2['account_id']];
        //     }
        // }

        $_is = true;
        foreach($accountRecycleList as $key => &$value){
            $_is = true;
            foreach ($value as $key2 => &$value2) {
                if(!isset($value2['id'])) continue;
                $dataWhere = [];
                $endOpenTime = '';
                if(isset($value[$key2+1])) $endOpenTime = $value[$key2+1]['open_time'];

                if($value2['open_time'] > ($startTime." 23:59:59") && $_is && $key2 === 0){
                    $dataWhere[] = ['account_id','=',$value2['account_id']];
                    array_push($dataWhere,['date_start','>=',$startTime]);
                    array_push($dataWhere,['date_start','<',$value2['open_time']]);

                    $result = Db::table('ba_account_consumption')
                    ->field('sum(spend) as spend, account_id')
                    ->where($dataWhere)->find();
                    if(!empty($result['account_id'])){
                        $accountConsumptionResults[$result['account_id']][] = [
                            'spend' => $result['spend'],
                            'account_id' => $result['account_id'],
                            'date_start' => $startTime,
                            'date_stop' => $value2['open_time'],
                            'nickname' => '',
                        ];
                    }
                    $dataWhere = [];
                    $_is = false;
                }

                // if($value2['account_id'] == '1072789028075390'){
                //     dd($accountRecycleList['1072789028075390']??[],$value2['open_time'],$value2['open_time'] > ($startTime." 23:59:59"),$_is);
                // }


                $dataWhere[] = ['account_id','=',$value2['account_id']];
                if(empty($endOpenTime)){
                    if($value2['open_time'] >= $startTime)
                    {
                        array_push($dataWhere,['date_start','>=',$value2['open_time']]);
                        array_push($dataWhere,['date_start','<',$endTime]);
                    }else{
                        array_push($dataWhere,['date_start','>=',$startTime]);
                        array_push($dataWhere,['date_start','<',$endTime]);
                    }
                }else{
                    if($endOpenTime < $startTime) continue;
                    if($value2['open_time'] <= $startTime)
                    {
                        if($endOpenTime < $endTime)
                        {
                            array_push($dataWhere,['date_start','>=',$startTime]);
                            array_push($dataWhere,['date_start','<',$endOpenTime]);
                        }else if($endOpenTime > $endTime){
                            array_push($dataWhere,['date_start','>=',$startTime]);
                            array_push($dataWhere,['date_start','<',$endTime]);
                        }
                        
                    }else if($value2['open_time'] > $startTime)
                    {
                        if($endOpenTime < $endTime)
                        {
                            array_push($dataWhere,['date_start','>=',$value2['open_time']]);
                            array_push($dataWhere,['date_start','<',$endOpenTime]);
                        }else if($endOpenTime > $endTime){
                            array_push($dataWhere,['date_start','>=',$value2['open_time']]);
                            array_push($dataWhere,['date_start','<',$endTime]);
                        }
                    }
                }
                $result = Db::table('ba_account_consumption')
                ->field('sum(spend) as spend, account_id')
                ->where($dataWhere)->find();
                if(!empty($result['account_id'])){
                    $accountConsumptionResults[$result['account_id']][] = [
                        'spend' => $result['spend'],
                        'account_id' => $result['account_id'],
                        'date_start' => $dataWhere[1][2],
                        'date_stop' => date('Y-m-d',strtotime($dataWhere[2][2])),
                        'nickname' => $value2['nickname'],
                    ];
                }
                // dd($dataWhere,$value,$result,$accountConsumptionResults);            

                //  if(!isset($value[$key2+1]) && isset($accountList[$value2['account_id']])) $value2['end_open_time'] = $accountList[$value2['account_id']];
             }
         }
        // dd($accountConsumptionResults['1072789028075390']??[],$accountRecycleList['1072789028075390']??[]);
        return $accountConsumptionResults;
    }


    public function accountRecycle3($accountRecycleWhere)
    {   


        /**
         * 1.求出老外消耗的 （开始）
         * 2.开户时间 ~ 开户时间 求出每一段时间消耗 （）
         * 
         * 
         * 
         */

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

    //新统计
    public function newStatistics()
    {
        $data = $this->request->post();
        $adminId  = $data['id'];
        $v = DB::table('ba_admin')
            ->alias('admin')
            ->field('admin.id,admin.nickname,admin.money,admin_group_access.group_id')
            ->leftJoin('ba_admin_group_access admin_group_access','admin_group_access.uid = admin.id')
            ->where(['id'=>$adminId])->find();
        $dataList = [];
        $thePreviousDayDollar = 0;
        if($v) {
                $consumptionService = new \app\admin\services\fb\Consumption();
                $totalDollar = $consumptionService->getTotalDollar($v['id']);
                $thePreviousDayDollar = $consumptionService->thePreviousDay($v['id']); //前一天消耗总金额
                $money = DB::table('ba_admin_money_log')->where('admin_id',$v['id'])->sum('money'); //总消耗
                $remainingAmount = bcsub((string)$money,(string)$totalDollar,'2');
                $dataList[] = [
                    'id' => $v['id'],
                    'nickname' => $v['nickname'],
                    'money' => $money,
                    // 'total_dollar' => bcadd((string)$totalDollar,'0',2),
                    'remaining_amount' => $remainingAmount,
                ];
        }
        //--------------------------------------------------------------------------------------------
        $dayList = $this->model->dayConsumption($adminId, $data['start_date'] ?? '', $data['end_date'] ?? '');
        // $monthList = $this->model->monthConsumption($adminId, $data['start_date'] ?? '', $data['end_date'] ?? '');
        $list = [];
        // foreach($monthList as $value)
        // {
        //     $list['month'][] = [
        //         'total_dollar'=>$value['total_dollar'],
        //         "date_start"=>$value['date_start'],
        //     ];
        // }
        foreach($dayList as $value2)
        {
            $list['day'][] = [
               'total_dollar'=>$value2['total_dollar'],
                "date_start"=>$value2['date_start'],
            ];
        }
        $list['day'] = array_reverse($list['day']);
        $list['all'] = $list['day'];
        // array_merge($list['month'],$list['day']);
        //---------------------------------------------------------------------------------------------

        $this->withJoinTable = [];
        $where = [];
        // $alias = ['ba_admin_money_log'=>'admin_money_log'];
        // $order = ['id'=>"desc"];
        $limit = 999;
        array_push($where,['admin_id','=',$adminId]);
        $res = DB::table('ba_admin_money_log')->where($where)->select()->toArray();
        
        $result = DB::table('ba_rate')->where('admin_id',$adminId)->order('create_time asc')->select()->toArray();
        $section = [];
        if(!empty($result))foreach($result as $k => $v)
        {
            $section[] =  ['start_tmie' => $v['create_time'],'end_tmie' => isset($result[$k+1]['create_time'])?$result[$k+1]['create_time']:'','rate'=>$v['rate']];
        }
        //上上2个月的总消耗和总费率
        $archived =  DB::table('ba_archived')->where('admin_id',$adminId)->field('month_total_dollar total_dollar,month date_start,rate_total_dollar rate','month')
        ->order('month desc')->select()->toArray();
        // ->where(function($query) {
        //     $query->where('month', date("Y-m", strtotime("-2 month")))->whereOr('month', date("Y-m", strtotime("-3 month")));
        // })

        $section = array_reverse($section);
        if(!empty($archived))foreach($archived as $kk =>&$vv)
        {
            array_unshift($list['all'], $vv);
        }
        foreach($list['all'] AS $k => &$v){
             $v['money'] = $dataList[$k]['money']??'';

            //  $v['month_total_dollar'] = $list['month'][$k]['total_dollar']??"";
            //  $v['month_date_start'] = $list['month'][$k]['date_start']??'';
             $v['remaining_amount'] = $dataList[$k]['remaining_amount']??''; //可用金额
             if($k == 0)
             {
                $v['atLeast_money']    = bcmul($thePreviousDayDollar,'2','2')??''; // 最低打款
                $v['suggestzui_money'] = bcmul($thePreviousDayDollar,'4','2')??''; // 建议打款
             }
             $v['money1'] = $res[$k]['money']??'';
             $v['raw_money'] = $res[$k]['raw_money']??'';
             if(isset($res[$k]['create_time'])) $v['create_time'] = date('Y-m-d H:i',$res[$k]['create_time']);
             else $v['create_time'] = '';
             //服务费率
             if(!empty($section)&&count($archived)<=$k)foreach($section as $kk => $vv)
             {
                 $start = strtotime($vv['start_tmie']);
                 $end   = strtotime($vv['end_tmie']);
                 //大于设定日期,设定当日不生效
                 if (strtotime($v['date_start']) > $start && strtotime($v['date_start']) <= $end) {
                    //  $dd[] =   $v['start_tmie'] .">$thsiTime 在区间内".$v['rate']."--".$v['end_tmie']."\n";
                    $v['rate'] =  round($v['total_dollar']*$vv['rate'], 2);
                 }
                 if (strtotime($v['date_start']) > $start && empty($end)) {
                    //  $dd[] =  $v['start_tmie'] .">$thsiTime 没有结束时间".$v['rate']."--".$v['end_tmie']."\n";
                    $v['rate'] =  round($v['total_dollar']*$vv['rate'], 2);
                 }
             }
        }
      
        unset($list['day']);
        unset($list['month']);
        return $this->success('',$list);
    }

    public function consumptionList()
    {
        $data = $this->request->param();
        $adminId = $data['id'];

        $dayList = $this->model->dayConsumption($adminId, $data['start_date'] ?? '', $data['end_date'] ?? '');
        $monthList = $this->model->monthConsumption($adminId, $data['start_date'] ?? '', $data['end_date'] ?? '');

        $list = [];
        foreach($monthList as $value)
        {
            $list['month'][] = [
                'total_dollar'=>$value['total_dollar'],
                "date_start"=>$value['date_start'],
            ];
        }

        foreach($dayList as $value2)
        {
            $list['day'][] = [
               'total_dollar'=>$value2['total_dollar'],
                "date_start"=>$value2['date_start'],
            ];
        }
        $list['day'] = array_reverse($list['day']);
        return $this->success('',$list);


        // dd($list);

        // $folders = (new \app\common\service\Utils)->getExcelFolders();

        // $header = [
        //     '账单日期',
        //     '账单金额',
        //     '总付款金额',
        //     '可用金额'
        // ];

        // $config = [
        //     'path' => $folders['path']
        // ];
        // $excel  = new \Vtiful\Kernel\Excel($config);
        // $name = $folders['name'].'.xlsx';      

        // $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
        //     ->header($header)
        //     ->data($list);

        // $excel->output();

        // $this->success('',['path'=>$folders['filePath'].'/'.$name]);


        // $this->success('', ['list' => $list]);

    }

    public function export3()
    {
        set_time_limit(600);

        $data = $this->request->post();

        // if(empty($data['status']) || $data['status'] != '99') $this->error('导出功能维护中!');

        $isCount = $data['is_count']??2;
        $startTime = $data['start_time']??'';
        $endTime = $data['end_time']??'';

        if(empty($startTime) || empty($endTime)) $this->error('请选择时间！');

        $accountRecycleWhere = [
            'start_time'=>$startTime,
            'end_time'=>$endTime
        ];

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 3000;
        $processedCount = 0;
        $redisKey = 'export3_consumpotion'.'_'.$this->auth->id;
        
        $accountRecycleWhere = [];
        array_push($where,['date_start','>=',$startTime]);
        array_push($where,['date_stop','<=',$endTime]);
       
        $query =  $this->model
        ->alias('account_consumption')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->leftJoin('ba_account account','account.account_id=account_consumption.account_id')
        ->leftJoin('ba_admin admin','admin.id=account_consumption.admin_id')
        ->where($where)
        ->order('account_consumption.id','asc');

        $statusList = config('basics.ACCOUNT_STATUS');

        if($isCount == 1){
            $query->field('account.status open_account_status,account.open_time account_open_time,accountrequest_proposal.admin_id admin_channel,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.status,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend,accountrequest_proposal.time_zone');
            $query->group('account_consumption.admin_id,account_id');
        }else{
            $accountRecycleWhere = [];
            $query->field('account.status open_account_status,account.open_time account_open_time,accountrequest_proposal.admin_id admin_channel,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.status
            ,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop
            ,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,accountrequest_proposal.time_zone');

            $accountIskeepList = $this->accountIskeepList($accountRecycleWhere);
        }

        $adminChannelList = DB::table('ba_admin')->field('id,nickname')->select()->toArray();
        $adminChannelList = array_column($adminChannelList,'nickname','id');
        $openAccountStatusValue = config('basics.OPEN_ACCOUNT_STATUS');
        
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
            '系统状态',
            '开户状态',
            '渠道',
            '时区',
        ];

        if($isCount != 1){
            $header[] = '开户时间';
            $header[] = '养户消耗(是)';
        }

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
                
                // if($isCount != 1 && !empty($v['account_open_time'])) $openTime = date('Y-m-d',$v['account_open_time']);
                // else 
                $openTime = '';
                
                $openStatus = $openAccountStatusValue[$v['open_account_status']]??'未知的状态';               
                $adminChannel = $adminChannelList[$v['admin_channel']]??'';
                $statusValue = $statusList[$v['status']]??'未找到状态';
                
                $nickname = $v['nickname'];                
                $serialName = $v['serial_name'];
                $spend = $v['spend']??0;
                $dateStart =  $v['date_start'];
                $isKeep = '';

                if($isCount != 1 && isset($accountIskeepList[$v['account_id']])){
                    $cc = $accountIskeepList[$v['account_id']]['keep']??[];
                    $dd = $accountIskeepList[$v['account_id']]['open']??[];
                    foreach($cc as $item)
                    {
                        if($item['open_time'] <= $v['date_start'] &&  $item['keep_time'] >= $v['date_start']) $isKeep = '是';
                    }
                    foreach($dd as $item2)
                    {
                        if($item2['strat_open_time'] <= $v['date_start'] &&  $item2['end_open_time'] >= $v['date_start']) $openTime = $item2['strat_open_time'];
                    }
                }
                // dd($accountIskeepList['411380995335659']);
                
                $dataList[] = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $serialName,                    
                    $v['account_id'],
                    $v['currency'],
                    (float)$spend,
                    $dateStart,
                    $v['date_stop'],
                    $nickname,
                    $v['bm'],
                    $v['affiliation_bm'],
                    $statusValue,
                    $openStatus,
                    $adminChannel,
                    $v['time_zone'],
                    $openTime,
                    $isKeep
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

    public function export4()
    {
        set_time_limit(600);

        $data = $this->request->post();

        // if(empty($data['status']) || $data['status'] != '99') $this->error('导出功能维护中!');

        $isCount = $data['is_count']??2;
        $startTime = $data['start_time']??'';
        $endTime = $data['end_time']??'';

        if(empty($startTime) || empty($endTime)) $this->error('请选择时间！');

        $accountRecycleWhere = [
            'start_time'=>$startTime,
            'end_time'=>$endTime
        ];

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 3000;
        $processedCount = 0;
        $redisKey = 'export3_consumpotion'.'_'.$this->auth->id;
        
        $accountRecycleWhere = [];
        array_push($where,['date_start','>=',$startTime]);
        array_push($where,['date_stop','<=',$endTime]);
       
        $query =  $this->model
        ->alias('account_consumption')        
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->leftJoin('ba_account account','account.account_id=account_consumption.account_id')
        ->leftJoin('ba_admin admin','admin.id=account_consumption.admin_id')
        ->where($where)
        ->order('account_consumption.id','asc');

        $statusList = config('basics.ACCOUNT_STATUS');

        if($isCount == 1){
            $query->field('account.name,account.status open_account_status,account.open_time account_open_time,accountrequest_proposal.admin_id admin_channel,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.status,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend,accountrequest_proposal.time_zone');
            $query->group('account_consumption.admin_id,account_id');
        }else{
            $accountRecycleWhere = [];
            $query->field('account.name,account.status open_account_status,account.open_time account_open_time,accountrequest_proposal.admin_id admin_channel,admin.nickname,accountrequest_proposal.currency,accountrequest_proposal.status
            ,accountrequest_proposal.account_status,accountrequest_proposal.serial_name,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop
            ,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,accountrequest_proposal.time_zone');

            $accountIskeepList = $this->accountIskeepList($accountRecycleWhere);
        }

        $adminChannelList = DB::table('ba_admin')->field('id,nickname')->select()->toArray();
        $adminChannelList = array_column($adminChannelList,'nickname','id');
        $openAccountStatusValue = config('basics.OPEN_ACCOUNT_STATUS');
        
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
            '系统状态',
            '开户状态',
            '渠道',
            '时区',
        ];

        if($isCount != 1){
            $header[] = '开户时间';
            $header[] = '养户消耗(是)';
        }

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
                
                if($isCount != 1) $openTime = date('Y-m-d H:i:s',$v['account_open_time']);
                else $openTime = '';
                
                $openStatus = $openAccountStatusValue[$v['open_account_status']]??'未知的状态';               
                $adminChannel = $adminChannelList[$v['admin_channel']]??'';
                $statusValue = $statusList[$v['status']]??'未找到状态';
                
                $nickname = $v['nickname'];                
                //$serialName = $v['serial_name'];
                $serialName = $v['name'];
                $spend = $v['spend']??0;
                $dateStart =  $v['date_start'];
                $isKeep = '';

                if($isCount != 1 && isset($accountIskeepList[$v['account_id']])){
                    $cc = $accountIskeepList[$v['account_id']];
                    foreach($cc as $item)
                    {
                        if($item['open_time'] <= $v['date_start'] &&  $item['keep_time'] >= $v['date_start']) $isKeep = '是';
                    }
                }
                
                $dataList[] = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $serialName,                    
                    $v['account_id'],
                    $v['currency'],
                    (float)$spend,
                    $dateStart,
                    $v['date_stop'],
                    $nickname,
                    $v['bm'],
                    $v['affiliation_bm'],
                    $statusValue,
                    $openStatus,
                    $adminChannel,
                    $v['time_zone'],
                    $openTime,
                    $isKeep
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

    public function accountIskeepList($accountRecycleWhere)
    {
        //TODO...
        #如果每一次多要查询全部，太消耗性能了，获取  养户完成时间 > 导出开始时间 中开户时间最大的
        // $startTime = $accountRecycleWhere['start_time'];
        // $endTime = $accountRecycleWhere['end_time'];

        $where = [
            // ['is_keep','=',1]
            ['status','=',4]
        ];
        $accountList = DB::table('ba_account')->field('is_keep,account_id,open_time,keep_time')->where($where)->order('id','asc')->select()->toArray();
        array_push($where,['open_time','<>','NULL']);
        // array_push($where,['keep_time','<>','NULL']);
        $accountRecycleList = DB::table('ba_account_recycle')->field('is_keep,account_id,open_time,keep_time')->where($where)->order('id','asc')->select()->toArray();


        $list = array_merge($accountRecycleList,$accountList);

        $data = [];
        foreach($list as $v)
        {        
            $v['open_time'] = date('Y-m-d',$v['open_time']);
            if($v['is_keep'] == 1 && !empty($v['keep_time']))
            {
                if(empty($v['keep_time'])) $v['keep_time'] = time();
                $v['keep_time'] = date('Y-m-d',$v['keep_time']);
                $data[$v['account_id']]['keep'][] = [
                    'open_time' => $v['open_time'],
                    'keep_time' => $v['keep_time']
                ];
            }             
        }

        $accountRecycleList = [];
        foreach ($list as $key => $item) {
            $item['open_time'] = date('Y-m-d',$item['open_time']);
            $accountRecycleList[$item['account_id']][] = $item;
        }

        foreach($accountRecycleList as $key => &$value){
            foreach ($value as $key2 => &$value2) {  
                $value2['strat_open_time'] = $value2['open_time'];
                $value2['end_open_time'] = '';
                if(isset($value[$key2+1])) $value2['end_open_time'] = $value[$key2+1]['open_time'];
                else $value2['end_open_time'] = date('Y-m-d',time());

                $data[$value2['account_id']]['open'][] = [
                    'strat_open_time' => $value2['strat_open_time'],
                    'end_open_time' => $value2['end_open_time']
                ];
            }
        }
        return $data;
    }

    public function getExport3Progress()
    {
        $progress = Cache::store('redis')->get('export3_consumpotion'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }
}