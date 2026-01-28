<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;
use app\common\service\QYWXService;
use think\facade\Db;
use Throwable;
use app\admin\model\card\CardsModel;
use app\services\CardService;
use think\facade\Cache;
use think\facade\Queue;

/**
 * 充值需求
 */
class Recharge extends Backend
{
    /**
     * Recharge模型对象
     * @var object
     * @phpstan-var \app\admin\model\demand\Recharge
     */
    protected object $model;

    protected array|string $preExcludeFields = ['id', 'account_name', 'status', 'create_time', 'update_time'];

    protected string|array $quickSearchField = ['id'];
    protected array $withJoinTable = ['accountrequestProposal'];
    protected array $noNeedPermission = ['batchAdd','edit','getRechargeAnnouncement','accountSpendDelete','accountSpendUp','accountSpendDeductions','export','getExportRecharge'];

    // protected bool|string|int $dataLimit = 'parent';

    protected $currencyRate = ["EUR"=>"0.8","ARS"=>"940","PEN"=>"3.6","IDR"=>"16000","VND"=>"23500","GBP"=>"0.7"];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Recharge();
    }


    public function index(): void
    {
        $wk = DB::table('ba_wk_account')->column('account_id');

        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($this->withJoinTable,'account');

        foreach($where as $k => &$v){
            if($v[0] == 'recharge.id'){
                if (preg_match('/\d+/', $v[2], $matches)) {
                    $number = ltrim($matches[0], '0'); // 移除开头的零
                    $v[2] = '%'.$number.'%';
                } else {
                    //$v[2] = $number;
                }
            }
            if($v[0] == 'recharge.username'){
                $companyId = DB::table('ba_admin')->where('type',2)->where([['nickname','like',$v[2]]])->column('company_id');
                if(!empty($companyId)) array_push($where,['account.company_id','IN',$companyId]);
                unset($where[$k]);
                continue;
            }

        }
        // dd($where);

        array_push($where,['recharge.audit_status','=',2]);

        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

            $result = $res->toArray();
            $dataList = [];
            // $adminNameArr = DB::table('ba_admin')->column('username','id');
            $companyNameArr = DB::table('ba_company')->field('id,prepayment_type')->select()->toArray();
            $companyAdminNameArr = DB::table('ba_admin')->field('company_id,nickname,id')->where('type',2)->select()->toArray();
            $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');

            if(!empty($result['data'])) {
                $dataList = $result['data'];
                $companyNameArr = array_column($companyNameArr,'prepayment_type','id');
                $companyAdminNameArr = array_column($companyAdminNameArr,null,'company_id');

                
                // dd($adminNameArr);
                foreach($dataList as &$v){
                    $companyId = $v['account']['company_id']??'';
                    $v['account']['currency_number'] = ROUND($v['account']['currency_number']??0, 2);

                    $nickname = '';
                    if(!empty($companyId)) $nickname = $companyAdminNameArr[$companyId]['nickname'];

                    $v['add_operate_user_name'] = $adminNameArr[$v['add_operate_user']]??"";
                    // $v['username'] = $adminNameArr[$v['admin_id']]['nickname']??"";
                    $v['username'] = $nickname;
                    $v['prepayment_type'] = $companyNameArr[$companyId]??"";
                    if(in_array($v['account_id'],$wk)){
                        $v['wk_type'] = 1;
                        $v['wk_comment'] = "注意，注意，注意：该账户需要您自己去卡平台调整限额！！！！！！！";
                    }else{
                        $v['wk_type'] = 0;
                        $v['wk_comment'] = "";
                    }
                    $v['fb_money'] = ROUND($v['fb_money']??0, 2);
                    //if(isset($v['accountrequestProposal']) && !in_array($v['accountrequestProposal']['bm_token_id'],[1,6,29,30,31,32])) $v['accountrequestProposal']['bm_token_id'] = null;
                }
            }

            $this->success('', [
                'list'   => $dataList,
                'total'  => $res->total(),
                'remark' => get_route_remark(),
            ]);
    
            // $res->visible(['account'=>['money']]);
    
            // $this->success('', [
            //     'list'   => $res->items(),
            //     'total'  => $res->total(),
            //     'remark' => get_route_remark(),
            // ]);
    }

    public function add(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            $wk = DB::table('ba_wk_account')->column('account_id');

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

            $lock = new \app\services\RedisLock();
            $lockValue = uniqid();
            $expire = 180;
            $lockKey = $data['account_id'];

            $acquired = $lock->acquire($lockKey, $lockValue, $expire);
            if(!$acquired) $this->error("该账户暂时被锁定，请稍后再试！");
            

            $result = false;
            $this->model->startTrans();
            try {
                // 模型验证
                if ($this->modelValidate) {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    if (class_exists($validate)) {
                        $validate = new $validate();
                        if ($this->modelSceneValidate) $validate->scene('add');
                        $validate->check($data);
                    }
                }
                $groupid = $this->uidGetGroupId();
                $whereC = [];
                if(in_array($groupid,[3,4]))$whereC['admin_id'] = $this->auth->id; //管理员和员工组跳过
                $account = Db::table('ba_account')->where('account_id',$data['account_id'])->where($whereC)->where('status',4)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID或账户不可用");

                // $recharge = $this->model->where('account_id',$data['account_id'])->order('id','desc')->find();
                // if(!empty($recharge) && in_array($recharge['type'],[3,4]) && $recharge['status'] == 0) throw new \Exception("有未完成的清零请求,请找客服处理!");

                $recharge = $this->model->where('account_id',$data['account_id'])->whereIn('type',[3,4])->where('status',0)->find();
                if(!empty($recharge)) throw new \Exception("已收到清零需求，请勿重复提交，如需加急，请联系客服!");
                
                if($data['type'] == 1){
                    if($data['number'] <= 0) throw new \Exception("充值金额不能小于零");
                    
                    $cc = DB::table('ba_accountrequest_proposal')->where('account_id',$account['account_id'])->value('status');
                    if($cc == 4) throw new \Exception("该账号暂停充值，请联系管理员！");

                    $admin = Db::table('ba_admin')->where('id',$account['admin_id'])->find();
                    if($admin['prepayment_type'] == 2){
                        $usableMoney = ($admin['money'] - $admin['used_money']);
                        if($usableMoney <= 0 || $usableMoney < $data['number']) throw new \Exception("余额不足,请联系管理员！");
                        DB::table('ba_admin')->where('id',$account['admin_id'])->inc('used_money',$data['number'])->update();
                    }
                }elseif(in_array($data['type'],[3,4])){
                    $recharge = $this->model->where('account_id',$data['account_id'])->where('status',1)->whereIn('type',[3,4])->order('id','desc')->find();

                    if(!empty($recharge['id'])){
                        $where = [
                            ['account_id','=',$data['account_id']],
                            ['type','=',1],
                            ['id','>',$recharge['id']],
                            ['status','=',1]
                        ];
    
                        $recharge2 = $this->model->where($where)->find();
                        if(!empty($recharge) && empty($recharge2)) throw new \Exception("账号已经完成了清零请求,不可以在提交清零与扣款!");
                    }

                    $cards = DB::table('ba_accountrequest_proposal')
                    ->field('cards_info.id,cards_info.card_status,cards_info.card_id,cards_info.account_id')
                    ->alias('accountrequest_proposal')
                    ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                    ->where('accountrequest_proposal.account_id',$account['account_id'])
                    ->find();
                    //$cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                    if(!empty($cards) && $cards['card_status'] == 'normal') {
                        $resultCards = (new CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                    }
                    
                    (new \app\admin\services\card\Cards())->allCardFreeze($account['account_id']);
                }
                
                $adminArr   = $this->getPermissionUser([$account['account_id']]); //权限判断
                $data['account_name'] = $account['name'];
                $data['admin_id'] = $adminArr?$adminArr[$account['account_id']]:$this->auth->id;
                $data['add_operate_user'] = $this->auth->id;

                if(in_array($data['type'],[1,2]) && env('IS_ENV',false)) (new QYWXService())->send(['account_id'=>$data['account_id']],$data['type']);

                if(in_array($data['type'],[3,4])) $data['number'] = 0;

                $result = $this->model->save($data);

                if ($this->model->id && !in_array($data['account_id'],$wk)) {
                    $id = $this->model->id;
                    $this->rechargeJob($id);
                }
                $this->model->commit();
            } catch (Throwable $e) {
                $this->model->rollback();
                $this->error($e->getMessage());
            }finally {
                $lock->release($lockKey, $lockValue);
            }  
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }


    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }
        $this->error('编辑功能已经禁用,请找客服！');
        if($row['status'] != 0 && $row['type'] == 1) $this->error('该状态不可编辑');
        

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


    public function audit(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            $redisLock = new \app\services\RedisLock();
            $redisLockList = $data['ids'];

            foreach($redisLockList as $v){
                $key = 'recharge_audit_'.$v;
                $acquired = $redisLock->acquire($key, 'audit', 180);
                // if(!$acquired)  $this->error($v.":该需求被锁定，处理中！");               
            }

            $result = false;
            DB::startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $money = $data['money']??0;
                $type = $data['type']??0;
                $fbBoney = $data['fb_money']??0;

                if(empty($ids) || empty($status)) throw new \Exception("请选择需要操作的数据！");

                $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();

                if($status == 1){
                    foreach($ids as $v){

                        // $accountIs_ = DB::table('ba_account')->where('account_id',$v['account_id'])->inc('money',$v['number'])->value('is_');
                        //if($accountIs_ != 1) throw new \Exception("错误：账户不可用请先确认账户是否活跃或账户清零回来是否调整限额！"); 

                        $resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->find();
                        if((empty($resultProposal) || $resultProposal['status'] == 99) && !in_array($type,[3,4]) ) throw new \Exception("错误：账户未找到或账户已经终止使用！"); 

                        if($v['type'] == 1){
                            DB::table('ba_account')->where('account_id',$v['account_id'])->inc('money',$v['number'])->update(['update_time'=>time()]);

                            $param = [
                                'transaction_limit_type'=>'limited',
                                'transaction_limit_change_type'=>'increase',
                                'transaction_limit'=>$v['number'],
                            ];                            
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                            if(empty($cards)) {
                                //TODO...
                                throw new \Exception("未找到分配的卡");
                            }else{
                                 //SX-用户不改限额
                                
                                $adminId = $resultProposal['admin_id'];
                                $isQuota = DB::table('ba_admin')->where('id',$adminId)->value('is_quota');
                                // if(env('APP.IS_QUOTA'))
                                if($isQuota == 1)
                                {   
                                    $resultCards = (new CardsModel())->updateCard($cards,$param);
                                    if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                                }
                            }
                        }elseif($v['type'] == 2){
                            DB::table('ba_account')->where('account_id',$v['account_id'])->dec('money',$v['number'])->update(['update_time'=>time()]);
                            // DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['number'])->update();
                            $usedMoney =  $this->auditTeamUsedMoney($v['number'],$v['account_id']);
                            if($usedMoney['code'] != 1)  throw new \Exception($usedMoney['msg']);
                            

                            $param = [
                                'transaction_limit_type'=>'limited',
                                'transaction_limit_change_type'=>'decrease',
                                'transaction_limit'=>$v['number'],
                            ];
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                            if(empty($cards)) {
                                //TODO...
                                throw new \Exception("未找到分配的卡");
                            }else{
                                //SX-用户不改限额

                                $adminId = $resultProposal['admin_id'];
                                $isQuota = DB::table('ba_admin')->where('id',$adminId)->value('is_quota');

                                // if(env('APP.IS_QUOTA'))
                                if($isQuota == 1)
                                {
                                    $resultCards = (new CardsModel())->updateCard($cards,$param);
                                    if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                                }
                            }
                        }elseif($v['type'] == 3 || $v['type'] == 4){

                            $currency = $resultProposal['currency'];

                            $currencyNumber =  '';
                            $currencyRate = json_decode(env('APP.currency'),true);
                            if(!empty($currencyRate[$currency])){
                                $currencyNumber = bcdiv((string)$money, $currencyRate[$currency],2);
                            }else{
                                $currencyNumber = (string)$money;
                            }

                            $accountMoney = DB::table('ba_account')->where('account_id',$v['account_id'])->value('money');
                            if($accountMoney < $currencyNumber) throw new \Exception('警告：清零金额不可大于账户总充值！');

                            $data = [
                                'fb_money'=>$fbBoney,
                                'number'=>$currencyNumber,
                                'type'=>$type
                            ];
                            $this->model->where('id',$v['id'])->update($data);
                            DB::table('ba_account')->where('account_id',$v['account_id'])->update(['money'=>0,'is_'=>2,'update_time'=>time()]);
                            // DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$currencyNumber)->update();

                            $usedMoney =  $this->auditTeamUsedMoney($currencyNumber,$v['account_id']);
                            if($usedMoney['code'] != 1)  throw new \Exception($usedMoney['msg']);
                            
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                            if(empty($cards)) {
                                //TODO...
                                // if($resultProposal['is_cards'] != 2) throw new \Exception("未找到分配的卡");
                                throw new \Exception("未找到分配的卡");
                            }else{
                                if($cards['card_status'] == 'normal'){
                                    $resultCards = (new CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                                    if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                                    if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                                }
                            }
                        }
                        // Cache::store('redis')->delete($key);
                    }
                }else{
                    foreach($ids as $v){
                        if($v['type'] == 1){
                            //DB::table('ba_account')->where('account_id',$v['account_id'])->dec('money',$v['number'])->update(['update_time'=>time()]);
                            
                            
                            // DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['number'])->update();
                            $usedMoney =  $this->auditTeamUsedMoney($v['number'],$v['account_id']);
                            if($usedMoney['code'] != 1)  throw new \Exception($usedMoney['msg']);

                        }else if($v['type'] == 3 || $v['type'] == 4)
                        {

                            $cards = DB::table('ba_accountrequest_proposal')
                            ->field('cards_info.id,cards_info.card_status,cards_info.card_id,cards_info.account_id')
                            ->alias('accountrequest_proposal')
                            ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                            ->where('accountrequest_proposal.account_id',$v['account_id'])
                            ->find();
                            //$cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                            if(!empty($cards) && $cards['card_status'] == 'frozen') {
                                $resultCards = (new CardService($cards['account_id']))->cardUnFreeze(['card_id'=>$cards['card_id']]);
                                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                                if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                            }
                            (new \app\admin\services\card\Cards())->allCardUnFreeze($cards['account_id']);
                        }
                    }
                }

                $result = $this->model->whereIn('id',array_column($ids,'id'))->where('status',0)->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);
                $result = true;
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
                // Cache::store('redis')->delete($key);
                $this->error($e->getMessage());
            }finally {
                foreach($redisLockList as $v){
                    $key = 'recharge_audit_'.$v;
                    $redisLock->release($key, 'audit');
                }
            } 
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }

    public function accountSpendUp()
    {
        // sleep(5);
        // $this->success(__('Update successful'));
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            //DB::startTrans();
            try {
                $id = $data['id'];

                $result = (new \app\admin\services\demand\Recharge())->spendUp(['id'=>$id]);
                if($result['code'] != 1) throw new \Exception($result['msg']);

                $result = true;
                //DB::commit();
            } catch (Throwable $e) {
                //DB::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }

    public function accountSpendDelete()
    {
        // sleep(5);
        // $this->success(__('Update successful'));
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            //DB::startTrans();
            try {
                $id = $data['id'];

                $result = (new \app\admin\services\demand\Recharge())->spendDelete(['id'=>$id]);
                if($result['code'] != 1) throw new \Exception($result['msg']);

                $result = true;
                //DB::commit();
            } catch (Throwable $e) {
                //DB::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }

    public function accountSpendDeductions()
    {
        // $this->success(__('Update successful'));
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            //DB::startTrans();
            try {
                $id = $data['id'];

                $result = (new \app\admin\services\demand\Recharge())->spendDeductions(['id'=>$id]);
                if($result['code'] != 1) throw new \Exception($result['msg']);

                $result = true;
                //DB::commit();
            } catch (Throwable $e) {
                //DB::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }


    public function accountSpendDelete222()
    {
        $this->success(__('Update successful'));
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = false;
            DB::startTrans();
            try {
                $id = $data['id'];

                $key = 'recharge_audit_'.$id;
                $redisValue = Cache::store('redis')->get($key);
                if(!empty($redisValue)) throw new \Exception("该数据在处理中，不需要重复点击！");
                Cache::store('redis')->set($key, '1', 180);

                $result = $this->model->where('id',$id)->where([['status','=',0],['type','IN',[3,4]]])->find();
                if(empty($result)) throw new \Exception("未找到需要或需要已经处理！"); 

                //====================

                $accountrequestProposal = DB::table('ba_accountrequest_proposal')
                ->alias('accountrequest_proposal')
                ->field('accountrequest_proposal.currency,accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token')
                ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
                ->where('fb_bm_token.status',1)
                ->whereNotNull('fb_bm_token.token')
                ->where('accountrequest_proposal.account_id',$result['account_id'])
                ->find();
                //dd($accountrequestProposal);

                if(empty($accountrequestProposal)) throw new \Exception("未找到账户或账户授权异常！");
                
                $FacebookService = new \app\services\FacebookService();
                $result1 = $FacebookService->adAccounts($accountrequestProposal);
                $result2 = $FacebookService->adAccountsDelete($accountrequestProposal);
                $result3 = $FacebookService->adAccountsLimit($accountrequestProposal);
                if($result1['code'] != 1) throw new \Exception($result1['msg']);
                if($result2['code'] != 1) throw new \Exception("FB删除限额错误，请联系管理员！");
                if($result3['code'] != 1) throw new \Exception("FB重置限额错误，请联系管理员！");
                
                $money = $result1['data']['balance_amount'];
                $fbBoney = $result1['data']['spend_cap'];

                //$resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$result['account_id'])->find();
                $currency = $accountrequestProposal['currency'];

                $currencyNumber =  '';
                $currencyRate = json_decode(env('APP.currency'),true);
                if(!empty($currencyRate[$currency])){
                    $currencyNumber = bcdiv((string)$money, $currencyRate[$currency],2);
                }else{
                    $currencyNumber = (string)$money;
                }

                $data = [
                    'fb_money'=>$fbBoney,
                    'number'=>$currencyNumber,
                    'status'=>1,
                ];
                $this->model->where('id',$result['id'])->update($data);
                DB::table('ba_account')->where('account_id',$result['account_id'])->update(['money'=>0,'is_'=>2,'update_time'=>time()]);
                DB::table('ba_admin')->where('id',$result['admin_id'])->dec('used_money',$currencyNumber)->update();

                if($accountrequestProposal['is_cards'] != 2) {
                    $cards = DB::table('ba_cards_info')->where('cards_id',$accountrequestProposal['cards_id']??0)->find();
                    if(empty($cards)) {
                        //TODO...
                        // if($resultProposal['is_cards'] != 2) throw new \Exception("未找到分配的卡");
                        throw new \Exception("未找到分配的卡");
                    }else{
                        $resultCards = (new CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                    }
                }
                
                //=============

                Cache::store('redis')->delete($key);
                $result = true;
                DB::commit();
            } catch (Throwable $e) {
                DB::rollback();
                Cache::store('redis')->delete($key);
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }
    }
    public function accountSpendDeleteAll()
    {
        $result = [];

         foreach($result as  $v){
             $jobHandlerClassName = 'app\job\AccountSpendDelete';
             $jobQueueName = 'AccountSpendDelete';
             Queue::later(1, $jobHandlerClassName, $v, $jobQueueName);        
         }

    }

    public function getRechargeAnnouncement(): void
    {
        if ($this->request->param('select')) {
            $this->select();
        }

        $groupsId = ($this->auth->getGroups()[0]['group_id'])??0;
        if($groupsId != 2 && !$this->auth->isSuperAdmin()) {
            $this->success('', [
                'list'   => [],
                'total'  => 0,
            ]);
        }

        $this->withJoinTable = [];

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        array_push($where,['recharge.type','IN',[1]]);
        array_push($where,['recharge.status','=',0]);
        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate();

        $list = $res->items();
        $listTotal = $res->total();

        $where = [];
        array_push($where,['recharge.type','IN',[2]]);
        array_push($where,['recharge.status','=',0]);

        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate();

        $deductionList = $res->items();
        $deductionTotal = $res->total();

        $bmTokenList = DB::table('ba_fb_personalbm_token')->where([
            ['expired_time','<',date("Y-m-d",strtotime("+6 day"))],
            ['status','=',1]
        ])->field('name,expired_time')->select()->toArray();

        $accountReturn = DB::table('ba_account_return')->where('status',0)->count();
        $accountKeep = DB::table('ba_account')->where('status',4)->where('is_keep',1)->where('keep_succeed',0)->count();
        
        $this->success('', [
            'list'   =>$list,
            'deduction_list'=>$deductionList,
            'bm_token_list'=>$bmTokenList,
            'balance'=>0,
            'total'=>$listTotal,
            'deduction_total'=>$deductionTotal,
            'balance_total'=>0,
            'account_keep_count'=>$accountKeep,
            'account_return'=>$accountReturn,
        ]);
    }

    public function rechargeJob($id)
    {
        // $this->model = new \app\admin\model\Demand\Recharge();
        //$result = $this->model->where('recharge.id',$id)->withJoin(['accountrequestProposal'], $this->withJoinType)->find();
        $result = DB::table('ba_recharge')->where('recharge.id',$id)->alias('recharge')
        ->leftJoin('ba_accountrequest_proposal accountrequest_proposal','accountrequest_proposal.account_id=recharge.account_id')
        ->field('accountrequest_proposal.bm_token_id,recharge.type,accountrequest_proposal.status accountrequest_proposal_status')->find();
        if(!empty($result) && $result['accountrequest_proposal_status'] == 99 && !in_array($result['type'],[3,4])){
            throw new \Exception("该账户已经终止使用不可操作，请联系管理员！");
        }else if(!empty($result['bm_token_id']) && in_array($result['type'],[3,4])){
            $this->addDeleteJob($id);
        }else if(!empty($result['bm_token_id']) && in_array($result['type'],[1])){
            $this->addUpJob($id);
        }else if(!empty($result['bm_token_id']) && in_array($result['type'],[2])){
            $this->addDeductionsJob($id);
        }
        return true;
    }

    public function addUpJob($id)
    {
        $jobHandlerClassName = 'app\job\AccountSpendUp';
        $jobQueueName = 'AccountSpendUp';
        Queue::later(1, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }

    public function addDeleteJob($id)
    {
        $jobHandlerClassName = 'app\job\AccountSpendDelete';
        $jobQueueName = 'AccountSpendDelete';
        Queue::later(259200, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }

    public function addDeductionsJob($id)
    {
        $jobHandlerClassName = 'app\job\AccountSpendDeductions';
        $jobQueueName = 'AccountSpendDeductions';
        Queue::later(1, $jobHandlerClassName, ['id'=>$id], $jobQueueName);
        return true;
    }


    public function export()
    {
        $ids = $this->request->get('ids');
        $where = [];
        set_time_limit(300);
        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_recharge'.'_'.$this->auth->id;
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($this->withJoinTable,'account');
        //array_push($where,['recharge.id','IN',$ids]);

        $query = DB::table('ba_recharge')
        ->field('recharge.id,accountrequestProposal.bm,accountrequestProposal.serial_name,accountrequestProposal.account_id,recharge.type,recharge.number,recharge.status,recharge.create_time,recharge.update_time')
        ->alias('recharge')
        ->leftJoin('ba_accountrequest_proposal accountrequestProposal','accountrequestProposal.account_id=recharge.account_id')
        ->leftJoin('ba_account account','account.account_id=recharge.account_id')
        ->where($where);

        $total = $query->count();

        $type = [1=>'充值',2=>'扣款',3=>'封户清零',4=>'活跃清零'];
        $status = [0=>'待处理',1=>'成功',2=>'失败'];

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            'ID',
            // 'Bm',
            '账户名称',
            '账户ID',
            '类型',
            '金额',
            '状态',
            '创建时间',
            '修改时间'
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);


        $name = $folders['name'].'.xlsx';
        $excel->fileName($folders['name'].'.xlsx', 'sheet1');

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->order('id','desc')->limit($offset, $batchSize)->select()->toArray();
           
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['id'],
                    // $v['bm'],
                    $v['serial_name'],
                    $v['account_id'],
                    $type[$v['type']]??'',
                    $v['number'],
                    $status[$v['status']]??'',
                    $v['create_time']?date('Y-m-d H:i',$v['create_time']):'',
                    $v['update_time']?date('Y-m-d H:i',$v['update_time']):'',
                ];  
                $processedCount++;
            }
            $excel->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }

    public function getExportRecharge()
    {
        $progress = Cache::store('redis')->get('export_recharge'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }

    public function batchAdd(): void
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) $this->error(__('Parameter %s can not be empty', ['']));

            $list = $data['list'];

            if(empty($list)) $this->error('参数错误');
            if(count($list) > 30) $this->error('批量添加不能超过30条');

            $lock = new \app\services\RedisLock();
            
            $wk = DB::table('ba_wk_account')->column('account_id');
            
            $accountListC =  array_column($list,'account_id');
            $adminArr   = $this->getPermissionUser($accountListC); //权限判断

            $groupid = $this->uidGetGroupId();
            $whereC = [];
            // if(in_array($groupid,[3,4])) $whereC['admin_id'] =  $this->auth->id;


            $errorList = [];
            foreach($list as $v)
            {
                $accountId = $v['account_id'];
                $amount = $v['amount'];
                $type = $v['type'];
                $lockValue = uniqid();
                $expire = 180;
                $data = [];

                $acquired = $lock->acquire($accountId, $lockValue, $expire);
                if(!$acquired) $this->error($accountId.":该需求被锁定，处理中！");
                
                try {
                    $account = Db::table('ba_account')->where('account_id',$accountId)->where('status',4)->find();
                    if(empty($account)) throw new \Exception("未找到该账户或账户不可用！");                
    
                    $nOTConsumptionStatus = config('basics.NOT_consumption_status');
                    $accountServerStatus = DB::table('ba_accountrequest_proposal')->where('account_id',$account['account_id'])->value('status');
                    if(in_array($accountServerStatus,$nOTConsumptionStatus) && !in_array($type,[3,4])) throw new \Exception("该账号暂停使用，请联系管理员！");

                    $recharge = $this->model->where('account_id',$accountId)->whereIn('type',[3,4])->where('status',0)->find();
                    if(!empty($recharge)) throw new \Exception("已收到清零需求，请勿重复提交，如需加急，请联系客服!");

                    if($type != 1 && $account['money'] <= 0) throw new \Exception("该账号余额是零，不需要处理！");
         
                    if($acquired){
                        if($type == 1)
                        {
                            if($amount <= 0) throw new \Exception("充值金额不能小于零");
                            $admin = Db::table('ba_company')->where('id',$account['company_id'])->find();
                            if($admin['prepayment_type'] == 2){
                                $usableMoney = bcadd((string)$amount,(string)$admin['used_money'],2);
                                if($usableMoney > $admin['money']) throw new \Exception("余额不足,请联系管理员！");
                                if($admin['used_money'] < 0) throw new \Exception("钱包金额不对,请联系管理员！");
                                DB::table('ba_company')->where('id',$account['company_id'])->update(['used_money'=>$usableMoney]);
                            }
                        }else if($type == 2)
                        {

                        }else if(in_array($type,[3,4]))
                        {
                            $recharge = $this->model->where('account_id',$accountId)->where('status',1)->whereIn('type',[3,4])->order('id','desc')->find();

                            if(!empty($recharge['id'])){
                                $where = [
                                    ['account_id','=',$accountId],
                                    ['type','=',1],
                                    ['id','>',$recharge['id']],
                                    ['status','=',1]
                                ];
            
                                $recharge2 = $this->model->where($where)->find();
                                if(!empty($recharge) && empty($recharge2)) throw new \Exception("账号已经完成了清零请求,不可以在提交清零与扣款!");
                            }

                            $cards = DB::table('ba_accountrequest_proposal')
                            ->field('cards_info.id,cards_info.card_status,cards_info.card_id,cards_info.account_id')
                            ->alias('accountrequest_proposal')
                            ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                            ->where('accountrequest_proposal.account_id',$account['account_id'])
                            ->find();
                            if(!empty($cards) && $cards['card_status'] == 'normal') {
                                $resultCards = (new CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                                if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                            }
                            (new \app\admin\services\card\Cards())->allCardFreeze($account['account_id']);
                        }
                    }else{
                        throw new \Exception('该账户暂时被锁定，请稍后再试！');
                    }

                    $data['create_time'] = time();
                    $data['type'] = $type;
                    $data['account_id'] = $accountId;
                    $data['account_name'] = $account['name'];
                    $data['team_id'] = $account['team_id']??'';
                    $data['admin_id'] = $adminArr?$adminArr[$accountId]:$this->auth->id;                    
                    $data['add_operate_user'] = $this->auth->id;
                    if(in_array($type,[3,4]))$data['number'] = 0;
                    else $data['number'] = $amount;
                    $rechargeId = DB::table('ba_recharge')->insertGetId($data);

                    if ($rechargeId && !in_array($accountId,$wk)) {
                        $this->rechargeJob2($rechargeId,$type);
                    }

                }catch(\Exception $e) {
                    $errorList[] = ['account_id'=>$accountId,'msg'=>$e->getMessage()];
                }finally {
                    $lock->release($accountId, $lockValue);
                }        

            }
            $this->success(__('Added successfully'),['error_list'=>$errorList]);
        }

        $this->error(__('Parameter error'));
    }
    
    public function rechargeJob2($id,$type)
    {
        if(in_array($type,[3,4])){
            $this->addDeleteJob($id);
        }else if(in_array($type,[1])){
            $this->addUpJob($id);
        }
        return true;
    }
    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */

         /**
     * 根据权限获取账户开户user
     */
    public function getPermissionUser($accountArr)
    {
        $adminArr =[];     
        $accountAdminIdArr = $this->getAccountAdminId($accountArr);
        if(!empty($accountAdminIdArr))switch($this->uidGetGroupId()){
            case 1:
            case 2: 
                   $adminArr = $accountAdminIdArr; 
                break;
            case 3: 
                   $adminArr = array_map(function($value) {
                    return $this->auth->id;
                }, $accountAdminIdArr); 
                break;
        }
        return $adminArr;
    }


     public function exportDealWith()
    {
        $ids = $this->request->get('ids');
        $where = [];
        set_time_limit(300);
        $batchSize = 2000;
        $processedCount = 0;
        $redisKey = 'export_recharge_with'.'_'.$this->auth->id;
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        array_push($this->withJoinTable,'account');
        //array_push($where,['recharge.id','IN',$ids]);

        $query = DB::table('ba_recharge')
        ->field('recharge.operate_admin_id,account.open_time,accountrequestProposal.admin_id accountrequest_proposal_admin_id,recharge.id,accountrequestProposal.bm,accountrequestProposal.serial_name,accountrequestProposal.account_id,recharge.type,recharge.number,recharge.status,recharge.create_time,recharge.update_time')
        ->alias('recharge')
        ->leftJoin('ba_accountrequest_proposal accountrequestProposal','accountrequestProposal.account_id=recharge.account_id')
        ->leftJoin('ba_account account','account.account_id=recharge.account_id')
        ->where($where);

        $total = $query->count();

        $resultAdmin = DB::table('ba_admin')->select()->toArray();
        $adminList = array_combine(array_column($resultAdmin,'id'),array_column($resultAdmin,'nickname'));

        $type = [1=>'充值',2=>'扣款',3=>'封户清零',4=>'活跃清零'];
        $status = [0=>'待处理',1=>'成功',2=>'失败'];

        $folders = (new \app\common\service\Utils)->getExcelFolders();
        $header = [
            '管理BM',
            '渠道',
            '账户名称',
            '账户ID',
            '类型',
            '金额',
            '状态',
            '创建时间',
            '修改时间',
            '开户时间',
            '处理人',
        ];

        $config = [
            'path' => $folders['path']
        ];
        $excel  = new \Vtiful\Kernel\Excel($config);


        $name = $folders['name'].'.xlsx';
        $excel->fileName($folders['name'].'.xlsx', 'sheet1');

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $data = $query->order('id','desc')->limit($offset, $batchSize)->select()->toArray();
           
            $dataList=[];
            foreach($data as $v){
                $dataList[] = [
                    $v['bm'],
                    ($adminList[$v['accountrequest_proposal_admin_id']])??'',
                    $v['serial_name'],                    
                    $v['account_id'],
                    $type[$v['type']]??'',
                    $v['number'],
                    $status[$v['status']]??'',
                    $v['create_time']?date('Y-m-d H:i',$v['create_time']):'',
                    $v['update_time']?date('Y-m-d H:i',$v['update_time']):'',
                    $v['open_time']?date('Y-m-d H:i',$v['open_time']):'',
                    ($adminList[$v['operate_admin_id']])??'',
                ];  
                $processedCount++;
            }
            $excel->header($header)
            ->data($dataList);
            $progress = min(100, ceil($processedCount / $total * 100));
            Cache::store('redis')->set($redisKey, $progress, 300);
        }

        $excel->output();
        Cache::store('redis')->delete($redisKey);

        $this->success('',['path'=>$folders['filePath'].'/'.$name]);
    }

    public function getExportRechargeWith()
    {
        $progress = Cache::store('redis')->get('export_recharge_with'.'_'.$this->auth->id, 0);
        return $this->success('',['progress' => $progress]);
    }
}