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
            $accountStatus = $params['account_status']??'';
            $type = $params['type']??'';
            $before = $params['before']??'';
            $after = $params['after']??'';

            if(empty($businessId)) throw new \Exception("未找到管理BM");
            
            $param = [
                'fields'=>'id,name,account_status,amount_spent,currency,created_time,timezone_offset_hours_utc',
                'limit'=>500
            ];
            if($accountStatus == 1){
                // $param['filtering'][] =  [
                //     "field"=> "account_status",
                //     "operator"=> "NOT_EQUAL",
                //     "value"=> 101
                // ];
            }else if($accountStatus == 2){
                $param['filtering'][] =  [
                    "field"=> "account_status",
                    "operator"=> "NOT_EQUAL",
                    "value"=> 1
                ];
            }

            if(!empty($before)) $param['before'] = $before;
            if(!empty($after)) $param['after'] = $after;

            $n = 'client';
            if($type == 2) $n = 'owned';
            $url = "https://graph.facebook.com/v21.0/{$businessId}/{$n}_ad_accounts";
            $method = 'get';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['data'])){
                $data = [
                    'data' => $result['data'],
                    'pageSize' => $param['limit'],
                    'pageIndex' => 1,
                    'total' => count($result['data']),
                    'after'=>$result['paging']['cursors']['after']??''
                    // 'numbers' => $result['numbers'],
    
                ];
                return $this->returnSucceed($data);
            }else{
                $this->log('FB_list',$result['msg']??'',$params,$businessId);
                //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$result['msg']]);
                return $this->returnError($result['msg']);
            }
        } catch (\Throwable $th) {
            $this->log('FB_list',$th->getMessage(),$params,$businessId);
            //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$th->getMessage()]);
            return $this->returnError($result['msg']);
        }
    }

    public function insights($params)
    {
        try {
            $accountId = $params['account_id'];
            $token = $params['token'];
            $businessId = $params['business_id'];
            $startTime = $params['stort_time']??date('Y-m-01');
            $stopTime = $params['stop_time']??date('Y-m-t');

            //if(empty($businessId)) throw new \Exception("未找到管理BM");
            
            $param = [
                'fields'=> 'account_name,account_id,spend',
                'level'=> 'account',
                'time_range'=> ["since"=>$startTime,"until"=>$stopTime],
                'time_increment'=> '1',
                //'date_preset'=>'last_7d',
                'limit'=>100
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
                $error = json_decode($result['msg']??'',true);
                $code = $error['error']['code']??0;

                if($code == 4){
                    $this->log('FB_abnormal',$result['msg']??'',$params,$accountId);
                    return $this->returnAbnormal(4);
                }

                if($code == 200){
                    $this->log('FB_insights',$result['msg']??'',$params,$accountId);
                    return $this->returnAbnormal(5);
                }
                
                $this->log('FB_abnormal',$result['msg']??'',$params,$accountId);
                //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$result['msg']]);
                return $this->returnError($result['msg']);
            }
        } catch (\Throwable $th) {
            $this->log('FB_abnormal',$th->getMessage(),$params,$accountId);
            //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$th->getMessage()]);
            return $this->returnError($th->getMessage()); 
        }
    }


    public function insights2($params)
    {
        try {
            $accountId = $params['account_id'];
            $token = $params['token'];
            $businessId = $params['business_id'];
            $startTime = $params['stort_time']??date('Y-m-01');
            $stopTime = $params['stop_time']??date('Y-m-t');

            //if(empty($businessId)) throw new \Exception("未找到管理BM");
            
            $param = [
                'fields'=> 'account_name,account_id,spend,impressions',
                'breakdowns'=>'country',
                'level'=> 'account',
                'time_range'=> ["since"=>$startTime,"until"=>$stopTime],
                'time_increment'=> '1',
                //'date_preset'=>'last_7d',
                'limit'=>300
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
                $error = json_decode($result['msg']??'',true);
                $code = $error['error']['code']??0;

                if($code == 4){
                    $this->log('FB_abnormal',$result['msg']??'',$params,$accountId);
                    return $this->returnAbnormal(4);
                }

                if($code == 200){
                    $this->log('FB_insights',$result['msg']??'',$params,$accountId);
                    return $this->returnAbnormal(5);
                }
                
                $this->log('FB_abnormal',$result['msg']??'',$params,$accountId);
                //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$result['msg']]);
                return $this->returnError($result['msg']);
            }
        } catch (\Throwable $th) {
            $this->log('FB_abnormal',$th->getMessage(),$params,$accountId);
            //DB::table('ba_fb_bm_token')->where('business_id',$businessId)->update(['log'=>$th->getMessage()]);
            return $this->returnError($th->getMessage()); 
        }
    }

    public function adAccounts($params){
        try {
            $token = $params['token'];
            $accountId = $params['account_id']??'439626741939329';
            
            $param = [
                'fields'=> 'spend_cap,amount_spent,balance,currency,account_status',
            ];
            $url = "https://graph.facebook.com/v21.0/act_".$accountId;
            $method = 'get';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['id'])){
                //未限制FB额度 - 不可查看
                //FB数据限制，异常状态
                //冻卡异常
                //添加FB操作日志记录，记录所有操作失败的原因与参数
                
                //if(empty($result['spend_cap'])) throw new \Exception("错误: 未限制额度！");
                
                $balanceAmount = $result['spend_cap'] == 0 ? 0 : (($result['spend_cap'] - $result['amount_spent']) / 100);
                $data = [
                    "spend_cap" => $result['spend_cap'] == 0 ? 0 : $result['spend_cap'] / 100,
                    "amount_spent" => $result['amount_spent'] == 0 ? 0 : $result['amount_spent'] / 100,
                    'balance_amount'=>$balanceAmount,
                    "balance" => $result['balance'],
                    "currency" => $result['currency'],
                    "account_status" => $result['account_status'],
                    "id" => $accountId,
                ];
                return $this->returnSucceed($data);
            }else{
                $this->log('FB_adAccounts',$result['msg']??'',$params,$accountId);
                return $this->returnError($result['msg']??'');
            }
        } catch (\Throwable $th) {
            $this->log('FB_adAccounts',$th->getMessage(),$params,$accountId);
            return $this->returnError($th->getMessage()); 
        }
    }

    public function adAccountsDelete($params){
        try {
            $token = $params['token'];
            $accountId = $params['account_id']??'439626741939329';
            
            $param = [
                'spend_cap_action'=> 'delete',
            ];
            $url = "https://graph.facebook.com/v21.0/act_".$accountId;
            $method = 'POST';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['success']) && $result['success']){                
                return $this->returnSucceed([]);
            }else{
                $this->log('FB_adAccountsDelete',$result['msg']??'',$params,$accountId);
                return $this->returnError($result['msg']??'');
            }
        } catch (\Throwable $th) {
            $this->log('FB_adAccountsDelete',$th->getMessage(),$params,$accountId);
            return $this->returnError($th->getMessage()); 
        }
    }

    public function adAccountsLimit($params){
        try {
            $token = $params['token'];
            $accountId = $params['account_id']??'439626741939329';
            $spend = $params['spend']??0.01;
            
            $param = [
                'spend_cap'=> $spend,
            ];
            $url = "https://graph.facebook.com/v21.0/act_".$accountId;
            $method = 'POST';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['success']) && $result['success']){         
                return $this->returnSucceed([]);
            }else{
                $this->log('FB_adAccountsLimit',$result['msg']??'',$params,$accountId);
                return $this->returnError($result['msg']??'');
            }
        } catch (\Throwable $th) {
            $this->log('FB_adAccountsLimit',$th->getMessage(),$params,$accountId);
            return $this->returnError($th->getMessage()); 
        }
    }

    public function assignedUsers($params){
        try {
            $token = $params['token'];
            $accountId = $params['account_id'];
            $userId = $params['user_id'];
            
            $param = [
                'tasks'=> ['MANAGE','ADVERTISE','ANALYZE'],
                'user'=>$userId,
            ];
            $url = "https://graph.facebook.com/v21.0/act_{$accountId}/assigned_users";
            $method = 'POST';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            if(isset($result['success']) && $result['success']){         
                return $this->returnSucceed([]);
            }else{
                $this->log('FB_assignedUsers',$result['msg']??'',$params,$accountId);
                return $this->returnError($result['msg']??'');
            }
        } catch (\Throwable $th) {
            $this->log('FB_assignedUsers',$th->getMessage(),$params,$accountId);
            return $this->returnError($th->getMessage()); 
        }
    }

    public function businessesList($params){
        try {
            $token = $params['token'];
            $accountId = $params['account_id'];
            
            $param = [
                'fields'=> 'business,account_id',
            ];
            $url = "https://graph.facebook.com/v21.0/act_{$accountId}";
            $method = 'GET';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            dd($result);
            if(isset($result['success']) && $result['success']){         
                return $this->returnSucceed([]);
            }else{
                $this->log('FB_assignedUsers',$result['msg']??'',$params,$accountId);
                return $this->returnError($result['msg']??'');
            }
        } catch (\Throwable $th) {
            $this->log('FB_assignedUsers',$th->getMessage(),$params,$accountId);
            return $this->returnError($th->getMessage()); 
        }
    }


    public function businessesAdaccountsList($params){

        // ^ array:3 [
        //     "business" => array:2 [
        //       "id" => "750517456175427"
        //       "name" => "miemirates"
        //     ]
        //     "account_id" => "9628116697204069"
        //     "id" => "act_9628116697204069"
        //   ]

        try {
            $token = $params['token'];
            $accountId = $params['account_id'];
            
            $param = [
                //'fields'=> 'business,account_id',
            ];
            $url = "https://graph.facebook.com/v21.0/750517456175427/adaccounts";
            $method = 'GET';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>"Bearer {$token}",
            ];
            $result = $this->curlHttp($url,$method,$header,$param);
            dd($result);
            if(isset($result['success']) && $result['success']){         
                return $this->returnSucceed([]);
            }else{
                $this->log('FB_assignedUsers',$result['msg']??'',$params,$accountId);
                return $this->returnError($result['msg']??'');
            }
        } catch (\Throwable $th) {
            $this->log('FB_assignedUsers',$th->getMessage(),$params,$accountId);
            return $this->returnError($th->getMessage()); 
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

    public function returnAbnormal(int $code = 0,Array $data = [])
    {
        return [
            'code'=>$code,
            'msg'=>'abnormal',
            'data'=>$data
        ];
    }

    public function log(String $type='error',String $msg = 'Error',Array $params = [],String $logId='')
    {
        DB::table('ba_fb_logs')->insert(
            ['log_id'=>$logId,'type'=>$type,'data'=>json_encode($params),'logs'=>$msg,'create_time'=>date('Y-m-d H:i:s',time())]
        );
        return true;
    }
}