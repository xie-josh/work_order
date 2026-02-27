<?php

namespace app\admin\controller\user;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\common\controller\Backend;
use think\facade\Cache;

class Recycle extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index','manageExport','getManageExportProgress'];

    protected string|array $quickSearchField = 'name';

    // protected bool|string|int $dataLimit = false;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\Account();
    }

    /**
     * 回收列表
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        foreach($where as $k => $v){
            if($v[0] == 'account.account_id'){
                array_push($where,['account_id','IN',$v[2]]);
                unset($where[$k]);
                continue;
            }
        }

        if($this->auth->type == 4) $this->success('', [
            'list'   => [],
            'total'  => 0,
            'remark' => get_route_remark(),
        ]);
        array_push($where,['company_id','=',$this->auth->company_id]);
        $res = DB::table('ba_account_recycle')
            ->field('name,account_id,currency,total_consumption,account_recycle_time,total_up,total_delete,total_deductions')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
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

        if($this->auth->type == 4) $this->success('', [
            'list'   => [],
            'total'  => 0,
            'remark' => get_route_remark(),
        ]);        

        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        
        foreach($where as $k => $v){
            if($v[0] == 'account.account_id'){
                array_push($where,['account_id','IN',$v[2]]);
                unset($where[$k]);
                continue;
            }
        }
        array_push($where,['company_id','=',$this->auth->company_id]);

        $query = DB::table('ba_account_recycle')
            ->field('name,account_id,currency,total_consumption,account_recycle_time,total_up,total_delete,total_deductions')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order);

        $query2 = clone $query;
        $total = $query2->count(); 

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '账户名称',
            '账号ID',            
            '币种',
            '总消耗',
            '总充值',
            '总清零',
            '总扣款',
            '回收时间'
        ];
        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();
            if(!empty($data)) {
                $dataList = $data;
                $List = [];

                foreach($dataList as &$v){
                    $List[] = [
                        $v['name'],
                        $v['account_id'],
                        $v['currency'],
                        $v['total_consumption'],
                        $v['total_up'],
                        $v['total_delete'],
                        $v['total_deductions'],
                        $v['account_recycle_time']
                    ];
                    $processedCount++;
                }

                $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
                ->header($header)
                ->data($List);
                $progress = min(100, ceil($processedCount / $total * 100));
                Cache::store('redis')->set('export_manage_'.$this->auth->id, $progress, 300);
            }
        }

        $excel->output();
        Cache::store('redis')->delete('export_manage');

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);  
    }

    public function getManageExportProgress()
    {
        $progress = Cache::store('redis')->get('export_manage_'.$this->auth->id, 0); // 获取进度
        return $this->success('',['progress' => $progress]);
    }

}