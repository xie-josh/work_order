<?php
namespace app\admin\services\card;

use GuzzleHttp\Client;

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

}