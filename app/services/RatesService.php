<?php
namespace app\services;

use think\facade\Cache;
use GuzzleHttp\Client;
use app\services\Basics;
use Throwable;

class RatesService
{
    protected $redis;

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();
    }

   
    public function list($time='')
    {
        if (empty($time))  $time = date('Y-m-d');        
        try {
            $url = "https://openexchangerates.org/api/historical/{$time}.json?app_id=469ab6bde5894df886e7649dc620209f&base=USD&symbols=" . implode(',', config('basics.GET_RATES'));
            $client = (new Client(['verify' => false]));
            $response = $client->request('GET', $url,[]);
            //$response = $client->request('POST', $url,['form_params'=>$data]);
            $data = $response->getBody()->getContents();
            $data = json_decode($data, true);

            $dataList = [];
            foreach($data['rates'] as $k => $v){
                $dataList[] = [
                    'time'=>$time,
                    'currency'=>$k,
                    'rate'=>$v,
                ];
            }
            // DB::table('ba_exchange_rate')->insertAll($dataList);
            return (new Basics())->returnSucceed($dataList);
        } catch (Throwable $th) {
            Basics::logs('RatesService',  [$url], $th->getMessage());
            return (new Basics())->returnError('');
        }
    }
}

  

