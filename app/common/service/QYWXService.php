<?php

namespace app\common\service;

use GuzzleHttp\Client;

class QYWXService
{
    public function send($params,$type=1)
    {
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=459ac1da-be37-4d63-b905-9952f5b1d161';
        $header = [
            'Content-Type' => 'application/json',
        ];
        $id = $params['account_id'];        
        $typeName = $this->rechargeType($type);
        $data = [
            "msgtype"=>"markdown", 
            "markdown"=>[
                "content"=> "你有新的 [$typeName] 需求待处理。\n
                >CZ:<font color=\"comment\">($id)</font>"
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
        $url = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=10187bfa-6ce0-46f5-98ab-03ee2242846a';
        $header = [
            'Content-Type' => 'application/json',
        ];
        $id = $params['account_id'];        
        $typeName = $this->bmType($type);
        $data = [
            "msgtype"=>"markdown", 
            "markdown"=>[
                "content"=> "你有新的 [$typeName] 需求待处理。\n
                >BM:<font color=\"comment\">($id)</font>"
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

    
    //推送开户成功通知
    function send_notification($bm): array
    {
        $url = "https://api.worktool.ymdyes.cn/wework/sendRawMessage?robotId=wtxhu6fdf3pk0wj2wq8g50v86kaznydd";
        $weixinUrl = 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=7e98597c-c085-445a-ac48-f1aaa87fbcd4';
        $comment = "";
        if(!empty($bm['comment']))  $comment = "备注：".$bm['comment'];
        $messageStr = "您的开户申请已完成！\n账户名称：".$bm['account_name']." \n账户ID：".$bm['account_id']."\n已绑定至以下邮箱/BM：\n".$bm['bm']."\n$comment";
        $param = [
            'socketType'=>'2',
            'list' => [[
                'type' =>'203',
                'titleList'=>[$bm['notice_group']],
                'receivedContent'=>$messageStr
                ]]
        ];
        $result =  (new \app\api\cards\Backend())->curlHttp($url,'POST',['Content-Type'=>'application/json'],$param);
        if($result['code']!=200)
        {
            $data = [
                "msgtype"=>"markdown", 
                "markdown"=>[
                    "content"=> "用户".$bm['account_name']."->>发送开户通知失败！请检查机器人是否在线and授权是否到期！"
                ]
            ];
            $client = (new Client(['verify' => false,'headers'=>['Content-Type' => 'application/json']]));
            $response = $client->request('POST', $weixinUrl,['body'=>json_encode($data)]);
            $data = $response->getBody()->getContents();
        }
        return [];
    }
}