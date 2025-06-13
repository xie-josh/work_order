<?php

namespace app\api\controller;

use think\Request;
use think\facade\Log;
use app\common\controller\Frontend;
use think\facade\Db;

class CardCallback extends Frontend
{

    protected array $noNeedLogin = ['handleCallback'];

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
    * 发卡交易类型:
    * auth	消费
    * corrective_auth	纠正授权
    * verification	验证
    * void	撤销
    * refund	退款
    * corrective_refund	校正退款
    * recharge	转入
    * recharge_return	卡金额退还
    * discard_recharge_return	销卡退回
    * service_fee	服务费
    * refund_reversal	退款撤销
    * fund_in	汇入
     * 
     */
   
     /**
      * 发卡状态:
      * normal	可用
      * pending_recharge	待转入
      * unactivated	未激活
      * freezing	冻结中
      * frozen	冻结
      * risk_frozen	风控冻结
      * system_frozen	系统冻结
      * unfreezing	解冻中
      * expired	过期
      * canceling	销卡中
      * cancelled	销卡
      */


    public function handleCallback(Request $request)
    {

        //TODO...  该数据没有鉴权，后面补充
        // 获取参数
        $params = $request->param();
        $header = $request->header();

        // {
        //     "user-agent":"okhttp\/3.14.9"
        //     "accept-encoding":"gzip"
        //     "connection":"Keep-Alive"
        //     "host":"47.243.243.112:10083"
        //     "content-length":"1207"
        //     "content-type":"application\/json; charset=utf-8"
        //     "x-pd-sign":"B8JAYOHVUayBfOVPmEc5fNikviwa6FcaZtEeY5eEEwLcqlnv79XRBwySsGumpFg9fdja8q261FhIf+OqI4xfMqsR8tJ3ZIc2hfpceHLzOQwBONBKresqKYlguj5aS\/plrXOiD41yhnv6i\/55xVwnbo8cipX3AlLB19Ha9B38Rfc="
        //     "x-pd-published-at":"1724815214543"
        //     "x-pd-notification-type":"auth"
        //     "x-pd-notification-catagory":"issuing"
        // }
        

        try {
            $cardId =  0;
            $is_type = '';
            if($header['x-pd-notification-catagory'] == 'issuing' && in_array($header['x-pd-notification-type'],["auth","corrective_auth","verification","void","refund","corrective_refund","corrective_refund_void","recharge","recharge_return","discard_recharge_return","service_fee","refund_reversal","fund_in"]))
            {
                $is_type = 'is_transactions';
                $cardId = $params['cardId'];
                $cards = DB::table('ba_cards')->where('card_id',$cardId)->find();
                if(empty($cards)) return json(['roger' => false]);

                $this->transactionDetailCallback($cards,$params);
            }elseif($header['x-pd-notification-catagory'] == 'issuing_card' && $header['x-pd-notification-type'] == 'card_status_update'){
                $cardId = $params['cardId'];
                $cards = DB::table('ba_cards')->where('card_id',$cardId)->find();
                if(empty($cards)) return json(['roger' => false]);

                $this->cardStatusUpdateCallback($cards,$params);
            }
        } catch (\Throwable $th) {       
            $logs = 'callback错误：'.'('.$th->getLine().')'.json_encode($th->getMessage());
            $data = json_encode($header).json_encode($params);
            Db::table('ba_cards_logs')->insert(['type'=>'callback','data'=>$data,'logs'=>$logs]);
            if(!empty($cardId) && !empty($is_type))  DB::table('ba_cards')->where('card_id',$cardId)->update([$is_type=>2,'update_time'=>time(),'info_logs'=>$logs]);
            return json(['roger' => false]);
        }
        // 记录日志
        // Log::info('Received callback', $params);
        // Log::info(json_encode($params));
        // Log::info(json_encode($header));
        // 返回成功响应
        return json(['roger' => true]);
    }


    public function cardStatusUpdateCallback($cards,$params)
    {


        // {
        //     "server":"1"
        //     "memberId":"2024082009984333"
        //     "matrixAccount":""
        //     "cardId":"XR1825848415234301952"
        //     "cardStatus":"frozen"
        //     "updatedAt":"2024-08-22T03:35:31"
        // }

        DB::table('ba_cards')->where('id',$cards['id'])->update(['card_status'=>$params['cardStatus'],'update_at'=>$params['updatedAt'],'update_time'=>time()]);
        DB::table('ba_cards_info')->where('cards_id',$cards['id'])->update(['card_status'=>$params['cardStatus']]);
        return true;
    }
    
