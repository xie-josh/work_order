<?php
namespace App\Api\Cards;

use app\api\interfaces\CardInterface;
use think\facade\Db;
use app\api\cards\Backend;
use think\facade\Queue;

class Lampay extends Backend implements CardInterface
{

    protected $url ;
    protected $accountId;
    protected $appId;
    protected $appSecret;
    protected $token;
    protected $privateKey;
    protected $publicKey;
    protected $platformKey;

    public function __construct($cardAccount)
    {
        $token = json_decode($cardAccount['token'],true);
        $this->url = env('CARD.LAMPAY_RUL');

        $this->appId = $token['appId'];
        $this->appSecret = $token['appSecret'];        
        $this->accountId = $cardAccount['id'];
        $this->privateKey = $cardAccount['private_key'];
        $this->publicKey = $cardAccount['public_key'];
        $this->platformKey = $cardAccount['platform_key'];

        if($cardAccount['expires_in'] < time())
        {
            $url = "$this->url/web/api/login";
            $method = 'POST2';
            $header = [];
            $data = [
                'username'=>$this->appId,
                'password'=>$this->appSecret,
            ];
            $result = $this->curlHttp($url,$method,$header,$data);

            if(empty($result) || empty($result['data']) || substr($result['code'], 0, 1) != 2){
                $error = $this->returnError('账户授权错误');
                DB::table('ba_card_account')->where('id',$this->accountId)->update(['status'=>0,'logs'=>json_encode($error)]);
                throw new \Exception("Error Processing Request");
            } 
            $accessToken = $result['data'];

            $data = [
                'expires_in'=>(time() + 21600),
                'access_token'=>$accessToken,
                'update_time'=>date("Y-m-d H:i:s",time()),
            ];
            DB::table('ba_card_account')->where('id',$this->accountId)->update($data);
            
            $this->token  = $accessToken;
        }else{
            $this->token  = $cardAccount['access_token'];
        }
    }

