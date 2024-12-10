<?php
namespace app\api\cards;

use app\api\interfaces\CardInterface;
use think\facade\Db;

class Airwallex extends Backend implements CardInterface 
{
    
    protected $url ;
    protected $accountId;
    protected $appId;
    protected $appSecret;
    protected $token;
    protected $privateKey;
    protected $publicKey;
    protected $platformKey;
    //protected $header;    
    public $cardStatus = ['NO'=>'NO','ACTIVE'=>'normal','PENDING'=>'pending_recharge','INACTIVE'=>'frozen','BLOCKED'=>'risk_frozen','LOST'=>'risk_frozen','STOLEN'=>'risk_frozen','CLOSED'=>'cancelled','FAILED'=>''];

    function __construct($cardAccount)
    {
        //'https://x-api1.uat.photontech.cc'
        $token = json_decode($cardAccount['token'],true);
        $this->url = env('CARD.AIRWALLEX_RUL');
        $this->appId = $token['appId'];
        $this->appSecret = $token['appSecret'];
        $this->accountId = $cardAccount['id'];

        //dd($cardAccount['expires_in'],time());

        if($cardAccount['expires_in'] < time())
        {
            $url = "$this->url/api/v1/authentication/login";
            $method = 'POST1';
            $header = [
                'Content-Type'=>'application/json',
                'x-client-id'=>$token['appId'],
                'x-api-key'=>$token['appSecret'],
                // 'Authorization'=>'basic '.base64_encode($token['appId'].'/'.$token['appSecret'])
            ];
            $result = $this->curlHttp($url,$method,$header);

            if(empty($result) || empty($result['token']) || empty($result['expires_at'])){
                $error = $this->returnError('账户授权错误');
                DB::table('ba_card_account')->where('id',$this->accountId)->update(['status'=>0,'logs'=>json_encode($error)]);
                throw new \Exception("Error Processing Request");
            } 
            $expiresIn = $result['expires_at'];
            $token = $result['token'];
            $expiresAt = strtotime($expiresIn);
            $data = [
                'expires_in'=>($expiresAt - 600),
                'access_token'=>$token,
                'update_time'=>date("Y-m-d H:i:s",time()),
            ];
            DB::table('ba_card_account')->where('id',$this->accountId)->update($data);
            
            $this->token  = $token;
        }else{
            $this->token  = $cardAccount['access_token'];
        }
    }

    public function request($endpoint, $method = 'GET', $data = [])
    {
        // 实现 Platform A 的 API 请求逻辑
    }

    /**
     * 卡列表
     * Summary of cardList
     * @return void
     */
    public function cardList($params):array
    {
        $url = "$this->url/api/v1/issuing/cards";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];

