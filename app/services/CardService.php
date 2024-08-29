<?php
namespace app\services;

use app\api\factories\CardFactory;
use app\api\interfaces\ApiClientInterface;
use think\facade\Db;

class CardService
{
    private $apiClient;

    public function __construct($cardAccountId)
    {
        $account = DB::table('ba_card_account')
        ->alias('card_account')
        ->field('card_account.*,card_platform.name,card_platform.platform')
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('card_account.id',$cardAccountId)
        ->find();
        $this->apiClient = CardFactory::create($account);
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


    public function fetchData($endpoint, $method = 'GET', $data = [])
    {
        return $this->apiClient->request($endpoint, $method, $data);
    }








    public function getCardAccount($accountId)
    {
        $account = Db::table('ba_card_account')
        ->alias('card_account')        
        ->leftJoin('ba_card_platform card_platform','card_platform.id=card_account.card_platform_id')
        ->where('card_account.id',$accountId)
        ->find();
        
        dd($account);
    }
}
