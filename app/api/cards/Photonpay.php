<?php
namespace app\api\cards;

use app\api\interfaces\CardInterface;
use think\facade\Db;

class Photonpay extends Backend implements CardInterface 
{
    
    protected $url ;
    protected $accountId;
    protected $appId;
    protected $appSecret;
    protected $token;
    protected $privateKey;
    protected $publicKey;
    protected $platformKey;
    protected $matrixAccount;
    //protected $header;

    function __construct($cardAccount)
    {
        //'https://x-api1.uat.photontech.cc'
        $token = json_decode($cardAccount['token'],true);
        $this->url = env('CARD.PHOTONPAY_RUL');
        // $this->appId = $token['appId'];
        // $this->appSecret = $token['appSecret'];
        // $this->accountId = $cardAccount['id'];
        // $this->privateKey = $cardAccount['private_key'];
        // $this->publicKey = $cardAccount['public_key'];
        // $this->platformKey = $cardAccount['platform_key'];

        // //dd($cardAccount['expires_in'],time());

        // if($cardAccount['expires_in'] < time())
        // {
        //     $url = "$this->url/oauth2/token/accessToken";
        //     $method = 'POST1';
        //     $header = [
        //         'Content-Type'=>'application/json',
        //         'Authorization'=>'basic '.base64_encode($token['appId'].'/'.$token['appSecret'])
        //     ];
        //     $result = $this->curlHttp($url,$method,$header);

        //     if(empty($result) || empty($result['data']) || empty($result['data']['token'])){
        //         $error = $this->returnError('账户授权错误');
        //         DB::table('ba_card_account')->where('id',$this->accountId)->update(['status'=>0,'logs'=>json_encode($error)]);
        //         throw new \Exception("Error Processing Request");
        //     } 
        //     $accessToken = $result['data'];

        //     $data = [
        //         'expires_in'=>($accessToken['expiresIn'] / 1000 - 3600),
        //         'access_token'=>$accessToken['token'],
        //         'update_time'=>date("Y-m-d H:i:s",time()),
        //     ];
        //     DB::table('ba_card_account')->where('id',$this->accountId)->update($data);
            
        //     $this->token  = $accessToken['token'];
        // }else{
        //     $this->token  = $cardAccount['access_token'];
        // }

        $result = $this->curlHttp('http://8.218.77.200:10084/admin/Index/token?server=1','GET',[],['server'=>1,'type'=>'sdoijfon64651']);
        if(empty($result['token']['access_token'])) throw new \Exception("账户授权错误");
        $this->token = $result['token']['access_token'];
        $this->privateKey = $result['token']['private_key'];
        $this->matrixAccount = $token['matrixAccount']??'';
    }

    function cardCreate($params): array{
        return [];
    }
    function cardGetLimits($params): array{
        return [];
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
        $url = "$this->url/vcc/openApi/v4/pagingVccCard";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token
        ];

