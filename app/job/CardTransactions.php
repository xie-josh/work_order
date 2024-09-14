<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class CardTransactions
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue CardTransactions
        sleep(1);
        
        if($data['platform'] == 'photonpay')
        {
            $this->photonpayCardTransactions($data);
        }elseif($data['platform'] == 'lampay')
        {
            
        }
        $job->delete();
    }

    public function failed($data)
    {
        // 任务失败时执行的逻辑，例如记录日志或发送通知
    }

    public function photonpayCardTransactions($param)
    {

        $accountId = $param['account_id'];
        $cardsId = $param['id'];
        $cardId = $param['card_id'];
        
        $pageIndex = 1;
        $pageSize = 200;
        $is_ = true;
        while($is_){
            try {
                Db::startTrans();
                $param = [
                    'page_index'=>$pageIndex,
                    'page_size'=>$pageSize,
                    'card_id'=>$cardId
                ];
                $transactions = (new CardService($accountId))->transactionDetail($param);
                if(empty($transactions['data'])){
                    DB::table('ba_cards')->where('id',$cardsId)->update(['is_transactions'=>1]);
                    $is_ = false;
                    continue;
                }

                $transactionsList = $transactions['data'];                      
                $list = $transactionsList['data'];

                $transactionsIds = array_column($list,'transactionId');

                $resultListIds = DB::table('ba_cards_transactions')->where('account_id',$accountId)->whereIn('transaction_id',$transactionsIds)->column('transaction_id');

                                    
                $dataList = [];            
                foreach($list as $v){

                    if(in_array($v['transactionId'],$resultListIds)) continue;

                    $dataList[] = [
                        'account_id'=>$accountId,
                        'cards_id'=> $cardsId,
                        'member_id'=>$v['memberId']??'',
                        'matrix_account'=>$v['matrixAccount']??'',
                        'created_at'=>$v['createdAt']??''??'',
                        'card_id'=>$v['cardId']??'',
                        'card_type'=>$v['cardType']??'',
                        'card_currency'=>$v['cardCurrency']??'',
                        'transaction_id'=>$v['transactionId']??'',
                        'origin_transaction_id'=>$v['originTransactionId']??'',
                        'request_id'=>$v['requestId']??'',
                        'transaction_type'=>$v['transactionType']??'',
                        'status'=>$v['status']??'',
                        'code'=>$v['code']??'',
                        'msg'=>$v['msg']??'',
                        'mcc'=>$v['mcc']??'',
                        'auth_code'=>$v['authCode']??'',
                        'settle_status'=>$v['settleStatus']??'',
                        'transaction_amount'=>$v['transactionAmount']??'',
                        'transaction_currency'=>$v['transactionCurrency']??'',
                        'txn_principal_change_account'=>$v['txnPrincipalChangeAccount']??'',
                        'txn_principal_change_amount'=>$v['txnPrincipalChangeAmount']??'',
                        'txn_principal_change_currency'=>$v['txnPrincipalChangeCurrency']??'',
                        'txn_principal_change_settled_amount'=>$v['txnPrincipalChangeSettledAmount']??'',
                        'settle_spread_change_account'=>$v['settleSpreadChangeAccount']??'',
                        'settle_spread_change_currency'=>$v['settleSpreadChangeCurrency']??'',
                        'fee_deduction_account'=>$v['feeDeductionAccount']??'',
                        'fee_deduction_amount'=>$v['feeDeductionAmount']??'',
                        'fee_deduction_currency'=>$v['feeDeductionCurrency']??'',
                        'fee_detail_json'=>empty($v['feeDetailJson'])?'{}':json_encode($v['feeDetailJson']),
                        'fee_return_account'=>$v['feeReturnAccount']??'',
                        'fee_return_amount'=>$v['feeReturnAmount']??'',
                        'fee_return_currency'=>$v['feeReturnCurrency']??'',
                        'fee_return_detail_json'=>empty($v['feeReturnDetailJson'])?'{}':json_encode($v['feeReturnDetailJson']),
                        'arrival_account'=>$v['arrivalAccount']??'',
                        'arrival_amount'=>$v['arrivalAmount']??'',
                        'mask_card_no'=>$v['maskCardNo']??'',
                        'merchant_name_location'=>$v['merchantNameLocation']??'',
                        'create_time'=>time()
                    ];
                }
                DB::table('ba_cards_transactions')->insertAll($dataList);
                DB::table('ba_cards')->where('id',$cardsId)->update(['is_transactions'=>1]);
    
                $pageIndex ++;
                if($pageSize > $transactionsList['numbers']){
                    $is_ = false;
                } 
                //echo $pageIndex;
                Db::commit();
            } catch (\Throwable $th) {
                Db::rollback();
                $logs = '错误:('.$th->getLine().')'.json_encode($th->getMessage());
                $is_ = false;
                DB::table('ba_cards')->where('id',$cardsId)->update(['is_transactions'=>2,'update_time'=>time(),'transactions_logs'=>$logs]);
            }
            return true;
        }
    }

}
