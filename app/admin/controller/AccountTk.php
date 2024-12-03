<?php

namespace app\admin\controller;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户列管理
 */
class AccountTk extends Backend
{
    /**
     * AccountTk模型对象
     * @var object
     * @phpstan-var \app\admin\model\AccountTk
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = [];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = [];

    //protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\AccountTk();
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
                // if ($this->modelValidate) {
                //     $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                //     if (class_exists($validate)) {
                //         $validate = new $validate();
                //         if ($this->modelSceneValidate) $validate->scene('add');
                //         $validate->check($data);
                //     }
                // }
                if(empty($data['money']) || $data['money'] < 100) throw new \Exception("首充不能小于100");
                $data['admin'] = $this->auth->id;
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


    public function audit()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $accountId = $data['account_id']??0;
                $status = $data['status'];

                $whereStatus = $status == 1 ?0:($status == 2?0:($status == 3?1:($status == 4?3:($status == 5?3:666))));
                $ids = $this->model->whereIn('id',$ids)->where('status',$whereStatus)->select()->toArray();
                
                switch ($status) {
                    case '1':
                        $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                        break;
                    case '2':
                        $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                        break;
                    case '3':
                        $accountrequestProposal = DB::table('ba_accounttkrequest_proposal')->where('account_id',$accountId)->where('status',0)->find();
                        if(empty($accountrequestProposal)) throw new \Exception("未找到账户!");
                        
                        foreach($ids as $v){
                            $data = [
                                'status'=>3,
                                'account_id'=>$accountId,
                                //'is_'=>1,
                                'update_time'=>time(),
                                'operate_admin_id'=>$this->auth->id
                            ];
                            if(!empty($accountrequestProposal['country'])) $data['country'] = $accountrequestProposal['country'];
                            $this->model->where('id',$v['id'])->update($data);
                            DB::table('ba_accounttkrequest_proposal')->where('account_id',$accountId)->update(['status'=>1,'affiliation_admin_id'=>$v['admin_id'],'update_time'=>time()]);

                            if(!empty($v['bc_url'])){
                                DB::table('ba_bc_tk')->insert(['admin_id'=>$v['admin_id'],'account_name'=>$v['name'],'account_id'=>$accountId,'bc'=>$v['bc_url'],'status'=>0,'create_time'=>time()]);
                            }
                        }
                        break;
                    case '4':
                        foreach($ids as $v){
                            $this->model->whereIn('id',$v['id'])->update(['open_money'=>$v['money']]);
                        }
                        $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>4,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                        break;
                    case '5':
                        $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>5,'money'=>0,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                        break;
                    default:
                        throw new \Exception("Wrong Params!");
                }
                $result = true;
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
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}