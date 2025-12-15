<?php
namespace app\api\cards;

use app\api\interfaces\CardInterface;
use think\facade\Db;

class Slash extends Backend implements CardInterface 
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
    public $cardStatus = ['active'=>'normal','inactive'=>'frozen','closed'=>'cancelled','paused'=>'frozen'];

    function __construct($cardAccount)
    {
        $token = json_decode($cardAccount['token'],true);
        $this->url = env('CARD.SLASH_RUL');
        
        $this->token  = $token['appSecret'];
    }

    public function cardCreate($params):array
    {

        
    }


    /**
     * 卡列表
     * Summary of cardList
     * @return void
     */
    public function cardList($params):array
    {
        $url = "$this->url/card";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];

        $param = [
            'sort'=>'createdAt',
            'sortDirection'=>'DESC',
            'filter:status'=>'active',
            'cursor'=>$params['cursor'] ?? '',
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['items'])){
            $metadata = $result['metadata'] ?? [];
            $data = [
                'data' => $result['items'],
                'pageSize' => 100,
                'total' => $metadata['count']??0,
                'cursor'=> $metadata['nextCursor']??'',
            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function cardInfo($params):array
    {
        $url = "https://vault.joinslash.com/card/".$params['card_id'].'?include_pan=true&include_cvv=true';
        $method = 'GET2';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $param = [];
        $result = $this->curlHttp($url,$method,$header,$param);
        
        if(isset($result['id'])){
            $cardGetLimits = $this->cardGetLimits(['card_id'=>$params['card_id']]);

            list($maxOnPercent, $totalTransactionLimit, $availableTransactionLimit,$transactionLimitType) = [0,0,0,'unlimited'];
            $limits = $result['spendingConstraint']??[];
            if(!empty($limits))
            {
                $maxOnPercent = bcdiv((string)($limits['spendingRule']['transactionSizeLimit']['maximum']['amountCents']??0) ,100,2);
                $totalTransactionLimit = bcdiv((string)($limits['spendingRule']['utilizationLimit']['limitAmount']['amountCents']??0),100,2);
                $availableTransactionLimit = $cardGetLimits['data']['availableBalance']??0;
                $transactionLimitType = 'limited';
            }
            
            $data = [              
                'cardId'=>$result['id'],
                'cardNo'=>$result['pan'],
                'cardCurrency'=>'USD',
                'cardScheme'=>'VISA',
                'cardStatus'=>$this->cardStatus[$result['status']],
                'cardType'=>'share',
                'createdAt'=>date('Y-m-d H:i:s',strtotime($result['createdAt'])),
                'memberId'=>'',
                'matrixAccount'=>'',
                'email'=>'',
                'expirationDate'=>$result['expiryMonth'].'/'.$result['expiryYear'],
                'cvv'=>$result['cvv'],
                'firstName'=>'',
                'lastName'=>'',
                'maskCardNo'=>'',
                'maxOnDaily'=>'',
                'maxOnMonthly'=>'',
                'maxOnPercent'=>$maxOnPercent,
                'mobile'=>'',
                'mobilePrefix'=>'',
                'nationality'=>'',
                'nickname'=>$result['name'],
                'totalTransactionLimit'=>$totalTransactionLimit,
                'transactionLimitType'=>$transactionLimitType,
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
        $url = "$this->url/card/{$params['card_id']}/utilization";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $params = [];
        $result = $this->curlHttp($url,$method,$header,$params);   
        if(isset($result['spend'])){
            $spend = (string)($result['spend']['amountCents']??'0.00');
            if($spend > 0) $spend = bcdiv($spend,100,2);

            $availableBalance = (string)($result['availableBalance']['amountCents']??'0.00');
            if($availableBalance) $availableBalance = bcdiv($availableBalance,100,2);

            $result = [
                'spend'=>$spend,
                'availableBalance'=>$availableBalance
            ];
            return $this->returnSucceed($result);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function updateCard($params):array
    {
        $param = [];
        if(!empty($params['nickname'])) $param['name'] = $params['nickname'];
        if(!empty($params['max_on_percent'])) $param['spendingConstraint']['spendingRule']['transactionSizeLimit'] = ['minimum'=>['amountCents'=>0],'maximum'=>['amountCents'=>$params['max_on_percent'] * 100]];
        if(!empty($params['transaction_limit'])) {            
            // $param['spendingConstraint']['spendingConstraint']['utilizationLimit']['limitAmount'] = ['amountCents'=>$params['transaction_limit'] * 100];
            $param['spendingConstraint']['spendingRule']['utilizationLimit']['limitAmount'] = ['amountCents'=>$params['transaction_limit'] * 100];
            $param['spendingConstraint']['spendingRule']['utilizationLimit']['preset'] = 'collective';
        }

        if(!empty($params['transaction_limit_type']) && $params['transaction_limit_type'] == 'unlimited') $param['spendingConstraint'] = null;

        // if($params['card_id'] == 'c_2ft6cz81lsto4') dd($param);

        $url = "$this->url/card/{$params['card_id']}";
        $method = 'PATCH';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);

        if(isset($result['id'])){
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
            'status'=>'paused'
        ];
        $url = "$this->url/card/{$params['card_id']}";
        $method = 'PATCH';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['status'])){
            return $this->returnSucceed(['cardStatus'=>$this->cardStatus[$result['status']??'NO']]);
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
            'status'=>'active'
        ];
        $url = "$this->url/card/{$params['card_id']}";
        $method = 'PATCH';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['status'])){
            return $this->returnSucceed(['cardStatus'=>$this->cardStatus[$result['status']??'NO']]);
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
        $url = "$this->url/transaction";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$params); 

        if(isset($result['items'])){
            return $this->returnSucceed($result);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function transactionGetIdDetail($params):array
    {
        $url = "$this->url/transaction/".$params['entityId'];
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,[]); 
        //dd($result);

        if(isset($result['id'])){
            return $this->returnSucceed($result);
        }else{
            return $this->returnError($result['msg']);
        }
    }

    public function getCardDetails($params):array
    {
        $url = "$this->url/card/".$params['entityId'];
        $method = 'get';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        // dd($url,$method,$header,$params);
        $result = $this->curlHttp($url,$method,$header,[]); 
        if(isset($result['id'])){
            return $this->returnSucceed($result);
        }else{
            return $this->returnError($result['msg']);
        }

    }


    public function createCard($params)
    {
        $UUID = $this->getUUID();
        // $param = [];
        // $url = "$this->url/api/v1/issuing/cardholders";
        // $method = 'GET';

        $cardholder_id = 'eddff90c-036e-421b-8f43-4789b2105a8f';

        $param = [
            'authorization_controls'=>[
                'allowed_transaction_count'=>'MULTIPLE',
                'transaction_limits'=>[
                    "currency"=> "USD",
                    'limits'=>[
                        [
                            'interval'=>'ALL_TIME',
                            'amount'=>2000
                        ],
                        [
                            'interval'=>'PER_TRANSACTION',
                            'amount'=>5000
                        ]
                    ]
                ],
            ],
            'cardholder_id'=>$cardholder_id,
            'created_by'=>'建江',
            "form_factor"=>"VIRTUAL",
            'is_personalized'=>false,
            'program'=>[
                'purpose'    =>'COMMERCIAL'
            ],
            //"issue_to"=>"ORGANISATION",
            //'purpose'=>'MARKETING_EXPENSES',
            'nick_name'=>'250503',
            'request_id'=>$UUID
        ];
        $url = "$this->url/api/v1/issuing/cards/create";
        $method = 'POST';


        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        // dd($result,'846522');

        if($result['msg'] == 'succeed'){           
            return $this->returnSucceed();
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


    public function cancelCard($params):array
    {
        $param = [
            'status'=>'closed'
        ];
        $url = "$this->url/card/{$params['card_id']}";
        $method = 'PATCH';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['status'])){
            return $this->returnSucceed(['cardStatus'=>$this->cardStatus[$result['status']??'NO']]);
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

    //https://x-api.photonpay.com/vcc/open/v2/sandBoxTransaction
    public function test($params):array
    {
        // $this->cc();
        // dd(333);
        if(!empty($params['type']) && $params['type'] == 'getVirtualAccount'){
            $result = $this->getVirtualAccount();
            if($result['code'] == 1){
                return $result;
            }else{
                return $this->returnError($result['msg']);
            }
        }
        // $this->createCard($params);
        // return [];
        // $UUID = $this->getUUID();

        if(!empty($params['type']) && $params['type'] == 'transactionDetail'){
            $result = $this->transactionDetail2($params);
            if($result['code'] == 1){
                return $result;
            }else{
                return $this->returnError($result['msg']);
            }
        }

        if(!empty($params['type']) && $params['type'] == 'closed')
        {
            $result = $this->cancelCard($params);
            if($result['code'] == 1){
                return $result;
            }else{
                return $this->returnError($result['msg']);
            }
        }


        //模拟消费
        // $param = [
        //     'card_id'=>'48b973bd-9ba1-4057-9b43-9b8697ad2d2d',
        //     'transaction_amount'=>'20',
        //     'transaction_currency'=>'HKD'
        // ];
        // $url = "$this->url/api/v1/simulation/issuing/create";
        // $method = 'POST';


        //  $param = [
        //     'card_id'=>'f28426d7-06a8-47f0-9e62-481dbe3da85f',
        // ];
        // $url = "$this->url/api/v1/issuing/authorizations";
        // $method = 'GET';


        //销卡
        // $param = [
        //     'id'=>$params['card_id'],
        //     'card_status'=>'CLOSED'
        // ];
        // $url = "$this->url/api/v1/issuing/cards/{$params['card_id']}/update";
        // $method = 'POST';


        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $result = $this->curlHttp($url,$method,$header,$param);

        if(!empty($result['updated_at'])){           
            return $this->returnSucceed();
        }else{
            return $this->returnError($result['msg']);
        }
    }


    public function transactionDetail2($params):array
    {
        $url = "$this->url/api/v1/issuing/transactions";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $param = [
            'from_created_at'=>'2024-12-30T11:05:00+0000',
            'to_created_at'=>'2025-05-07T11:05:00+0000',
            'card_id'=>$params['card_id'],
            'page_num'=>$params['page_index']??0,
            'page_size'=>$params['page_size']??200,
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        // dd($result);
        if(isset($result['items'])){
            $items = $result['items'];
            $data = [
                'data' => $items,
                'total' => '',
                'pageSize' => $param['page_size'],
                'pageIndex' => $param['page_num'],
                'numbers' => count($items),
            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }


    public function getVirtualAccount()
    {
        //subaccount_3htl994rvabwn
        $url = "$this->url/virtual-account/subaccount_3htl994rvabwn";
        $method = 'GET';
        $header = [
            'Content-Type'=>'application/json',
            'X-API-Key'=>$this->token
        ];

        $param = [
            
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(isset($result['virtualAccount'])){
            $item = [
                'balanceAmountCents'=>bcdiv((string)$result['balance']['amountCents'],'100',2)??0,
                'spendAmountCents'=>bcdiv((string)$result['spend']['amountCents'],'100',2)??0
            ];
            return $this->returnSucceed( $item);
        }else{
            return $this->returnError($result['msg']);
        }
    }

}
