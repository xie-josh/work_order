<?php

namespace app\admin\controller\fb;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use app\admin\model\User as UserModel;

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


    // public function del(array $ids = []): void
    // {
    //     $this->error('该功能被禁用，请联系管理员！',[]);
    // }

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