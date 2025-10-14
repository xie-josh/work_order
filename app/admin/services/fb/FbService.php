<?php
namespace app\admin\services\fb;

use app\services\FacebookService;
use think\facade\Db;

class FbService
{


    public function list()
    {
        (new FacebookService())->list([]);
    }

    public function editAdAccounts($params)
    {
        if(empty($params['account_id']) || empty($params['name'])) return ['code'=>0,'msg'=>'参数错误','data'=>[]];
        
        $accountId = $params['account_id'];
        $name = $params['name'];

        $param = DB::table('ba_accountrequest_proposal')
        ->alias('accountrequest_proposal')
        ->field('accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids')
        ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
        ->where('accountrequest_proposal.account_id',$accountId)
        ->find();

        if(empty($param)) return ['code'=>0,'msg'=>'未找到对应的授权账户','data'=>[]];

        $param['token'] = (new \app\admin\services\fb\FbService())->getPersonalbmToken($param['personalbm_token_ids']);
        $param['name'] = $name;
        $result = (new FacebookService())->editAdAccounts($param);
        return $result;
    }

    public function editAccountsLimit($params)
    {
        if(empty($params['account_id']) || empty($params['spend']) || !isset($params['is_type'])) return ['code'=>0,'msg'=>'参数错误','data'=>[]];
        
        $accountId = $params['account_id'];
        $spend = $params['spend'];
        $isType = $params['is_type'];

        $param = DB::table('ba_accountrequest_proposal')
        ->alias('accountrequest_proposal')
        ->field('accountrequest_proposal.currency,accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids')
        ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
        ->where('accountrequest_proposal.account_id',$accountId)
        ->find();

        // $param['currency'] = 'GBP';

        if($isType == 0){
            $currencyRate = config('basics.currency');
            $currency = $param['currency'];
            if(!empty($currencyRate[$currency])){
                $spend = bcmul((string)$spend, $currencyRate[$currency],2);
            }
        }

        if(empty($param)) return ['code'=>0,'msg'=>'未找到对应的授权账户','data'=>[]];

        $param['token'] = (new \app\admin\services\fb\FbService())->getPersonalbmToken($param['personalbm_token_ids']);
        $param['spend'] = $spend;
        $param['spend_cap_action'] = 'delete';

        // dd($param,$currency);
        $result = (new FacebookService())->adAccountsLimit($param);
        dd($result);
        return $result;
    }

    public function assignedUsers($params)
    {
        if(empty($params['account_id'])) return ['code'=>0,'msg'=>'参数错误','data'=>[]];
        
        $accountId = $params['account_id'];

        $param = DB::table('ba_accountrequest_proposal')
        ->alias('accountrequest_proposal')
        ->field('fb_bm_token.user_id,accountrequest_proposal.id,accountrequest_proposal.account_id,fb_bm_token.business_id,fb_bm_token.token,fb_bm_token.type,fb_bm_token.personalbm_token_ids')
        ->leftJoin('ba_fb_bm_token fb_bm_token','fb_bm_token.id=accountrequest_proposal.bm_token_id')
        ->where('accountrequest_proposal.account_id',$accountId)
        ->find();

        if(empty($param)) return ['code'=>0,'msg'=>'未找到对应的授权账户','data'=>[]];

        $param['token'] = (new \app\admin\services\fb\FbService())->getPersonalbmToken($param['personalbm_token_ids']);
        $result = (new FacebookService())->assignedUsers($param);
        return $result;
    }

    public function getPersonalbmToken($fbTokenIds)
    {
        $model = (new \app\admin\model\fb\PersonalBmTokenModel());
        $id = explode(',',$fbTokenIds)[0];        
        $result = $model->where('id',$id)->value('token');
        return $result;
    }


    public function getPersonalbmToken2($type=1,$fbTokenId = '',$accountrequestProposalId='')
    {
        $model = (new \app\admin\model\fb\PersonalBmTokenModel());

        if($type == 1){
            if(!empty($fbTokenId) && $fbTokenId > 47){
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