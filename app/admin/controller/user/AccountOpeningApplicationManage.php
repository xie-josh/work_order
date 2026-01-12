<?php

namespace app\admin\controller\user;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;

/**
 * 账户列管理
 */
class AccountOpeningApplicationManage extends Backend
{
    /**
     * AccountTk模型对象
     * @var object
     * @phpstan-var \app\admin\model\AccountOpeningApplicationManage
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'create_time', 'update_time'];

    protected array $withJoinTable = [];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = ['edit'];

    //protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\AccountOpeningApplicationManage();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();
        
        $type = $this->request->get('type');

        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $dataList = $res->toArray()['data'];
        if($dataList){
            $channeList = [];
            if($type == 1)
            {
                $channeResult = DB::table('ba_aoam_channel_relat')
                ->alias('relat')
                ->field('relat.aoam_id,admin.id,admin.nickname')
                ->leftJoin('ba_admin admin','admin.id=relat.channel_id')
                ->where('relat.status',1)
                ->select()->toArray();
                foreach($channeResult as $v)
                {
                    $channeList[$v['aoam_id']][] = $v['nickname'];
                }
            }
            foreach($dataList as &$v)
            {
                $v['channe_list'] = $channeList[$v['id']]??[];
            }
        }

        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function addChannelRelatList()
    {
        $data = $this->request->get(['channel_id']);
        $list = $this->model->select()->toArray();

        $channelRelatList = DB::table('ba_aoam_channel_relat')->where('channel_id',$data['channel_id']??0)->where('status',1)->column('id','aoam_id');
        $statusCount = count($channelRelatList);
        foreach($list as &$v)
        {
            $v['status'] = in_array($v['id'],array_keys($channelRelatList)) ? 1 : 2;
            $v['aoam_id'] = $v['id'];
            $v['id'] = $channelRelatList[$v['id']]??0;
            if(empty($v['id'])) unset($v['id']);
        }

        $this->success('', [
            'list'   => $list,
            'status_count'=>$statusCount
        ]);    
    }

    public function addChannelRelat()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $result = false;
            $this->model->startTrans();
            try {

                $channelId = $data['channel_id'];
                $aoamId = $data['aoam_id'];
                $status = $data['status'];

                $where = [
                    ['channel_id','=',$channelId],
                    ['aoam_id','=',$aoamId],
                ];
                $result = DB::table('ba_aoam_channel_relat')->where($where)->find();

                if($status == 1)
                {
                    if($result){
                        DB::table('ba_aoam_channel_relat')->where($where)->update(['status'=>1]);
                    }else{
                        DB::table('ba_aoam_channel_relat')->insert(
                            ['channel_id'=>$channelId,'aoam_id'=>$aoamId]
                        );
                    }
                }else{
                    DB::table('ba_aoam_channel_relat')->where($where)->update(['status'=>2]);
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

        $this->error(__('Parameter error'));
    
    }



    

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}