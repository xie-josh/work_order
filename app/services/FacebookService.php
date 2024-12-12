<?php
namespace app\services;

use GuzzleHttp\Client;
use think\facade\Db;

class FacebookService
{


    public function list($params)
    {
        // $businessId = '572445614639900';
        // $token  = 'EAASjp4c5uZAUBO2serO8D4pSBsv5CP3rBrMAsC22IU4q9pLeeKil31N9OaQhVzScFRNRDmrLyZAQzWoEKXHbgFVPoaBcKZAPbJk4OqVzEdl7HEo3TECFWAd28PZCyc3URTlAEWUU73FhlOBNJEJ680xCHWhtYXz2s95rKXG4IXhRjHMjT2MImWOhhBZB2dZCFL';
        try {
            $businessId = $params['business_id'];
            $token = $params['token'];
            if(empty($businessId)) throw new \Exception("未找到管理BM");
            
            $param = [
                'fields'=>'id,name,account_status,amount_spent,currency,created_time',
                'limit'=>2550
            ];
            $url = "https://graph.facebook.com/v21.0/{$businessId}/client_ad_accounts";
            $method = 'get';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['data'])){
                $data = [
                    'data' => $result['data'],
                    'pageSize' => 2550,
                    'pageIndex' => 1,
                    'total' => count($result['data']),
                    // 'numbers' => $result['numbers'],
    
                ];
                return $this->returnSucceed($data);
            }else{
                DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$result['msg']]);
                return $this->returnError($result['msg']);
            }
        } catch (\Throwable $th) {
            DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$th->getMessage()]);
            return $this->returnError($result['msg']);
        }
    }

    public function insights($params)
    {
        try {
            $accountId = $params['account_id'];
            $token = $params['token'];
            $businessId = $params['business_id'];

            if(empty($businessId)) throw new \Exception("未找到管理BM");
            
            $param = [
                'fields'=> 'account_name,account_id,spend',
                'level'=> 'account',
                'time_range'=> ["since"=>"2024-12-01","until"=>"2024-12-13"],
                'time_increment'=> '1',
                //'date_preset'=>'last_7d',
                //'limit'=>2550
            ];
            $url = "https://graph.facebook.com/v21.0/act_{$accountId}/insights";
            $method = 'get';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['data'])){
                $data = [
                    'data' => $result['data'],
                    //'pageSize' => 2550,
                    //'pageIndex' => 1,
                    'total' => count($result['data']),
                    // 'numbers' => $result['numbers'],
    
                ];
                return $this->returnSucceed($data);
            }else{
                DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$result['msg']]);
                return $this->returnError($result['msg']);
            }
        } catch (\Throwable $th) {
            DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$th->getMessage()]);
            return $this->returnError($result['msg']);
        }
    }


    public function curlHttp(string $url,string $method='GET',array $header = ['Accept' => 'application/json'],array $data=[])
    {
        try {
            //code...
            $client = new Client(['verify' => false,'headers'=>$header]);
            if($method == 'GET' || $method == 'get'){
                $response = $client->request('GET', $url,['query'=>$data]);
            }elseif($method == 'POST'){
                $response = $client->request('POST', $url,['body'=>json_encode($data)]);
            }elseif($method == 'POST1'){
                $response = $client->request('POST', $url);
            }elseif($method == 'POST2'){
                $response = $client->request('POST', $url,['form_params'=>$data]);
            }elseif($method == 'PUT'){
                $response = $client->request('PUT', $url,['form_params'=>$data]);
            }elseif($method == 'DELETE'){
                $response = $client->request('DELETE', $url,['body'=>json_encode($data)]);
            }
    
            $result = $response->getBody()->getContents();

            $result = json_decode($result, true);
            return $result;

        } catch (\Throwable $th) {
            $responseBody = $th->getResponse()->getBody()->getContents();
            if(!empty($responseBody)){
                return ['code'=>0,'msg'=>$responseBody];
            }else{
                return ['code'=>0,'msg'=>$th->getMessage()];
            }
        }
    }

    public function returnError(String $msg='error',Array $data = [])
    {
        return [
            'code'=>0,
            'msg'=>$msg,
            'data'=>$data
        ];
    }
     public function returnSucceed(Array $data = [])
    {
        return [
            'code'=>1,
            'msg'=>'succeed',
            'data'=>$data
        ];
    }
}