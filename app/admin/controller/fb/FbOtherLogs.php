<?php

namespace app\admin\controller\fb;


use app\common\controller\Backend;
use think\facade\Db;

class FbOtherLogs extends Backend
{
    /**
     * @var object
     * @phpstan-var BmToken
     */
    protected object $model;

    protected array $withJoinTable = [];

    // 排除字段
    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = [];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\fb\FbLogsModel();
    }

    public function index(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res = DB::table('ba_fb_logs')
            ->field('fb_logs_model.type,fb_logs_model.log_id,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.status,accountrequest_proposal.serial_name,fb_logs_model.logs,admin.nickname,account.open_money,fb_logs_model.create_time,accountrequest_proposal.processing_status,accountrequest_proposal.processing_amount,accountrequest_proposal.processing_time')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=fb_logs_model.log_id')
            ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
            ->leftJoin('ba_admin admin','admin.id=account.admin_id')
            ->alias($alias)
            ->where($where)
            ->order('fb_logs_model.id','desc')
            ->paginate($limit);
            
        $dataList = $res->toArray()['data'];

        if($dataList)
        {
            $accountListIds = array_column($dataList,'account_id');
             
            //     1.fb余额(（总充值 + 首充） - 总扣款 - 总消费账单 - 总清零)
            $recharge = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->where('type',1)->where('status',1)->group('account_id')->select()->toArray();
            //$recharge1 = DB::table('ba_account')->field('open_money,account_id')->whereIn('account_id',$accountListIds)->select()->toArray();
            $recharge2 = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->where('type',2)->where('status',1)->group('account_id')->select()->toArray();
            //$recharge3 = DB::table('ba_account_consumption')->field('sum(spend) spend,account_id')->whereIn('account_id',$accountListIds)->group('account_id')->select()->toArray();
            $recharge4 = DB::table('ba_recharge')->field('sum(number) number,account_id')->whereIn('account_id',$accountListIds)->whereIn('type',[3,4])->where('status',1)->group('account_id')->select()->toArray();

            $recharge = array_column($recharge,'number','account_id');
            //$recharge1 = array_column($recharge1,'open_money','account_id');
            $recharge2 = array_column($recharge2,'number','account_id');
            //$recharge3 = array_column($recharge3,'spend','account_id');
            $recharge4 = array_column($recharge4,'number','account_id');

            foreach($dataList as &$v){
                $totalRecharge = $recharge[$v['account_id']]??0;
                //$firshflush = $recharge1[$v['account_id']]??0;
                $totalDeductions = $recharge2[$v['account_id']]??0;
                //$totalConsumption = $recharge3[$v['account_id']]??0;
                $totalReset = $recharge4[$v['account_id']]??0;
                $openAmount = empty($v['open_money'])?0:$v['open_money'];

                $v['total_recharge_amount'] = ($totalRecharge + $openAmount) - $totalDeductions;
                $v['total_reset'] = $totalReset;

                $v['accountrequest_proposal']['account_id'] = $v['account_id'];
                $v['accountrequest_proposal']['bm'] = $v['bm'];
                $v['accountrequest_proposal']['status'] = $v['status'];
                $v['accountrequest_proposal']['serial_name'] = $v['serial_name'];
                $v['accountrequest_proposal']['processing_status'] = $v['processing_status'];
                $v['accountrequest_proposal']['processing_amount'] = $v['processing_amount'];
                $v['accountrequest_proposal']['processing_time'] = $v['processing_time'];
                $v['admin']['nickname'] = $v['nickname'];

            }
        }
        $this->success('', [
            'list'   => $dataList,
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function audit()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            $this->model->startTrans();
            try {
                $accountId = $data['account_id']??[];
                $status = $data['status']??'';
                $processingStatus = $data['processing_status']??1;
                $processingAmount = $data['processing_amount']??0;
                $comment = $data['comment']??'';
                
                if(!$accountId || !$processingStatus) throw new \Exception("Error Processing Request");

                $dataList = [
                    'processing_status'=>$processingStatus,
                    'processing_amount'=>$processingAmount,
                    'processing_time'=>date('Y-m-d H:i',time()),
                ];
                
                if(isset($status)) $dataList['status'] = $status;
                if(isset($comment)) $dataList['comment'] = $comment;
                
                $result = DB::table('ba_accountrequest_proposal')->where('processing_status',0)->whereIn('account_id',$accountId)->update($dataList);
                $this->model->commit();
            } catch (\Throwable $e) {
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



}