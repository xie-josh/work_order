<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
set_time_limit(3600);

class CardList
{
    public function fire(Job $job, $data)
    {
        try {
            //php think queue:listen --queue CardList
            if($data['platform'] == 'photonpay')
            {
                $this->photonpayCardList($data);
            }elseif($data['platform'] == 'lampay'){
                $this->lampayCardList($data);
            }elseif($data['platform'] == 'airwallex' || $data['platform'] == 'airwallexUs'){
                $this->airwallexCardList($data);
            }elseif($data['platform'] == 'slash'){
                $this->slashCardList($data);
            }
            $job->delete();

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
        //$acc = (new CardService($data['id']))->cardList([]);
        //dd(1,$acc);
        
        //echo '111';
        // 任务逻辑，例如发送邮件
        // $isJobDone = $this->sendEmail($data);

        // if ($isJobDone) {
        //     // 任务执行成功后删除任务
        //     $job->delete();
        // } else {
        //     // 任务执行失败时根据需要进行重新发布
        //     $job->release(3); // 延迟3秒后重新执行
        // }
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


    public function photonpayCardList($param)
    {
        $accountId = $param['id'];

        // if(empty($param['pull_time'])){
        // }else{
        //     $createdAtEnd = $param['pull_time'];
        // }
        $createdAtEnd = date('Y-m-d\TH:i:d', strtotime('-7 days'));
        //$createdAtEnd = str_replace(' ', 'T', $createdAtEnd);
        
        $pageIndex = 1;
        $pageSize = 200;
        $time = -10;
        $is_ = true;
        $result = true;
        while($is_){
            try {
                Db::startTrans();
                $param = [
                    'page_index'=>$pageIndex,
                    'page_size'=>$pageSize,
                    'from_updated_at'=>$createdAtEnd
                ];
                $cardList = (new CardService($accountId))->cardList($param);

                //dd($cardList,$param);
                $cardList = $cardList['data'];                    
                $list = $cardList['data'];

                $cardIds = array_column($list,'cardId');

                $resultListIds = DB::table('ba_cards')->where('account_id',$accountId)->whereIn('card_id',$cardIds)->column('card_id');

                                    
                $dataList = [];            
                foreach($list as $v){

                    if(in_array($v['cardId'],$resultListIds)) continue;

                    $dataList[] = [
                        'account_id'=>$accountId,
                        'created_at'=>$v['createdAt'],
                        'member_id'=>$v['memberId'],
                        'matrix_account'=>$v['matrixAccount']??'',
                        'card_id'=>$v['cardId'],
                        'card_currency'=>$v['cardCurrency'],
                        'card_scheme'=>$v['cardScheme'],
                        'card_status'=>$v['cardStatus'],
                        'card_type'=>$v['cardType'],
                        'mask_card_no'=>$v['maskCardNo'],
                        'nickname'=>$v['nickname'],
                        'card_balance'=>$v['cardBalance']??'',
                        'create_time'=>time(),
                    ];
                }
    
                DB::table('ba_cards')->insertAll($dataList);
    
                $pageIndex ++;
                if($pageSize > $cardList['numbers']){
                    $is_ = false;
                } 
                //echo $pageIndex;
                Db::commit();
            } catch (\Throwable $th) {
                Db::rollback();
                $logs = '错误:('.$th->getLine().')'.json_encode($th->getMessage());
                $result = false;
                $is_ = false;
                DB::table('ba_card_account')->where('id',$accountId)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>$logs]);
            }
        }

        if($result){
            $updatedAtMin = date('Y-m-d H:i:d', strtotime($time.' hour'));
            DB::table('ba_card_account')->where('id',$accountId)->update(['pull_time'=>$updatedAtMin,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>'']);
            //return ['code'=>1,'msg'=>'拉取完成'];
        }else{
            //return ['code'=>0,'msg'=>'拉取失败或者没有数据'];
        }
    }

