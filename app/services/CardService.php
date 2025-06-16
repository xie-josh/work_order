<?php
namespace app\services;

use app\api\factories\CardFactory;
use app\api\interfaces\ApiClientInterface;
use think\facade\Db;
use think\facade\Queue;

class CardService
{
    private $apiClient;
    private $accountId;
    private $platform;

    public function __construct($cardAccountId)
    {
        $account = DB::table('ba_card_account')
        ->alias('card_account')
        ->field('card_account.*,card_platform.name,card_platform.platform')
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('card_account.id',$cardAccountId)
        ->find();

        if(empty($account)) throw new \Exception("service:账户异常!");
        if($account['status'] != 1) throw new \Exception('service:'.$account['logs']);

        $this->apiClient = CardFactory::create($account);
        
        
        $this->platform = $account['platform'];
        $this->accountId = $cardAccountId;
    }

    public function cardCreate($params)
    {
        try {
            return $this->apiClient->cardCreate($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }
       
    public function cardList($params)
    {
        try {
            return $this->apiClient->cardList($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }
    public function cardInfo($params)
    {
        try {
            return $this->apiClient->cardInfo($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }

    public function updateCard($params)
    {
        try {
            $params = $this->updateCardParams($params);
            return $this->apiClient->updateCard($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }

    public function cardFreeze($params)
    {
        try {
            return $this->apiClient->cardFreeze($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }
    
    public function cardUnfreeze($params)
    {
        try {
            return $this->apiClient->cardUnfreeze($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }

    public function transactionDetail($params)
    {
        try {
            return $this->apiClient->transactionDetail($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }


    public function test($params)
    {
        try {
            return $this->apiClient->test($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }


    public function cardGetLimits($params){
        try {
            return $this->apiClient->cardGetLimits($params);
        } catch (\Throwable $th) {
            $logs = '错误(service):'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }



    public function updateCardParams($params)
    {
        $param = [];
        $param['card_id'] = $params['card_id'];
        if(!empty($params['nickname'])) $param['nickname'] = $params['nickname'];
        if(!empty($params['max_on_daily'])) $param['max_on_daily'] = $params['max_on_daily'];
        if(!empty($params['max_on_monthly'])) $param['max_on_monthly'] = $params['max_on_monthly'];
        if(!empty($params['max_on_percent'])) $param['max_on_percent'] = $params['max_on_percent'];
        if(!empty($params['transaction_limit_type'])) $param['transaction_limit_type'] = $params['transaction_limit_type'];
        if(!empty($params['transaction_limit_change_type'])) $param['transaction_limit_change_type'] = $params['transaction_limit_change_type'];
        if(!empty($params['transaction_limit'])) $param['transaction_limit'] = $params['transaction_limit'];

        if($params['transaction_is'] == 1){
            //覆盖
            if($this->platform == 'photonpay'){
                //TODO...
                $cardInfo = $this->cardInfo(['card_id'=>$params['card_id']]);
                if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
                $totalTransactionLimit = $cardInfo['data']['totalTransactionLimit']??0;
                $param['transaction_limit_type'] = 'limited';
                if($totalTransactionLimit > $params['transaction_limit']){
                    $param['transaction_limit_change_type'] = 'decrease';
                    $param['transaction_limit'] = ($totalTransactionLimit - $params['transaction_limit']);
                }elseif($totalTransactionLimit < $params['transaction_limit']){
                    $param['transaction_limit_change_type'] = 'increase';
                    $param['transaction_limit'] = ($params['transaction_limit'] - $totalTransactionLimit);
                }else{
                    $param['transaction_limit'] = 0;
                }

            }elseif($this->platform == 'lampay'){
                //不需要处理，没有加减的概览
                if(!empty($params['max_on_percent']) || !empty($params['transaction_limit'])){
                    $param['max_on_percent'] = (int)$param['max_on_percent'];
                    $param['transaction_limit'] = (int)$param['transaction_limit'];
                    if($param['transaction_limit'] < 1) throw new \Exception('限额不能小于1');
                }
            }elseif($this->platform == 'airwallex' || $this->platform == 'airwallexUs'){
                
            }elseif($this->platform == 'slash'){

            }else{
                // return ['code'=>0,'msg'=>'未找到该平台！'];
                throw new \Exception('未找到该平台！');
            }
        }else if($params['transaction_is'] == 2){
            $param['transaction_limit_type'] = 'limited';   
            if($this->platform == 'photonpay'){
                //TODO...
                $cardInfo = $this->cardInfo(['card_id'=>$params['card_id']]);
                if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);

                $totalTransactionLimit = $cardInfo['data']['totalTransactionLimit']??0;
                $availableTransactionLimit = $cardInfo['data']['availableTransactionLimit']??0;

                $c = bcsub((string)$totalTransactionLimit,(string) $availableTransactionLimit,2);
                
                $param['max_on_percent'] = $cardInfo['data']['maxOnPercent']??0;
                if($availableTransactionLimit > $params['transaction_limit']){
                    $param['transaction_limit_change_type'] = 'decrease';
                    $param['transaction_limit'] = ($availableTransactionLimit - $params['transaction_limit']);
                }elseif($availableTransactionLimit < $params['transaction_limit']){
                    $param['transaction_limit_change_type'] = 'increase';
                    $param['transaction_limit'] = ($params['transaction_limit'] - $availableTransactionLimit);
                }else{
                    $param['transaction_limit'] = 0;
                }                
                
            }elseif($this->platform == 'lampay'){
               
            }elseif($this->platform == 'airwallex' || $this->platform == 'airwallexUs'){
                $cardInfo = $this->cardGetLimits(['card_id'=>$params['card_id']]);
                if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
                $totalTransactionLimit = $cardInfo['data']['limits']['ALL_TIME_AMOUNT']??0;
                $perTransaction  = $cardInfo['data']['limits']['PER_TRANSACTION']??0;
                $maxOnDaily = $cardInfo['data']['limits']['DAILY']??0;
                $allTime = $cardInfo['data']['limits']['ALL_TIME']??0;

                //使用
                $c = bcsub((string)$totalTransactionLimit,(string) $allTime,2);  
                $param['transaction_limit'] = $params['transaction_limit'] + $c;

                if(empty($params['transaction_limit']) && !empty($allTime)) $param['transaction_limit'] = $totalTransactionLimit;
                if(empty($params['max_on_percent']) && !empty($perTransaction)) $param['max_on_percent'] = $perTransaction;
                if(empty($params['max_on_daily']) && !empty($maxOnDaily)) $param['max_on_daily'] = $maxOnDaily;
            }elseif($this->platform == 'slash'){
                $cardInfo = $this->cardInfo(['card_id'=>$params['card_id']]);

                $c = bcsub((string)$cardInfo['data']['totalTransactionLimit'],(string) $cardInfo['data']['availableTransactionLimit'],2);  
                $param['transaction_limit'] = $params['transaction_limit'] + $c;

                if(empty($params['max_on_percent'])) $param['max_on_percent'] = $cardInfo['data']['maxOnPercent']??0;
            }else{
                // return ['code'=>0,'msg'=>'未找到该平台！'];
                throw new \Exception('未找到该平台！');
            }
        }else{
            //叠加
            if($this->platform == 'photonpay'){
                //不需要处理，就是叠加
                
            }elseif($this->platform == 'lampay'){
                $cardInfo = $this->cardInfo(['card_id'=>$params['card_id']]);
                if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
                $totalTransactionLimit = $cardInfo['data']['totalTransactionLimit']??0;
                if(!empty($params['transaction_limit']) && $params['transaction_limit_change_type'] == 'increase'){
                    $param['transaction_limit'] = $totalTransactionLimit + $params['transaction_limit'];
                }elseif(!empty($params['transaction_limit']) && $params['transaction_limit_change_type'] == 'decrease'){
                    $param['transaction_limit'] = $totalTransactionLimit - $params['transaction_limit'];
                }
                
                //lamp (两个要是必填，不然报错)
                //只能填整数
                if(!empty($params['max_on_percent']) || !empty($params['transaction_limit'])){
                    if(empty($params['max_on_percent'])) $param['max_on_percent'] = $cardInfo['data']['maxOnPercent'];
                    if(empty($params['transaction_limit'])) $param['transaction_limit'] = $cardInfo['data']['totalTransactionLimit'];
                    $param['max_on_percent'] = (int)$param['max_on_percent'];
                    $param['transaction_limit'] = (int)$param['transaction_limit'];
                    if($param['transaction_limit'] < 1) throw new \Exception('限额不能小于1');
                }
            }elseif($this->platform == 'airwallex' || $this->platform == 'airwallexUs'){
                $cardInfo = $this->cardGetLimits(['card_id'=>$params['card_id']]);
                if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
                $totalTransactionLimit = $cardInfo['data']['limits']['ALL_TIME_AMOUNT']??0;
                $perTransaction  = $cardInfo['data']['limits']['PER_TRANSACTION']??0;
                $maxOnDaily = $cardInfo['data']['limits']['DAILY']??0;

                if(!empty($params['transaction_limit']) && $params['transaction_limit_change_type'] == 'increase'){
                    $param['transaction_limit'] = $totalTransactionLimit + $params['transaction_limit'];
                }elseif(!empty($params['transaction_limit']) && $params['transaction_limit_change_type'] == 'decrease'){
                    $param['transaction_limit'] = $totalTransactionLimit - $params['transaction_limit'];
                }
                if(empty($params['transaction_limit']) && !empty($totalTransactionLimit)) $param['transaction_limit'] = $totalTransactionLimit;
                if(empty($params['max_on_percent']) && !empty($perTransaction)) $param['max_on_percent'] = $perTransaction;
                if(empty($params['max_on_daily']) && !empty($maxOnDaily)) $param['max_on_daily'] = $maxOnDaily;
            }elseif($this->platform == 'slash'){
                if(!empty($params['max_on_percent']) || !empty($params['transaction_limit']))
                {
                    $cardInfo = $this->cardInfo(['card_id'=>$params['card_id']]);
                    if(empty($params['max_on_percent'])) $param['max_on_percent'] = ($cardInfo['data']['maxOnPercent']??0) < 1 ? env('CARD.MAX_ON_PERCENT',901) : ($cardInfo['data']['maxOnPercent']??0);

                    if(empty($params['transaction_limit'])){
                        $param['transaction_limit'] = $cardInfo['data']['totalTransactionLimit']??0;
                    }else{
                        $param['transaction_limit'] =  bcdiv((string)$params['transaction_limit'], '100', 2);
                        if($params['transaction_limit_change_type'] == 'increase'){
                            $param['transaction_limit'] = bcadd((string)$params['transaction_limit'] ,(string)$cardInfo['data']['totalTransactionLimit'],2);
                        }elseif($params['transaction_limit_change_type'] == 'decrease'){
                            $param['transaction_limit'] = bcsub((string)$cardInfo['data']['totalTransactionLimit'],(string)$params['transaction_limit'] ,2);
                        }
                    }
                }
            }else{
                // return ['code'=>0,'msg'=>'未找到该平台！'];
                throw new \Exception('未找到该平台！');
            }
        }
        return $param;
    }
    


    // public function fetchData($endpoint, $method = 'GET', $data = [])
    // {
    //     return $this->apiClient->request($endpoint, $method, $data);
    // }








    // public function getCardAccount($accountId)
    // {
    //     $account = Db::table('ba_card_account')
    //     ->alias('card_account')        
    //     ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
    //     ->where('card_account.id',$accountId)
    //     ->find();
        
    //     dd($account);
    // }
}
