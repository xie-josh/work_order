<?php

namespace app\admin\controller\user;

use Throwable;
use think\facade\Db;
use app\admin\model\user\Team as TeamModel;
use app\common\controller\Backend;

class Team extends Backend
{
    /**
     * @var object
     * @phpstan-var UserGroup
     */
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];

    protected string|array $quickSearchField = 'name';

    protected array $noNeedPermission = ['index','add','edit','getTemaLog'];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new TeamModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
       
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($where,['company_id','=',$this->auth->company_id]);

        $res = $this->model
        ->withJoin($this->withJoinTable, $this->withJoinType)
        ->alias($alias)
        ->where($where)
        ->order($order)
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        // if($dataList){
        //     foreach($dataList as &$v){
        //         $v['userNick'] = $v['nickname'].'('.($v['username']??'').')';
        //     }
        // }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);

    }

    /**
     * 添加
     * @throws Throwable
    */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $data['company_id'] = $this->auth->company_id;
                    $validate->scene('add')->check($data);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }

            $this->model->startTrans();
            try {
                $result             = $this->model->save($data);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }

        }
        $this->error(__('Parameter error'));
    }

     /**
     * 编辑
     * @throws Throwable
     */
    public function edit($id = null): void
    {
        if($this->request->isGet()) $info = $this->request->get();
        if($this->request->isPost())$info = $this->request->post();
        $row = $this->model->find($info['id']);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            /**
             * 由于有密码字段-对方法进行重写
             * 数据验证
             */
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $data['company_id'] = $this->auth->company_id;
                    $validate->scene('edit')->check($data);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }
            if($data['team_money']<$row['team_used_money']){
                $this->error("注意：团队总额度不能小于已使用金额！");
            }
            $result = false;
            $this->model->startTrans();
            try {
                if($data['team_money']!=$row['team_money'])
                {
                    $teamLog['team_id']      = $row['id'];   
                    $teamLog['team_name']    = $row['team_name'];   
                    $teamLog['company_id']   = $this->auth->company_id; 
                    $teamLog['change_money'] = $data['team_money'];
                    $teamLog['old_money']    = $row['team_money'];
                    $teamLog['admin_name']   = $this->auth->username;
                    $teamLog['admin_id']     = $this->auth->id;
                    $teamLog['create_time']  = time();
                    DB::table('ba_team_change_log')->insert($teamLog);
                }
                $result = $row->save($data);
                // if ($groupAccess) Db::name('admin_group_access')->insertAll($groupAccess);
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $this->success('', [
            'row' => $row
        ]);
    }


        /**
     * 删除
     * @param null $ids
     * @throws Throwable
     */
    public function del($ids = null): void
    {
        if (!$this->request->isDelete() || !$ids) {
            $this->error(__('Parameter error'));
        }

        $where   = [];
        $pk      = $this->model->getPk();
        $where[] = [$pk, 'in', $ids];

        $count = 0;
        $data  = $this->model->where($where)->select();
        $this->model->startTrans();
        try {
            foreach ($data as $v) 
            {
                    $find = Db::name('admin')->where('team_id', $v->id)->find();
                    if(empty($find)) $count = Db::name('team')->where('id', $v->id)->delete();
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success(__('Deleted successfully'));
        } else {
            $this->error(__('No rows were deleted'));
        }
    }

    public function getTemaLog($id = null)
    {
        $data['team_id'] = $id;
        $data['company_id'] = $this->auth->company_id; 
        $row = DB::table('ba_team_change_log')->field('change_money,old_money,change_money,admin_name,create_time')->where($data)->select()->order('create_time desc')->toArray();
        foreach($row as $k => &$v){
            $v['create_time'] = date('Y-m-d H:i',$v['create_time']);
        }
        $this->success('',$row);
    }
}