<?php

namespace app\admin\controller\basics;

use Throwable;
use ba\Random;
use app\common\controller\Backend;
use app\admin\model\User as UserModel;

class RecyclingGracePeriod extends Backend
{
    /**
     * @var object
     * @phpstan-var Rates
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\basics\RecyclingGracePeriodModel();
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

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $order = ['star_time'=>'asc'];
        $res = $this->model
            ->field($this->indexField)
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

    /**
     * 添加
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                $starTime = $data['star_time'];
                $endTime = $data['end_time'];

                $list = $this->model
                ->whereOr(function($query) use($starTime){
                    $query->where([
                        ['star_time', '<=', $starTime],
                        ['end_time', '>=', $starTime]
                    ]);
                
                })->whereOr(function($query) use($endTime){
                    $query->where([
                        ['star_time', '<=', $endTime],
                        ['end_time', '>=', $endTime]
                    ]);
                
                })->whereOr(function($query) use($starTime,$endTime){
                    $query->where([
                        ['star_time', '>=', $starTime],
                        ['end_time', '<=', $endTime]
                    ]);
                
                })->find();

                if(!empty($list)) throw new \Exception("保存失败：所选时间段与已有配置 [".$list['name']."] 重叠，请检查后重新提交");
                
                $result = $this->model->save($data);
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
    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds && !in_array($row[$this->dataLimitField], $dataLimitAdminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('edit');
                        $data[$pk] = $row[$pk];
                        $validate->check($data);
                    }
                }

                $starTime = $data['star_time'];
                $endTime = $data['end_time'];

                $list = $this->model->where(
                    [
                        ['id', '<>', $id],
                    ]
                )
                ->where(function($query) use($starTime,$endTime){
                    $query->whereOr(function($query2) use($starTime){
                        $query2->where([
                            ['star_time', '<=', $starTime],
                            ['end_time', '>=', $starTime]
                        ]);
                    
                    })->whereOr(function($query2) use($endTime){
                        $query2->where([
                            ['star_time', '<=', $endTime],
                            ['end_time', '>=', $endTime]
                        ]);
                    
                    })->whereOr(function($query2) use($starTime,$endTime){
                        $query2->where([
                            ['star_time', '>=', $starTime],
                            ['end_time', '<=', $endTime]
                        ]);
                    
                    });
                
                })->find();

                if(!empty($list)) throw new \Exception("保存失败：所选时间段与已有配置 [".$list['name']."] 重叠，请检查后重新提交");

                $result = $row->save($data);
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
     * @param array $ids
     * @throws Throwable
     */
    public function del(array $ids = []): void
    {
        $ids = $this->request->param('ids');
        if (!$this->request->isDelete() || !$ids) {
            $this->error(__('Parameter error'));
        }

        $where             = [];
        $dataLimitAdminIds = $this->getDataLimitAdminIds();
        if ($dataLimitAdminIds) {
            $where[] = [$this->dataLimitField, 'in', $dataLimitAdminIds];
        }

        $pk      = $this->model->getPk();
        $where[] = [$pk, 'in', $ids];

        $count = 0;
        $data  = $this->model->where($where)->select();
        $this->model->startTrans();
        try {
            foreach ($data as $v) {
                $count += $v->delete();
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


}