<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class FbAccountUnUpdate
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue FbAccountUnUpdate
        sleep(1);

        try {
            $this->accountUpdate($data);
            $job->delete();
        } catch (\Throwable $th) {

            if ($job->attempts() >= 3) {
                $job->delete();
            }
        }
        if ($job->attempts() >= 3) {
            $job->delete();
        }
    }

    public function accountUpdate($params)
    {
        set_time_limit(300);
        try {
            $businessId = $params['business_id'];
            $id = $params['id'];

            $_is = true;
            if($params['type'] == 1){
                $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(1,$id);
                $params['token'] = $token;
            }else if($params['type'] == 2){
                $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(2,$id);
                $params['token'] = $token;
            }
            while ($_is) {
                $params['account_status'] = 1;
                $result = (new \app\services\FacebookService())->list($params);
                if(empty($result) || $result['code'] == 0) return true;

                $params['after'] = $result['data']['after']??'';  
                if(empty($params['after'])) $_is = false;
                
                if(empty($result['data']['data'])){
                    $_is = false;
                    return true;
                } 

                $accountList = [];
                $currencyAccountList = [];
                $accountStatusList = [];
                foreach($result['data']['data'] as $item)
                {  
                    if(!in_array($item['account_status'],[1,3])) continue;
                    $item['id'] = str_replace('act_', '', $item['id']);
                    $accountList[] = $item;
                    $currencyAccountList[$item['currency']][] = $item['id'];
                    $accountStatusList[$item['account_status']][] = $item['id'];
                }
                $accountIds = array_column($accountList,'id');

                // $cardList = DB::table('ba_accountrequest_proposal')
                // ->alias('accountrequest_proposal')
                // ->field('accountrequest_proposal.close_time,accountrequest_proposal.account_id,cards_info.card_no,cards_info.card_status,cards_info.card_id,cards_info.account_id cards_account_id,cards_info.cards_id')
                // ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                // ->whereIn('accountrequest_proposal.account_id',$accountIds)
                // ->select()->toArray();

                // foreach($cardList as $v){
                //     if(empty($v['card_status']) || $v['card_status'] != 'frozen') continue;
                    //$result = (new CardService($v['cards_account_id']))->cardUnfreeze(['card_id'=>$v['card_id']]);
                    // if(isset($result['data']['cardStatus'])){
                    //     DB::table('ba_cards_info')->where('cards_id',$v['cards_id'])->update(['card_status'=>$result['data']['cardStatus']]);
                    // }else{
                    //     DB::table('ba_cards_logs')->insert([
                    //         'type'=>'FB_cardUnfreeze',
                    //         'data'=>json_encode($v),
                    //         'logs'=>$result['msg']??'',
                    //         'create_time'=>date('Y-m-d H:i:s',time())
                    //     ]);
                    // }
                // }
                foreach($currencyAccountList as $k => $v){
                    $where = [
                        ['accountrequest_proposal.account_id','IN',$v],
                        ['accountrequest_proposal.status','=',1],
                        ['account.status','=',4],
                    ];
                    DB::table('ba_accountrequest_proposal')
                    ->alias('accountrequest_proposal')
                    ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')->where($where)->update(['accountrequest_proposal.currency'=>$k]);
                }
                foreach($accountStatusList as $k => $v){
                    DB::table('ba_accountrequest_proposal')->whereIn('account_id',$v)->update(['account_status'=>$k,'bm_token_id'=>$id,'close_time'=>'','pull_status'=>1,'pull_account_status'=>date('Y-m-d H:i',time())]);
                }
                //DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['account_status'=>1,'bm_token_id'=>$id,'close_time'=>'','pull_status'=>1,'pull_account_status'=>date('Y-m-d H:i',time())]);
            }
        } catch (\Throwable $th) {
            $logs = '错误info_cardUnfreeze_('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$id??'','type'=>'job_FbAccountUnUpdate','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
            //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$logs]);
        }
        return true;   
    }

    public function accountInsights(){

    }
}
