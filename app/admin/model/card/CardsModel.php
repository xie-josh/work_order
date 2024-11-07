<?php

namespace app\admin\model\card;

use think\Model;
use app\services\CardService;
use think\facade\Db;

/**
 * RechargeChannel
 */
class CardsModel extends Model
{
    // 表名
    protected $name = 'cards';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    public function cardInfo()
    {
        return $this->belongsTo(\app\admin\model\card\CardsInfoModel::class, 'id', 'cards_id');
    }


    function updateCardsInfo($cards,$params=[])
    {
        $accountId = $cards['account_id'];
        $cardId = $cards['card_id'];

        try {
            $cardInfo = (new CardService($accountId))->cardInfo(['card_id'=>$cardId]);
            if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
            
            $infoData = $cardInfo['data'];
            $data = [];
            if(!empty($infoData['nickname'])) $data['nickname'] = $infoData['nickname'];
            if(!empty($infoData['maxOnDaily'])) $data['max_on_daily'] = $infoData['maxOnDaily'];
            if(!empty($infoData['maxOnMonthly'])) $data['max_on_monthly'] = $infoData['maxOnMonthly'];
            if(!empty($infoData['maxOnPercent'])) $data['max_on_percent'] = $infoData['maxOnPercent'];
            if(!empty($infoData['totalTransactionLimit'])) $data['total_transaction_limit'] = $infoData['totalTransactionLimit'];
            if(!empty($infoData['transactionLimitType'])) $data['transaction_limit_type'] = $infoData['transactionLimitType'];
            if(!empty($infoData['availableTransactionLimit'])) $data['available_transaction_limit'] = $infoData['availableTransactionLimit'];
            DB::table('ba_cards_info')->where('account_id',$accountId)->where('card_id',$cardId)->update($data);
        } catch (\Throwable $th) {
            return ['code'=>0,'msg'=>$th->getMessage()];
        }
        $row = Db::table('ba_cards_info')->where('card_id',$cardId)->find();
        $row['cvv'] = $infoData['cvv']??'';
        $row['expiration_date'] = $infoData['expirationDate']??'';
        return ['code'=>1,'msg'=>'','data'=>$row];
    }

    public function updateCard($cards,$params)
    {
        $param = [];
        if(!empty($params['nickname'])) $param['nickname'] = $params['nickname'];
        if(!empty($params['max_on_daily'])) $param['max_on_daily'] = $params['max_on_daily'];
        if(!empty($params['max_on_monthly'])) $param['max_on_monthly'] = $params['max_on_monthly'];
        if(!empty($params['max_on_percent'])) $param['max_on_percent'] = $params['max_on_percent'];
        if(!empty($params['transaction_limit_type'])) $param['transaction_limit_type'] = $params['transaction_limit_type'];
        if(!empty($params['transaction_limit_change_type'])) $param['transaction_limit_change_type'] = $params['transaction_limit_change_type'];
        if(!empty($params['transaction_limit'])) $param['transaction_limit'] = $params['transaction_limit'];
        if(!empty($params['transaction_is'])){
            $param['transaction_is'] = $params['transaction_is'];   
        }else{
            $param['transaction_is'] = 0;
        }

        try {
            $accountId = $cards['account_id'];
            $cardId = $cards['card_id'];
            $param['card_id'] = $cardId;
            $result = (new CardService($accountId))->updateCard($param);
            if($result['code'] == 1){
                $resultUpdateCardsInfo = $this->updateCardsInfo($cards,$params);
                if($resultUpdateCardsInfo['code'] !=1) throw new \Exception($resultUpdateCardsInfo['msg']);
            }else{
                throw new \Exception($result['msg']);
            }
        } catch (\Throwable $th) {
            return ['code'=>0,'msg'=>$th->getMessage()];
        }
        return ['code'=>1,'msg'=>''];
    }

    

    function cardsInfo($cards,$params)
    {
        $accountId = $cards['account_id'];
        $cardId = $cards['card_id'];

        try {
            $cardInfo = (new CardService($accountId))->cardInfo(['card_id'=>$cardId]);
            if($cardInfo['code'] != 1) throw new \Exception($cardInfo['msg']);
            
            $infoData = $cardInfo['data'];
            if(!empty($params['nickname'])) DB::table('ba_cards_info')->where('card_id',$cardId)->update(['nickname'=>$infoData['nickname']]);
            
            if(!empty($params['transaction_limit_type']))
            {
                $data = [];
                if(!empty($infoData['nickname'])) $data['nickname'] = $infoData['nickname'];
                if(!empty($infoData['maxOnDaily'])) $data['max_on_daily'] = $infoData['maxOnDaily'];
                if(!empty($infoData['maxOnMonthly'])) $data['max_on_monthly'] = $infoData['maxOnMonthly'];
                if(!empty($infoData['maxOnPercent'])) $data['max_on_percent'] = $infoData['maxOnPercent'];
                if(!empty($infoData['totalTransactionLimit'])) $data['total_transaction_limit'] = $infoData['totalTransactionLimit'];
                if(!empty($infoData['transactionLimitType'])) $data['transaction_limit_type'] = $infoData['transactionLimitType'];
                if(!empty($infoData['availableTransactionLimit'])) $data['available_transaction_limit'] = $infoData['availableTransactionLimit'];
                DB::table('ba_cards_info')->where('account_id',$accountId)->where('card_id',$cardId)->update($data);
            }
        } catch (\Throwable $th) {
            return ['code'=>0,'msg'=>$th->getMessage()];
        }
        return ['code'=>1,'msg'=>''];
    }

    





}