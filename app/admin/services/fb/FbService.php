<?php
namespace app\admin\services\fb;

use app\services\FacebookService;

class FbService
{


    public function list()
    {
        (new FacebookService())->list([]);
    }


}