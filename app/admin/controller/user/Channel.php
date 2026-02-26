<?php

namespace app\admin\controller\user;

use Throwable;
use ba\Random;
use think\facade\Db;
use app\admin\model\Admin as AdminModel;
use app\common\controller\Backend;

class Channel extends Backend
{
    /**
     * @var object
     * @phpstan-var UserGroup
     */
    protected object $model;

    // 排除字段
    protected string|array $preExcludeFields = ['update_time', 'create_time'];
    
    protected array $noNeedPermission = ['index'];

    protected string|array $quickSearchField = 'name';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new AdminModel();
    }

    public function index(): void
    {

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        if ($this->request->param('select')) {
            $this->select();
        }
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $adminIds = Db::table('ba_admin_group_access')->where('group_id',5)->column('uid');
        array_push($where,['id','in',$adminIds]);
        array_push($where,['status','=',1]);

        $res = DB::table('ba_admin')
            // ->withoutField('login_failure,password,salt')
            ->field('nickname')
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate(999);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

}