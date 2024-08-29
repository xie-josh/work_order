<?php
namespace app\api\interfaces;

interface CardInterface
{
    public function request($endpoint, $method = 'GET', $data = []);
    public function cardList($params):array;
    public function cardInfo($params):array;
    public function updateCard($params):array;
    public function cardFreeze($params):array;
    public function cardUnfreeze($params):array;
    public function transactionDetail($params):array;
    public function test($params):array;
    
}
