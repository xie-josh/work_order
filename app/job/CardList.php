<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;

class CardList
{
    public function fire(Job $job, $data)
    {
        //php think queue:listen --queue CardList
        $this->photonpayCardList($data);
        $job->delete();
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

        if(empty($param['pull_time'])){
            $createdAtEnd = date('Y-m-d H:i:d', strtotime('-60 days'));
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
                    'created_st_start'=>$createdAtEnd
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

}
