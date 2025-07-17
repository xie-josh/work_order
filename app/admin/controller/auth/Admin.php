<?php

namespace app\admin\controller\auth;


use Throwable;
use think\facade\Db;
use app\common\controller\Backend;
use ba\Random;
use app\admin\model\Admin as AdminModel;

class Admin extends Backend
{
    /**
     * 模型
     * @var object
     * @phpstan-var AdminModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['create_time', 'update_time', 'password', 'salt', 'login_failure', 'last_login_time', 'last_login_ip'];

    protected array|string $quickSearchField = ['username', 'nickname'];
    protected array $noNeedPermission = ['index','channel','editPE'];

    /**
     * 开启数据限制
     */
    protected string|int|bool $dataLimit = 'allAuthAndOthers';

    protected string $dataLimitField = 'id';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AdminModel();
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
        // if($this->request->param('type') == 1){
        //     $adminIds = Db::table('ba_admin_group_access')->where('group_id',5)->column('uid');
        //     array_push($where,['id','in',$adminIds]);
        // }
        
        $res = $this->model
            ->field('*,ROUND((money - used_money),2) usableMoney')
            ->withoutField('login_failure,password,salt')
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


    public function channel(): void
    {

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        if ($this->request->param('select')) {
            $this->select();
        }

        $accountType = $this->request->get('account_type');

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $adminIds = Db::table('ba_admin_group_access')->where('group_id',5)->column('uid');
        array_push($where,['id','in',$adminIds]);
        if(!empty($accountType)){
            // $adminList = DB::table('ba_association_account_type')->whereIn('account_type_id',$accountType)->column('admin_id');
            // array_push($where,['id','in',$adminList]);
        }

        $res = $this->model
            ->withoutField('login_failure,password,salt')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate(999);

        $result = $res->toArray();
        $dataList = [];
        if(!empty($result['data'])) {
            $dataList = $result['data'];

            $proposal = Db::table('ba_accountrequest_proposal')->whereIn('status',config('basics.FH_status'))->field('count(*) countNumber,admin_id')->group('admin_id')->select()->toArray();
            $proposalList = array_combine(array_column($proposal,'admin_id'),array_column($proposal,'countNumber'));

            foreach($dataList as &$v){
                $v['username'] = $v['nickname'].'('.($proposalList[$v['id']]??0).')';
            }
        }

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

            /**
             * 由于有密码字段-对方法进行重写
             * 数据验证
             */
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('add')->check($data);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }

            $salt   = Random::build('alnum', 16);
            $passwd = encrypt_password($data['password'], $salt);

            $data   = $this->excludeFields($data);
            $result = false;
            if ($data['group_arr']) $this->checkGroupAuth($data['group_arr']);
            $this->model->startTrans();
            try {
                $data['salt']     = $salt;
                $data['password'] = $passwd;
                $result           = $this->model->save($data);
                if ($data['group_arr']) {
                    $groupAccess = [];
                    foreach ($data['group_arr'] as $datum) {
                        $groupAccess[] = [
                            'uid'      => $this->model->id,
                            'group_id' => $datum,
                        ];
                    }
                    Db::name('admin_group_access')->insertAll($groupAccess);
                }
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

            /**
             * 由于有密码字段-对方法进行重写
             * 数据验证
             */
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate();
                    $validate->scene('edit')->check($data);
                } catch (Throwable $e) {
                    $this->error($e->getMessage());
                }
            }


            $openAccountNumber = $this->openAccountNumber();


            if(isset($data['account_number'])) if($data['account_number'] < $openAccountNumber) $this->error('调整后的数量不能小于已经使用的数量！');


            if ($this->auth->id == $data['id'] && $data['status'] == '0') {
                $this->error(__('Please use another administrator account to disable the current account!'));
            }

            if (isset($data['password']) && $data['password']) {
                $this->model->resetPassword($data['id'], $data['password']);
            }

            $groupAccess = [];
            if ($data['group_arr']) {
                $checkGroups = [];
                foreach ($data['group_arr'] as $datum) {
                    if (!in_array($datum, $row->group_arr)) {
                        $checkGroups[] = $datum;
                    }
                    $groupAccess[] = [
                        'uid'      => $id,
                        'group_id' => $datum,
                    ];
                }
                $this->checkGroupAuth($checkGroups);
            }

            Db::name('admin_group_access')
                ->where('uid', $id)
                ->delete();

            $data   = $this->excludeFields($data);
            $result = false;
            $this->model->startTrans();
            try {
                $result = $row->save($data);
                if ($groupAccess) Db::name('admin_group_access')->insertAll($groupAccess);
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

        unset($row['salt'], $row['login_failure']);
        $row['password'] = '';
        $this->success('', [
            'row' => $row
        ]);
    }

    public function editInName(): void
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

    public function openAccountNumber()
    {
        $time = date('Y-m-d',time());
        $openAccountNumber = Db::table('ba_account')->where('admin_id',$this->auth->id)->whereDay('create_time',$time)->count();
        return $openAccountNumber;
    }

    /**
     * 删除
     * @param null $ids
     * @throws Throwable
     */
    public function del($ids = null): void
    {
        $this->error('功能暂停使用，请联系管理员！');
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
                if ($v->id != $this->auth->id) {
                    $count += $v->delete();
                    Db::name('admin_group_access')
                        ->where('uid', $v['id'])
                        ->delete();
                }
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

    /**
     * 检查分组权限
     * @throws Throwable
     */
    public function checkGroupAuth(array $groups): void
    {
        if ($this->auth->isSuperAdmin()) {
            return;
        }
        $authGroups = $this->auth->getAllAuthGroups('allAuthAndOthers');
        foreach ($groups as $group) {
            if (!in_array($group, $authGroups)) {
                $this->error(__('You have no permission to add an administrator to this group!'));
            }
        }
    }

    /**
     * 修改密码和邮箱
     */
    public function editPE()
    {
         $info = $this->request->post();
         if(empty($info)) $this->error("更新参数不能为空!");
         if(isset($info['password'])) $password = $info['password'];
         if(isset($info['email'])) $email = $info['email'];
         $userId =  $this->auth->id;
         $upArr = [];
         if(!empty($password)){
            if (!preg_match('/^(?!.*[&<>"\'])[\w\W]{6,32}$/',  $password)){
                $this->error('密码要求6到32位，不能包含 & < > "'."'");
             }
             $upArr['salt'] = Random::build('alnum', 16);
             $upArr['password'] = encrypt_password($password, $upArr['salt']);
         }
         if(!empty($email)){
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                $this->error('输入的邮箱格式不正确！');
            }
            $upArr['email'] = $email;
        }
        $result =   Db::table('ba_admin')->where(['id' => $userId])->update($upArr);
        if ($result !== false) {
            if(!empty($upArr['password'])){
                Db::table('ba_token')->where('user_id',$this->auth->id)->delete(); //修改密码重新登录
            }
            $this->success(__('Update successful'));
        } else {
            $this->error(__('No rows updated'));
        }
    }

}