<?php

namespace app\admin\controller\fb;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use app\admin\model\User as UserModel;
use think\facade\Db;

class PersonalBmToken extends Backend
{
    /**
     * @var object
     * @phpstan-var PersonalBmToken
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\fb\PersonalBmTokenModel();
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

        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];
            foreach($dataList as &$v)
            {
                $sum = DB::table('ba_fb_bm_token')->where('personalbm_token_ids',$v['id'])->where('pull_status',1)->count();
                $v['bm_token_total'] = $sum;
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function getList()
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field('id,name')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate(1000);        

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
    
    public function updateStatus()
    {
        $data = $this->request->param();
        $ids = $data['ids']??[];
        $status = $data['status']??0;
        $result = $this->model->whereIn('id',$ids)->update(['status'=>$status]);
        if ($result !== false) {
            $this->success(__('Update successful'));
        } else {
            $this->error(__('No rows updated'));
        }
    }    

}