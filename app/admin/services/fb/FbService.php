<?php
namespace app\admin\services\fb;

use app\services\FacebookService;

class FbService
{


    public function list()
    {
        (new FacebookService())->list([]);
    }

    public function getPersonalbmToken($type=1,$fbTokenId = '',$accountrequestProposalId='')
    {
        $model = (new \app\admin\model\fb\PersonalBmTokenModel());

        if($type == 1){
            if(!empty($fbTokenId) && $fbTokenId > 51){
                $result = $model->where('type',$type)->where('id',3)->value('token');
            }else if(!empty($accountrequestProposalId) && $accountrequestProposalId > 43557){
                $result = $model->where('type',$type)->where('id',3)->value('token');
            }else{
                $result = $model->where('type',$type)->where('id',1)->value('token');
            }
        }else{
            $result = $model->where('type',$type)->value('token');
        }
        return $result;
    }


}