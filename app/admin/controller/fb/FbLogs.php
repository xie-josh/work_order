<?php

namespace app\admin\controller\fb;


use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Cache;

class FbLogs extends Backend
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

        array_push($where,['fb_logs_model.type','IN',['FB_insights']]);

        $res = DB::table('ba_fb_logs')
            ->field('fb_logs_model.status,fb_logs_process.amout logs_process_amout,fb_logs_process.comment logs_process_comment,fb_logs_process.create_time logs_process_create_time,fb_logs_model.type,fb_logs_model.log_id,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.serial_name,accountrequest_proposal.currency,fb_logs_model.logs,admin.nickname,account.open_time,account.money,fb_logs_model.create_time')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=fb_logs_model.log_id')
            ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
            ->leftJoin('ba_admin admin','admin.id=account.admin_id')
            ->leftJoin('ba_fb_logs_process fb_logs_process','fb_logs_process.fb_logs_id=fb_logs_model.id')
            ->alias($alias)
            ->where($where)
            ->order('fb_logs_model.id','desc')
            ->paginate($limit);
            
        $dataList = $res->toArray()['data'];

        $rechargeModel = new \app\admin\model\demand\Recharge();

        if($dataList)
        {
            $currencyRate = config('basics.currency');

            $accountIds = array_column($dataList,'account_id');
            $rechargeList = $rechargeModel->field('account_id,sum(number) number')->whereIn('account_id',$accountIds)->where('type',1)->where('status',1)->select()->append([])->toArray();
            $chargebacksList = $rechargeModel->field('account_id,sum(number) number')->whereIn('account_id',$accountIds)->where('type',2)->where('status',1)->select()->append([])->toArray();
            $rechargeList = array_column($rechargeList,'number','account_id');
            $chargebacksList = array_column($chargebacksList,'number','account_id');

            foreach($dataList as &$v){

                $recharge = $rechargeList[$v['account_id']]??0;
                $chargebacks = $chargebacksList[$v['account_id']]??0;
                $money = bcsub((string)$recharge, (string)$chargebacks, 2);

                $currencyNumber =  '';
                if(!empty($currencyRate[$v['currency']])){
                    $currencyNumber = bcmul($money, $currencyRate[$v['currency']],2);
                }else{
                    $currencyNumber = $money;
                }
                $v['currency_account'] = $currencyNumber;

                 /**
         * 
            管理BM  accountrequest_proposal->bm (可搜索)
            账户名称  accountrequest_proposal->serial_name (可搜索)
            账户ID   accountrequest_proposal->account_id (可搜索)
            开户时间 open_time
            归属用户 admin->nickname
            总充值  currency_account
            总消耗    logs_process_amout  
            货币      accountrequest_proposal->currency (可搜索)
            创建时间  create_time
            处理状态  fb_logs_status (可搜索)
            处理时间  logs_process_create_time
            备注    logs_process_comment
         */

                $v['accountrequest_proposal']['account_id'] = $v['account_id'];
                $v['accountrequest_proposal']['bm'] = $v['bm'];
                $v['accountrequest_proposal']['status'] = $v['status'];
                $v['accountrequest_proposal']['serial_name'] = $v['serial_name'];
                $v['accountrequest_proposal']['currency'] = $v['currency'];
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
                $accountId = $data['account_id'];
                $status = $data['status']??'';
                $processingAmount = $data['processing_amount']??0;
                $comment = $data['comment']??'';
                
                // $fblogsModel = new \app\admin\model\fb\FbLogsModel();
                if(!$accountId) throw new \Exception("Error Processing Request");

                $accountIds = array_unique($accountId);

                $fbLogsList = $this->model->field('id')->whereIn('log_id',$accountIds)->where('type','FB_insights')->where('status',0)->select()->toArray();

                $fbLogsIds = array_column($fbLogsList,'id');

                $data = [];
                foreach($fbLogsList as $v)
                {
                    $data[] = [
                        'fb_logs_id'=>$v['id'],
                        'amout'=>$processingAmount,
                        'comment'=>$comment,
                        'create_time'=>date('Y-m-d H:i:d',time()),
                    ];
                }

                if(!empty($fbLogsIds)) $this->model->whereIn('id',$fbLogsIds)->update(['status'=>1]);
                if(!empty($data)) DB::table('ba_fb_logs_process')->insertAll($data);               
                
                if(!empty($status)) DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['status'=>$status]);
                $result = true;
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


    public function lostNumber()
    {
        $where = [
            ['type','IN',['FB_insights']],
        ];
        $numbertotal = $this->model->where($where)->where([['create_time','like',"%".date('Y-m-d',time())."%"]])->group('log_id')->count('id');
        $numberNotProcess = $this->model->where($where)->where('status',0)->group('log_id')->count('id');

        $this->success('', [
            'number_total' => $numbertotal,
            'number_not_process' => $numberNotProcess,
        ]);
    } 

    public function export()
    {
        $where = [];
        set_time_limit(300);
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_progress'.'_'.$this->auth->id;
        
        array_push($where,['fb_logs_model.type','IN',['FB_insights']]);

        $query = DB::table('ba_fb_logs')
            ->field('fb_logs_model.status fb_logs_status,fb_logs_process.amout logs_process_amout,fb_logs_process.comment logs_process_comment,fb_logs_process.create_time logs_process_create_time,fb_logs_model.type,fb_logs_model.log_id,accountrequest_proposal.account_id,accountrequest_proposal.bm,accountrequest_proposal.status,accountrequest_proposal.serial_name,accountrequest_proposal.currency,fb_logs_model.logs,admin.nickname,account.open_time,account.money,fb_logs_model.create_time')
            ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=fb_logs_model.log_id')
            ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')
            ->leftJoin('ba_admin admin','admin.id=account.admin_id')
            ->leftJoin('ba_fb_logs_process fb_logs_process','fb_logs_process.fb_logs_id=fb_logs_model.id')
            ->alias($alias)
            ->where($where)
            ->order('fb_logs_model.id','desc');

        $total = $query->count(); 

        $statusValue = [0=>'未处理',1=>'处理完成'];
        $currencyRate = config('basics.currency');

        $rechargeModel = new \app\admin\model\demand\Recharge();
        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '管理BM',
            '账户名称',
            '账户ID',
            '开户时间',
            '归属用户',
            '总充值',
            '总消耗',
            '货币',
            '创建时间',
            '处理状态',
            '处理时间',
            '备注'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);

        $name = $folders['name'].'.xlsx';
        
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->limit($offset, $batchSize)->select()->toArray();

            $accountIds = array_column($data,'account_id');
            $rechargeList = $rechargeModel->field('account_id,sum(number) number')->whereIn('account_id',$accountIds)->where('type',1)->where('status',1)->select()->append([])->toArray();
            $chargebacksList = $rechargeModel->field('account_id,sum(number) number')->whereIn('account_id',$accountIds)->where('type',2)->where('status',1)->select()->append([])->toArray();
            $rechargeList = array_column($rechargeList,'number','account_id');
            $chargebacksList = array_column($chargebacksList,'number','account_id');

            $dataList=[];
            foreach($data as $v){
                $recharge = $rechargeList[$v['account_id']]??0;
                $chargebacks = $chargebacksList[$v['account_id']]??0;
                $money = bcsub((string)$recharge, (string)$chargebacks, 2);

                $currencyNumber =  '';
                if(!empty($currencyRate[$v['currency']])){
                    $currencyNumber = bcmul($money, $currencyRate[$v['currency']],2);
                }else{
                    $currencyNumber = $money;
                }

                $dataList[] = [
                    $v['bm'],
                    $v['serial_name'],
                    $v['account_id'],
                    empty($v['open_time'])?'':date('Y-m-d H:i:s',$v['open_time']),
                    $v['nickname'],
                    $currencyNumber,
                    $v['logs_process_amout'],
                    $v['currency'],
                    $v['create_time'],
                    $statusValue[$v['fb_logs_status']]??'未知状态',
                    $v['logs_process_create_time'],
                    $v['logs_process_comment'],
                ];  
                $processedCount++;
            }
            $excel->fileName($folders['name'].'.xlsx', 'sheet1')
            ->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }

    public function getExportProgress()
    {
        $progress = Cache::store('redis')->get('export_progress'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

}