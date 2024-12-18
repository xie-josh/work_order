<?php
namespace app\admin\services\card;

class Cards
{

    protected object $model;
    protected object $auth;

    public function __construct($auth=null)
    {
        $this->model = new \app\admin\model\card\CardsModel();
        $this->auth = $auth;
    }

}