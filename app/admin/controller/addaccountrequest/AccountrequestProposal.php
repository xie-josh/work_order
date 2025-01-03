<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Queue;

/**
 * 账户列管理
 */
class AccountrequestProposal extends Backend
{
    /**
     * AccountrequestProposal模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\AccountrequestProposal
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'accountrequest_id', 'create_time', 'update_time'];

    protected array $withJoinTable = ['admin'];

    protected string|array $quickSearchField = ['id'];

    protected array $noNeedPermission = ['Export','getAccountrequestProposal'];

    protected bool|string|int $dataLimit = 'parent';

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountrequestProposal();
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

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId == 2) {
            $this->dataLimit = false;
        }

        //dd($this->request->get());
        array_push($this->withJoinTable,'affiliationAdmin');
        array_push($this->withJoinTable,'cards');

        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();


        if($limit == 999) $limit = 2500;

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);
        $res->visible(['admin' => ['username','nickname'],'cards'=>['card_no']]);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    public function audit(): void
    {
       
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $ids = $data['ids'];
                $bm = $data['bm'];
                $affiliationBm = $data['affiliationBm'];
                $timeZone = $data['timeZone'];
                $adminId = $data['adminId'];
                $isCards = $data['is_cards']??0;
                $type = $data['type']??1;
                $currency = $data['currency']??'';
                $nameList = $data['name_list']??[];
                $dataList = [];

                if(empty($ids)) throw new \Exception("账户为空！");

                $accountCount = $this->model->where('admin_id',$adminId)->count();

                $accountList = $this->model->whereIn('account_id',$ids)->column('account_id');

                $bmTokenResult = DB::table('ba_fb_bm_token')->where('id',$bm)->find();
                if(empty($bmTokenResult)) throw new \Exception("管理BM必选！");
                
                $bm = $bmTokenResult['name'];
                $bmTokenId = $bmTokenResult['id'];

                foreach($ids as $k =>$v){
                    if(in_array($v,$accountList)) continue;
                    $accountCount++;
                    $dataList[] = [
                        'bm'=>$bm,
                        'affiliation_bm'=>$affiliationBm,
                        'admin_id'=>$adminId,
                        'status'=>0,
                        'time_zone'=>$timeZone,
                        'currency'=>$currency,
                        'account_id'=>$v,
                        'is_cards'=>$isCards,
                        'name'=>$nameList[$k]??'',
                        'type'=>$type,
                        'serial_number'=>$accountCount,
                        'create_time'=>time()
                    ];
                    $this->assignedUsersJob($v,$bmTokenId);
                }
                Db::table('ba_accountrequest_proposal')->insertAll($dataList);

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

    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
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

    public function Export()
    {
        set_time_limit(600);
        $where = [];
        $ids = $this->request->post('ids');
        if($ids) array_push($where,['accountrequest_proposal.id','in',$ids]);
        
        // $data = DB::table('ba_accountrequest_proposal')
        // ->alias('accountrequest_proposal')
        // ->field('accountrequest_proposal.*,account.name account_name,account.bm account_bm,admin.nickname,cards_info.card_no')
        // ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        // ->leftJoin('ba_admin admin','admin.id=accountrequest_proposal.admin_id')
        // ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        // ->where($where)->select()->toArray();

        $batchSize = 2000;
        $processedCount = 0;

        $query = $this->model
        ->alias('accountrequest_proposal')
        ->field('accountrequest_proposal.*,account.name account_name,account.bm account_bm,admin.nickname,cards_info.card_no')
        ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
        ->leftJoin('ba_admin admin','admin.id=accountrequest_proposal.admin_id')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
        ->where($where);
        $total = $query->count(); 

        $resultAdmin = DB::table('ba_admin')->select()->toArray();

        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $statusValue = [0=>'未分配',1=>'已分配',2=>'绑卡挂户',3=>'大BM挂',4=>'其他币种',5=>'丢失账户',99=>'终止使用'];


        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'bm',
            'time_zone',
            'account_id',
            'account_name',
            'affiliation_bm',
            'affiliation_admin_name',
            'account_bm',
            'status',
            'nickname',
            'card_no'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';
        
        
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->append([])->toArray();
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    'bm'=>$v['bm'],
                    'time_zone'=>$v['time_zone'],
                    'account_id'=>$v['account_id'],
                    'account_name'=>$v['serial_name'],
                    'affiliation_bm'=>$v['affiliation_bm'],
                    'affiliation_admin_name'=> $adminList[$v['affiliation_admin_id']]??'',
                    'account_bm'=> $v['account_bm'],
                    'status'=> $statusValue[$v['status']]??'未知的状态',
                    'nickname'=>$v['nickname'],
                    'card_no'=>$v['card_no']
                ];  
                $processedCount++;
            }
            $filePath = $excel->fileName($folders['name'].'.xlsx', 'sheet1')
            ->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set('export_progress', $progress, 300);
            // 刷新缓冲区
            //ob_flush();
            //flush();
        }   
        $excel->output();
        Cache::store('redis')->delete('export_progress');

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);        
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_progress', 0); // 获取进度
        return $this->success('',['progress' => $progress]);
    }


    public function getAccountrequestProposal(): void
    {
        $accountId  = $this->request->param('account_id');
        $row = $this->model->where('affiliation_admin_id',$this->auth->id)->where('account_id',$accountId)->find();
        
        $this->success('', [
            'row' => $row
        ]);
    }


    public function assignedUsersJob($accountId,$bmTokenId)
    {
        $jobHandlerClassName = 'app\job\AccountAssignedUsers';
        $jobQueueName = 'AccountAssignedUsers';
        Queue::later(1, $jobHandlerClassName, ['account_id'=>$accountId,'bm_token_id'=>$bmTokenId], $jobQueueName);
        return true;
    }

    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}