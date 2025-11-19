<?php
namespace app\admin\services\card;

use GuzzleHttp\Client;
use think\facade\Db;
use app\services\CardService;

class Cards
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\card\CardsModel();
        $this->auth = $auth??new \stdClass;
    }

    public function accountSingle()
    {
        // $client = new Client(['verify' => false,'headers'=>[]]);
        // $response = $client->request('GET', 'http://8.218.77.200:10082/api/Cards/accountSingle?server=1',['query'=>['server'=>1]]);
        // $result = $response->getBody()->getContents();
        // $result = json_decode($result, true);
        // return $result;
        return [];
    }

    public function allCardFreeze($accountId)
    {
        $cardList = DB::table('ba_account_card')
        ->alias('account_card')
        ->field('cards_info.card_id,cards_info.account_id cards_account_id,cards_info.card_status')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=account_card.cards_id')
        ->where('account_card.account_id',$accountId)
        ->select()->toArray();

        foreach ($cardList as $v)
        {
            if($v['card_status'] == 'normal'){
                $result = (new CardService($v['cards_account_id']))->cardFreeze(['card_id'=>$v['card_id']]);
                if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('card_id',$v['card_id'])->update(['card_status'=>$result['data']['cardStatus']]);
            }

        }
        return ['code'=>1,'msg'=>'操作成功'];
    }

    public function allCardUnFreeze($accountId)
    {
        $cardList = DB::table('ba_account_card')
        ->alias('account_card')
        ->field('cards_info.card_id,cards_info.account_id cards_account_id,cards_info.card_status')
        ->leftJoin('ba_cards_info cards_info','cards_info.cards_id=account_card.cards_id')
        ->where('account_card.account_id',$accountId)
        ->select()->toArray();

        foreach ($cardList as $v)
        {
            if($v['card_status'] == 'frozen'){
                $result = (new CardService($v['cards_account_id']))->cardUnFreeze(['card_id'=>$v['card_id']]);
                if(isset($result['data']['cardStatus'])) DB::table('ba_cards_info')->where('card_id',$v['card_id'])->update(['card_status'=>$result['data']['cardStatus']]);
            }

        }
        return ['code'=>1,'msg'=>'操作成功'];
    }

}