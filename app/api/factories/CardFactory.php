<?php
namespace app\api\factories;

use app\api\cards\Photonpay;
use app\api\interfaces\CardInterface;

class CardFactory
{
    public static function create($cardAccount): CardInterface
    {
        $platform = $cardAccount['platform']??'';
        switch ($platform) {
            case 'photonpay':
                return new Photonpay($cardAccount);
            default:
                return throw new \Exception("账户不可使用!");
        }
    }
}




