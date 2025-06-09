<?php

namespace app\admin\controller\wallet;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;

/**
 * 入账申请
 */
class AccountApplication extends Backend
{
    /**
     * AccountApplication模型对象
     * @var object
     * @phpstan-var \app\admin\model\wallet\AccountApplication
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'admin_id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin','type'];

    protected string|array $quickSearchField = ['id'];
    protected array $noNeedPermission = ['export','getExportRecharge'];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\wallet\AccountApplication();
    }

    /**
     * 查看
     * @throws Throwable
     */
    public function index(): void
    {
        // 如果是 select 则转发到 select 方法，若未重写该方法，其实还是继续执行 index
        if ($this->request->param('select')) {
            $this->select();
        }

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username'],'type' => ['name']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

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

                //if(!empty($data['images'])) $data['status'] = 3;

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

    public function edit(): void
    {
        //$this->error('不可编辑！');
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        if($row['status'] != 0 && !$this->auth->isSuperAdmin()) $this->error('该状态不可编辑！');

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
                if(empty($data['images']))  throw new \Exception("请上传凭证！");
                //$list = ['images'=>$data['images'],'type_id'=>$data['type_id'],'status'=>3];

                $result = $row->save(['images'=>$data['images'],'type_id'=>$data['type_id']]);
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

    public function del(array $ids = []): void
    {
        $this->error('功能暂停使用，请联系管理员！');
        // $this->error('不可删除！');
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
        $where[] = ['status', '=', 0];

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
     public function audit()
    {
        if(!$this->auth->isSuperAdmin())    $this->error('无权限！');
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->where([['status','<>',1]])->find($id);
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

                //if($data['status'] == 1 && !in_array($row['status'],[0,2,3]))  throw new \Exception("请上传凭证！");
                //if($data['status'] === 0)  throw new \Exception("状态选择错误，不可以选择待处理");

                if($data['status'] == 1){
                    $money = Db::table('ba_admin')->where('id',$row->admin_id)->value('money');
                    $money = bcadd((string)$money,(string)$row['amount'],2);
                    Db::table('ba_admin')->where('id',$row->admin_id)->update(['money'=>$money]);
                }
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


    public function export()
    {
        $where = [];
        set_time_limit(300);

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'wallet_account_application_'.$this->auth->id;
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $query = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order('account_application.id desc');
        

        $total = $query->count();

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '提交时间',
            '入账金额',
            '付款方式',
            '状态',            
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $statusList = ['0'=>'待入账','1'=>'已入账','2'=>'已取消'];


        $name = $folders['name'].'.xlsx';
        $excel->fileName($folders['name'].'.xlsx', 'sheet1');

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['create_time']?date('Y-m-d H:i',$v['create_time']):'',
                    $v['amount'],
                    $v['type']['name']??'',
                    $statusList[$v['status']]??'',
                ];  
                $processedCount++;
            }
            $excel->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }

    public function getExportRecharge()
    {
        $progress = Cache::store('redis')->get('wallet_account_application_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}