<?php

namespace app\api\controller;

use think\Request;
use think\facade\Log;
use app\common\controller\Frontend;
use think\facade\Db;

class AirwallexCallback extends Frontend
{

    protected array $noNeedLogin = ['handleCallback'];

    public function initialize(): void
    {
        parent::initialize();
    }

    public function transmit(Request $request)
    {
        $url = "https://star.wewallads.com/api/AirwallexCallback/handleCallback?server=1";
        // $url = "http://47.243.243.112:10083/api/AirwallexCallback/testssss?server=1";
        $backend = (new \app\api\cards\Backend());
        $result =  $backend->curlHttp($url,'POST',$request->header(),$request->param());
        if(!empty($result['roger'])) 
            return true;  
        else 
            return false; 
    }

    public function handleCallback(Request $request)
    {
        if(env('CACHE.DISTINCTION_CALLBACK')!=1)if(!$this->transmit($request)){
            return json(['roger' => false],400);
        }
        //TODO...  该数据没有鉴权，后面补充
        // 获取参数
        $params = $request->param();
        $header = $request->header();
        // Log::info('Received callback:'.json_encode($params));
        // Log::info('Received callback Header:'.json_encode($header));
        try {
            $cardId =  0;
            $is_type = '';
            if($params['name'] == 'issuing.transaction.succeeded' || $params['name'] = 'issuing.transaction.failed')
            {
                $is_type = 'is_transactions';
                $cardId = $params['data']['card_id'];
                $cards = DB::table('ba_cards')->where('card_id',$cardId)->find();
                if(empty($cards)) throw new \Exception("no cards");
                
                $this->transactionDetailCallback($cards,$params);
            }
        } catch (\Throwable $th) {       
            $logs = 'callback错误：'.'('.$th->getLine().')'.json_encode($th->getMessage());
            $data = json_encode($header).json_encode($params);
            Db::table('ba_cards_logs')->insert(['type'=>'callback','data'=>$data,'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]);
            //if(!empty($cardId) && !empty($is_type))  DB::table('ba_cards')->where('card_id',$cardId)->update([$is_type=>2,'update_time'=>time(),'info_logs'=>$logs]);
            if(env('CACHE.DISTINCTION_CALLBACK')==1)
                 return json(['roger' => false]);      //新
            else
                 return json(['roger' => false],400);  //旧
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
        $accountId = $cards['account_id'];
        $transactionData = $params['data'];

        $transactionId = $transactionData['transaction_id'];

        $transactions = DB::table('ba_cards_transactions')->field('id')->where('account_id',$accountId)->where('transaction_id',$transactionId)->find();
        if($transactions) return true;        

        $transactionType = ['AUTHORIZATION'=>'verification','CLEARING'=>'auth','REFUND'=>'refund','REVERSAL'=>'REVERSAL','ORIGINAL_CREDIT'=>'ORIGINAL_CREDIT'];
        $status = ['APPROVED'=>'succeed','PENDING'=>'authorized','FAILED'=>'failed'];

        $data = [
            'account_id'=>$cards['account_id'],
            'cards_id'=> $cards['id'],
            'member_id'=>'',
            'matrix_account'=>'',
            'created_at'=>date('Y-m-d H:i:s',strtotime($params['created_at'])),
            'card_id'=>$transactionData['card_id']??'',
            'card_type'=>'share',
            'card_currency'=>'',
            'transaction_id'=>$transactionData['transaction_id']??'',
            'origin_transaction_id'=>'',
            'request_id'=>'',
            'transaction_type'=> $transactionType[$transactionData['transaction_type']]??'',
            'status'=>$status[$transactionData['status']]??'',
            'code'=>'',
            'msg'=>'',
            'mcc'=>'',
            'auth_code'=>$transactionData['auth_code']??'',
            'settle_status'=>'',
            'transaction_amount'=>str_replace('-','',$transactionData['transaction_amount']),
            'transaction_currency'=>$transactionData['transaction_currency']??'',
            'txn_principal_change_account'=>'',
            'txn_principal_change_amount'=>$transactionData['billing_amount']??'',
            'txn_principal_change_currency'=>$transactionData['billing_currency']??'',
            'txn_principal_change_settled_amount'=>'',
            'settle_spread_change_account'=>'',
            'settle_spread_change_currency'=>'',
            'fee_deduction_account'=>'',
            'fee_deduction_amount'=>'',
            'fee_deduction_currency'=>'',
            'fee_detail_json'=>'{}',
            'fee_return_account'=>'',
            'fee_return_amount'=>'',
            'fee_return_currency'=>'',
            'fee_return_detail_json'=>'{}',
            'arrival_account'=>'',
            'arrival_amount'=>'',
            'mask_card_no'=>$transactionData['masked_card_number']??'',
            //'merchant_name_location'=>'',
            'merchant_name_location'=>($transactionData['merchant']['name']??'').','.($transactionData['merchant']['city']??'').','.($transactionData['merchant']['country']??''),
            'create_time'=>time()
        ];
        DB::table('ba_cards_transactions')->insert($data);
        return true;
    }

    

}
