<?php

namespace app\admin\controller\auth;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\user\Rate as RateModel;
use app\admin\model\Admin as AdminModel;
use app\common\controller\Backend;

class Rate extends Backend
{
    /**
     * @var object
     * @phpstan-var UserGroup
     */
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index','add','edit'];

    protected string|array $quickSearchField = 'name';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new RateModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }
        $this->dataLimit = false;
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
        ->field('*')
        ->withJoin($this->withJoinTable, $this->withJoinType)
        ->alias($alias)
        ->where($where)
        ->order($order)
        ->paginate($limit);

        $dataList = $res->toArray()['data'];
        $companyArr =  DB::table('ba_company')->column('company_name','id');
        if($dataList){
            foreach($dataList as &$v){
                $v['company_name'] = $companyArr[$v['company_id']]??'';
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    /**
     * 添加公司费率
     * @throws Throwable
    */
    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            if (empty($data['company_id'])) $this->error("请选择公司!");
            if (empty($data['rate'])) $this->error("请输入费率!");
            DB::startTrans();
            try {
                //费率处理
                if(isset($data['rate'])&&!empty($data['rate']))
                {
                    $inserData  = ['create_time'=>date('Y-m-d',time()),'company_id'=>$data['company_id'],'rate'=>$data['rate']];
                    $result = DB::table('ba_rate')->insert($inserData);
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
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
     * 编辑公司费率
     * @throws Throwable
    */
    public function edit($id =null): void
    {
        $this->error("请联系管理员!");
        if($this->request->isGet())$info = $this->request->get();
        if($this->request->isPost())$info = $this->request->post();
        $row = DB::table('ba_rate')->where('id',$id)->find();
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) 
        {
            $data = $this->request->post();
            DB::startTrans();
            try {
                //费率处理
                if(isset($data['rate'])&&!empty($data['rate']))
                {
                    // $rateResult = DB::table('ba_rate')->where('company_id',$data['company_id'])->order('create_time desc')->find();
                    if(!empty($data) && $data['create_time'] == date('Y-m-d',time()))
                    {
                        $result = DB::table('ba_rate')->where(['id'=>$id])->update(['rate'=>$data['rate']]);
                    }else{
                        throw new \Exception("请添加一条新的费率记录！！");
                    }
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
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
}