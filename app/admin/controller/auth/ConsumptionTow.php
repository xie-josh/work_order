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
        if(empty($create_time)) $this->error('请选择开始时间');
        if(empty($end_time)) $this->error('请选择结束时间');

        $query = $this->model
        ->alias('consumption_trusteeship')
        ->field('consumption_trusteeship.*,accountrequest_proposal.bm,accountrequest_proposal.admin_id,accountrequest_proposal.affiliation_bm,accountrequest_proposal.trusteeship_user,accountrequest_proposal.trusteeship_type')
        ->leftJoin('ba_accountrequest_proposal_trusteeship accountrequest_proposal','accountrequest_proposal.account_id=consumption_trusteeship.account_id')
        ->order('consumption_trusteeship.create_time','desc')
        ->where('consumption_trusteeship.date_start', '>=', $create_time)
        ->where('consumption_trusteeship.date_start', '<=', $end_time);
        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $adminList = array_column($resultAdmin,'nickname','id');

        $statusValue = ["1"=>"托管中","2"=>"已停止"];

        // $cardsList = DB::table('ba_account_card')->select()->toArray();

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'id',
            'account_id',
            'dollar',
            'bm',
            'affiliation_bm',
            'admin_id',
            'trusteeship_type',
            'trusteeship',
            'date_start',
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
        foreach($data as $k => $v) {
                $excelData  = [
                    'id'=>$v['id'],
                    'account_id'=>$v['account_id'],
                    'dollar'=>$v['dollar'],
                    'bm'=>$v['bm'],
                    'affiliation_bm'=>$v['affiliation_bm'],
                    'admin_id'=> $adminList[$v['admin_id']]??'',
                    'trusteeship_type'=> $statusValue[$v['trusteeship_type']]??'未知的状态',
                    'trusteeship'=>$v['trusteeship'],
                    'date_start'=>$v['date_start'],
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