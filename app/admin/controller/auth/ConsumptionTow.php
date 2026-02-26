<?php

namespace app\admin\controller\auth;


use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\fb\ConsumptionTrusteeshipModel;
use think\facade\Cache;


class ConsumptionTow extends Backend
{
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new ConsumptionTrusteeshipModel();
    }

    public function index(): void 
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $start = date('Y-m-d', strtotime('-1 month'));  

        $res   = Db::table('ba_account_consumption_trusteeship')
        ->field("date_start,sum(dollar) dollar")
        ->where('date_start','>', $start)
        ->group('date_start')
        ->order('date_start desc')
        ->paginate(15);//->select()->toArray();

        $dataList = [];
        if($res) {
            foreach($res->toArray()['data'] ?? [] as $v)
            {   
                $dataList[] = [
                    'date_start' => $v['date_start'],
                    'dollar' => number_format($v['dollar'], 2)
                ];
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function Export()
    {
        set_time_limit(600);
        $where = [];
        $create_time = $this->request->get('create_time');
        $end_time    = $this->request->get('end_time');
        $isCount    = $this->request->get('is_count');
        $isCount    = $isCount??2;
        if(empty($create_time)) $this->error('请选择开始时间');
        if(empty($end_time)) $this->error('请选择结束时间');

        $query = $this->model
        ->alias('account_consumption')
        // ->field('consumption_trusteeship.*,accountrequest_proposal.bm,accountrequest_proposal.admin_id,accountrequest_proposal.affiliation_bm,accountrequest_proposal.trusteeship_user,accountrequest_proposal.trusteeship_type')
        ->leftJoin('ba_accountrequest_proposal_trusteeship accountrequest_proposal','accountrequest_proposal.account_id=account_consumption.account_id')
        ->leftJoin('ba_account account','account.account_id=account_consumption.account_id')
        ->order('account_consumption.create_time','desc')
        ->where('account_consumption.date_start', '>=', $create_time)
        ->where('account_consumption.date_start', '<=', $end_time);
        $total = $query->count(); 
        if($total <= 0) $this->error('没有可导出的数据！');
        $statusList = config('basics.ACCOUNT_STATUS');
       
        if($isCount == 1)
        {
            // $query->field('consumption_trusteeship.*,accountrequest_proposal.bm,accountrequest_proposal.admin_id,accountrequest_proposal.affiliation_bm,accountrequest_proposal.trusteeship_user,accountrequest_proposal.trusteeship_type');
            $query->field('account_consumption.trusteeship,account.status open_account_status,account.open_time account_open_time,accountrequest_proposal.admin_id admin_channel,accountrequest_proposal.account_status,accountrequest_proposal.currency,accountrequest_proposal.status,accountrequest_proposal.trusteeship_type,accountrequest_proposal.serial_name,min(account_consumption.date_start) date_start,max(account_consumption.date_stop) date_stop,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,sum(account_consumption.spend) as spend,accountrequest_proposal.time_zone');
            $query->group('account_consumption.trusteeship,account_consumption.account_id');
        }else{
            $query->field('account_consumption.trusteeship,account.status open_account_status,account.open_time account_open_time,accountrequest_proposal.admin_id admin_channel,accountrequest_proposal.account_status,accountrequest_proposal.currency,accountrequest_proposal.status
            ,accountrequest_proposal.trusteeship_type,accountrequest_proposal.serial_name,account_consumption.spend,account_consumption.date_start,account_consumption.date_stop
            ,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.affiliation_bm,accountrequest_proposal.time_zone');        
        }

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $adminList = array_column($resultAdmin,'nickname','id');

        $statusValue = ["1"=>"托管中","2"=>"已停止"];

        // $cardsList = DB::table('ba_account_card')->select()->toArray();

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        // $header = [
        //     'id',
        //     'account_id',
        //     'dollar',
        //     'bm',
        //     'affiliation_bm',
        //     'admin_id',
        //     'trusteeship_type',
        //     'trusteeship',
        //     'date_start',
        // ];
        $header = [
            '托管状态',
            '账户状态',
            '账户ID',
            '货币',
            '消耗',
            '开始时间',
            '结束时间'
            // '时区',
        ];

        // for($i = 0; $i < $maxCount; $i++){
        //     $header[] = 'card_no'.($i+1);
        // }

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        
        $data = $query->select()->toArray();
        $dataList=[];
        $excelData = [];
        $accountStatus = [0=>'0',1=>'Active',2=>'Disabled',3=>'Need to pay'];
        foreach($data as $k => $v) {
               $adminChannel = $adminList[$v['admin_channel']]??'';
                $excelData  = [
                    // 'id'=>$v['id'],
                    // 'account_id'=>$v['account_id'],
                    // 'dollar'=>$v['dollar'],
                    // 'bm'=>$v['bm'],
                    // 'affiliation_bm'=>$v['affiliation_bm'],
                    // 'admin_id'=> $adminList[$v['admin_id']]??'',
                    // 'trusteeship_type'=> $statusValue[$v['trusteeship_type']]??'未知的状态',
                    // 'trusteeship'=>$v['trusteeship'],
                    // 'date_start'=>$v['date_start'],
                    $statusValue[$v['trusteeship_type']]??'未知的状态',
                    $accountStatus[$v['account_status']]??'未找到状态',
                    // $v['bm']??'',                    
                    $v['account_id'],
                    $v['currency'],
                    (float)$v['spend']??0,
                    $v['date_start'],
                    $v['date_stop']
                    // $v['time_zone'],
                ];
                $dataList[] = $excelData ;  
                // $processedCount++;

            // $progress = min(100, ceil($processedCount / $total * 100));
            // Cache::store('redis')->set('export_progress1', $progress, 300);
            // 刷新缓冲区
            //ob_flush();
            //flush();
        }   
        $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
        ->header($header)
        ->data($dataList);
        $excel->output();
        // Cache::store('redis')->delete('export_progress1');

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);        
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_progress1', 0); // 获取进度
        return $this->success('',['progress' => $progress]);
    }
}