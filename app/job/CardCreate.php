<?php

namespace app\job;

use think\queue\Job;
use app\services\CardService;
use think\facade\Db;
use think\facade\Log;
set_time_limit(3600);

class CardCreate
{
    public function fire(Job $job, $data)
    {
        try {
            //php think queue:listen --queue CardCreate
            if($data['platform'] == 'photonpay')
            {
                //$this->photonpayCardList($data);
            }elseif($data['platform'] == 'lampay'){
                //$this->lampayCardList($data);
            }elseif($data['platform'] == 'airwallex'){
                $this->airwallexCardList($data);
            }
            $job->delete();

        } catch (\Throwable $th) {
            if ($job->attempts() >= 3) $job->delete();
        }
        if ($job->attempts() >= 3)  $job->delete();
        
    }


    public function photonpayCardList($params)
    {
        
    }

    public function lampayCardList($params)
    {
       
    }

    public function airwallexCardList($params)
    {
        $accountId = $params['account_id'];
        $param = [];
        Log::info('card create job'.json_encode($params));
        // $cardList = (new CardService($accountId))->cardCreate($param);
    }

}
