<?php

namespace app\admin\services\demand;

use think\facade\Db;
use think\facade\Cache;
use Throwable;

class Recharge
{

    protected object $model;
    protected object $auth;

    protected $currencyRate = ["EUR"=>"0.8","ARS"=>"940","PEN"=>"3.6","IDR"=>"16000","VND"=>"23500"];

    protected $currency100 = ["IDR","VND"];

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\demand\Recharge();
        $this->auth = $auth ?? new \stdClass();
    }
    public function spendUp($params)
    {
        $result = false;
        $isCards = false;
        $cardsNumber = 0;
        $cards = [];
        $isCardStatus = false;
        try {
            
            $id = $params['id']??0;

            $result = $this->model->where('id',$id)->where([['status','=',0],['type','IN',[1]]])->find();
            if(empty($result)) throw new \Exception("未找到需求或需求已经处理！");

            $account = DB::table('ba_account')->where('account_id',$result['account_id'])->field('is_,money')->find();
            if($account['is_'] != 1) throw new \Exception("错误：系统账户不可用请先确认账户是否活跃或账户清零回来是否调整限额！"); 

            $key = 'recharge_spendUp_'.$id;
            $redis = Cache::store('redis')->handler(); 
            
            $lock = $redis->set($key, 1, ['nx', 'ex' => 180]);
            if (!$lock) throw new \Exception("该数据在处理中，不需要处理！");

            $accountrequestProposal = DB::table('ba_accountrequest_proposal')
            ->alias('accountrequest_proposal')
            ->field('accountrequest_proposal.currency,accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token')
            ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
            ->where('fb_bm_token.status',1)
            ->whereNotNull('fb_bm_token.token')
            ->where('accountrequest_proposal.account_id',$result['account_id'])
            ->find();

            if(empty($accountrequestProposal)) throw new \Exception("未找到账户或账户授权异常！");
            $currency = $accountrequestProposal['currency'];            

            $FacebookService = new \app\services\FacebookService();
            $result1 = $FacebookService->adAccounts($accountrequestProposal);
            if($result1['code'] != 1) throw new \Exception($result1['msg']);
            if(!in_array($result1['data']['account_status'],[1,3])) throw new \Exception('FB账户异常，请确认账户状态[已经封户或状态异常]！');

            if(in_array($currency,$this->currency100)){
                $result1['data']['spend_cap'] =  bcmul($result1['data']['spend_cap'], '100', 2);
            }

            $spendCap = $result1['data']['spend_cap'];
            $spendCapUs = $result1['data']['spend_cap'];

            $fbNumber = $result['number'];
            $accountMoney = $account['money'];
            if(!empty($this->currencyRate[$currency])){
                $fbNumber = bcmul((string)$fbNumber, $this->currencyRate[$currency],2);
                $spendCapUs = bcdiv((string)$spendCapUs, $this->currencyRate[$currency],2);
            }            
            
            if($spendCapUs == 0.01) $spendCapUs = 0;
            if(empty($accountMoney)) $accountMoney = 0;

            if($spendCapUs != $accountMoney) throw new \Exception("FB总限额与系统充值匹配错误！");

            if($spendCap == 0.01) $spendCap = 0;

            $spendCap = bcadd((string)$spendCap,(string)$fbNumber,2);


            $cardsNumber = $result['number'];

            $param = [
                'transaction_limit_type'=>'limited',
                'transaction_limit_change_type'=>'increase',
                'transaction_limit'=>$cardsNumber,
            ];
            $resultProposal = DB::table('ba_accountrequest_proposal')->where('account_id',$result['account_id'])->find();
            if($resultProposal['is_cards'] != 2){
                $cards = DB::table('ba_cards_info')->where('cards_id',$resultProposal['cards_id']??0)->find();
                if(empty($cards)) {
                    throw new \Exception("未找到分配的卡");
                }else{                    
                    if($cards['card_status'] != 'normal'){
                        $resultCards = (new \app\services\CardService($cards['account_id']))->cardUnfreeze(['card_id'=>$cards['card_id']]);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('card_id',$cards['card_id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                        $isCardStatus = true;
                    }

                    $resultCards = (new \app\admin\model\card\CardsModel())->updateCard($cards,$param);
                    if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);

                    $isCards = true;
                }
            }

            $accountrequestProposal['spend'] = $spendCap;
            $result3 = $FacebookService->adAccountsLimit($accountrequestProposal);
            if($result3['code'] != 1) throw new \Exception("FB重置限额错误，请联系管理员！");

            

            DB::table('ba_account')->where('account_id',$result['account_id'])->inc('money',$result['number'])->update(['update_time'=>time(),'is_'=>1]);
            $data = [
                'status'=>1,
            ];
            $this->model->where('id',$result['id'])->update($data);

            Cache::store('redis')->delete($key);
            $result = true;
        } catch (Throwable $th) {
            if(!empty($key)) Cache::store('redis')->delete($key);
            if(!empty($id)){
                DB::table('ba_fb_logs')->insert(
                    ['log_id'=>$id,'type'=>'services_error_spend_up','data'=>json_encode($result),'logs'=>$th->getMessage(),'create_time'=>date('Y-m-d H:i:s',time())]
                );
                $result = $this->model->where('id',$id)->update(['comment'=>$th->getMessage()]);
            }
            if($isCards){
                $param = [
                    'transaction_limit_type'=>'limited',
                    'transaction_limit_change_type'=>'decrease',
                    'transaction_limit'=>$cardsNumber,
                ];

                $resultCards = (new \app\admin\model\card\CardsModel())->updateCard($cards,$param);
                // if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                
                if($cards['card_status'] != 'frozen' && $isCardStatus){
                    $resultCards = (new \app\services\CardService($cards['account_id']))->cardfreeze(['card_id'=>$cards['card_id']]);
                    // if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                    if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('card_id',$cards['card_id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                }
 
            }

            return ['code'=>0,'msg'=>$th->getMessage()];
        }
        if ($result !== false) {
            return ['code'=>1,'msg'=>''];
        } else {
            return ['code'=>0,'msg'=>''];
        }
    }

    public function spendDelete($params)
    {
        $result = false;
        try {
            $id = $params['id']??0;

            $result = $this->model->where('id',$id)->where([['status','=',0],['type','IN',[3,4]]])->find();
            if(empty($result)) throw new \Exception("未找到需求或需求已经处理！");

            $key = 'recharge_audit_'.$id;
            $redisValue = Cache::store('redis')->get($key);
            if(!empty($redisValue)) throw new \Exception("该数据在处理中，不需要重复点击！");
            Cache::store('redis')->set($key, '1', 180);

            //====================
            $accountrequestProposal = DB::table('ba_accountrequest_proposal')
            ->alias('accountrequest_proposal')
            ->field('accountrequest_proposal.currency,accountrequest_proposal.cards_id,accountrequest_proposal.is_cards,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token')
            ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
            ->where('fb_bm_token.status',1)
            ->whereNotNull('fb_bm_token.token')
            ->where('accountrequest_proposal.account_id',$result['account_id'])
            ->find();

            if(empty($accountrequestProposal)) throw new \Exception("未找到账户或账户授权异常！");
            $currency = $accountrequestProposal['currency'];

            $accountMoney = DB::table('ba_account')->where('account_id',$accountrequestProposal['account_id'])->value('money');
            
            $FacebookService = new \app\services\FacebookService();
            $result1 = $FacebookService->adAccounts($accountrequestProposal);
            if($result1['code'] != 1) throw new \Exception($result1['msg']);

            if(in_array($currency,$this->currency100)){
                $result1['data']['balance_amount'] =  bcmul($result1['data']['balance_amount'], '100', 2);
                $result1['data']['spend_cap'] =  bcmul($result1['data']['spend_cap'], '100', 2);
            }

            $fbAccountStatus = $result1['data']['account_status'];
            $money = $result1['data']['balance_amount'];
            $fbBoney = $result1['data']['spend_cap'];

            $currencyNumber =  '';
            $spendCap = $fbBoney;
            if(!empty($this->currencyRate[$currency])){
                $currencyNumber = bcdiv((string)$money, $this->currencyRate[$currency],2);
                $spendCap = bcdiv((string)$fbBoney, $this->currencyRate[$currency],2);
            }else{
                $currencyNumber = (string)$money;
            }
            if($spendCap == 0.01) $spendCap = 0;
            if($spendCap != $accountMoney) throw new \Exception("FB总限额与系统充值匹配错误！");

            $result2 = $FacebookService->adAccountsDelete($accountrequestProposal);
            if($result2['code'] != 1) throw new \Exception("FB删除限额错误，请联系管理员！");
            $accountrequestProposal['spend'] = 0.01;
            if(in_array($currency,$this->currency100)) $accountrequestProposal['spend'] = 1;
            $result3 = $FacebookService->adAccountsLimit($accountrequestProposal);
            if($result3['code'] != 1) throw new \Exception("FB重置限额错误，请联系管理员！");
            
            $type = $fbAccountStatus == 2?3:4;
            $data = [
                'fb_money'=>$fbBoney,
                'number'=>$currencyNumber,
                'status'=>1,
                'type'=>$type,
                'update_time'=>time()
            ];
            
            

            if($accountrequestProposal['is_cards'] != 2) {
                $cards = DB::table('ba_cards_info')->where('cards_id',$accountrequestProposal['cards_id']??0)->find();
                if(empty($cards)) {
                    throw new \Exception("未找到分配的卡");
                }else{
                    if($currencyNumber > 1){
                        $param = [
                            'transaction_limit_type'=>'limited',
                            'transaction_limit_change_type'=>'decrease',
                            'transaction_limit'=>($currencyNumber-1),
                        ];
                        if($cards['account_id'] == 1 && $cards['card_status'] == 'frozen'){
                            $resultCards = (new \app\services\CardService($cards['account_id']))->cardUnfreeze(['card_id'=>$cards['card_id']]);
                            if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                            if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                            sleep(3);
                        }
                        $resultCards = (new \app\admin\model\card\CardsModel())->updateCard($cards,$param);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        
                        $resultCards = (new \app\services\CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                    }elseif($cards['card_status'] == 'normal'){
                        $resultCards = (new \app\services\CardService($cards['account_id']))->cardFreeze(['card_id'=>$cards['card_id']]);
                        if($resultCards['code'] != 1) throw new \Exception($resultCards['msg']);
                        if(isset($resultCards['data']['cardStatus'])) DB::table('ba_cards_info')->where('id',$cards['id'])->update(['card_status'=>$resultCards['data']['cardStatus']]);
                    }
                }
            }
            //=============
            
            DB::table('ba_account')->where('account_id',$result['account_id'])->update(['money'=>0,'is_'=>2,'update_time'=>time()]);
            DB::table('ba_admin')->where('id',$result['admin_id'])->dec('used_money',$currencyNumber)->update();
            $this->model->where('id',$result['id'])->update($data);
            Cache::store('redis')->delete($key);
            $result = true;
        } catch (Throwable $e) {
            if(!empty($key)) Cache::store('redis')->delete($key);
            
            if(!empty($id)){
                DB::table('ba_fb_logs')->insert(
                    ['log_id'=>$id,'type'=>'services_error_spend_delete','data'=>json_encode($result),'logs'=>$e->getMessage(),'create_time'=>date('Y-m-d H:i:s',time())]
                );
                $result = $this->model->where('id',$id)->update(['comment'=>$e->getMessage()]);
            }

            return ['code'=>0,'msg'=>$e->getMessage()];
        }
        if ($result !== false) {
            return ['code'=>1,'msg'=>''];
        } else {
            return ['code'=>0,'msg'=>''];
        }
    }
}