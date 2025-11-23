<?php

namespace app\admin\controller\auth;

use ba\Random;
use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use app\admin\model\AuditException as AuditExceptionModel;

class AuditException extends Backend
{

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AuditExceptionModel();
    }
     /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->field('*')
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    //审核
    public function audit()
    {
        $data = $this->request->post();
        $ids    = $data['ids']??[];
        $status = $data['status']??1;
        if(!empty($ids) && $status && in_array($status,[1,2])){
            $result =  $this->model->whereIn('id',$ids)->update(['status'=>$status]);
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }else{
            throw new \Exception("请选择要操作的数据,并且选择审核状态!");
        }
    }




}