    public function cardList($params):array{
        $url = "$this->url/web/api/list";
        $method = 'GET';
        $header = [
            'Authorization'=>'Bearer '.$this->token
        ];

        $param = [
            'pageSize'=>$params['page_size']??10,
            'pageNum'=>$params['page_index']??12,
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(substr($result['code'], 0, 1) == 2){
            $data = [
                'data' => $result['data']['cardInfoVOList'],
                'total' => $result['data']['totalRecord'],
                'pageSize' => $result['data']['pageSize'],
                'pageIndex' => $param['pageNum'],
                'numbers' => $result['data']['currentPage'],
            ];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }
    public function cardInfo($params):array{
        $cardNo = DB::table('ba_cards')->where('card_id',$params['card_id'])->value('card_no');
        $url = "$this->url/web/api/list/suffix/".$cardNo;
        $method = 'GET';
        $header = [
            'Authorization'=>'Bearer '.$this->token
        ];
        $param = [
            'pageSize'=>$params['page_size']??1,
            'pageNum'=>$params['page_index']??1,
        ];
        $result = $this->curlHttp($url,$method,$header,$param);
        if(substr($result['code'], 0, 1) == 2){
            if(empty($result['data']['cardInfoVOList'][0])) 
            {
                return $this->returnError('未找到卡详情！');
            }
            $info = $result['data']['cardInfoVOList'][0];

            $maxOnPercent = '';
            $totalTransactionLimit = '';
            foreach($info['cardLimits'] as $v){
                if($v['interval']=='PER_TRANSACTION') $maxOnPercent = $v['amount'];
                if($v['interval']=='ALL_TIME') $totalTransactionLimit = $v['amount'];
            }

            $cardBalance = '';
            if(!empty($info['cardBalance'])){
                $cardBalance = str_replace("USD", "", $info['cardBalance']);
                $cardBalance = trim($cardBalance); // 移除多余的空格
            }

            $data = [              
                'cardId'=>$info['cardBusinessId'],
                'cardNo'=>$info['fullCardNo'],
                'cardCurrency'=>'USD',
                'cardScheme'=>$info['cardNetwork'],
                'cardStatus'=>$info['cardStatus']=='ACTIVE'?'normal':'frozen',
                'cardType'=>'',
                'createdAt'=>date('Y-m-d H:i:s',($info['createdAt'] / 1000)),
                'memberId'=>'',
                'matrixAccount'=>'',
                'email'=>'',
                'expirationDate'=>'',
                'cvv'=>'',
                'firstName'=>'',
                'lastName'=>'',
                'maskCardNo'=>$info['cardNumber'],
                'maxOnDaily'=>'',
                'maxOnMonthly'=>'',
                'maxOnPercent'=>$maxOnPercent,
                'mobile'=>'',
                'mobilePrefix'=>'',
                'nationality'=>'',
                'nickname'=>$info['cardNickname'],
                'totalTransactionLimit'=>$totalTransactionLimit,
                'transactionLimitType'=>'limited',
                'availableTransactionLimit'=>$cardBalance,
                'billingAddress'=>'',
                'billingAddressUpdatable'=>'',
                'billingCity'=>'',
                'billingCountry'=>'',
                'billingPostalCode'=>'',
                'billingState'=>'',
                'cardBalance'=>'',
                'createTime'=>time()
            ];
            //$cardVcc = $this->cardCvv(['card_id'=>$param['cardId']]);
            //$data['cvv'] = $cardVcc['data']['cvv'];
            return $this->returnSucceed($data);
        }else{
            return $this->returnError($result['msg']);
        }
    }
    public function updateCard($params):array{
        if(!empty($params['nickname'])){
            $url = "$this->url/web/api/update/nickname";
            $param = [
                'cardIds'=>[$params['card_id']],
                'nickName'=>$params['nickname']
            ];
            $method = 'POST';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>'Bearer '.$this->token
            ];
            $cardLogs = $this->cardLogs($params,2);
            if($cardLogs['code'] != 1) return $this->returnError($cardLogs['msg']);
            $result = $this->curlHttp($url,$method,$header,$param);
        }
        if(!empty($params['transaction_limit']) || !empty($params['max_on_percent']))
        {
            $url = "$this->url/web/api/update/limits";
            $param = [
                'cardIds'=>[$params['card_id']],
            ];
            if(!empty($params['transaction_limit'])) $param['cardTotalLimit'] = $params['transaction_limit'];
            if(!empty($params['max_on_percent'])) $param['cardPerLimit'] = $params['max_on_percent'];

            $method = 'POST';
            $header = [
                'Content-Type'=>'application/json',
                'Authorization'=>'Bearer '.$this->token
            ];
            $cardLogs = $this->cardLogs($params,1);
            if($cardLogs['code'] != 1) return $this->returnError($cardLogs['msg']);
            $result = $this->curlHttp($url,$method,$header,$param);
        }
        
        if(substr($result['code'], 0, 1) == 2){
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
    public function cardFreeze($params):array{
        $param = [
            'cardIds'=>[$params['card_id']],
        ];

        $url = "$this->url/web/api/inactive";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $cardLogs = $this->cardLogs($params,3);
        if($cardLogs['code'] != 1) return $this->returnError($cardLogs['msg']);
        $result = $this->curlHttp($url,$method,$header,$param);

        if(substr($result['code'], 0, 1) == 2){
            return $this->returnSucceed();
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
    public function cardUnfreeze($params):array{
        $param = [
            'cardIds'=>[$params['card_id']],
        ];

        $url = "$this->url/web/api/active";
        $method = 'POST';
        $header = [
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$this->token
        ];
        $cardLogs = $this->cardLogs($params,4);
        if($cardLogs['code'] != 1) return $this->returnError($cardLogs['msg']);
        $result = $this->curlHttp($url,$method,$header,$param);

        if(substr($result['code'], 0, 1) == 2){
            return $this->returnSucceed();
        }else{
            DB::table('ba_cards_logs')->insert([
                'type'=>'card_un_freeze',
                'data'=>json_encode($param),
                'logs'=>$result['msg'],
                'create_time'=>date('Y-m-d H:i:s',time())
            ]);
            return $this->returnError($result['msg']);
        }
    }
    public function transactionDetail($params):array{
        return [];
    }
    public function test($params):array{
        return [];
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



    public function cardLogs($params,$type=1)
    {

        /**
         * 操作日志：
         * 
         * 
         * 1.限额（绑/充） + 卡
         * 2.修改昵称 + 卡
         * 3.冻卡（清零） + 卡
         * 4.解冻 + 卡
         * 
         * 
         * 0：未处理 ， 1=处理完成
         * 
         * 
         * 
         * 
         * 
         * 
         */
        // $data = [
        //     'card_id'=>$params['card_id']??"",
        //     'nickname'=>$params['nickname']??"",
        //     'card_status'=>$params['card_status']??"",
        //     'transaction_limit'=>$params['transaction_limit']??"",
        //     //'transaction_limit_change_type'=>$params['transaction_limit_change_type']??"",
        //     //'total_transaction_limit'=>$params['total_transaction_limit']??"",
        // ];

        $param = [
            'card_id'=>$params['card_id'],
            'type'=>$type,
            'time'=>date('Y-m-d H:i:s',time()),
            'data'=>json_encode($params)
        ];

        $result = DB::table('ba_cards_queue_logs')->where('card_id',$params['card_id'])->where('type',$type)->where('status',0)->find();
        if($result) return ['code'=>0,'msg'=>'该卡有未完成的操作,请联系管理员!'];

        $id = DB::table('ba_cards_queue_logs')->insertGetId($param);
        $param['id'] = $id;

        $jobHandlerClassName = 'app\job\CardQuLogs';
        $jobQueueName = 'CardQuLogs';
        Queue::later(10, $jobHandlerClassName, $param, $jobQueueName);
        return ['code'=>1,'msg'=>''];
         //return ['code'=>0,'msg'=>'前面有个限额未完成'];
    }

}