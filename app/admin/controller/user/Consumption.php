<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use app\admin\model\fb\ConsumptionModel;
use app\common\controller\Backend;
use think\facade\Cache;

class Consumption extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];

    protected string|array $quickSearchField = 'name';

    protected array $noNeedPermission = ['index','export','getExportProgress'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ConsumptionModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
       
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($where,['company_id','=',$this->auth->company_id]);

        if($this->auth->type == 4) $this->success('', [
            'list'   => [],
            'total'  => 0,
            'remark' => '',
        ]);

        // sart_time
        // end_time
        $startTime = $this->request->get('start_time');
        $endTime = $this->request->get('end_time');

        if(!empty($startTime) || !empty($endTime))
        {
            // $sartTime = date('Y-m-d',strtotime('-7 days'));
            // $endTime = date('Y-m-d',time());

        }else{
            $startTime = date('Y-m-d',strtotime('-7 days'));
            $endTime = date('Y-m-d',time());
        }
        

        array_push($where,['date_start','>=',$startTime]);
        array_push($where,['date_start','<=',$endTime]);

        $res = $this->model
        ->field('ROUND(sum(dollar),2) dollar,date_start')
        ->alias($alias)
        ->where($where)
        ->order($order)
        ->group('date_start')
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);

    }
    
    function export()
    {

        $batchSize = 8000;
        $processedCount = 0;

        $data = $this->request->post();

        $startTime = $data['start_time']??'';
        $endTime = $data['end_time']??'';
        $companyId = $this->auth->company_id;
        if(empty($companyId)) $this->error('未找到对应的！');
        $redisKey = 'export_consumpotion'.'_'.$this->auth->id;

        $where = [
            ['consumption.company_id','=',$companyId],
            ['consumption.date_start','>=',$startTime],
            ['consumption.date_start','<=',$endTime],
            // ['account.status','=',4],
        ];
        $query = DB::table('ba_account_consumption')
            ->field('account.open_time account_open_time,consumption.account_id,consumption.date_start,consumption.spend,accountrequest_proposal.account_status,accountrequest_proposal.currency,accountrequest_proposal.serial_name')
            ->alias('consumption')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id = consumption.account_id')
            ->leftJoin('ba_account account','account.account_id=consumption.account_id')
            ->order('consumption.id','desc')
            ->where($where);

        $total = $query->count();


        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];

        // if($params['prepayment_type'] == 1){
        //     $prepaymentName ='预付实销';
        // }else{
        //     $prepaymentName = '预付';
        // }

        // $resultPath = "excel/".date('Ym').'/settlement'.date('d').'/'.$prepaymentName;
        // if(file_exists($resultPath)) unlink($resultPath);

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '账户状态',
            '账户名称',
            '账户ID',
            '货币',
            '消耗',
            '开始时间',
            '结束时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);
        
        $name = $folders['name'].'.xlsx';

        if($total == 0) $this->error('没有可导出的数据！');

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();
            $accountIds = array_unique(array_column($data,'account_id'));
            $accountNameList = $this->accountNameList($accountIds);
            $dataList = [];
            foreach($data as $v){
                $serialName = $v['serial_name'];
                if(isset($accountNameList[$v['account_id']]))
                {
                    $dd = $accountNameList[$v['account_id']];

                    $openTime = $v['account_open_time']??'';
                    if(!empty($openTime) && $v['date_start'] >= date("Y-m-d",$openTime))
                    {
                        $serialName = $v['serial_name'];
                    }else{
                        foreach($dd as $item2)
                        {
                            if($item2['strat_open_time'] <= $v['date_start'] &&  $item2['end_open_time'] >= $v['date_start']){
                                $serialName = $item2['name'];
                            }
                        }
                    }
                }                

                $dataList[]  = [
                    $accountStatus[$v['account_status']]??'未找到状态',
                    $serialName,
                    $v['account_id'],
                    $v['currency'],
                    (float)$v['spend'],
                    $v['date_start'],
                    $v['date_start'],
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
    public function accountNameList($accountIds)
    {
        $where = [
            ['status','=',4],
            ['account_id','IN',$accountIds]
        ];
        $accountList = DB::table('ba_account')->field('is_keep,account_id,open_time,keep_time,name')->where($where)->order('id','asc')->select()->toArray();
        array_push($where,['open_time','<>','NULL']);
        $accountRecycleList = DB::table('ba_account_recycle')->field('is_keep,account_id,open_time,keep_time,name')->where($where)->order('id','asc')->select()->toArray();


        $list = array_merge($accountRecycleList,$accountList);
        $data = [];

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

                $data[$value2['account_id']][] = [
                    'strat_open_time' => $value2['strat_open_time'],
                    'end_open_time' => $value2['end_open_time'],
                    'name'=>$value2['name']
                ];
            }
        }
        return $data;
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_consumpotion'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }
    
}