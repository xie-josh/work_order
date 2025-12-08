<?php

namespace app\admin\controller\user;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\user\Company as CompanyModel;
use app\admin\model\Admin as AdminModel;
use app\common\controller\Backend;

class Consumer extends Backend
{

    protected array $noNeedPermission = ['index','add','edit','addUserTeam','delUserTeam','editPE'];

    public function initialize(): void
    {
        $this->model = new CompanyModel();
        parent::initialize();
    }
    
    /**
     * 人员列表
     * @throws Throwable
     */
    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }  
        $teamType =   $this->request->get('team_type')??0;
        $teamId   =   $this->request->get('team_id')??0;
        $this->dataLimit = false;

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($where,['admin.company_id','=',$this->auth->company_id]);
        array_push($where,['admin.type','in',[2,3,4]]);
        $adminType = true;
        foreach($where as $k => $v){
            // if($v[0] == 'admin.team_id'){
            //     unset($where[$k]);
            //     // if($v[1])
            //     if($v[1] == "=")  array_push($where,['admin.team_id','exp',Db::raw('IS NULL')]);
            //     if($v[1] == "<>") array_push($where,['admin.team_id','exp',Db::raw('IS NOT NULL')]);
            // }
            if($teamType == 1 && $adminType)
            {
                if($v[0] == 'admin.type'){
                    unset($where[$k]);
                    array_push($where,['admin.team_id','exp',Db::raw('IS NULL')]);
                    array_push($where,['admin.type','in',[4]]);
                    $adminType = false;
                }
            }
        }

        if(!empty($teamId)){
            array_push($where,['admin.team_id','=',$teamId]);
        }
        $res = DB::table('ba_admin')->alias('admin')->field('admin.email,admin.username,admin.status,admin.create_time,admin.update_time,admin.type,admin.id,team.team_name')
        ->withJoin($this->withJoinTable, $this->withJoinType)
        ->leftJoin('team team','admin.team_id=team.id')
        ->where($where)
        ->order('admin.id desc')//->select()->toArray();  dd(DB::table('ba_admin')->getLastSql());
        ->paginate($limit);

        $dataList = $res->toArray()['data'];//dd($dataList);
        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

     /**
     * 添加子管理或团队人员
     * @throws Throwable
     */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if (!in_array($this->auth->type,[2,3])) $this->error("非管理员不可添加!");

            $user['username'] = $data['username']??'';
            $user['password'] = $data['password']??'';
            $user['nickname'] = $data['nickname']??$user['username'];
            $user['email']    = $data['email']??'';
            // $user['team_id']  = $data['team_id']??''; 
            //$data['type'];   3,子管理员账号，4,普通团员账号
            if (!isset($data['type']))$this->error("请选择人员角色!");
            // if($data['type']==4)if (empty($user['team_id']))$this->error("请选择团队!");
            // if($data['type']==3)unset($user['team_id']);
            unset($data['password'],$data['username']);
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            $salt   = Random::build('alnum', 16);
            $this->model->startTrans();
            try {
                $user['company_id'] = $this->auth->company_id;
                $validate = new \app\admin\validate\user\Admin;
                $validate->scene('add')->check($user);
                $user['password'] = encrypt_password($user['password'], $salt);
                $user['salt']       = $salt;
                $user['type']       = $data['type'];
                $user['create_time']= time();

                 $uid = DB::table('ba_admin')->insertGetId($user);

                if($user['type'] ==3) $groupId = 8; //子账号
                else $groupId = 9; //团员
                $groupAccess = [
                    'uid'      => $uid,
                    'group_id' => $groupId,
                ];
                $result = Db::name('admin_group_access')->insert($groupAccess);

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
     * 编辑子管理或团队人员
     * @throws Throwable
     */
    public function edit(): void
    {
        if($this->request->isGet()) $info = $this->request->get();
        if($this->request->isPost())$info = $this->request->post();
        $admin = new AdminModel();
        $row = $admin->find($info['id']);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        
        if($row['type'] == 2 )  $this->error("主管理员不可编辑!");
        if ($this->request->isPost()) {    
            if (!in_array($this->auth->type,[2,3])) $this->error("非管理员不可编辑!");
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            if (isset($data['password']) && $data['password']) {
                $admin->resetPassword($data['id'], $data['password']); unset($data['password']);
            }
            $result = false;
            $this->model->startTrans();
            try {
                $validate = new \app\admin\validate\user\Admin;
                $validate->scene('edit')->check($data);
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

        unset($row['last_login_time']);
        unset($row['password']);
        unset($row['salt']);
        $this->success('', [
            'row' => $row
        ]);
    }

    /**
     * 添加子管理或团队人员
     * @throws Throwable
     */
    public function addUserTeam()
    {
        if ($this->request->isPost()) 
        {
            $data = $this->request->post();
            if(empty($data['admin_ids'])||empty($data['team_id']))
            {
               $this->error(__('Parameter %s can not be empty', ['']));
            }
            // if (!in_array($this->auth->type,[3])) $this->error("非管理员不可添加!");
            $ids = $data['admin_ids'];
            $this->model->startTrans();
            $res = false;
            try {
                foreach($ids as $v)
                {
                    $arr['id']   = $v;
                    $arr['type'] =  4;
                    $result =  Db::name('admin')->whereNull('team_id')->where($arr)->select()->toArray();
                    if(empty($result)){
                        continue;
                    }else{
                        $res = Db::name('admin')->where(['id'=>$v,'type'=>4])->update(['team_id'=>$data['team_id']]);
                    }
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }

            if ($res !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }

    /**
     * 从团队移除人员
     * @throws Throwable
     */
    public function delUserTeam()
    {
        if ($this->request->isPost()) 
        {
            $data = $this->request->post();
            if(empty($data['admin_ids'])||empty($data['team_id']))
            {
               $this->error(__('Parameter %s can not be empty', ['']));
            }
            $ids = $data['admin_ids'];
            $this->model->startTrans();
            $res = false;
            try {
                foreach($ids as $v)
                {
                    $arr['id'] = $v;
                    $arr['type'] = 4;
                    $result =  Db::name('admin')->whereNotNull('team_id')->where($arr)->find();
                    if(empty($result)){
                        continue;
                    }else{
                        $res = Db::name('admin')->where(['id'=>$v,'type'=>4])->update(['team_id'=> Db::raw('NULL')]);
                    }
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }

            if ($res !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
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