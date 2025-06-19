<?php

namespace app\api\controller;

use think\Request;
use think\facade\Log;
use app\common\controller\Frontend;
use think\facade\Db;

class SlashCallback extends Frontend
{

    protected array $noNeedLogin = ['handleCallback'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function handleCallback(Request $request)
    {
        //TODO...  该数据没有鉴权，后面补充
        // 获取参数
        $params = $request->param();
        $header = $request->header();
    
        Log::info('Received slash callback1:'.json_encode($params));
        Log::info('Received slash callback Header:'.json_encode($header));
        if(empty($params))throw new \Exception("no callback Info");
        try {
            $slash = DB::table('ba_card_account')->where('id',6)->find();
            $slashServer = new \app\api\cards\Slash($slash);
            // $cardsDeli = $slashServer->getCardDetails($params);
            // dd($cardsDeli);
            if($params['event'] == 'card.update' || $params['event'] == 'card.delete')
            {
                if(isset($params['entityId'])){
                    $card = DB::table('ba_cards')->where('card_id',$params['entityId'])->find();
                    if(empty($card)) throw new \Exception("no cards");
                    $cardsDeli = $slashServer->getCardDetails($params);
                }
                $this->cardStatusUpdateCallback($card,$cardsDeli['data']);
            }
            if($params['event'] == 'aggregated_transaction.create' || $params['event'] == 'aggregated_transaction.update')
            {
                if(isset($params['entityId'])){
                    $transactions = $slashServer->transactionGetIdDetail($params);
                    $cardId = $transactions['data']['cardId'];
                    $cards = DB::table('ba_cards')->where('card_id',$cardId)->find();
                    if(empty($cards)) throw new \Exception("no cards");
                }
                $this->transactionDetailCallback($cards,$transactions['data']);
            }
        } catch (\Throwable $th) {
            $logs = 'callback错误：'.'('.$th->getLine().')'.json_encode($th->getMessage());
            $data = json_encode($header).json_encode($params);
            Db::table('ba_cards_logs')->insert(['type'=>'callback','data'=>$data,'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]);
            return response('', 204);
        }
        // 记录日志
        // Log::info('Received callback', $params);
        // Log::info(json_encode($params));
        // Log::info(json_encode($header));
        // 返回成功响应
        return response('', 204);
    }


    public function cardStatusUpdateCallback($cards,$params)
    {
    //    $params = array:15 [
    //     "id" => "c_2yt0ych8czbm7"
    //     "name" => "664223951406539"
    //     "last4" => "6231"
    //     "accountId" => "sa_group_3smgrydqeq05"
    //     "virtualAccountId" => "subaccount_2q31v8i63i6mh"
    //     "expiryYear" => "2029"
    //     "expiryMonth" => "03"
    //     "cardGroupId" => "card_group_h3uoxmd6h9k5"
    //     "createdAt" => "2025-06-13T06:42:32.826Z"
    //     "isPhysical" => false
    //     "isSingleUse" => false
    //     "status" => "active"
    //     "spendingConstraint" => array:1 [
    //       "spendingRule" => array:2 [
    //         "utilizationLimit" => array:3 [
    //           "limitAmount" => array:1 [
    //             "amountCents" => 500000
    //           ]
    //           "preset" => "collective"
    //           "startDate" => "2025-06-13"
    //         ]
    //         "utilizationLimitV2" => array:1 [
    //           0 => array:3 [
    //             "limitAmount" => array:1 [
    //               "amountCents" => 500000
    //             ]
    //             "preset" => "collective"
    //             "startDate" => "2025-06-13"
    //           ]
    //         ]
    //       ]
    //     ]
    //     "userData" => null
    //     "cardProductId" => "card_product_21srtczdagp7p"
    //   ]
        $status = ['active'=>'normal','paused'=>'frozen','inactive'=>'frozen','closed'=>'cancelled'];
        DB::table('ba_cards')->where('id',$cards['id'])->update(['card_status'=>$status[$params['status']]??'','update_time'=>time()]);
        DB::table('ba_cards_info')->where('cards_id',$cards['id'])->update(['card_status'=>$status[$params['status']]??'']);
        return true;
    }
    
    public function transactionDetailCallback($cards,$params)
    {
        //$params = array:13 [
        //     "id" => "agg_tx_3iinl2arcvrtm"
        //     "accountId" => "sa_group_3smgrydqeq05"
        //     "amountCents" => -3500
        //     "cardId" => "c_h1hvcy5nwki"
        //     "date" => "2025-06-17T02:12:44.699Z"
        //     "accountSubtype" => "credit"
        //     "description" => "Facebook"
        //     "merchantDescription" => "FACEBK *6X9X5TGFK2"
        //     "merchantData" => array:4 [
        //       "categoryCode" => "7311"
        //       "description" => "FACEBK *6X9X5TGFK2"
        //       "location" => array:4 [
        //         "city" => "650-5434800"
        //         "country" => "US"
        //         "state" => "CA"
        //         "zip" => "94025    "
        //       ]
        //       "name" => "Facebook"
        //     ]
        //     "status" => "pending"
        //     "detailedStatus" => "pending"
        //     "originalCurrency" => array:3 [
        //       "amountCents" => 3500
        //       "code" => "USD"
        //       "conversionRate" => 1
        //     ]
        //     "authorizedAt" => "2025-06-17T02:12:43.591Z"
        //   ]

        $accountId = $cards['account_id'];
        $cardsId   = $cards['id'];
        $transactionType = ['pending'=>'auth','failed'=>'auth','settled'=>'auth','declined'=>'auth','reversed'=>'void','dispute'=>'corrective','returned'=>'void',
        'pending_approval'=>'verification','canceled'=>'void','refund'=>'refund'];
        $status = ['posted'=>'succeed','pending'=>'authorized','failed'=>'failed'];
            $transactionsId = $params['id']??0;
            $transactions = DB::table('ba_cards_transactions')->field('id')->where('account_id',$accountId)->where('transaction_id',$transactionsId)->find();
             if($transactions) return true;
             $orAmountCents = 0;
             if(isset($params['originalCurrency']['amountCents']))
             {
                if($params['originalCurrency']['amountCents']>0){
                    $orAmountCents =  abs($params['originalCurrency']['amountCents'])/100;
                 }else{
                    $orAmountCents =  '-'.abs($params['originalCurrency']['amountCents'])/100;
                 }
             }
             $data = [
                 'account_id'=>$accountId,
                 'cards_id'=> $cardsId,
                 'member_id'=>$params['memberId']??'',
                 'matrix_account'=>$params['matrixAccount']??'',
                 'created_at'=>date('Y-m-d H:i:s',strtotime($params['date'])),
                 'card_id'=>$params['cardId']??'',
                 'card_type'=>$params['cardType']??'',
                 'card_currency'=>$params['cardCurrency']??'',
                 'transaction_id'=>$params['id']??'',
                 'origin_transaction_id'=>$params['originTransactionId']??'',
                 'request_id'=>$params['requestId']??'',
                 'transaction_type'=>$transactionType[$params['detailedStatus']]??'',
                 'status'=>$status[$params['status']]??'',
                 'code'=>$params['code']??'',
                 'msg'=>$params['msg']??'',
                 'mcc'=>$params['mcc']??'',
                 'auth_code'=>$params['authCode']??'',
                 'settle_status'=>$params['settleStatus']??'',
                 'transaction_amount'=> (abs($params['amountCents'])/100)??0,
                 'transaction_currency'=>"USD",
                 'txn_principal_change_account'=>$params['txnPrincipalChangeAccount']??'',
                 'txn_principal_change_amount'=>$orAmountCents,
                 'txn_principal_change_currency'=>$params['originalCurrency']['code']??"",
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
                 'merchant_name_location'=>$params['merchantDescription'],
                 'create_time'=>time()
             ];
            DB::table('ba_cards_transactions')->insert($data);
            return true;
    }
}
