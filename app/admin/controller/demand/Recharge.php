<?php

namespace app\admin\controller\demand;

use app\common\controller\Backend;
use app\common\service\QYWXService;
use think\facade\Db;
use Throwable;
use app\admin\model\card\CardsModel;
use app\services\CardService;
use think\facade\Cache;

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
    protected array $noNeedPermission = ['edit','getRechargeAnnouncement'];

    protected bool|string|int $dataLimit = 'parent';

    protected $currencyRate = ["EUR"=>"0.84"];

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new \app\admin\model\demand\Recharge();
    }


    public function index(): void
    {
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
        }

        $res = $this->model
            ->field($this->indexField)
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $res->visible(['account'=>['money']]);

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

            $data = $this->excludeFields($data);
            if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                $data[$this->dataLimitField] = $this->auth->id;
            }

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
                $account = Db::table('ba_account')->where('account_id',$data['account_id'])->where('admin_id',$this->auth->id)->where('status',4)->find();
                if(empty($account)) throw new \Exception("未找到该账户ID或账户不可用");

                // $recharge = $this->model->where('account_id',$data['account_id'])->order('id','desc')->find();
                // if(!empty($recharge) && in_array($recharge['type'],[3,4]) && $recharge['status'] == 0) throw new \Exception("有未完成的清零请求,请找客服处理!");

                $recharge = $this->model->where('account_id',$data['account_id'])->whereIn('type',[3,4])->where('status',0)->find();
                if(!empty($recharge)) throw new \Exception("有未完成的清零请求,请找客服处理!");
                
                if($data['type'] == 1){
                    if($data['number'] <= 0) throw new \Exception("充值金额不能小于零");

                    $admin = Db::table('ba_admin')->where('id',$account['admin_id'])->find();
                    $usableMoney = ($admin['money'] - $admin['used_money']);
                    if($usableMoney <= 0 || $usableMoney < $data['number']) throw new \Exception("余额不足,请联系管理员！");

                    DB::table('ba_admin')->where('id',$account['admin_id'])->inc('used_money',$data['number'])->update();
                }elseif(in_array($data['type'],[3,4])){
                    $recharge = $this->model->where('account_id',$data['account_id'])->whereIn('type',[3,4])->order('id','desc')->find();

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
                }
                
                $data['account_name'] = $account['name'];
                $data['admin_id'] = $this->auth->id;

                if(in_array($data['type'],[1,2]) && env('IS_ENV',false)) (new QYWXService())->send(['account_id'=>$data['account_id']],$data['type']);

                if(in_array($data['type'],[3,4])) $data['number'] = 0;

                $result = $this->model->save($data);
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


    public function edit(): void
    {
        $pk  = $this->model->getPk();
        $id  = $this->request->param($pk);
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

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
            $result = false;
            DB::startTrans();
            try {
                $ids = $data['ids'];
                $status = $data['status'];
                $money = $data['money']??0;
                $type = $data['type']??0;
                $fbBoney = $data['fb_money']??0;

                $ids = $this->model->whereIn('id',$ids)->where('status',0)->select()->toArray();

                $result = $this->model->whereIn('id',array_column($ids,'id'))->update(['status'=>$status,'update_time'=>time(),'operate_admin_id'=>$this->auth->id]);

                if($status == 1){
                    foreach($ids as $v){

                        $key = 'recharge_audit_'.$v['id'];
                        $redisValue = Cache::store('redis')->get($key);
                        if(!empty($redisValue)) throw new \Exception("该数据在处理中，不需要重复点击！");
                        Cache::store('redis')->set($key, '1', 180);

                        $accountIs_ = DB::table('ba_account')->where('account_id',$v['account_id'])->inc('money',$v['number'])->value('is_');
                        if($accountIs_ != 1) throw new \Exception("错误：账户不可用请先确认账户是否活跃或账户清零回来是否调整限额！"); 

                        if($v['type'] == 1){
                            DB::table('ba_account')->where('account_id',$v['account_id'])->inc('money',$v['number'])->update(['update_time'=>time()]);

                            $param = [
                                'transaction_limit_type'=>'limited',
                                'transaction_limit_change_type'=>'increase',
                                'transaction_limit'=>$v['number'],
                            ];
                            $resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->find();
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                            if(empty($cards)) {
                                //TODO...
                                throw new \Exception("未找到分配的卡");
                            }else{
                                $resultCards = (new CardsModel())->updateCard($cards,$param);
                                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                            }
                        }elseif($v['type'] == 2){
                            DB::table('ba_account')->where('account_id',$v['account_id'])->dec('money',$v['number'])->update(['update_time'=>time()]);
                            DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['number'])->update();

                            $param = [
                                'transaction_limit_type'=>'limited',
                                'transaction_limit_change_type'=>'decrease',
                                'transaction_limit'=>$v['number'],
                            ];
                            $resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->find();
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                            if(empty($cards)) {
                                //TODO...
                                throw new \Exception("未找到分配的卡");
                            }else{
                                $resultCards = (new CardsModel())->updateCard($cards,$param);
                                if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                            }
                        }elseif($v['type'] == 3 || $v['type'] == 4){

                            $resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$v['account_id'])->find();
                            $currency = $resultProposal['currency'];

                            $currencyNumber =  '';
                            if(!empty($this->currencyRate[$currency])){
                                $currencyNumber = bcdiv((string)$money, $this->currencyRate[$currency],2);
                            }else{
                                $currencyNumber = (string)$money;
                            }

                            $data = [
                                'fb_money'=>$fbBoney,
                                'number'=>$currencyNumber,
                                'type'=>$type
                            ];
                            $this->model->where('id',$v['id'])->update($data);
                            DB::table('ba_account')->where('account_id',$v['account_id'])->update(['money'=>0,'is_'=>2,'update_time'=>time()]);
                            DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$currencyNumber)->update();

                            
                            if($resultProposal['is_cards'] == 2) continue;
                            $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
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
                        Cache::store('redis')->delete($key);
                    }
                }else{
                    foreach($ids as $v){
                        if($v['type'] == 1){
                            //DB::table('ba_account')->where('account_id',$v['account_id'])->dec('money',$v['number'])->update(['update_time'=>time()]);
                            DB::table('ba_admin')->where('id',$v['admin_id'])->dec('used_money',$v['number'])->update();
                        }
                    }
                }
                
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
            ->paginate(1);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }


    /**
     * 若需重写查看、编辑、删除等方法，请复制 @see \app\admin\library\traits\Backend 中对应的方法至此进行重写
     */
}