    public function transactionDetailCallback($cards,$params)
    {

        // {
        //     "server":"1"
        //     "memberId":"2024082009984333"
        //     "matrixAccount":""
        //     "createdAt":"2024-08-28T03:20:14"
        //     "updatedAt":"2024-08-28T03:20:14"
        //     "transactionId":"IT1828633663118512128"
        //     "originTransactionId":""
        //     "transactionType":"auth"
        //     "cardId":"XR1826914197393379328"
        //     "requestId":""
        //     "transactionAmount":"1.5"
        //     "transactionCurrency":"USD"
        //     "status":"succeed"
        //     "merchantNameLocation":"Amazon Newyork US"
        //     "code":"0000"
        //     "msg":"succeed"
        //     "mcc":"mcc"
        //     "authCode":"462138"
        //     "cardType":"share"
        //     "txnPrincipalChangeAccount":"member"
        //     "txnPrincipalChangeCurrency":"USD"
        //     "txnPrincipalChangeAmount":"-1.500000"
        //     "feeDeductionAccount":"member"
        //     "feeDeductionCurrency":"USD"
        //     "feeDeductionAmount":"-3.000000"
        //     "feeReturnAccount":""
        //     "feeReturnCurrency":""
        //     "feeReturnAmount":"0"
        //     "feeDetailJson":{
        //         "gatewayFeeAmount":"-1"
        //         "crossBroadFeeAmount":"-1"
        //         "transactionFeeAmount":"-1"
        //     }
        //     "feeReturnDetailJson":[]
        //     "arrivalAccount":""
        //     "arrivalAmount":"0"
        // }

        $accountId = $cards['account_id'];
        $transactionId = $params['transactionId'];

        $transactions = DB::table('ba_cards_transactions')->field('id')->where('account_id',$accountId)->where('transaction_id',$transactionId)->find();
        if($transactions) return true;

        $data = [
            'account_id'=>$cards['account_id'],
            'cards_id'=> $cards['id'],
            'member_id'=>$params['memberId']??'',
            'matrix_account'=>$params['matrixAccount']??'',
            'created_at'=>$params['createdAt']??''??'',
            'card_id'=>$params['cardId']??'',
            'card_type'=>$params['cardType']??'',
            'card_currency'=>$params['cardCurrency']??'',
            'transaction_id'=>$params['transactionId']??'',
            'origin_transaction_id'=>$params['originTransactionId']??'',
            'request_id'=>$params['requestId']??'',
            'transaction_type'=>$params['transactionType']??'',
            'status'=>$params['status']??'',
            'code'=>$params['code']??'',
            'msg'=>$params['msg']??'',
            'mcc'=>$params['mcc']??'',
            'auth_code'=>$params['authCode']??'',
            'settle_status'=>$params['settleStatus']??'',
            'transaction_amount'=>$params['transactionAmount']??'',
            'transaction_currency'=>$params['transactionCurrency']??'',
            'txn_principal_change_account'=>$params['txnPrincipalChangeAccount']??'',
            'txn_principal_change_amount'=>$params['txnPrincipalChangeAmount']??'',
            'txn_principal_change_currency'=>$params['txnPrincipalChangeCurrency']??'',
            'txn_principal_change_settled_amount'=>$params['txnPrincipalChangeSettledAmount']??'',
            'settle_spread_change_account'=>$params['settleSpreadChangeAccount']??'',
            'settle_spread_change_currency'=>$params['settleSpreadChangeCurrency']??'',
            'fee_deduction_account'=>$params['feeDeductionAccount']??'',
            'fee_deduction_amount'=>$params['feeDeductionAmount']??'',
            'fee_deduction_currency'=>$params['feeDeductionCurrency']??'',
            'fee_detail_json'=>empty($params['feeDetailJson'])?'{}':json_encode($params['feeDetailJson']),
            'fee_return_account'=>$params['feeReturnAccount']??'',
            'fee_return_amount'=>$params['feeReturnAmount']??'',
            'fee_return_currency'=>$params['feeReturnCurrency']??'',
            'fee_return_detail_json'=>empty($params['feeReturnDetailJson'])?'{}':json_encode($params['feeReturnDetailJson']),
            'arrival_account'=>$params['arrivalAccount']??'',
            'arrival_amount'=>$params['arrivalAmount']??'',
            'mask_card_no'=>$params['maskCardNo']??'',
            'merchant_name_location'=>$params['merchantNameLocation']??'',
            'create_time'=>time()
        ];
        DB::table('ba_cards_transactions')->insert($data);
        return true;
    }

    

}
