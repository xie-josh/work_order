<?php

namespace app\admin\controller\auth;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\auth\PlatformRate as PlatformRateModel;
use app\common\controller\Backend;

class PlatformRate extends Backend
{

    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = [];

    protected string|array $quickSearchField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new PlatformRateModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $where[] = ['pid','=',null];
        $res = $this->model
        ->field('*')
        ->withJoin($this->withJoinTable, $this->withJoinType)
        ->alias($alias)
        ->where($where)
        ->order($order)
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        if($dataList){

            $list = DB::table('ba_platform_rate')->where([['pid','<>','']])->select()->toArray();
            $childrenList = [];
            foreach($list as $v)
            {
                $childrenList[$v['pid']][] = $v;
            }

            foreach($dataList as &$v){
                $v['children'] = $childrenList[$v['id']]??[];
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function list(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
        $type = $this->request->get('type');
        $id = $this->request->get('id');
        

        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        
        if(!empty($id))
        {
            $where[] = ['pid','=',$id];
        }else{
            if($type == 1) $where[] = ['pid','=',null];
            elseif($type == 2) $where[] = ['pid','<>',''];
            else {
                $type = 3;
                $where[] = ['pid','=',null];
            }
        }
        
        $res = $this->model
        ->field('*')
        ->withJoin($this->withJoinTable, $this->withJoinType)
        ->alias($alias)
        ->where($where)
        ->order($order)
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        if($dataList){
            $list = DB::table('ba_platform_rate')->where([['pid','<>','']])->select()->toArray();

            
            $childrenList = [];
            if($type == 3)foreach($list as $v)
            {
                $childrenList[$v['pid']][] = $v;
            }
            
            foreach($dataList as &$v){
                $v['children'] = $childrenList[$v['id']]??[];
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function del(array $ids = []): void
    {
        $this->error('不可删除！');
    }

}