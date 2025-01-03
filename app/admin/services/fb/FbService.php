<?php
namespace app\admin\services\fb;

use app\services\FacebookService;

class FbService
{


    public function list()
    {
        (new FacebookService())->list([]);
    }

    public function getPersonalbmToken($type=1)
    {
        $model = (new \app\admin\model\fb\PersonalBmTokenModel());
        $result = $model->where('type',$type)->value('token');
        return $result;
    }


}