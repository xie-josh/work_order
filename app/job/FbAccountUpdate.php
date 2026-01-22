<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Queue;
set_time_limit(600);

class FbAccountUpdate
{
    public function fire(Job $job, $data)
    {
        set_time_limit(300);
        //php think queue:listen --queue FbAccountUpdate
        //sleep(1);

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
        try {
            $businessId = $params['business_id'];
            $id = $params['id'];

            $_is = true;
            // if($params['type'] == 1){
            //     $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(1,$params['personalbm_token_ids']);
            //     $params['token'] = $token;
            // }else if($params['type'] == 2){
            //     $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken(2,$params['personalbm_token_ids']);
            //     $params['token'] = $token;
            // }
            $token = (new \app\admin\services\fb\FbService())->getPersonalbmToken($params['personalbm_token_ids']);
            $params['token'] = $token;
            
            while ($_is) {
                $params['account_status'] = 2;
                $result = (new \app\services\FacebookService())->list($params);
                if(empty($result) || $result['code'] == 0) return true;
                
                $params['after'] = $result['data']['after']??''; 
                $params['pageSize'] = $result['data']['pageSize']??0;
                $params['total'] = $result['data']['total']??0;
                
                if(empty($params['after']) || $params['total'] < $params['pageSize']) $_is = false;

                if(empty($result['data']['data'])){
                    $_is = false;
                    return true;
                } 

                $accountList = [];
                $currencyAccountList = [];
                foreach($result['data']['data'] as $item)
                {   
                    if($item['account_status'] != 2) continue;
                    $item['id'] = str_replace('act_', '', $item['id']);
                    $accountList[] = $item;
                    // $currencyAccountList[$item['currency']][] = $item['id'];
                }

                $accountIds = array_column($accountList,'id');

                $cardList = DB::table('ba_accountrequest_proposal')
                ->alias('accountrequest_proposal')
                ->field('accountrequest_proposal.close_time,accountrequest_proposal.account_id,cards_info.card_no,cards_info.card_status,cards_info.card_id,cards_info.account_id cards_account_id,cards_info.cards_id,accountrequest_proposal.account_status')
                ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=accountrequest_proposal.cards_id')
                ->whereIn('accountrequest_proposal.account_id',$accountIds)
                ->select()->toArray();

                $accountrequestProposalClose = [];
                $accountrequestProposalCloseIs = [];
                // $errorList = [];
                foreach($cardList as $v){

                    // if($v['account_status'] != '2'){
                    //     $errorList[] = [
                    //         'log_id'=>$v['account_id'],
                    //         'type'=>'FB_accountStatus',
                    //         'data'=>'',
                    //         'logs'=>'账户状态发生变更【封户/冻卡】',
                    //         'create_time'=>date('Y-m-d H:i:s',time())
                    //     ];
                    // }

                    $closeTime = $v['close_time']??'';
                    if(empty($closeTime)) $accountrequestProposalClose[] = $v['account_id'];
                    if(!empty($closeTime) && strtotime($closeTime . ' +3 days') < time()) $accountrequestProposalCloseIs[] = $v['account_id'];

                    if(empty($v['card_status']) || $v['card_status'] != 'normal' || $v['cards_account_id'] == 2) continue;

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
                    (new \app\admin\services\card\Cards())->allCardFreeze($v['account_id']);
                }
                
                // foreach($currencyAccountList as $k => $v){
                //     //DB::table('ba_accountrequest_proposal')->whereIn('account_id',$v)->where('status',1)->update(['currency'=>$k]);
                //     $where = [
                //         ['accountrequest_proposal.account_id','IN',$v],
                //         ['accountrequest_proposal.status','=',1],
                //         ['account.status','=',4],
                //     ];
                //     DB::table('ba_accountrequest_proposal')
                //     ->alias('accountrequest_proposal')
                //     ->leftJoin('ba_account account','account.account_id=accountrequest_proposal.account_id')->where($where)->update(['accountrequest_proposal.currency'=>$k]);
                // }
                
                $accountIds2 = DB::table('ba_accountrequest_proposal')->whereIn('account_status',[1,3])->whereIn('account_id',$accountIds)->column('account_id');
                if(!empty($accountIds2)) $this->accountClear($accountIds2);

                DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountrequestProposalClose)->update(['close_time'=>date('Y-m-d',time())]);
                // DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountrequestProposalCloseIs)->update(['pull_status'=>2]);
                //DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['account_status'=>2,'bm_token_id'=>$id,'pull_account_status'=>date('Y-m-d H:i',time())]);
                DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->update(['account_status'=>2,'pull_account_status'=>date('Y-m-d H:i',time())]);
                // DB::table('ba_accountrequest_proposal')->whereIn('account_id',$accountIds)->where([['account_status','<>','2']])->update(['processing_status'=>0]);              
                
            }
            
        } catch (\Throwable $th) {
            $logs = '错误info('.$businessId .'):('.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            DB::table('ba_fb_logs')->insert(
                ['log_id'=>$id??'','type'=>'job_FbAccountUpdate','data'=>json_encode($params),'logs'=>$logs,'create_time'=>date('Y-m-d H:i:s',time())]
            );
        }
        return true;
    }

    public function accountClear($accountIds){
        //活跃 变 封户
        /**
         * 1.没有充值需求时，直接跳过
         * 1.最后一个充值需求是否是清零
         *    是：
         *      continue;
         * 
         *    否：
         *       1.统计所有改账户的需求
         *       2.求和所有“充值需求”的金额
         *       3.“扣款取消”与“充值需求”状态更新为失败
         *       4.把充值需求所有钱退回到对应账号
         *       
         * 
         *       最后是不是完成状态：
         *          是：
         *              是且是充值需求时：生成异步清零需求
         *          否：
         * 
         * 
         * 1.有未完成的充值需求-需要退回与修改状态
         * 2.有未完成的扣款取消-修改状态
         * 
         * 
         * 
         */

        $recharge = (new \app\admin\model\demand\Recharge());
        foreach($accountIds as $accountId){
            $id = 0;
            $accountClear = $recharge->order('id','desc')->field('type,admin_id,status,account_name')->where('account_id',$accountId)->find();
            if(empty($accountClear)) continue;
            if(in_array($accountClear['type'],[1,2]))
            {
                $account = DB::table('ba_account')->where('account_id',$accountId)->field('money,team_id')->find();                

                if(!empty($account) && $account['money'] > 1)
                {
                    $data = [
                        "type" => "3",
                        "number" => 0,
                        "account_id" => $accountId,
                        "admin_id" => $accountClear['admin_id'],
                        "account_name" => $accountClear['account_name'],
                        "add_operate_user"=>1,
                        "team_id"=> $account['team_id'],
                        'create_time'=>time()
                    ];
                    $id = $recharge->insertGetId($data);
                    if(!empty($id)) $this->rechargeJob($id);
                }
            }
        }        
        return true;
    }

    public function rechargeJob($id)
    {        
        $jobHandlerClassName = 'app\job\AccountSpendDelete';
        $jobQueueName = 'AccountSpendDelete';
        Queue::later(259200, $jobHandlerClassName, ['id'=>$id], $jobQueueName);        
        return true;
    }

}
