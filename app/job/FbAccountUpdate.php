<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class FbAccountUpdate
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue FbAccountUpdate
        sleep(1);

        $this->accountUpdate($data);
        
        $job->delete();
    }

    public function accountUpdate($params)
    {
        try {
            $businessId = $params['business_id'];

            $result = (new \app\services\FacebookService())->list($params);

            if(empty($result['data']['data'])) return true;

            $accountList = [];
            foreach($result['data']['data'] as $item)
            {            
                if($item['account_status'] != 2) continue;
                $item['id'] = str_replace('act_', '', $item['id']);
                $accountList[] = $item;
            }

            $accountIds = array_column($accountList,'id');

            $cardList = DB::table('ba_accountrequest_proposal')
            ->alias('accountrequest_proposal')
            ->field('accountrequest_proposal.account_id,cards_info.card_no,cards_info.card_status,cards_info.card_id,cards_info.account_id cards_account_id,cards_info.cards_id')
            ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
            ->whereIn('accountrequest_proposal.account_id',$accountIds)
            ->select()->toArray();

            foreach($cardList as $v){
                if(empty($v['card_status']) || $v['card_status'] != 'normal') continue;

                $result = (new CardService($v['cards_account_id']))->cardFreeze(['card_id'=>$v['card_id']]);
                if(isset($result['data']['cardStatus'])){
                    DB::table('ba_cards_info')->where('cards_id',$v['cards_id'])->update(['card_status'=>$result['data']['cardStatus']]);
                }else{
                    DB::table('ba_cards_logs')->insert([
                        'type'=>'FB_cardFreeze',
                        'data'=>json_encode($v),
                        'logs'=>$result['msg']??'',
                        'create_time'=>date('Y-m-d H:i:s',time())
                    ]);
                }
            }
        } catch (\Throwable $th) {
            $logs = '错误info('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$logs]);
        }
        return true;        
    }
}
