<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;


class AccountReturn extends Backend
{
    /**
     * AccountrequestProposal模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\BcTkModel
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['account'];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = [];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountReturnModel();
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

        $dataList = $res->toArray()['data'];
        if($dataList){
            $admin = DB::table('ba_admin')->field('id,nickname')->select()->toArray();
            $adminList = array_column($admin, 'nickname', 'id');
            foreach ($dataList as $key => &$value) {
                $value['account']['nickname'] = '';
                if(isset($adminList[$value['account']['admin_id']])) {
                    $nickname = $adminList[$value['account']['admin_id']];
                    unset($dataList[$key]['account']);
                    $value['account']['nickname'] = $nickname;
                }
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function audit(): void
    {
        
        if($this->request->isPost()){
            $data = $this->request->post();
            if(empty($data['ids']) || empty($data['status'])) $this->error('参数错误！');
            
            $resutn = $this->model->whereIn('id',$data['ids'])->update(['status'=>$data['status']]);

            if($resutn){
                $this->success('操作成功！');
            }else{
                $this->error('操作失败！');
            }
        }
        $this->error('操作失败！');
    }


    public function edit(): void
    {
        $this->error('功能未开放！');
    }

    public function del(array $ids = []): void
    
    {
        $this->error('功能未开放！');
    }



}