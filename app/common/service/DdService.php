<?php

namespace app\common\service;

use GuzzleHttp\Client;

class DdService
{
    public function send($params,$type=1)
    {
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=e67f1183519e8c882b63e888e7b1ae870ff88e177a2b40e6a6a67104176594df';
        $header = [
            'Content-Type' => 'application/json',
            'access_token'=>'e67f1183519e8c882b63e888e7b1ae870ff88e177a2b40e6a6a67104176594df',
        ];
        $id = $params['account_id'];        
        $typeName = $this->rechargeType($type);
        $data = [
            "msgtype"=>"link", 
            "link"=>[
                "text"=>"你有新的 [$typeName] 需求待处理", 
                "title"=>"CZ: ($id)", 
                "picUrl"=>"", 
                "messageUrl"=>"https://wewallads.com/admin/demand/recharge"
            ]
        ];
        $client = (new Client(['verify' => false,'headers'=>$header]));
        $response = $client->request('POST', $url,['body'=>json_encode($data)]);
        $data = $response->getBody()->getContents();
    }

    function rechargeType($type){
        switch ($type) {
            case '1':
                $typeName = '充值';
                break;
            case '2':
                $typeName = '扣款';
                break;
            default:
                $typeName = '未知';
                break;
        }

        return $typeName;
    }


    public function bmSend($params,$type=1)
    {
        $url = 'https://oapi.dingtalk.com/robot/send?access_token=35914e34e8692ddf1f2ec845ab18eba4065a13f23f043321d916f9103c2ebcac';
        $header = [
            'Content-Type' => 'application/json',
            'access_token'=>'35914e34e8692ddf1f2ec845ab18eba4065a13f23f043321d916f9103c2ebcac',
        ];
        $id = $params['account_id'];        
        $typeName = $this->bmType($type);
        $data = [
            "msgtype"=>"link", 
            "link"=>[
                "text"=>"你有新的 [$typeName] 需求待处理", 
                "title"=>"BM: ($id)", 
                "picUrl"=>"", 
                "messageUrl"=>"https://wewallads.com/admin/demand/bm"
            ]
        ];
        $client = (new Client(['verify' => false,'headers'=>$header]));
        $response = $client->request('POST', $url,['body'=>json_encode($data)]);
        $data = $response->getBody()->getContents();
    }

    function bmType($type){
        switch ($type) {
            case '1':
                $typeName = '绑定';
                break;
            case '2':
                $typeName = '解绑';
                break;
            case '3':
                $typeName = '全部解绑';
                break;
            case '4':
                $typeName = '开户绑定';
                break;
            default:
                $typeName = '未知';
                break;
        }
        return $typeName;
    }
}