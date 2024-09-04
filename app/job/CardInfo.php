<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class CardInfo
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue CardInfo
        sleep(1);
        $this->photonpayCardInfo($data);
        $job->delete();
    }

    public function failed($data)
    {
        // 任务失败时执行的逻辑，例如记录日志或发送通知
    }

    protected function sendEmail($data)
    {
        // 发送邮件的具体逻辑
        // 如果发送成功返回 true，否则返回 false
        return true;
    }


    public function photonpayCardInfo($param)
    {
        $cardsId = $param['id'];
        $accountId = $param['account_id'];
        $cardId = $param['card_id'];
        
        try {
            $result = DB::table('ba_cards_info')->where('account_id',$accountId)->where('card_id',$cardId)->find();
            if(!empty($result)) return;

            $cardList = (new CardService($accountId))->cardInfo(['card_id'=>$cardId]);
            $result = $cardList['data'];

            $data = [
                'cards_id'=>$cardsId,
                'account_id'=>$accountId,                
                'card_id'=>$result['cardId'],
                'card_no'=>$result['cardNo'],
                'card_currency'=>$result['cardCurrency'],
                'card_scheme'=>$result['cardScheme'],
                'card_status'=>$result['cardStatus'],
                'card_type'=>$result['cardType'],
                'created_at'=>$result['createdAt'],
                'member_id'=>$result['memberId'],
                'matrix_account'=>$result['matrixAccount'],
                'email'=>$result['email'],
                'expiration_date'=>$result['expirationDate']??'',
                'cvv'=>$result['cvv']??'',
                'first_name'=>$result['firstName'],
                'last_name'=>$result['lastName'],
                'mask_card_no'=>$result['maskCardNo'],
                'max_on_daily'=>$result['maxOnDaily']??'',
                'max_on_monthly'=>$result['maxOnMonthly']??'',
                'max_on_percent'=>$result['maxOnPercent']??'',
                'mobile'=>$result['mobile'],
                'mobile_prefix'=>$result['mobilePrefix'],
                'nationality'=>$result['nationality'],
                'nickname'=>$result['nickname'],
                'total_transaction_limit'=>$result['totalTransactionLimit']??'',
                'transaction_limit_type'=>$result['transactionLimitType'],
                'available_transaction_limit'=>$result['availableTransactionLimit']??'',
                'billing_address'=>$result['billingAddress'],
                'billing_address_updatable'=>$result['billingAddressUpdatable'],
                'billing_city'=>$result['billingCity'],
                'billing_country'=>$result['billingCountry'],
                'billing_postal_code'=>$result['billingPostalCode'],
                'billing_state'=>$result['billingState'],
                'card_balance'=>$result['cardBalance']??'',
                'create_time'=>time()
            ];

            DB::table('ba_cards_info')->insert($data);
            DB::table('ba_cards')->where('id',$cardsId)->update(['is_info'=>1]);
            //code...
        } catch (\Throwable $th) {
            //throw $th;
            $logs = '错误info('.$cardId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_cards')->where('id',$cardsId)->update(['is_info'=>2,'update_time'=>time(),'info_logs'=>$logs]);
        }
        return true;        
    }
}