    public function lampayCardList($param)
    {
        $accountId = $param['id'];
        $pageIndex = $param['pageIndex'];

        
        // $pageIndex = 1;
        $pageSize = 100;
        $is_ = true;
        $result = true;
        try {
            Db::startTrans();
            $param = [
                'page_index'=>$pageIndex,
                'page_size'=>$pageSize,
            ];
            $cardList = (new CardService($accountId))->cardList($param);

            // dd($cardList,$param);
            $cardList = $cardList['data'];                    
            $list = $cardList['data'];

            $cardIds = array_column($list,'cardBusinessId');

            $resultListIds = DB::table('ba_cards')->where('account_id',$accountId)->whereIn('card_id',$cardIds)->column('card_id');

            // dd($list);
                                
            $dataList = [];            
            foreach($list as $v){

                if(in_array($v['cardBusinessId'],$resultListIds)) continue;

                $dataList[] = [
                    'account_id'=>$accountId, 
                    'created_at'=>date('Y-m-d H:i:s',($v['createdAt'] / 1000)),
                    'member_id'=>'',
                    'matrix_account'=>'',
                    'card_id'=>$v['cardBusinessId'],
                    'card_no'=>$v['fullCardNo'],
                    'card_currency'=>'USD',
                    'card_scheme'=>$v['cardNetwork'],
                    'card_status'=>$v['cardStatus']=='ACTIVE'?'normal':'frozen',
                    'card_type'=>'',
                    'mask_card_no'=>$v['cardNumber'],
                    'nickname'=>$v['cardNickname'],
                    'card_balance'=>'',
                    'create_time'=>time(),
                ];
            }

            if(!empty($dataList)) DB::table('ba_cards')->insertAll($dataList);
            //echo $pageIndex;
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            $logs = '错误:('.$pageIndex.'-'.$th->getLine().')'.json_encode($th->getMessage());
            $result = false;
            $is_ = false;
            DB::table('ba_card_account')->where('id',$accountId)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>$logs]);
        }

        if($result){
            $updatedAtMin = '';
            DB::table('ba_card_account')->where('id',$accountId)->update(['pull_time'=>$updatedAtMin,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>'']);
            //return ['code'=>1,'msg'=>'拉取完成'];
        }else{
            //return ['code'=>0,'msg'=>'拉取失败或者没有数据'];
        }
    }

    public function airwallexCardList($param)
    {
        $accountId = $param['id'];

        if(empty($param['pull_time'])){
            $createdAtEnd = date('Y-m-d H:i:d', strtotime('-7 days'));
        }else{
            $createdAtEnd = $param['pull_time'];
        }
        $createdAtEnd = str_replace(' ', 'T', $createdAtEnd);
        
        $pageIndex = 1;
        $pageSize = 200;
        $time = -10;
        $is_ = true;
        $result = true;
        while($is_){
            try {
                Db::startTrans();
                $param = [
                    'page_index'=>$pageIndex,
                    'page_size'=>$pageSize,
                    'from_updated_at'=>date('c', strtotime('-7 days')),
                    'to_updated_at'=>date('c')
                ];
                $cardList = (new CardService($accountId))->cardList($param);

                //dd($cardList,$param);
                $cardList = $cardList['data'];
                $list = $cardList['data'];

                $cardIds = array_column($list,'card_id');

                $resultListIds = DB::table('ba_cards')->where('account_id',$accountId)->whereIn('card_id',$cardIds)->column('card_id');

                                    
                $dataList = [];            
                foreach($list as $v){

                    if(in_array($v['card_id'],$resultListIds)) continue;

                    $cardStatus = ['ACTIVE'=>'normal','PENDING'=>'pending_recharge','INACTIVE'=>'frozen','BLOCKED'=>'risk_frozen','LOST'=>'risk_frozen','STOLEN'=>'risk_frozen','CLOSED'=>'cancelled','FAILED'=>'unactivated'];

                    $dataList[] = [
                        'account_id'=>$accountId,
                        'created_at'=>date('Y-m-d H:i:s',strtotime($v['created_at'])),
                        'member_id'=>'',
                        'matrix_account'=>'',
                        'card_id'=>$v['card_id'],
                        'card_no'=>'',
                        'card_currency'=>'',
                        'card_scheme'=>$v['brand'],
                        'card_status'=>$cardStatus[$v['card_status']],
                        'card_type'=>'share',
                        'mask_card_no'=>$v['card_number']??'',
                        'nickname'=>$v['nick_name']??'',
                        'card_balance'=>'',
                        'create_time'=>time(),
                    ];
                }
    
                DB::table('ba_cards')->insertAll($dataList);
    
                $pageIndex ++;
                if($pageSize > count($list)){
                    $is_ = false;
                } 
                //echo $pageIndex;
                Db::commit();
            } catch (\Throwable $th) {
                Db::rollback();
                $logs = '错误:('.$th->getLine().')'.json_encode($th->getMessage());
                $result = false;
                $is_ = false;
                DB::table('ba_card_account')->where('id',$accountId)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>$logs]);
            }
        }

        if($result){
            $updatedAtMin = date('Y-m-d H:i:d', strtotime($time.' hour'));
            DB::table('ba_card_account')->where('id',$accountId)->update(['pull_time'=>$updatedAtMin,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>'']);
            //return ['code'=>1,'msg'=>'拉取完成'];
        }else{
            //return ['code'=>0,'msg'=>'拉取失败或者没有数据'];
        }
    }

    public function slashCardList($param)
    {
        $accountId = $param['id'];

        $createdAtEnd = date('Y-m-d\TH:i:d', strtotime('-7 days'));
        
        $cardStatus = ['active'=>'normal','inactive'=>'frozen','closed'=>'cancelled','paused'=>'frozen'];
        $pageSize = 100;
        $is_ = true;
        $result = true;
        $time = -10;
        $cursor = '';
        while($is_){
            try {
                Db::startTrans();
                $param = [
                    'cursor'=>$cursor,
                ];
                $cardList = (new CardService($accountId))->cardList($param);
                
                $cardList = $cardList['data'];
                $list = $cardList['data'];
                if(empty($list)) 
                {
                    $is_ = false;
                    continue;
                }


                $cardIds = array_column($list,'id');

                $resultListIds = DB::table('ba_cards')->where('account_id',$accountId)->whereIn('card_id',$cardIds)->column('card_id');

                       
                $dataList = [];            
                foreach($list as $v){

                    if(in_array($v['id'],$resultListIds)) continue;

                    $dataList[] = [
                        'account_id'=>$accountId,
                        'created_at'=> date('Y-m-d H:i:s',strtotime($v['createdAt'])),
                        'member_id'=>'',
                        'matrix_account'=>'',
                        'card_id'=>$v['id'],
                        'card_currency'=>'',
                        'card_scheme'=>'VISA',
                        'card_status'=>$cardStatus[$v['status']],
                        'card_type'=>'',
                        'mask_card_no'=>'',
                        'nickname'=>$v['name'],
                        'card_balance'=>'',
                        'create_time'=>time(),
                    ];
                }

                DB::table('ba_cards')->insertAll($dataList);
    
                if($pageSize > $cardList['total'] || !isset($cardList['cursor'])){
                    $is_ = false;
                }else $cursor = $cardList['cursor'];

                Db::commit();
            } catch (\Throwable $th) {
                Db::rollback();
                $logs = '错误:('.$th->getLine().')'.json_encode($th->getMessage());
                $result = false;
                $is_ = false;
                DB::table('ba_card_account')->where('id',$accountId)->update(['status'=>2,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>$logs]);
            }
        }

        if($result){
            $updatedAtMin = date('Y-m-d H:i:d', strtotime($time.' hour'));
            DB::table('ba_card_account')->where('id',$accountId)->update(['pull_time'=>$updatedAtMin,'update_time'=>date('Y-m-d H:i:s',time()),'logs'=>'']);
            //return ['code'=>1,'msg'=>'拉取完成'];
        }else{
            //return ['code'=>0,'msg'=>'拉取失败或者没有数据'];
        }

        
        
    }

}