        $param = [
            'pageIndex'=>$params['page_index'],
            'pageSize'=>$params['page_size'],
            'matrixAccount'=>0,
            // 'memberId'=>'2024102145150870'
            'createdAtStart'=>$params['from_updated_at']  
        ];
        if(!empty($this->matrixAccount)) $param['matrixAccount'] = $this->matrixAccount;
        $result = $this->curlHttp($url,$method,$header,$param);
        if($result['msg'] == 'succeed'){
            $data = [
                'data' => $result['data'],
                'total' => $result['total'],
                'pageSize' => $result['pageSize'],
                'pageIndex' => $result['pageIndex'],
                'numbers' => $result['numbers'],

            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function cardInfo($params):array
    {
        $url = "$this->url/vcc/openApi/v4/getCardDetail";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token
        ];
        $param = [
            'cardId'=>$params['card_id'],
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if($result['msg'] == 'succeed'){
            $data = $result['data'];
            
            $cardVcc = $this->cardCvv(['card_id'=>$param['cardId']]);
            $data['cvv'] = $cardVcc['data']['cvv'];
            // $data['cvv'] = '';
            // $data['expirationDate'] = '';
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function cardCvv($params):array
    {
        $url = "$this->url/vcc/openApi/v4/getCvv";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token
        ];
        $params = [
            'cardId'=>$params['card_id'],
        ];
        $result = $this->curlHttp($url,$method,$header,$params);
        if($result['msg'] == 'succeed'){
            $data = $result['data'];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function updateCard($params):array
    {
        $UUID = $this->getUUID();
        $param = [
            'cardId'=>$params['card_id'],
            'requestId'=>$UUID,
        ];
        if(!empty($params['nickname'])) $param['nickname'] = $params['nickname'];
        if(!empty($params['max_on_daily'])) $param['maxOnDaily'] = $params['max_on_daily'];
        if(!empty($params['max_on_monthly'])) $param['maxOnMonthly'] = $params['max_on_monthly'];
        if(!empty($params['max_on_percent'])) $param['maxOnPercent'] = $params['max_on_percent'];
        if(!empty($params['transaction_limit_type']) && $params['transaction_limit_type'] == 'unlimited') $param['transactionLimitType'] = $params['transaction_limit_type'];
        if(!empty($params['transaction_limit_type']) && !empty($params['transaction_limit_change_type']) && !empty($params['transaction_limit'])) {
            $param['transactionLimitType'] = $params['transaction_limit_type'];
            $param['transactionLimitChangeType'] = $params['transaction_limit_change_type'];
            $param['transactionLimit'] = $params['transaction_limit'];
        }

        $sign = $this->sign($param);

        $url = "$this->url/vcc/openApi/v4/updateCard";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token,
            'X-PD-SIGN'=>$sign
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed();
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'update_card',
                'data'=>json_encode($param),
                'logs'=>$result['msg'],
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }

    }


    public function cardFreeze($params):array
    {
        $UUID = $this->getUUID();
        // $params = [
        //     'cardId'=>'XR1825848412092768256',
        //     'requestId'=>$UUID,
        //     'status'=>'freeze',  //freeze：冻结卡； unfreeze：解冻卡。
        // ];
        $param = [
            'cardId'=>$params['card_id'],
            'requestId'=>$UUID,
            'status'=>'freeze',  //freeze：冻结卡； unfreeze：解冻卡。
        ];

        $sign = $this->sign($param);

        $url = "$this->url/vcc/openApi/v4/freezeCard";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token,
            'X-PD-SIGN'=>$sign
        ];
        $result = $this->curlHttp($url,$method,$header,$param);

        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed(['cardStatus'=>'frozen']);
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'card_freeze',
                'data'=>json_encode($param),
                'logs'=>$result['msg'],
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }
    }

    public function cancelCard($params):array
    {
        $param = [
            'cardId'=>$params['card_id'],
        ];

        $sign = $this->sign($param);

        $url = "$this->url/vcc/openApi/v4/cancelCard";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token,
            'X-PD-SIGN'=>$sign
        ];
        $result = $this->curlHttp($url,$method,$header,$param);

        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed([
                'cardStatus'=>'cancelled'
            ]);
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'cancel_card',
                'data'=>json_encode($param),
                'logs'=>$result['msg'],
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }
    }

    public function cardUnfreeze($params):array
    {
        $UUID = $this->getUUID();
        // $params = [
        //     'cardId'=>'XR1825848412092768256',
        //     'requestId'=>$UUID,
        //     'status'=>'freeze',  //freeze：冻结卡； unfreeze：解冻卡。
        // ];
        $param = [
            'cardId'=>$params['card_id'],
            'requestId'=>$UUID,
            'status'=>'unfreeze',  //freeze：冻结卡； unfreeze：解冻卡。
        ];

        $sign = $this->sign($param);

        $url = "$this->url/vcc/openApi/v4/freezeCard";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token,
            'X-PD-SIGN'=>$sign
        ];
        $result = $this->curlHttp($url,$method,$header,$param);

        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed(['cardStatus'=>'normal']);
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'card_unfreeze',
                'data'=>json_encode($param),
                'logs'=>$result['msg'],
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }
    }

    public function transactionDetail($params):array
    {
        $url = "$this->url/vcc/openApi/v4/pagingVccTradeOrder";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token
        ];
        $param = [
            'cardId'=>$params['card_id'],
            'pageIndex'=>$params['page_index']??1,
            'pageSize'=>$params['page_size']??200,
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if($result['msg'] == 'succeed'){
            $data = [
                'data' => $result['data'],
                'total' => $result['total'],
                'pageSize' => $result['pageSize'],
                'pageIndex' => $result['pageIndex'],
                'numbers' => $result['numbers'],

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

         if(!empty($params['type']) && $params['type'] == 'closed')
        {
            $this->cardUnfreeze($params);
            sleep(3);
            $result = $this->cancelCard($params);
            if($result['code'] == 1){
                return $result;
            }else{
                return $this->returnError($result['msg']);
            }
        }

        $UUID = $this->getUUID();
        $params = [
            'cardID'=>'XR1826914197393379328',
            'requestId'=>$UUID,
            'cvv'=>'CVV',
            'expirationDate'=>'02/27',
            'txnCurrency'=>'USD',
            'txnAmount'=>'1.5',
            'txnType'=>'auth',
            'mcc'=>'mcc',
            'merchantName'=>'Amazon',
            'merchantCountry'=>'US',
            'merchantCity'=>'Newyork',
            'merchantPostcode'=>'10001',
        ];

        $sign = $this->sign($params);

        $url = "$this->url/vcc/open/v2/sandBoxTransaction";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'X-PD-TOKEN'=>$this->token,
            'X-PD-SIGN'=>$sign
        ];
        $result = $this->curlHttp($url,$method,$header,$params);
        dd($result);

        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed();
        }else{
            return $this->returnError($result['msg']);
        }
    }


}
