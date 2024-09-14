<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class CardQuLogs
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue CardQuLogs


        /**
         * 1.限额（绑/充） + 卡
         * 2.修改昵称 + 卡
         * 3.冻卡（清零） + 卡
         * 4.解冻 + 卡
         */

        try {
            $cardId = $data['card_id'];
            $cardInfo = (new CardService(2))->cardInfo(['card_id'=>$cardId]);
            $cardLogs =  DB::table('ba_cards_queue_logs')->where('id',$data['id'])->find();
            $cardLogsData = json_decode($cardLogs['data'],true);
            $result = $cardInfo['data'];

            $type = $data['type'];
            $updateData = [];
            $is_ = false;
            if($cardLogs['type'] == 1){
                $transactionLimit =$cardLogsData['transaction_limit'];
                $maxOnPercent =$cardLogsData['max_on_percent'];

                $result = $cardInfo['data'];

                if($maxOnPercent == $result['maxOnPercent'] && $transactionLimit == $result['totalTransactionLimit']){
                    $is_ = true;
                    $updateData=[
                        'total_transaction_limit'=>$result['totalTransactionLimit'],
                        'max_on_percent'=>$result['maxOnPercent'],
                        // 'available_transaction_limit'=>'',
                    ];
                }
                
            }elseif($cardLogs['type'] == 2){
                $nickname =$cardLogsData['nickname'];
                if($nickname == $result['nickname']){
                    $is_ = true;
                    $updateData = [
                        'nickname'=>$result['nickname']
                    ];
                }
            }elseif($cardLogs['type'] == 3){
                $cardStatus = 'frozen';
                if($cardStatus == $result['cardStatus']){
                    $is_ = true;
                    $updateData=[
                        'card_status'=>$result['cardStatus']
                    ];
                }
                
            }elseif($cardLogs['type'] == 4){
                $cardStatus = 'normal';
                if($cardStatus == $result['cardStatus']){
                    $is_ = true;
                    $updateData=[
                        'card_status'=>$result['cardStatus']
                    ];
                }
            }
    
            if($is_){
                DB::table('ba_cards_queue_logs')->where('id',$data['id'])->update(['status'=>1]);
                if(!empty($updateData)) DB::table('ba_cards_info')->where('card_id',$cardId)->update($updateData);
                $job->delete();
            }

        } catch (\Throwable $th) {

            if ($job->attempts() >= 3) {
                // 超过3次，删除任务
                $job->delete();
            }
            //throw $th;
        }
        if ($job->attempts() >= 3) {
            // 超过3次，删除任务
            $job->delete();
        }
        
    }

    public function failed($data)
    {
        // 任务失败时执行的逻辑，例如记录日志或发送通知
    }

}
