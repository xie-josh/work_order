<?php
namespace app\services;

use app\api\factories\CardFactory;
use app\api\interfaces\ApiClientInterface;
use think\facade\Db;

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

        if(empty($account)) throw new \Exception("账户异常!");
        if($account['status'] != 1) throw new \Exception($account['logs']);

        $this->apiClient = CardFactory::create($account);
        
        
        $this->platform = $account['platform'];
        $this->accountId = $cardAccountId;
    }

       
    public function cardList($params)
    {
        try {
            return $this->apiClient->cardList($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }
    public function cardInfo($params)
    {
        try {
            return $this->apiClient->cardInfo($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }

    public function updateCard($params)
    {
        try {
            $params = $this->updateCardParams($params);
            return $this->apiClient->updateCard($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }

    public function cardFreeze($params)
    {
        try {
            return $this->apiClient->cardFreeze($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }
    
    public function cardUnfreeze($params)
    {
        try {
            return $this->apiClient->cardUnfreeze($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }

    public function transactionDetail($params)
    {
        try {
            return $this->apiClient->transactionDetail($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
            return ['code'=>0,'msg'=>$logs];
        }
    }


    public function test($params)
    {
        try {
            return $this->apiClient->test($params);
        } catch (\Throwable $th) {
            $logs = '错误:'.json_encode($th->getMessage());
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

            }else{
                return ['code'=>0,'msg'=>'未找到该平台！'];
                // throw new \Exception('未找到该平台！');
            }
        }else{
            //叠加
            if($this->platform == 'photonpay'){
                //不需要处理，就是叠加
                
            }elseif($this->platform == 'lampay'){
                $cardInfo = $this->cardInfo(['card_id'=>$params['card_id']]);
                if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
                $totalTransactionLimit = $cardInfo['data']['totalTransactionLimit']??0;
                if($params['transaction_limit_change_type'] == 'increase'){
                    $param['transaction_limit'] = $totalTransactionLimit + $params['transaction_limit'];
                }elseif($params['transaction_limit_change_type'] == 'decrease'){
                    $params['transaction_limit'] = $totalTransactionLimit - $params['transaction_limit'];
                }
            }else{
                return ['code'=>0,'msg'=>'未找到该平台！'];
                // throw new \Exception('未找到该平台！');
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
