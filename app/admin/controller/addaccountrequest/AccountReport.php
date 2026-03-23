<?php

namespace app\admin\controller\addaccountrequest;

use Throwable;
use app\common\controller\Backend;
use think\facade\Db;
use think\console\Output;
use think\facade\Queue;

/**
 * 账户请求
 */
class AccountReport extends Backend
{
    /**
     * Accountrequest模型对象
     * @var object
     * @phpstan-var \app\admin\model\addaccountrequest\Accountrequest
     */
    protected object $model;

    protected bool|string|int $dataLimit = 'parent';
    protected array $noNeedPermission = ['index','add'];


    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\addaccountrequest\AccountReport();
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
        DB::table('ba_account_report')->where('create_time', '<', date('Y-m-d H:i:s', strtotime('-24 hours')))->select()->toArray()->delete();
        /**
         * 1. withJoin 不可使用 alias 方法设置表别名，别名将自动使用关联模型名称（小写下划线命名规则）
         * 2. 以下的别名设置了主表别名，同时便于拼接查询参数等
         * 3. paginate 数据集可使用链式操作 each(function($item, $key) {}) 遍历处理
         */
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        $res = $this->model
            ->alias($alias)
            // ->where($where)
            ->order($order)
            ->paginate($limit);
            
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
            
            $result = false;
            $this->model->startTrans();
            try {
                $temp = array_unique($data['account_ids']);
                $account = DB::table('ba_accountrequest_proposal')->whereIn('account_id',$temp)->column('account_id');
                $notExists = array_diff($temp, $account);
                if(!empty($notExists)){
                    throw new \Exception("非账户列表数据请检查！".implode(',',$notExists));
                }
                $data['account_ids'] = implode(',',array_unique($data['account_ids'])??[]);
                $data['name'] = $this->auth->getInfo()['nickname']?$this->auth->getInfo()['nickname']:$this->auth->getInfo()['username'];
                // 模型验证
                $result = $this->model->save($data);
                if ($temp) {
                    $detali = [];
                    foreach ($temp as $accountId) {
                        $detali[] = [
                            'report_id'      => $this->model->id,
                            'account_id'     => $accountId,
                            'create_time'    => date('Y-m-d H:i:s', time()),
                        ];
                    }
                    Db::name('account_report_detali')->insertAll($detali);
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }finally{
                     $Id = $this->model->id;
                    //  $notConsumptionStatus = config('basics.NOT_consumption_status');
                    //  $notConsumptionStatus = array_values(array_diff($notConsumptionStatus, [94]));
                     $result2 = DB::table('ba_accountrequest_proposal')
                     ->alias('accountrequest_proposal')
                     ->field('detali.report_id,detali.id self_id,fb_bm_token.is_token,accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids,accountrequest_proposal.currency')
                     ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
                     ->leftJoin('ba_account_report_detali detali','detali.account_id=accountrequest_proposal.account_id')
                     ->where('detali.report_id',$Id)
                     ->where('detali.status',1)
                    //  ->whereNotIn('accountrequest_proposal.status',$notConsumptionStatus)
                     ->whereNotNull('accountrequest_proposal.bm_token_id')
                     ->select()->toArray();
                     foreach($result2 as $k => $v)
                     {
                         $v['create_report_time'] = $data['create_report_time'];
                         $v['end_report_time']    = $data['end_report_time'];
                         $jobHandlerClassName     = 'app\job\AccountReport';
                         $jobQueueName = 'AccountReport';
                         Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
                     }
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }

}