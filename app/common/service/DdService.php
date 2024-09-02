<?php

namespace app\common\service;

use GuzzleHttp\Client;

class DdService
{
    public function send($params)
    {
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=e67f1183519e8c882b63e888e7b1ae870ff88e177a2b40e6a6a67104176594df';
        $header = [
            'Content-Type' => 'application/json',
            'access_token'=>'e67f1183519e8c882b63e888e7b1ae870ff88e177a2b40e6a6a67104176594df',
        ];
        $id = $params['account_id'];
        $type = '充值';
        $data = [
            "msgtype"=>"link", 
            "link"=>[
                "text"=>"你有新的 [$type] 需求待处理", 
                "title"=>"CZ: ($id)", 
                "picUrl"=>"", 
                "messageUrl"=>"https://wewallads.com/admin/demand/recharge"
            ]
        ];
        $client = (new Client(['verify' => false,'headers'=>$header]));
        $response = $client->request('POST', $url,['body'=>json_encode($data)]);
        $data = $response->getBody()->getContents();
    }
}