        $pageIndex = $params['page_index'] - 1;
        $param = [
            'page_num'=>$pageIndex,
            'page_size'=>$params['page_size'],
            'from_updated_at'=>$params['from_updated_at'],
            'to_updated_at'=>$params['to_updated_at'],
            // 'cardholder_id'=>111
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['items'])){
            $data = [
                'data' => $result['items'],
                'pageSize' => $params['page_size'],
                'pageIndex' => $pageIndex,
                // 'total' => $result['total'],
                // 'numbers' => $result['numbers'],

            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function cardInfo($params):array
    {
        $url = "$this->url/api/v1/issuing/cards/".$params['card_id'];
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $param = [];
        $result = $this->curlHttp($url,$method,$header,$param);
        
        if(isset($result['card_id'])){
            $cvvData = $this->cardCvv(['card_id'=>$params['card_id']]);
            $cardGetLimits = $this->cardGetLimits(['card_id'=>$params['card_id']]);  

            list($maxOnPercent,$maxOnDaily,$maxOnMonthly,$totalTransactionLimit,$transactionLimitType) = [0,0,0,0,'unlimited'];
            foreach($result['authorization_controls']['transaction_limits']['limits'] as $v){
                if($v['interval']=='PER_TRANSACTION') $maxOnPercent = $v['amount'];
                if($v['interval']=='DAILY') $maxOnDaily = $v['amount'];
                if($v['interval']=='MONTHLY') $maxOnMonthly = $v['amount'];
                if($v['interval']=='ALL_TIME'){
                    $totalTransactionLimit = $v['amount'];
                    $transactionLimitType = 'limited';
                } 
                // `max_on_percent` decimal(10,2) DEFAULT NULL COMMENT '单笔交易最大金额',
                // `max_on_daily` int DEFAULT NULL COMMENT '日交易限额',
                // `max_on_monthly` int DEFAULT NULL COMMENT '月交易限额',
                // transaction_limit_type
                // total_transaction_limit
            }

            $availableTransactionLimit = $cardGetLimits['data']['limits']['ALL_TIME']??0;
            //$cardStatus = ['ACTIVE'=>'normal','PENDING'=>'pending_recharge','INACTIVE'=>'frozen','BLOCKED'=>'risk_frozen','LOST'=>'risk_frozen','STOLEN'=>'risk_frozen','CLOSED'=>'cancelled','FAILED'=>''];

            // dd($result,$cvvData,$cardGetLimits);
            $data = [              
                'cardId'=>$result['card_id'],
                'cardNo'=>$cvvData['data']['card_number']??'',
                'cardCurrency'=>'USD',
                'cardScheme'=>$result['brand'],
                'cardStatus'=>$this->cardStatus[$result['card_status']],
                'cardType'=>'share',
                'createdAt'=>$result['created_at'],
                'memberId'=>'',
                'matrixAccount'=>'',
                'email'=>'',
                'expirationDate'=>$cvvData['data']['expiry_month'].'/'.$cvvData['data']['expiry_year'],
                'cvv'=>$cvvData['data']['cvv'],
                'firstName'=>'',
                'lastName'=>'',
                'maskCardNo'=>$result['card_number'],
                'maxOnDaily'=>$maxOnDaily,
                'maxOnMonthly'=>'',
                'maxOnPercent'=>$maxOnPercent,
                'mobile'=>'',
                'mobilePrefix'=>'',
                'nationality'=>'',
                'nickname'=>$result['nick_name'],
                'totalTransactionLimit'=>$totalTransactionLimit,
                'transactionLimitType'=>'limited',
                'availableTransactionLimit'=>$availableTransactionLimit,
                'billingAddress'=>'',
                'billingAddressUpdatable'=>'',
                'billingCity'=>'',
                'billingCountry'=>'',
                'billingPostalCode'=>'',
                'billingState'=>'',
                'cardBalance'=>'',
                'createTime'=>time()
            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function cardCvv($params):array
    {
        $url = "$this->url/api/v1/issuing/cards/{$params['card_id']}/details";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $params = [];
        $result = $this->curlHttp($url,$method,$header,$params);        
        if(isset($result['card_number'])){
            return $this->returnSucceed($result);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function cardGetLimits($params):array
    {
        $url = "$this->url/api/v1/issuing/cards/{$params['card_id']}/limits";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $params = [];
        $result = $this->curlHttp($url,$method,$header,$params);    
        if(isset($result['limits'])){
            foreach($result['limits'] as $v){
                $result['limits'][$v['interval']] = $v['remaining'];
                $result['limits'][$v['interval'].'_AMOUNT'] = $v['amount'];
            }
            return $this->returnSucceed($result);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function updateCard($params):array
    {
        
        // if(!empty($params['nickname'])) $param['nickname'] = $params['nickname'];
        // if(!empty($params['max_on_daily'])) $param['maxOnDaily'] = $params['max_on_daily'];
        // if(!empty($params['max_on_monthly'])) $param['maxOnMonthly'] = $params['max_on_monthly'];
        // if(!empty($params['max_on_percent'])) $param['maxOnPercent'] = $params['max_on_percent'];
        // if(!empty($params['transaction_limit_type']) && !empty($params['transaction_limit_change_type']) && !empty($params['transaction_limit'])) {
        //     $param['transactionLimitType'] = $params['transaction_limit_type'];
        //     $param['transactionLimitChangeType'] = $params['transaction_limit_change_type'];
        //     $param['transactionLimit'] = $params['transaction_limit'];
        // }

        $transactionLimits = [];

        if(!empty($params['max_on_percent'])) array_push($transactionLimits,['interval'=>'PER_TRANSACTION','amount'=>$params['max_on_percent']]);
        if(!empty($params['transaction_limit'])) array_push($transactionLimits,['interval'=>'ALL_TIME','amount'=>$params['transaction_limit']]);
        if(!empty($params['max_on_daily'])) array_push($transactionLimits,['interval'=>'DAILY','amount'=>$params['max_on_daily']]);

        $param = [
            'cardId'=>$params['card_id'],
        ];

        if(!empty($transactionLimits)) {
            $param['authorization_controls']['transaction_limits']['limits'] = $transactionLimits;
            $param['authorization_controls']['transaction_limits']['currency'] = 'USD';
        }  
        if(!empty($params['nickname'])) $param['nick_name'] = $params['nickname'];

        $url = "$this->url/api/v1/issuing/cards/{$params['card_id']}/update";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);

        if(isset($result['authorization_controls'])){
            return $this->returnSucceed();
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'update_card',
                'data'=>json_encode($param),
                'logs'=>$result['msg']??'',
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }

    }


    public function cardFreeze($params):array
    {
        $param = [
            'id'=>$params['card_id'],
            'card_status'=>'INACTIVE'
        ];
        $url = "$this->url/api/v1/issuing/cards/{$params['card_id']}/update";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['card_status'])){
            return $this->returnSucceed(['cardStatus'=>$this->cardStatus[$result['card_status']??'NO']]);
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'card_freeze',
                'data'=>json_encode($param),
                'logs'=>$result['msg']??'',
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }
    }

    public function cardUnfreeze($params):array
    {
        $param = [
            'id'=>$params['card_id'],
            'card_status'=>'ACTIVE'
        ];

        $url = "$this->url/api/v1/issuing/cards/{$params['card_id']}/update";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['card_status'])){
            return $this->returnSucceed(['cardStatus'=>$this->cardStatus[$result['card_status']??'NO']]);
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'card_unfreeze',
                'data'=>json_encode($param),
                'logs'=>$result['msg']??"",
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }
    }

    public function transactionDetail($params):array
    {
        $url = "$this->url/api/v1/issuing/transactions";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $param = [
            // 'from_created_at'=>'2024-07-09T11:05:00+0000',
            // 'to_created_at'=>'2024-12-01T11:05:00+0000',
            'card_id'=>$params['card_id'],
            'page_num'=>$params['page_index']??0,
            'page_size'=>$params['page_size']??200,
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['items'])){

            $items = $result['items'];
            $transactionType = ['AUTHORIZATION'=>'verification','CLEARING'=>'auth','REFUND'=>'refund','REVERSAL'=>'REVERSAL','ORIGINAL_CREDIT'=>'ORIGINAL_CREDIT'];
            $status = ['APPROVED'=>'succeed','PENDING'=>'authorized','FAILED'=>'failed'];
            $dataList = [];
            foreach($items as $v){
                $dataList[] = [
                    'memberId'=>'',
                    'matrixAccount'=>'',
                    'createdAt'=>date('Y-m-d H:i:s',strtotime($v['posted_date'])),
                    'cardId'=>$v['card_id']??'',
                    'cardType'=>'share',
                    'cardCurrency'=>'',
                    'transactionId'=>$v['transaction_id']??'',
                    'originTransactionId'=>'',
                    'requestId'=>'',
                    'transactionType'=> $transactionType[$v['transaction_type']]??'',
                    'status'=>$status[$v['status']]??'',
                    'code'=>'',
                    'msg'=>'',
                    'mcc'=>'',
                    'authCode'=>$v['auth_code']??'',
                    'settleStatus'=>'',
                    'transactionAmount'=>str_replace('-','',$v['transaction_amount']),
                    'transactionCurrency'=>$v['transaction_currency']??'',
                    'txnPrincipalChangeAccount'=>'',
                    'txnPrincipalChangeAmount'=>$v['billing_amount']??'',
                    'txnPrincipalChangeCurrency'=>$v['billing_currency']??'',
                    'txnPrincipalChangeSettledAmount'=>'',
                    'settleSpreadChangeAccount'=>'',
                    'settleSpreadChangeCurrency'=>'',
                    'feeDeductionAccount'=>'',
                    'feeDeductionAmount'=>'',
                    'feeDeductionCurrency'=>'',
                    'feeDetailJson'=>'',
                    'feeReturnAccount'=>'',
                    'feeReturnAmount'=>'',
                    'feeReturnCurrency'=>'',
                    'feeReturnDetailJson'=>'',
                    'arrivalAccount'=>'',
                    'arrivalAmount'=>'',
                    'maskCardNo'=>$v['masked_card_number']??'',
                    'merchantNameLocation'=>$v['merchant']['name'].','.$v['merchant']['city'].','.$v['merchant']['country'],
                    'create_time'=>time()
                ];
            }

            $data = [
                'data' => $dataList,
                'total' => '',
                'pageSize' => $param['page_size'],
                'pageIndex' => $param['page_num'],
                'numbers' => count($dataList),
            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }



    public function sign($params = [])
    {
        $sign = '';
        $privateKey = $this->privateKey;
        $data = json_encode($params);
        $private_content = wordwrap($privateKey, 64, "\n", true);
        $key = "-----BEGIN RSA PRIVATE KEY-----\r\n" . $private_content . "\r\n-----END RSA PRIVATE KEY-----";
        $private_key = openssl_pkey_get_private($key);
        openssl_sign($data, $sign, $private_key, OPENSSL_ALGO_MD5);
        $sign = base64_encode($sign);
        return $sign;
    }


    //https://x-api.photonpay.com/vcc/open/v2/sandBoxTransaction
    public function test($params):array
    {
        $UUID = $this->getUUID();


        //模拟消费
        $param = [
            'card_id'=>'48b973bd-9ba1-4057-9b43-9b8697ad2d2d',
            'transaction_amount'=>'20',
            'transaction_currency'=>'HKD'
        ];
        $url = "$this->url/api/v1/simulation/issuing/create";
        $method = 'POST';


        //  $param = [
        //     'card_id'=>'f28426d7-06a8-47f0-9e62-481dbe3da85f',
        // ];
        // $url = "$this->url/api/v1/issuing/authorizations";
        // $method = 'GET';


        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        dd($result);

        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed();
        }else{
            return $this->returnError($result['msg']);
        }
    }


}
