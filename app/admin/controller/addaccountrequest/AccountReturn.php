<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;


class AccountReturn extends Backend
{
    /**
     * AccountrequestProposal模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\AccountReturnModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['account','accountrequestProposal'];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = ['index','audit','export'];

    //protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountReturnModel();
    }


    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $dataList = $res->toArray()['data'];
        if($dataList){
            $admin = DB::table('ba_admin')->field('id,nickname')->select()->toArray();
            $adminList = array_column($admin, 'nickname', 'id');
            foreach ($dataList as $key => &$value) {
                $value['account']['nickname'] = '';
                if(isset($adminList[$value['account']['admin_id']??0])) {
                    $nickname = $adminList[$value['account']['admin_id']];
                    $bm = $value['accountrequestProposal']['bm'];
                    $status = $value['accountrequestProposal']['status'];
                    $spendCap = $value['accountrequestProposal']['spend_cap'] == 0.01?0:$value['accountrequestProposal']['spend_cap'];  
                    $amountSpent = $value['accountrequestProposal']['amount_spent'];
                    $balance = bcsub((string)$spendCap,(string)$amountSpent,'2');  
                    unset($dataList[$key]['account']);
                    unset($dataList[$key]['accountrequestProposal']);
                    $value['account']['nickname'] = $nickname;
                    $value['accountrequestProposal']['bm'] = $bm;
                    $value['accountrequestProposal']['status'] = $status;
                    $value['accountrequestProposal']['balance'] = $balance??0;
                }
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function audit(): void
    {
        
        if($this->request->isPost()){
            $data = $this->request->post();
            if(empty($data['ids']) || empty($data['status'])) $this->error('参数错误！');
            
            $resutn = $this->model->whereIn('id',$data['ids'])->update(['status'=>$data['status'],'update_time'=>time()]);

            if($resutn){
                $this->success('操作成功！');
            }else{
                $this->error('操作失败！');
            }
        }
        $this->error('操作失败！');
    }


    public function edit(): void
    {
        $this->error('功能未开放！');
    }

    public function del(array $ids = []): void
    
    {
        $this->error('功能未开放！');
    }


    public function export()
    {
        $where = [];
        set_time_limit(300);
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_progress'.'_'.$this->auth->id;
        
        $query = $this->model
        ->field($this->indexField)
        ->withJoin($this->withJoinTable, $this->withJoinType)
        ->alias($alias)
        ->where($where)
        ->order("account_return_model.id",'desc');

        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->field('id,nickname')->select()->toArray();

        $adminListValue = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));
        $statusValue = config('basics.ACCOUNT_STATUS');
        $typeListValue = [1=>"封户回来活跃",2=>"封户回来待支付",3=>"丢失回来活跃",4=>"丢失回来封户",5=>"丢失回来待支付"];
        $statusListValue = [0=>"未处理",1=>"处理完成"];

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '管理BM',
            '账户ID',
            '用户名',
            '类型',
            '账户状态',
            '处理状态',
            '创建时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['accountrequestProposal']['bm'],
                    $v['account_id'],
                    empty($v['account']['admin_id']) ? '' :$adminListValue[$v['account']['admin_id']] ?? '',                    
                    $typeListValue[$v['type']] ?? '',
                    $statusValue[$v['accountrequestProposal']['status']]??'未知的状态',
                    $statusListValue[$v['status']] ?? '',
                    date('Y-m-d H:i:s', $v['create_time']),
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