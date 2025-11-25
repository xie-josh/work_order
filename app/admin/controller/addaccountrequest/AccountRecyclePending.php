<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
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
    protected array $noNeedPermission = ['index','getExportProgress','export'];

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
        foreach($where as $k => &$v){
            if($v[0] == 'account.idle_time'){                
                $v[2] = floor((int)$v[2] * 86400);
            }
            if($v[0] == 'account_recycle_pending.account_id'){                
                $v[0] = 'account.account_id';
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
        }
        $res = DB::table('ba_account')
        ->alias('account')
        ->field('account.account_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm,admin_a.nickname admin_a_nickname,account.idle_time,accountrequest_proposal.total_consumption,admin_b.nickname admin_b_nickname,accountrequest_proposal.account_status,accountrequest_proposal.time_zone,account.open_time')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin_a','admin_a.id=accountrequest_proposal.admin_id')
        ->leftJoin('ba_admin admin_b','admin_b.id=account.admin_id')
        ->where($where);
        
        if(!empty($totalConsumption)) $res = $res->whereRaw("COALESCE(accountrequest_proposal.total_consumption, 0) + 0 > $totalConsumption");

        $res = $res->paginate($limit);
        // dd($where);

        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            foreach($dataList as &$v){
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
                ];
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
        $where[] = ['account.status','=',4];
        foreach($where as $k => &$v){
            if($v[0] == 'account.idle_time'){                
                $v[2] = floor($v[2] * 86400);
            }
        }

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_progress'.'_'.$this->auth->id;
        
        $query = DB::table('ba_account')
        ->alias('account')
        ->field('account.account_id,accountrequest_proposal.serial_name,accountrequest_proposal.bm,admin_a.nickname nickname_a,account.idle_time,accountrequest_proposal.total_consumption,admin_b.nickname nickname_b,accountrequest_proposal.account_status,accountrequest_proposal.time_zone,account.open_time')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=account.account_id')
        ->leftJoin('ba_admin admin_a','admin_a.id=accountrequest_proposal.admin_id')
        ->leftJoin('ba_admin admin_b','admin_b.id=account.admin_id')
        ->where($where);
        
        $total = $query->count(); 

        $acountStatusValue = [0=>'异常',1=>'活跃',2=>'封户',3=>'待处理'];

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
            "开户时间"
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
                $dataList[] = [
                    $v['account_id'],
                    $v['serial_name'],
                    $v['bm'],
                    $v['nickname_a'],
                    floor($v['idle_time'] / 86400),
                    $v['total_consumption'],
                    $v['nickname_b'],
                    $acountStatusValue[$v['account_status']]??'未知状态',
                    $v['time_zone'],
                    $v['open_time']?date('Y-m-d H:i',$v['open_time']):'',
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

}