<?php
namespace app\api\factories;

use app\api\cards\Photonpay;
use app\api\interfaces\CardInterface;
use app\api\cards\Lampay;
use app\api\cards\Airwallex;

class CardFactory
{
    public static function create($cardAccount): CardInterface
    {
        $platform = $cardAccount['platform']??'';
        switch ($platform) {
            case 'photonpay':
                return new Photonpay($cardAccount);
            case 'lampay':
                return new Lampay($cardAccount);
            case 'airwallex':
                return new airwallex($cardAccount);
            default:
                return throw new \Exception("账户不可使用!");
        }
    }
}




